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

namespace Pimcore\CsFixer\Fixer;

use PhpCsFixer\AbstractFunctionReferenceFixer as BaseAbstractFunctionReferenceFixer;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;

abstract class AbstractFunctionReferenceFixer extends BaseAbstractFunctionReferenceFixer
{
    /**
     * Finds function call candidates from from a sequence. Sequence must end in opening parenthesis!
     *
     * @param Tokens $tokens
     * @param array $sequence
     *
     * @return array
     */
    protected function findFunctionCallCandidates(Tokens $tokens, array $sequence)
    {
        if (count($sequence) === 0) {
            throw new \InvalidArgumentException('Sequence can\'t be empty!');
        }

        // add opening parenthesis if missing
        if ($sequence[count($sequence) -1] !== '(') {
            throw new \InvalidArgumentException('Sequence must end in opening parenthesis!');
        }

        $candidates = [];

        $currIndex = 0;
        while (null !== $currIndex) {
            $match = $tokens->findSequence($sequence, $currIndex, $tokens->count() - 1);

            // stop looping if didn't find any new matches
            if (null === $match) {
                break;
            }

            $indexes         = array_keys($match);
            $openParenthesis = array_pop($indexes);

            $closeParenthesis = $tokens->findBlockEnd(Tokens::BLOCK_TYPE_PARENTHESIS_BRACE, $openParenthesis);

            $candidates[] = [$match, $openParenthesis, $closeParenthesis];

            $currIndex = $openParenthesis + 1;
            if ($currIndex >= count($tokens) - 1) {
                break;
            }
        }

        return array_reverse($candidates);
    }

    /**
     * Extracts tokens for a given argument
     *
     * @param Tokens $tokens
     * @param array $arguments      The result of getArguments()
     * @param int $argumentIndex    The index of the argument
     * @param bool $keepIndex
     *
     * @return array|Token[]
     */
    protected function extractArgumentTokens(Tokens $tokens, array $arguments, int $argumentIndex, bool $keepIndex = false): array
    {
        $indexes = array_keys($arguments);

        if (!isset($indexes[$argumentIndex])) {
            throw new \InvalidArgumentException(sprintf('Argument at index %d does not exist', $argumentIndex));
        }

        // arguments is an array indexed by start -> end
        $startIndex = $indexes[$argumentIndex];
        $endIndex   = $arguments[$startIndex];

        $argument = [];
        for ($i = $startIndex; $i <= $endIndex; $i++) {
            if ($keepIndex) {
                $argument[$i] = $tokens[$i];
            } else {
                $argument[] = $tokens[$i];
            }
        }

        return $argument;
    }
}
