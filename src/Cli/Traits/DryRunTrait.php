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

trait DryRunTrait
{
    /**
     * Prefix message with dry run if in dry-run mode
     *
     * @param $message
     * @param string $prefix
     *
     * @return string
     */
    protected function dryRunMessage(string $message, string $prefix = 'DRY-RUN'): string
    {
        if ($this->isDryRun()) {
            $message = $this->prefixDryRun($message, $prefix);
        }

        return $message;
    }

    /**
     * Prefix message with DRY-RUN
     *
     * @param $message
     * @param string $prefix
     *
     * @return string
     */
    protected function prefixDryRun(string $message, string $prefix = 'DRY-RUN'): string
    {
        return sprintf(
            '<fg=cyan;bg=black>[%s]</> %s',
            $prefix,
            $message
        );
    }

    abstract protected function isDryRun(): bool;
}
