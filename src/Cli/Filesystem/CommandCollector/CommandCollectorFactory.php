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

class CommandCollectorFactory
{
    private static $types = [
        'shell' => ShellCommandCollector::class,
        'git'   => GitCommandCollector::class
    ];

    public static function create(string $type): CommandCollectorInterface
    {
        if (!isset(self::$types[$type])) {
            throw new \InvalidArgumentException(sprintf('Collector for type "%s" does not exist', $type));
        }

        $class = self::$types[$type];

        return new $class;
    }

    public static function getValidTypes(): array
    {
        return array_keys(static::$types);
    }
}
