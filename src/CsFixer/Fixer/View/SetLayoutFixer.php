<?php

namespace Pimcore\CsFixer\Fixer\View;

use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\Tokenizer\Analyzer\ArgumentsAnalyzer;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;
use Pimcore\CsFixer\Fixer\AbstractFunctionReferenceFixer;
use Pimcore\CsFixer\Fixer\Traits\FixerNameTrait;

final class SetLayoutFixer extends AbstractFunctionReferenceFixer
{
    use FixerNameTrait;

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
        $sequence = [
            [T_VARIABLE, '$this'],
            [T_OBJECT_OPERATOR, '->'],
            [T_STRING, 'layout'],
            '(', ')',
            [T_OBJECT_OPERATOR, '->'],
            [T_STRING, 'setLayout'],
            '('
        ];

        $candidates = $this->findFunctionCallCandidates($tokens, $sequence);

        foreach ($candidates as $candidate) {
            list($match, $openParenthesis, $closeParenthesis) = $candidate;

            $this->processMatch($tokens, $match, $openParenthesis, $closeParenthesis);
        }
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

        $indexes = array_keys($match);
        $tokens->overrideRange($indexes[0], $closeParenthesis - 1, $replacement);
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
        $analyzer  = new ArgumentsAnalyzer();
        $arguments = $analyzer->getArguments($tokens, $openParenthesis, $closeParenthesis);
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

            $tokens[$startIndex] = new Token([$tokens[$startIndex]->getId(), implode('', $chars) . '.html.php' . $quoteChar]);
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
