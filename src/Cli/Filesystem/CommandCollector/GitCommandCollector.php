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

class GitCommandCollector implements CommandCollectorInterface
{
    /**
     * @var ShellCommandCollector
     */
    private $collector;

    /**
     * @var array
     */
    private $gitCommands = [
        'mv',
        'rm'
    ];

    public function __construct(ShellCommandCollector $collector = null)
    {
        if (null === $collector) {
            $collector = new ShellCommandCollector();
        }

        $this->collector = $collector;
    }

    public function collect(string $command)
    {
        foreach ($this->gitCommands as $gitCommand) {
            if (0 === strpos($command, $gitCommand)) {
                $command = 'git ' . $command;
                break;
            }
        }

        $this->collector->collect($command);
    }

    public function getCommands(): array
    {
        return $this->collector->getCommands();
    }
}
