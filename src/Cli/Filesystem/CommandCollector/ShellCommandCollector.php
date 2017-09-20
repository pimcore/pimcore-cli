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

namespace Pimcore\Cli\Filesystem\CommandCollector;

class ShellCommandCollector implements CommandCollectorInterface
{
    /**
     * @var array
     */
    private $commands = [];

    /**
     * @var array
     */
    private $blacklist = [
        'dumpFile'
    ];

    public function collect(string $command)
    {
        foreach ($this->blacklist as $entry) {
            if (0 === strpos($command, $entry)) {
                return;
            }
        }

        $this->commands[] = $command;
    }

    public function getCommands(): array
    {
        return $this->commands;
    }
}
