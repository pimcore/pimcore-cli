<?php
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

namespace Pimcore\Tests\CsFixer;

use PhpCsFixer\FixerFactory;
use PhpCsFixer\Test\AbstractFixerTestCase as BaseAbstractFixerTestCase;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

abstract class AbstractFixerTestCase extends BaseAbstractFixerTestCase
{
    /**
     * Create fixer factory with all needed fixers registered.
     *
     * @return FixerFactory
     */
    protected function createFixerFactory()
    {
        $factory = FixerFactory::create();
        $this->registerCustomFixers($factory);

        return $factory;
    }

    private function registerCustomFixers(FixerFactory $factory)
    {
        static $customFixers = null;

        if (null === $customFixers) {
            $customFixers = [];

            /** @var SplFileInfo $file */
            foreach (Finder::create()->files()->in(__DIR__ . '/../../src/CsFixer/Fixer') as $file) {
                $relativeNamespace = $file->getRelativePath();
                $fixerClass        = 'Pimcore\\CsFixer\Fixer\\' . ($relativeNamespace ? $relativeNamespace . '\\' : '') . $file->getBasename('.php');

                if ('Fixer' === substr($fixerClass, -5)) {
                    $customFixers[] = $fixerClass;
                }
            }
        }

        foreach ($customFixers as $class) {
            $factory->registerFixer(new $class(), false);
        }
    }
}
