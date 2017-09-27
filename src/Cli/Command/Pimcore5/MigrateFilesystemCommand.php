<?php

declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Enterprise License (PEL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 * @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 * @license    http://www.pimcore.org/license     GPLv3 and PEL
 */

namespace Pimcore\Cli\Command\Pimcore5;

use Distill\Distill;
use Pimcore\Cli\Command\AbstractCommand;
use Pimcore\Cli\Console\Style\RequirementsFormatter;
use Pimcore\Cli\Console\Style\VersionFormatter;
use Pimcore\Cli\Filesystem\DryRunFilesystem;
use Pimcore\Cli\Pimcore5\Pimcore5Requirements;
use Pimcore\Cli\Traits\CommandCollectorCommandTrait;
use Pimcore\Cli\Traits\DryRunCommandTrait;
use Pimcore\Cli\Util\FileUtils;
use Pimcore\Cli\Util\VersionReader;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

class MigrateFilesystemCommand extends AbstractCommand
{
    use DryRunCommandTrait;
    use CommandCollectorCommandTrait;

    /**
     * @var Filesystem
     */
    protected $fs;

    /**
     * @var string
     */
    protected $path;

    /**
     * @var string
     */
    protected $tmpDir;

    /**
     * Files to move to migration-backup directory
     *
     * @var array
     */
    private $filesToBackup = [
        'app', 'bin', 'src', 'web', 'var', 'pimcore',
        'index.php', '.htaccess', 'composer.json', 'composer.lock'
    ];

    /**
     * Files to use from archive
     *
     * @var array
     */
    private $filesToUse = [
        'app', 'bin', 'pimcore', 'web',
        'composer.json',
        'update-scripts/pimcore-4-to-5.php'
    ];

    protected function configure()
    {
        $this
            ->setName('pimcore5:migrate:filesystem')
            ->setDescription('Migrates a Pimcore 4 filesystem to Pimcore 5 layout from a Pimcore 5 release ZIP');

        $this
            ->addArgument('path', InputArgument::REQUIRED, 'Path to Pimcore 4 installation')
            ->addArgument('zipFile', InputArgument::REQUIRED, 'Path to Pimcore 5 zip file')
            ->addOption(
                'no-check-version', null, InputOption::VALUE_NONE,
                'Do not check version prerequisites'
            )
            ->addOption(
                'no-check-requirements', null, InputOption::VALUE_NONE,
                'Do not check Pimcore 5 requirements (you can check them manually via pimcore5:check-requirements command)'
            );

        $this
            ->configureCollectCommandsOption()
            ->configureDryRunOption();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = $this->io;

        $collector = $this->createCommandCollector();

        $fs = $this->fs = new DryRunFilesystem($io, $this->isDryRun(), false, $collector);
        $fs->setForceVerbose(true);

        $path = $input->getArgument('path');
        if (!$fs->exists($path)) {
            $io->error(sprintf('Given path %s does not exist', $path));

            return 1;
        }

        $this->path = $path = rtrim($fs->makePathRelative(realpath($path), getcwd()), DIRECTORY_SEPARATOR);

        $zipFile = $input->getArgument('zipFile');
        if (!$fs->exists($zipFile)) {
            $io->error(sprintf('Given zip file %s does not exist', $zipFile));

            return 1;
        }

        $title = sprintf('Migrating installation %s', $path);
        if ($this->isDryRun()) {
            $title .= ' (DRY-RUN)';
        }

        $io->title($title);

        $versionReader    = new VersionReader($path);
        $versionFormatter = new VersionFormatter($io);

        $io->comment('Installed version:');
        $versionFormatter->formatVersions($versionReader);

        if (!$input->getOption('no-check-version')) {
            try {
                $this->checkVersionPrerequisites($versionReader);
            } catch (\Exception $e) {
                return $this->handleException($e, 2);
            }
        }

        if (!$input->getOption('no-check-requirements')) {
            try {
                $this->checkPimcoreRequirements();
            } catch (\Exception $e) {
                return $this->handleException($e, 3);
            }
        }

        try {
            $this->checkFilesystemPrerequisites();
        } catch (\RuntimeException $e) {
            return $this->handleException($e, 4);
        }

        $this->tmpDir = $this->path('.migration', uniqid('tmp.', true));

        $io->comment('Temporary directory: ' . $this->tmpDir);

        try {
            $this
                ->backupFiles()
                ->extractZip($zipFile)
                ->createNewDirectories()
                ->moveFilesIntoPlace()
                ->moveLegacyFiles()
                ->fixConfig()
                ->enableDebugMode();
        } catch (\Exception $e) {
            return $this->handleException($e, 5);
        }

        $io->success('All done! Please check if all your files were moved properly and remove the .migration directory');
        $io->success('Please update the composer.json with your custom entries (see migration-backup directory for your previous version) and run composer update');

        if (null !== $collector) {
            $this->printCollectedCommands($collector);
        }

        return 0;
    }

    /**
     * Backup existing files (see filesToBackup property) to migration-backup directory
     *
     * @return self
     */
    private function backupFiles(): self
    {
        $this->fs->mkdir($this->path('migration-backup'));

        foreach ($this->filesToBackup as $file) {
            $source = $this->path($file);
            $target = $this->path('migration-backup', $file);

            if ($this->fs->exists($source)) {
                $this->fs->rename($source, $target);
            }
        }

        return $this;
    }

    /**
     * Extracts zip file to temporary directory and copies the files we want to use to
     * the project root
     *
     * @param string $zipFile
     *
     * @return MigrateFilesystemCommand
     */
    private function extractZip(string $zipFile): self
    {
        if (!$this->fs->exists($this->tmpDir)) {
            $this->fs->mkdir($this->tmpDir);
        }

        $this->io->writeln($this->dryRunMessage(
            sprintf('Extracting %s to %s', $zipFile, $this->tmpDir)
        ));

        if (!$this->isDryRun()) {
            $distill = new Distill();
            $distill->extract($zipFile, $this->tmpDir);
        }

        foreach ($this->filesToUse as $file) {
            $source = FileUtils::buildPath($this->tmpDir, $file);
            $target = $this->path($file);

            if ($this->fs->exists($source)) {
                if (false !== strpos($file, '/')) {
                    $targetDir = dirname($target);
                    if (!$this->fs->exists($targetDir)) {
                        $this->fs->mkdir($targetDir);
                    }
                }

                $this->fs->rename($source, $target);
            }
        }

        return $this;
    }

    /**
     * Creates new directory structure
     *
     * @return self
     */
    private function createNewDirectories(): self
    {
        $dirs = array_map(function ($dir) {
            return $this->path($dir);
        }, [
            'src',
            'var',
            'var/cache/pimcore',
            'var/sessions',
            'var/system',
            'var/tmp',
            'web/var/tmp'
        ]);

        $this->fs->mkdir($dirs);

        return $this;
    }

    /**
     * Moves files from website into new directories
     *
     * @return self
     */
    private function moveFilesIntoPlace(): self
    {
        $fs = $this->fs;

        $pairs = [
            'website/var/config'     => 'var/config',
            'website/config'         => 'app/config/pimcore',
            'website/var/classes'    => 'var/classes',
            'website/var/versions'   => 'var/versions',
            'website/var/log'        => 'var/logs/pimcore',
            'website/var/recyclebin' => 'var/recyclebin',
            'website/var/user-image' => 'var/user-image',
            'website/var/system'     => 'var/system',
            'website/var/email'      => 'var/email',
            'website/var/assets'     => 'web/var/assets',
        ];

        $path = function ($p) {
            $parts = explode('/', $p);

            return call_user_func_array([$this, 'path'], $parts);
        };

        foreach ($pairs as $source => $target) {
            $source = $path($source);
            $target = $path($target);

            if (!$fs->exists($source)) {
                continue;
            }

            $finder = new Finder();
            $finder
                ->in($source)
                ->depth('== 0');

            if ($finder->count() === 0) {
                continue;
            }

            if (!$fs->exists($target)) {
                $fs->mkdir($target);
            }

            foreach ($finder as $file) {
                $sourceFile = $file->getRealPath();
                $targetFile = FileUtils::buildPath($target, $file->getFilename());

                $fs->rename($sourceFile, $targetFile, true);
            }

            if (FileUtils::isDirectoryEmpty($source)) {
                $fs->remove($source);
            }
        }

        return $this;
    }

    /**
     * Moves legacy files to legacy/ directory
     *
     * @return self
     */
    private function moveLegacyFiles(): self
    {
        $fs = $this->fs;

        $legacyPath = $this->path('legacy');
        foreach (['website', 'plugins'] as $dir) {
            $source = $this->path($dir);

            if ($fs->exists($source)) {
                if (!$fs->exists($legacyPath)) {
                    $fs->mkdir($legacyPath);
                }

                $target = $this->path('legacy', $dir);

                $fs->rename($source, $target);
            }
        }

        return $this;
    }

    private function fixConfig(): self
    {
        $file = $this->path('var', 'config', 'system.php');

        if (!file_exists($file)) {
            $this->io->comment('Not updating config as var/config/system.php was not found');

            return $this;
        }

        return $this->runCommand('pimcore5:config:fix', [
            'config-file' => $file
        ]);
    }

    private function enableDebugMode(): self
    {
        $dir = $this->path('var', 'config');

        if (!file_exists($dir) && is_dir($dir)) {
            $this->io->comment('Not setting debug mode as var/config directory was not found');

            return $this;
        }

        return $this->runCommand('config:debug-mode', [
            'config-dir' => $dir
        ]);
    }

    private function runCommand(string $command, array $arguments): self
    {
        if ($this->isDryRun()) {
            $arguments['--dry-run'] = true;
        }

        $command = $this->getApplication()->find($command);
        $command->run(
            new ArrayInput($arguments),
            $this->io->getOutput()
        );

        return $this;
    }

    private function checkFilesystemPrerequisites()
    {
        if (!$this->fs->exists($this->path('website'))) {
            throw new \RuntimeException('Website directory not found in ' . $this->path('website'));
        }

        if ($this->fs->exists($this->path('legacy'))) {
            throw new \RuntimeException('Legacy directory already exists');
        }

        if ($this->fs->exists($this->path('migration-backup'))) {
            throw new \RuntimeException('migration-backup directory already exists');
        }
    }

    /**
     * Tests if version prerequisites match (Pimcore >= 4.6.0, < 5)
     *
     * @param VersionReader $versionReader
     */
    private function checkVersionPrerequisites(VersionReader $versionReader)
    {
        $version = $versionReader->getVersion();

        if (version_compare($version, '5', '>=')) {
            throw new \RuntimeException(sprintf('Installation is already is already version %s...aborting', $version));
        }

        if (version_compare($version, '4.6', '<')) {
            throw new \RuntimeException(sprintf('Current version: %s. Please update to version 4.6 before upgrading to version 5', $version));
        }

        $this->io->success('Pimcore version prerequisites match');
    }

    /**
     * Tests if Pimcore 5 requirements match
     *
     * @return bool
     */
    private function checkPimcoreRequirements()
    {
        $this->io->text('');
        $this->io->comment('Checking Pimcore 5 requirements');

        $formatter = new RequirementsFormatter($this->io);

        if (!$formatter->checkRequirements(new Pimcore5Requirements())) {
            throw new \RuntimeException('Pimcore 5 requirements check failed');
        }
    }

    /**
     * Prints exception message as error and resolves exit code
     *
     * @param \Exception $e
     * @param int $defaultExitCode
     *
     * @return int
     */
    private function handleException(\Exception $e, int $defaultExitCode = 1): int
    {
        if ($defaultExitCode <= 0) {
            throw new \InvalidArgumentException('Default return code must be >= 0');
        }

        $this->io->error($e->getMessage());

        $exitCode = (int)$e->getCode();
        if ($exitCode > 0) {
            return $exitCode;
        }

        return $defaultExitCode;
    }

    /**
     * Build path prefixed with project path
     *
     * @param array ...$parts
     *
     * @return string
     */
    private function path(...$parts): string
    {
        return FileUtils::buildPath($this->path, $parts);
    }
}
