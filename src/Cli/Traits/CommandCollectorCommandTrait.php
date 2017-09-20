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

namespace Pimcore\Cli\Traits;

use Pimcore\Cli\Command\AbstractCommand;
use Pimcore\Cli\Filesystem\CommandCollector\CommandCollectorFactory;
use Pimcore\Cli\Filesystem\CommandCollector\CommandCollectorInterface;
use Symfony\Component\Console\Input\InputOption;

trait CommandCollectorCommandTrait
{
    protected function configureCollectCommandsOption()
    {
        $types = array_map(function (string $type) {
            return sprintf('<comment>%s</comment>', $type);
        }, CommandCollectorFactory::getValidTypes());

        $description = sprintf(
            'Collect and output commands which can used as script. Valid values: %s.',
            implode(', ', $types)
        );

        /** @var $this AbstractCommand */
        $this->addOption(
            'collect-commands', null,
            InputOption::VALUE_REQUIRED,
            $description
        );

        return $this;
    }

    protected function createCommandCollector()
    {
        /** @var $this AbstractCommand */
        $collect = $this->io->getInput()->getOption('collect-commands');

        if ($collect && !empty($collect)) {
            return CommandCollectorFactory::create($collect);
        }
    }

    protected function printCollectedCommands(CommandCollectorInterface $collector)
    {
        /** @var $this AbstractCommand */
        $this->io->section('The following commands were collected during the migration');

        foreach ($collector->getCommands() as $command) {
            $this->io->writeln($command);
        }
    }
}
