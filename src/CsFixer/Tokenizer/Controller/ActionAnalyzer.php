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

namespace Pimcore\CsFixer\Tokenizer\Controller;

use PhpCsFixer\Tokenizer\Tokens;
use PhpCsFixer\Tokenizer\TokensAnalyzer;

final class ActionAnalyzer
{
    public function isValidAction(Tokens $tokens, TokensAnalyzer $tokensAnalyzer, int $index, bool $allowAbstractMethods = false): bool
    {
        $methodNameToken = $tokens->getNextMeaningfulToken($index);
        if (null === $methodNameToken) {
            return false;
        }

        $methodName = $tokens[$methodNameToken];

        // only update methods ending in Action
        if (!$methodName->isGivenKind(T_STRING) || !preg_match('/Action$/', $methodName->getContent())) {
            return false;
        }

        $attributes = $tokensAnalyzer->getMethodAttributes($index);

        // only update public methods
        if (!(null === $attributes['visibility'] || T_PUBLIC === $attributes['visibility'])) {
            return false;
        }

        // do not touch abstract methods
        if (!$allowAbstractMethods && true === $attributes['abstract']) {
            return false;
        }

        return true;
    }
}
