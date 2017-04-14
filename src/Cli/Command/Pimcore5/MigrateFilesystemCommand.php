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

use Pimcore\Cli\Console\Style\VersionFormatter;
use Pimcore\Cli\Util\VersionReader;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

class MigrateFilesystemCommand extends Command
{
    protected function configure()
    {
        $this->setName('pimcore5:migrate-filesystem');

        $this
            ->addArgument('path', InputArgument::REQUIRED, 'Path to Pimcore 4 installation')
            ->addOption(
                'dry-run', 'N', InputOption::VALUE_NONE,
                'Simulate only (do not change anything)'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $fs = new Filesystem();

        $path = $input->getArgument('path');
        if (!$fs->exists($path)) {
            $io->error(sprintf('Given path %s does not exist', $path));

            return 1;
        }

        $path = realpath($path);

        $title = sprintf('Migrating installation %s', $path);
        if ($this->isDryRun($input)) {
            $title .= ' (DRY-RUN)';
        }

        $io->title($title);

        try {
            $this->checkPrerequisites($io, $path);
            $io->success('Ready to update');
        } catch (\Exception $e) {
            $io->error($e->getMessage());

            $code = (int)$e->getCode();
            if ($code > 0) {
                return $code;
            }

            return 2;
        }
    }

    private function checkPrerequisites(SymfonyStyle $io, string $path)
    {
        $versionReader    = new VersionReader($path);
        $versionFormatter = new VersionFormatter($io);
        $version          = $versionReader->getVersion();

        $io->comment('Installed version:');
        $versionFormatter->formatVersions($versionReader);

        if (version_compare($version, '5', '>=')) {
            throw new \RuntimeException(sprintf('Installation is already is already version %s...aborting', $version));
        }

        if (version_compare($version, '4.5', '<')) {
            throw new \RuntimeException(sprintf('Please update to version 4.5.0 before upgrading to version 5', $version));
        }
    }

    private function isDryRun(InputInterface $input): bool
    {
        return (bool)$input->getOption('dry-run');
    }
}
