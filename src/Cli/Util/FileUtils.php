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

namespace Pimcore\Cli\Util;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\SplFileInfo;

class FileUtils
{
    /**
     * @param string $path
     *
     * @return string
     */
    public static function getFileContents(string $path): string
    {
        $fs = new Filesystem();

        if (!$fs->exists($path) || !is_file($path)) {
            throw new \InvalidArgumentException(sprintf('File %s does not exist', $path));
        }

        $relativePath = $fs->makePathRelative($path, getcwd());
        $info         = new SplFileInfo($path, dirname($relativePath), $relativePath);

        return $info->getContents();
    }
}
