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
     * Extract quote and raw content from quoted string
     *
     * @param string $string
     *
     * @return array Array with extracted string as first item, quote as second
     */
    public static function extractQuotedString(string $string): array
    {
        $chars = str_split($string);

        $startQuote = array_shift($chars);
        if (!in_array($startQuote, ["'", '"'])) {
            throw new \InvalidArgumentException(sprintf('String does not start with a quote. First char is "%s"', $startQuote));
        }

        $endQuote = array_pop($chars);
        if ($endQuote !== $startQuote) {
            throw new \InvalidArgumentException(sprintf('Start and end quotes do not match. Start: "%s" End: "%s"', $startQuote, $endQuote));
        }

        $string = implode('', $chars);

        return [$string, $startQuote];
    }

    /**
     * Quote a string
     *
     * @param string $string
     * @param string $quote
     *
     * @return string
     */
    public static function quoteString(string $string, string $quote): string
    {
        return $quote . $string . $quote;
    }

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
