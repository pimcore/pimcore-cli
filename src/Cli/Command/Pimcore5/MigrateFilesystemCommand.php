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
use Pimcore\Cli\Traits\DryRunCommandTrait;
use Pimcore\Cli\Traits\DryRunTrait;
use Pimcore\Cli\Util\FileUtils;
use Pimcore\Cli\Util\VersionReader;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

class MigrateFilesystemCommand extends AbstractCommand
{
    use DryRunCommandTrait;

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
    ];

    protected function configure()
    {
        $this->setName('pimcore5:migrate:filesystem');

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

        $this->configureDryRunOption();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = $this->io;

        $fs = $this->fs = new DryRunFilesystem($io, $this->isDryRun());
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
                ->extractZip($zipFile)
                ->backupFiles()
                ->updateWorkingDirectory()
                ->moveFilesIntoPlace();
        } catch (\Exception $e) {
            return $this->handleException($e, 5);
        }

        $io->success('All done! Please check if all your files were moved properly and remove the .migration directory');

        return 0;
    }

    /**
     * @param array ...$parts
     *
     * @return string
     */
    private function path(...$parts): string
    {
        array_unshift($parts, $this->path);

        return FileUtils::buildPath($parts);
    }

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

        return $this;
    }

    private function backupFiles(): self
    {
        $fs = $this->fs;

        $fs->mkdir($this->path('migration-backup'));

        foreach ($this->filesToBackup as $file) {
            $source = $this->path($file);
            $target = $this->path('migration-backup', $file);

            if ($fs->exists($source)) {
                $fs->rename($source, $target);
            }
        }

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

    private function updateWorkingDirectory(): self
    {
        $fs = $this->fs;

        foreach ($this->filesToUse as $file) {
            $source = FileUtils::buildPath($this->tmpDir, $file);
            $target = $this->path($file);

            if ($fs->exists($source)) {
                $fs->rename($source, $target);
            }
        }

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

        $fs->mkdir($dirs);

        return $this;
    }

    private function moveFilesIntoPlace(): self
    {
        $fs = $this->fs;

        $pairs = [
            'legacy/website/var/config'     => 'var/config',
            'legacy/website/config'         => 'app/config/pimcore',
            'legacy/website/var/classes'    => 'var/classes',
            'legacy/website/var/versions'   => 'var/versions',
            'legacy/website/var/log'        => 'var/logs/pimcore',
            'legacy/website/var/recyclebin' => 'var/recyclebin',
            'legacy/website/var/email'      => 'var/email',
            'legacy/website/var/assets'     => 'web/var/assets',
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
        }

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
     * Test if version prerequisites match (Pimcore >= 4.5.0, < 5)
     *
     * @param VersionReader $versionReader
     */
    private function checkVersionPrerequisites(VersionReader $versionReader)
    {
        $version = $versionReader->getVersion();

        if (version_compare($version, '5', '>=')) {
            throw new \RuntimeException(sprintf('Installation is already is already version %s...aborting', $version));
        }

        if (version_compare($version, '4.5', '<')) {
            throw new \RuntimeException(sprintf('Please update to version 4.5.0 before upgrading to version 5', $version));
        }

        $this->io->success('Pimcore version prerequisites match');
    }

    /**
     * Test if Pimcore 5 requirements match
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
}
