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
use Pimcore\CsFixer\Tokenizer\FunctionAnalyzer;

abstract class AbstractFunctionReferenceFixer extends BaseAbstractFunctionReferenceFixer
{
    /**
     * Finds function call candidates from from a sequence. Sequence must end in opening parenthesis!
     *
     * @param Tokens $tokens
     * @param array $sequence
     *
     * @return array
     *
     * @deprecated use FunctionAnalyzer instead
     */
    protected function findFunctionCallCandidates(Tokens $tokens, array $sequence)
    {
        return (new FunctionAnalyzer())->findFunctionCallCandidates($tokens, $sequence);
    }

    /**
     * Extracts tokens for a given argument
     *
     * @param Tokens $tokens
     * @param array $arguments   The result of getArguments()
     * @param int $argumentIndex The index of the argument
     * @param bool $keepIndex
     *
     * @return array|Token[]
     *
     * @deprecated use FunctionAnalyzer instead
     */
    protected function extractArgumentTokens(Tokens $tokens, array $arguments, int $argumentIndex, bool $keepIndex = false): array
    {
        return (new FunctionAnalyzer())->extractArgumentTokens($tokens, $arguments, $argumentIndex, $keepIndex);
    }
}
