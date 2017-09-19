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

use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;

class TokenInsertManipulator
{
    /**
     * @var Tokens
     */
    private $tokens;

    public function __construct(Tokens $tokens)
    {
        $this->tokens = $tokens;
    }

    /**
     * Inserts sequence at given position and handles adding new lines before and after inserting.
     *
     * The difficulty is that we can't just add a \n token before after the sequence, but we need to edit an existing
     * whitespace token if it is the previous/next one as consequent whitespace tokens lead to errors.
     *
     * @param int $index
     * @param array $tokens
     * @param int $leadingNewlines
     * @param int $trailingNewlines
     */
    public function insertAtIndex(int $index, array $tokens, int $leadingNewlines = 0, int $trailingNewlines = 0)
    {
        $this->tokens->insertAt($index, $tokens);

        if ($leadingNewlines > 0) {
            $leadingContent = str_repeat("\n", $leadingNewlines);

            $previousToken = $this->tokens[$index - 1];

            if ($previousToken->isWhitespace()) {
                $this->tokens->offsetSet(
                    $index - 1,
                    new Token([$previousToken->getId(), $previousToken->getContent() . $leadingContent])
                );
            } else {
                $this->tokens->insertAt($index, new Token([T_WHITESPACE, $leadingContent]));
            }
        }

        if ($trailingNewlines > 0) {
            $trailingContent = str_repeat("\n", $trailingNewlines);

            $endIndex  = $index + count($tokens);
            $nextToken = $this->tokens[$endIndex + 1];

            if ($nextToken->isWhitespace()) {
                $this->tokens->offsetSet(
                    $endIndex + 1,
                    new Token([$nextToken->getId(), $trailingContent . $nextToken->getContent()])
                );
            } else {
                $this->tokens->insertAt($endIndex + 1, new Token([T_WHITESPACE, $trailingContent]));
            }
        }
    }
}
