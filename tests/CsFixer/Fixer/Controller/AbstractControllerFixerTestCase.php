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

namespace Pimcore\Tests\CsFixer\Fixer\Controller;

use Pimcore\Tests\CsFixer\AbstractFixerTestCase;

abstract class AbstractControllerFixerTestCase extends AbstractFixerTestCase
{
    /**
     * @inheritDoc
     */
    protected function getTestFile($filename = __FILE__)
    {
        return new \SplFileInfo('src/AppBundle/Controller/TestController.php');
    }
}
