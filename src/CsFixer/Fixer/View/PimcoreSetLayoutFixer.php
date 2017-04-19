<?php

namespace Pimcore\CsFixer\Fixer\View;

use PhpCsFixer\AbstractFixer;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;

final class PimcoreSetLayoutFixer extends AbstractFixer
{
    /**
     * @inheritDoc
     */
    public function getDefinition()
    {
        return new FixerDefinition(
            'Replace calls to $this->layout()->setLayout(\'layout\') with calls to $this->extend()',
            [new CodeSample('<?php $this->layout()->setLayout(\'layout\') ?>')]
        );
    }

    protected function applyFix(\SplFileInfo $file, Tokens $tokens)
    {
        $sequence = [
            [T_VARIABLE, '$this'],
            [T_OBJECT_OPERATOR, '->'],
            [T_STRING, 'layout'],
            '(', ')',
            [T_OBJECT_OPERATOR, '->'],
            [T_STRING, 'setLayout'],
            '('
        ];

        $currIndex = 0;
        while (null !== $currIndex) {
            $match = $tokens->findSequence($sequence, $currIndex, $tokens->count() - 1);

            // stop looping if didn't find any new matches
            if (null === $match) {
                break;
            }

            $indexes   = array_keys($match);
            $lastIndex = array_pop($indexes);
            $currIndex = $lastIndex + 1;

            if ($currIndex >= count($tokens)) {
                break;
            }

            $this->processMatch($tokens, $match);
        }
    }

    /**
     * @param Tokens $tokens
     * @param Token[] $match
     */
    private function processMatch(Tokens $tokens, array $match)
    {
        $indexes  = array_keys($match);
        $rangeEnd = $tokens->getNextTokenOfKind($indexes[count($indexes) - 1], [')']);

        /** @var Token[] $argumentTokens */
        $argumentTokens = [];

        // find first argument - start at opening brace
        $currentIndex = $indexes[count($indexes) - 1];
        while (null !== $currentIndex) {
            $currentIndex = $currentIndex + 1;

            if (!isset($tokens[$currentIndex])) {
                break;
            }

            $current = $tokens[$currentIndex];
            if ($current->equalsAny([',', ')'])) {
                break;
            } else {
                $argumentTokens[] = $current;
            }
        }

        $replacement = [
            new Token([T_VARIABLE, '$this']),
            new Token([T_OBJECT_OPERATOR, '->']),
            new Token([T_STRING, 'extend']),
            new Token('(')
        ];

        // arguments is a single string (e.g. 'layout') -> just add the extension
        if (count($argumentTokens) === 1 && $argumentTokens[0]->isGivenKind(T_CONSTANT_ENCAPSED_STRING)) {
            $chars     = str_split($argumentTokens[0]->getContent());
            $quoteChar = array_pop($chars);

            $argumentTokens[0]->setContent(implode('', $chars) . '.html.php' . $quoteChar);
        } else {
            $argumentTokens[] = new Token([T_WHITESPACE, ' ']);
            $argumentTokens[] = new Token('.');
            $argumentTokens[] = new Token([T_WHITESPACE, ' ']);
            $argumentTokens[] = new Token([T_CONSTANT_ENCAPSED_STRING, "'.html.php'"]);
        }

        foreach ($argumentTokens as $argumentToken) {
            $replacement[] = $argumentToken;
        }

        $replacement[] = new Token(')');

        $tokens->overrideRange($indexes[0], $rangeEnd, $replacement);
    }

    /**
     * @inheritDoc
     */
    public function supports(\SplFileInfo $file)
    {
        $expectedExtension = '.html.php';

        return substr($file->getFilename(), -strlen($expectedExtension)) === $expectedExtension;
    }

    /**
     * @inheritDoc
     */
    public function isCandidate(Tokens $tokens)
    {
        return $tokens->isTokenKindFound(T_VARIABLE) && $tokens->isTokenKindFound(T_OBJECT_OPERATOR);
    }
}
