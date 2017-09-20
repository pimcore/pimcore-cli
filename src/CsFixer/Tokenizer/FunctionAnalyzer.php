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

namespace Pimcore\CsFixer\Tokenizer;

use PhpCsFixer\Tokenizer\Tokens;

final class FunctionAnalyzer
{
    /**
     * Finds function call candidates from from a sequence. Sequence must end in opening parenthesis!
     *
     * @param Tokens $tokens
     * @param array $sequence
     * @param int|null $startIndex
     * @param int|null $endIndex
     *
     * @return array
     */
    public function findFunctionCallCandidates(Tokens $tokens, array $sequence, int $startIndex = null, int $endIndex = null): array
    {
        if (count($sequence) === 0) {
            throw new \InvalidArgumentException('Sequence can\'t be empty!');
        }

        // add opening parenthesis if missing
        if ($sequence[count($sequence) - 1] !== '(') {
            throw new \InvalidArgumentException('Sequence must end in opening parenthesis!');
        }

        if (null === $startIndex) {
            $startIndex = 0;
        }

        if (null === $endIndex) {
            $endIndex = count($tokens) - 1;
        }

        $candidates = [];

        $currIndex = 0;
        while (null !== $currIndex) {
            $match = $tokens->findSequence($sequence, $currIndex, $endIndex);

            // stop looping if didn't find any new matches
            if (null === $match) {
                break;
            }

            $indexes         = array_keys($match);
            $openParenthesis = array_pop($indexes);

            $closeParenthesis = $tokens->findBlockEnd(Tokens::BLOCK_TYPE_PARENTHESIS_BRACE, $openParenthesis);

            $candidates[] = [$match, $openParenthesis, $closeParenthesis];

            $currIndex = $openParenthesis + 1;
            if ($currIndex >= $endIndex) {
                break;
            }
        }

        return array_reverse($candidates);
    }

    /**
     * Extracts tokens for a given argument
     *
     * @param Tokens $tokens
     * @param array $arguments   The result of getArguments()
     * @param int $argumentIndex The index of the argument
     * @param bool $keepIndex
     *
     * @return array
     */
    public function extractArgumentTokens(Tokens $tokens, array $arguments, int $argumentIndex, bool $keepIndex = false): array
    {
        $indexes = array_keys($arguments);

        if (!isset($indexes[$argumentIndex])) {
            throw new \InvalidArgumentException(sprintf('Argument at index %d does not exist', $argumentIndex));
        }

        // arguments is an array indexed by start -> end
        $startIndex = $indexes[$argumentIndex];
        $endIndex   = $arguments[$startIndex];

        $argumentTokens = [];
        for ($i = $startIndex; $i <= $endIndex; $i++) {
            // ignore leading whitespace tokens
            if (empty($argumentTokens) && $tokens[$i]->isGivenKind(T_WHITESPACE)) {
                continue;
            }

            if ($keepIndex) {
                $argumentTokens[$i] = $tokens[$i];
            } else {
                $argumentTokens[] = $tokens[$i];
            }
        }

        return $argumentTokens;
    }
}
