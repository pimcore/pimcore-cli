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
use Pimcore\Cli\Command\Pimcore5\Traits\RenameViewCommandTrait;
use Pimcore\Cli\Filesystem\DryRunFilesystem;
use Pimcore\Cli\Traits\CommandCollectorCommandTrait;
use Pimcore\Cli\Traits\DryRunCommandTrait;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;

class RenameViewsCommand extends AbstractCommand
{
    use RenameViewCommandTrait;
    use DryRunCommandTrait;
    use CommandCollectorCommandTrait;

    protected function configure()
    {
        $this
            ->setName('pimcore5:views:rename')
            ->setDescription('Rename view files (change extension and file casing)')
            ->addArgument('sourceDir', InputArgument::REQUIRED)
            ->addArgument('targetDir', InputArgument::REQUIRED)
            ->addOption(
                'copy', 'c',
                InputOption::VALUE_NONE,
                'Copy files instead of moving them'
            )
            ->configureCollectCommandsOption()
            ->configureViewRenameOptions()
            ->configureDryRunOption();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $sourceDir = $input->getArgument('sourceDir');
        $targetDir = $input->getArgument('targetDir');

        $sourceDir = $sourceDir ? realpath($sourceDir) : null;
        if (!($sourceDir && file_exists($sourceDir) && is_dir($sourceDir))) {
            throw new \InvalidArgumentException('Invalid source directory');
        }

        $sourceDir = rtrim($sourceDir, DIRECTORY_SEPARATOR);
        $targetDir = rtrim($targetDir, DIRECTORY_SEPARATOR);

        $finder = new Finder();
        $finder
            ->files()
            ->in($sourceDir)
            ->name('*.php');

        $collector = $this->createCommandCollector();
        $fs        = new DryRunFilesystem($this->io, $this->isDryRun(), false, $collector);

        $createdDirs = [];

        $fs->mkdir($targetDir);
        $createdDirs[] = $targetDir;

        foreach ($finder as $file) {
            $relativePath = str_replace($sourceDir . DIRECTORY_SEPARATOR, '', $file->getRealPath());
            $updatedPath  = $this->processPath($input, $relativePath);
            $targetPath   = $targetDir . DIRECTORY_SEPARATOR . $updatedPath;

            if ($fs->exists($targetPath)) {
                $this->io->writeln(sprintf('<comment>WARNING:</comment> File %s already exists, skipping...', $targetPath));
                continue;
            }

            $dirToCreate = dirname($targetPath);
            if (!in_array($dirToCreate, $createdDirs)) {
                $fs->mkdir($dirToCreate);
                $createdDirs[] = $dirToCreate;
            }

            if ($input->getOption('copy')) {
                $fs->copy($file->getRealPath(), $targetPath);
            } else {
                $fs->rename($file->getRealPath(), $targetPath);
            }

            if ($this->io->isVerbose()) {
                $this->io->newLine();
            }
        }

        if (null !== $collector) {
            $this->printCollectedCommands($collector);
        }
    }
}
