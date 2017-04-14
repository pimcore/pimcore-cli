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

use Pimcore\Cli\Command\AbstractCommand;
use Pimcore\Cli\Console\Style\RequirementsFormatter;
use Pimcore\Cli\Console\Style\VersionFormatter;
use Pimcore\Cli\Filesystem\DryRunFilesystem;
use Pimcore\Cli\Pimcore5\Pimcore5Requirements;
use Pimcore\Cli\Util\VersionReader;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

class MigrateFilesystemCommand extends AbstractCommand
{
    /**
     * @var Filesystem
     */
    protected $fs;

    /**
     * @var string
     */
    protected $path;

    protected function configure()
    {
        $this->setName('pimcore5:migrate-filesystem');

        $this
            ->addArgument('path', InputArgument::REQUIRED, 'Path to Pimcore 4 installation')
            ->addOption(
                'no-check-version', null, InputOption::VALUE_NONE,
                'Do not check version prerequisites'
            )
            ->addOption(
                'no-check-requirements', null, InputOption::VALUE_NONE,
                'Do not check Pimcore 5 requirements (you can check them manually via pimcore5:check-requirements command)'
            )
            ->addOption(
                'dry-run', 'N', InputOption::VALUE_NONE,
                'Simulate only (do not change anything)'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = $this->io;
        $fs = $this->fs = new DryRunFilesystem($io, $this->isDryRun());

        $path = $input->getArgument('path');
        if (!$fs->exists($path)) {
            $io->error(sprintf('Given path %s does not exist', $path));

            return 1;
        }

        $this->path = $path = realpath($path);

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

        return 0;
    }

    /**
     * @param array ...$parts
     *
     * @return string
     */
    private function path(...$parts)
    {
        return $this->path . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $parts);
    }

    private function backupFiles()
    {
        $fs = $this->fs;

        $fs->mkdir($this->path('migration-backup'));

        $dirs = ['app', 'bin', 'src', 'web', 'var', 'pimcore'];
        foreach ($dirs as $dir) {
            $source = $this->path($dir);
            $target = $this->path('migration-backup', $dir);

            if ($fs->exists($source)) {
                $fs->rename($source, $target);
            }
        }

        $files = ['index.php', '.htaccess'. 'composer.json', 'composer.lock'];
        foreach ($files as $file) {
            $source = $this->path($file);
            $target = $this->path('migration-backup', $file);

            if ($fs->exists($source)) {
                $fs->rename($source, $target);
            }
        }

        $websiteSource = $this->path('website');
        $websiteTarget = $this->path('website');

        if ($fs->exists($websiteSource)) {
            $fs->mkdir($this->path('legacy'));
            $fs->rename($websiteSource, $websiteTarget);
        }
    }

    private function checkFilesystemPrerequisites()
    {
        if (!$this->fs->exists($this->path('website'))) {
            throw new \RuntimeException('Website directory found in ' . $this->path('website'));
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

    private function isDryRun(): bool
    {
        return (bool)$this->io->getInput()->getOption('dry-run');
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
