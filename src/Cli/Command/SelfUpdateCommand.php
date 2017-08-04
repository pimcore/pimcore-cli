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

namespace Pimcore\Cli\Command;

use Humbug\SelfUpdate\Strategy\GithubStrategy;
use Humbug\SelfUpdate\Updater;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SelfUpdateCommand extends AbstractCommand
{
    protected function configure()
    {
        $this
            ->setName('self-update')
            ->setDescription('Updates the pimcore-cli PHAR to the latest GitHub release')
            ->addOption(
                'check-only', 'c', InputOption::VALUE_NONE,
                'Just check if a new version exists, do not actually update'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $updater = $this->buildUpdater();

        if ($input->getOption('check-only')) {
            return $this->checkForUpdate($updater);
        } else {
            return $this->update($updater);
        }
    }

    private function checkForUpdate(Updater $updater): int
    {
        $result = $updater->hasUpdate();

        if ($result) {
            $this->io->writeln(sprintf(
                'Update available! The current stable build available is: %s. Current version: %s',
                $updater->getNewVersion(),
                $this->getApplication()->getVersion()
            ));
        } elseif (false === $updater->getNewVersion()) {
            $this->io->writeln('There are no stable builds available.');
        } else {
            $this->io->writeln(sprintf(
                'You are already using the latest version %s',
                $this->getApplication()->getVersion()
            ));
        }

        return 0;
    }

    private function update(Updater $updater): int
    {
        if (!$updater->hasUpdate()) {
            $this->io->writeln(sprintf(
                'You are already using the latest version %s',
                $this->getApplication()->getVersion()
            ));

            return 0;
        }

        $currentVersion = $this->getApplication()->getVersion();
        $newVersion     = $updater->getNewVersion();

        try {
            if ($updater->update()) {
                $this->io->success(sprintf('Successfully updated to version %s', $newVersion));

                return 0;
            } else {
                $this->io->error(sprintf('Failed to update to version %s', $newVersion));

                return 1;
            }
        } catch (\Exception $e) {
            $this->io->error(sprintf('Update failed: %s', $e->getMessage()));

            if ($updater->rollback()) {
                $this->io->warning(sprintf('Rolled back to version %s', $currentVersion));
            } else {
                $this->io->error(sprintf('Failed to roll back to version %s', $currentVersion));
            }

            return 2;
        }
    }

    private function buildUpdater(): Updater
    {
        $updater = new Updater(null, false, Updater::STRATEGY_GITHUB);

        /** @var GithubStrategy $strategy */
        $strategy = $updater->getStrategy();
        $strategy->setPackageName('pimcore/pimcore-cli');
        $strategy->setPharName('pimcore.phar');
        $strategy->setCurrentLocalVersion($this->getApplication()->getVersion());

        return $updater;
    }
}
