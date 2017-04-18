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

class TextUtils
{
    /**
     * @param string $content
     *
     * @return string
     *
     * @see http://stackoverflow.com/a/7836692/9131
     */
    public static function normalizeLineEndings(string $content): string
    {
        $content = preg_replace('~\R~u', "\r\n", $content);

        return $content;
    }

    /**
     * @param string $string
     * @param bool $capitalizeFirstCharacter
     *
     * @return string
     */
    public static function dashesToCamelCase(string $string, bool $capitalizeFirstCharacter = false): string
    {
        $str = str_replace('-', '', ucwords($string, '-'));

        if (!$capitalizeFirstCharacter) {
            $str = lcfirst($str);
        }

        return $str;
    }
}
