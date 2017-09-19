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

final class ImportsModifier
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
     * Add a class name import. classStart is used to find the best position inside the class' namespace
     *
     * @param int $classStart
     * @param string $className
     */
    public function addImport(int $classStart, string $className)
    {
        $namespaceStart = $this->getNamespaceStart($classStart);
        if (null === $namespaceStart) {
            return;
        }

        $leadingNewline  = true;
        $trailingNewline = false;

        $importTokens = $this->createImportSequence($className);

        $imports = $this->getImports($namespaceStart, $classStart);
        if (0 === count($imports)) {
            // add a trailing new line if it's a new use block
            $trailingNewline = true;

            $this->insertImport($namespaceStart, $importTokens, $leadingNewline, $trailingNewline);

            return;
        }

        $newImportString = $this->stringifyTokenSequence($importTokens);

        $hasImport      = false;
        $insertPosition = $namespaceStart;

        // try to find ordered insert position by comparing sequence string to existing imports
        foreach ($imports as $import) {
            $importString = $this->stringifyTokenSequence($import['import']);

            // simple check if class was already imported. this will fail for group imports or other
            // more sophicsticated notations, but handling all cases is overkill
            if (false !== strpos($importString, $className)) {
                $hasImport = true;
                break;
            }

            $cmp = strcmp($newImportString, $importString);

            if ($cmp >= 0) {
                $insertPosition = $import['end'] + 1;
            } elseif ($cmp < 0) {
                break;
            }
        }

        if (!$hasImport) {
            $this->insertImport($insertPosition, $importTokens, $leadingNewline, $trailingNewline);
        }
    }

    /**
     * Inserts import sequence at given position and handles adding new lines before and after inserting.
     *
     * The difficulty is that we can't just add a \n token before after the sequence, but we need to edit an existing
     * whitespace token if it is the previous/next one as consequent whitespace tokens lead to errors.
     *
     * @param int $index
     * @param array $importTokens
     * @param bool $leadingNewline
     * @param bool $trailingNewline
     */
    private function insertImport(int $index, array $importTokens, bool $leadingNewline = true, bool $trailingNewline = false)
    {
        $this->tokens->insertAt($index, $importTokens);

        if ($leadingNewline) {
            $previousToken = $this->tokens[$index - 1];

            if ($previousToken->isWhitespace()) {
                $this->tokens->offsetSet(
                    $index - 1,
                    new Token([$previousToken->getId(), $previousToken->getContent() . "\n"])
                );
            } else {
                $this->tokens->insertAt($index, new Token([T_WHITESPACE, "\n"]));
            }
        }

        if ($trailingNewline) {
            $endIndex  = $index + count($importTokens);
            $nextToken = $this->tokens[$endIndex + 1];

            if ($nextToken->isWhitespace()) {
                $this->tokens->offsetSet(
                    $endIndex + 1,
                    new Token([$nextToken->getId(), "\n" . $nextToken->getContent()])
                );
            } else {
                $this->tokens->insertAt($endIndex + 1, new Token([T_WHITESPACE, "\n"]));
            }
        }
    }

    /**
     * Generate token use sequence for a class name
     *
     * @param string $className
     *
     * @return Token[]
     */
    private function createImportSequence(string $className): array
    {
        $tokens = Tokens::fromCode('<?php use ' . $className . ';');

        // ignore first token which is <?php
        $result = [];
        for ($i = 1; $i < count($tokens); $i++) {
            $result[] = $tokens[$i];
        }

        return $result;
    }

    /**
     * Namespace start is either after namespace declaration or after php open tag if
     * no namespace is declared.
     *
     * @param int $classStart
     *
     * @return int|null
     */
    private function getNamespaceStart(int $classStart)
    {
        $namespaceStart = null;
        for ($i = $classStart; $i >= 0; $i--) {
            if ($this->tokens[$i]->isGivenKind(T_NAMESPACE)) {
                $nextTokenIndex = $this->tokens->getNextTokenOfKind($i, [';', '{']);
                if (null !== $nextTokenIndex) {
                    $namespaceStart = $nextTokenIndex;
                }

                break;
            }

            if ($this->tokens[$i]->isGivenKind(T_OPEN_TAG)) {
                $namespaceStart = $i + 1;
                break;
            }
        }

        return $namespaceStart;
    }

    /**
     * Loads all import statements between namespace/php block start and class definition
     *
     * @param int $namespaceStart
     * @param int $classStart
     *
     * @return array
     * @internal param Tokens $tokens
     */
    private function getImports(int $namespaceStart, int $classStart): array
    {
        $imports = [];
        for ($index = $namespaceStart; $index <= $classStart; ++$index) {
            $token = $this->tokens[$index];

            if ($token->isGivenKind(T_USE)) {
                $importEnd = $this->tokens->getNextTokenOfKind($index, [';']);

                $import = [];
                for ($i = $index; $i <= $importEnd; $i++) {
                    $import[] = $this->tokens[$i];
                }

                $imports[] = [
                    'start'  => $index,
                    'end'    => $importEnd,
                    'import' => $import,
                ];
            }
        }

        return $imports;
    }

    /**
     * Converts token sequence to string which will be used to determine sort order
     *
     * @param Token[] $tokens
     *
     * @return string
     */
    private function stringifyTokenSequence(array $tokens): string
    {
        $string = '';
        foreach ($tokens as $token) {
            if ($token instanceof Token) {
                $string .= $token->getContent();
            }
        }

        return trim($string);
    }
}
