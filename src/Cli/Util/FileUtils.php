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
     * Flattens parts of a path to a path string. Parts can either be strings or array
     *
     * @param array ...$parts
     *
     * @return string
     */
    public static function buildPath(...$parts): string
    {
        $iterator = new \RecursiveIteratorIterator(new \RecursiveArrayIterator($parts));

        $array = [];
        foreach ($iterator as $part) {
            $array[] = $part;
        }

        return implode(DIRECTORY_SEPARATOR, $array);
    }

    /**
     * @param string $path
     *
     * @return bool
     */
    public static function isDirectoryEmpty(string $path): bool
    {
        if (!file_exists($path) || !is_dir($path)) {
            throw new \InvalidArgumentException('Given path is no directory');
        }

        $iterator = new \FilesystemIterator($path, \FilesystemIterator::SKIP_DOTS);
        foreach ($iterator as $file) {
            return false;
        }

        return true;
    }

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
