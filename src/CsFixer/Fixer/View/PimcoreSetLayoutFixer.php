<?php

namespace Pimcore\CsFixer\Fixer\View;

use PhpCsFixer\AbstractFunctionReferenceFixer;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;

final class PimcoreSetLayoutFixer extends AbstractFunctionReferenceFixer
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

    /**
     * @inheritDoc
     */
    public function isRisky()
    {
        return false;
    }

    protected function applyFix(\SplFileInfo $file, Tokens $tokens)
    {
        $candidates = $this->findCandidates($tokens);

        foreach ($candidates as $candidate) {
            list($match, $openParenthesis, $closeParenthesis) = $candidate;

            $this->processMatch($tokens, $match, $openParenthesis, $closeParenthesis);
        }
    }

    /**
     * @param Tokens $tokens
     *
     * @return array
     */
    private function findCandidates(Tokens $tokens)
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
     * @param Tokens $tokens
     * @param Token[] $match
     */
    private function processMatch(Tokens $tokens, array $match, $openParenthesis, $closeParenthesis)
    {
        $replacement = [
            new Token([T_VARIABLE, '$this']),
            new Token([T_OBJECT_OPERATOR, '->']),
            new Token([T_STRING, 'extend']),
            new Token('(')
        ];

        $argument = $this->processArguments($tokens, $openParenthesis, $closeParenthesis);
        foreach ($argument as $token) {
            $replacement[] = $token;
        }

        $replacement[] = new Token(')');

        $indexes = array_keys($match);
        $tokens->overrideRange($indexes[0], $closeParenthesis, $replacement);
    }

    /**
     * Finds arguments, adds .html.php to the first one and drops the rest
     *
     * @param Tokens $tokens
     * @param int $openParenthesis
     * @param int $closeParenthesis
     *
     * @return Token[]
     */
    private function processArguments(Tokens $tokens, $openParenthesis, $closeParenthesis)
    {
        $arguments = $this->getArguments($tokens, $openParenthesis, $closeParenthesis);
        $indexes   = array_keys($arguments);

        // arguments is an array indexed by start -> end
        $startIndex = $indexes[0];
        $endIndex   = $arguments[$startIndex];

        // we just use the first argument and drop the rest, so we just need tokens of the first argument
        /** @var Token[] $argument */
        $argument = [];

        // first argument is a simple string -> just alter the string and add our file extension
        if ($startIndex === $endIndex && $tokens[$startIndex]->isGivenKind(T_CONSTANT_ENCAPSED_STRING)) {
            $chars     = str_split($tokens[$startIndex]->getContent());
            $quoteChar = array_pop($chars);

            $tokens[$startIndex]->setContent(implode('', $chars) . '.html.php' . $quoteChar);
            $argument[] = $tokens[$startIndex];
        } else {
            // add all argument tokens and concat the file extension
            for ($i = $startIndex; $i <= $endIndex; $i++) {
                $argument[] = $tokens[$i];
            }

            $argument[] = new Token([T_WHITESPACE, ' ']);
            $argument[] = new Token('.');
            $argument[] = new Token([T_WHITESPACE, ' ']);
            $argument[] = new Token([T_CONSTANT_ENCAPSED_STRING, "'.html.php'"]);
        }

        return $argument;
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
