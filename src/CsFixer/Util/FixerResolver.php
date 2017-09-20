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

namespace Pimcore\CsFixer\Util;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

final class FixerResolver
{
    /**
     * @param string|null $subNamespace
     *
     * @return array
     */
    public static function getCustomFixers(string $subNamespace = null): array
    {
        $fixers = [];
        foreach (self::getCustomFixerClasses($subNamespace) as $class) {
            $fixers[] = new $class;
        }

        return $fixers;
    }

    /**
     * @param string|null $subNamespace
     *
     * @return array
     */
    public static function getCustomFixerClasses(string $subNamespace = null): array
    {
        static $customFixers = null;

        if (null === $customFixers) {
            $customFixers = [];
        }

        $key = $subNamespace;
        if (null === $key) {
            $key = '_all';
        }

        if (!isset($customFixers[$key])) {
            $customFixers[$key] = [];

            $dir = __DIR__ . '/../Fixer';
            $baseNamespace = 'Pimcore\\CsFixer\Fixer\\';

            if (null !== $subNamespace) {
                $dir = $dir . '/' . $subNamespace;
                $baseNamespace .= str_replace('/', '\\', $subNamespace) . '\\';
            }

            /** @var SplFileInfo $file */
            foreach (Finder::create()->files()->in($dir) as $file) {
                $relativeNamespace = $file->getRelativePath();
                $fixerClass        = $baseNamespace . ($relativeNamespace ? $relativeNamespace . '\\' : '') . $file->getBasename('.php');

                if ('Fixer' === substr($fixerClass, -5)) {
                    $reflector = new \ReflectionClass($fixerClass);
                    if (!$reflector->isInstantiable()) {
                        continue;
                    }

                    $customFixers[$key][] = $fixerClass;
                }
            }
        }

        return $customFixers[$key];
    }
}
