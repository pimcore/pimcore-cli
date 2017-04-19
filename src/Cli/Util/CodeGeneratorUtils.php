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

use Zend\Code\Generator\ClassGenerator;

class CodeGeneratorUtils
{
    /**
     * Generates class code as configured in generator and removes superfluous blank lines
     * after class opening and before class closing.
     *
     * @param ClassGenerator $class
     * @param bool $addOpeningTag
     *
     * @return string
     */
    public static function generateClassCode(ClassGenerator $class, bool $addOpeningTag = true): string
    {
        $code = '';
        if ($addOpeningTag) {
            $code = '<?php' . "\n\n";
        }

        $code .= $class->generate();

        // remove superfluous blank lines after class opening and before class closing
        $code = preg_replace('/^(\{)(\n+)/m', "{\n", $code);
        $code = preg_replace('/^(\n+)(\})/m', '}', $code);

        return $code;
    }
}
