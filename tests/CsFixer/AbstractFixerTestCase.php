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
use PhpCsFixer\Tests\Test\AbstractFixerTestCase as BaseAbstractFixerTestCase;
use Pimcore\CsFixer\Log\FixerLogger;
use Pimcore\CsFixer\Util\FixerResolver;

abstract class AbstractFixerTestCase extends BaseAbstractFixerTestCase
{
    /**
     * @inheritDoc
     */
    protected function getFixerName()
    {
        return sprintf('Pimcore/%s', parent::getFixerName());
    }

    /**
     * @inheritDoc
     */
    protected function createFixerFactory()
    {
        $logger = new FixerLogger();

        $factory = FixerFactory::create();
        $factory->registerCustomFixers(FixerResolver::getCustomFixers($logger));

        return $factory;
    }
}
