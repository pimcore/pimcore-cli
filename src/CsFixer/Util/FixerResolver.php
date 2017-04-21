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

use PhpCsFixer\Fixer\FixerInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

final class FixerResolver
{
    /**
     * @return FixerInterface[]
     */
    public static function getCustomFixers(): array
    {
        $fixers = [];
        foreach (self::getCustomFixerClasses() as $class) {
            $fixers[] = new $class;
        }

        return $fixers;
    }

    /**
     * @return array
     */
    public static function getCustomFixerClasses(): array
    {
        static $customFixers = null;

        if (null === $customFixers) {
            $customFixers = [];

            /** @var SplFileInfo $file */
            foreach (Finder::create()->files()->in(__DIR__ . '/../Fixer') as $file) {
                $relativeNamespace = $file->getRelativePath();
                $fixerClass        = 'Pimcore\\CsFixer\Fixer\\' . ($relativeNamespace ? $relativeNamespace . '\\' : '') . $file->getBasename('.php');

                if ('Fixer' === substr($fixerClass, -5)) {
                    $customFixers[] = $fixerClass;
                }
            }
        }

        return $customFixers;
    }
}
