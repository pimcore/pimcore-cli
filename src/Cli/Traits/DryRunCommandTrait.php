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
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;

trait DryRunCommandTrait
{
    use DryRunTrait;

    /**
     * @param string|null $description
     *
     * @return $this
     */
    protected function configureDryRunOption(string $description = null)
    {
        if (null === $description) {
            $description = 'Simulate only (do not change anything)';
        }

        /** @var Command $this */
        $this->addOption(
            'dry-run', 'N', InputOption::VALUE_NONE,
            $description
        );

        return $this;
    }

    protected function isDryRun(): bool
    {
        /** @var AbstractCommand $this */
        return (bool)$this->io->getInput()->getOption('dry-run');
    }
}
