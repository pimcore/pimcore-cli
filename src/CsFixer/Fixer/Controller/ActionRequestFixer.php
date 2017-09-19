<?php

namespace Pimcore\CsFixer\Fixer\Controller;

use PhpCsFixer\AbstractFixer;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\Tokenizer\Analyzer\ArgumentsAnalyzer;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;
use PhpCsFixer\Tokenizer\TokensAnalyzer;
use Pimcore\CsFixer\Fixer\Traits\FixerNameTrait;
use Pimcore\CsFixer\Fixer\Traits\SupportsControllerTrait;
use Pimcore\CsFixer\Tokenizer\ImportsModifier;

final class ActionRequestFixer extends AbstractFixer
{
    use FixerNameTrait;
    use SupportsControllerTrait;

    /**
     * @inheritDoc
     */
    public function getDefinition()
    {
        return new FixerDefinition(
            'Adds a Request $request parameter to controller actions',
            [new CodeSample('public function fooAction()')]
        );
    }

    /**
     * @inheritDoc
     */
    public function isCandidate(Tokens $tokens)
    {
        return $tokens->isTokenKindFound(T_CLASS);
    }

    protected function applyFix(\SplFileInfo $file, Tokens $tokens)
    {
        $tokensAnalyzer = new TokensAnalyzer($tokens);

        for ($index = $tokens->getSize() - 1; $index > 0; --$index) {
            if (!$tokens[$index]->isGivenKind(T_CLASS)) {
                continue;
            }

            $className = $tokens->getNextMeaningfulToken($index);
            if (!$className || !$this->isValidControllerName($tokens[$className]->getContent())) {
                continue;
            }

            // figure out where the classy starts
            $classStart = $tokens->getNextTokenOfKind($index, ['{']);

            // figure out where the classy ends
            $classEnd = $tokens->findBlockEnd(Tokens::BLOCK_TYPE_CURLY_BRACE, $classStart);

            $updated = $this->updateActions($tokens, $tokensAnalyzer, $classStart, $classEnd);

            if ($updated) {
                $importsModifier = new ImportsModifier($tokens);
                $importsModifier->addImport($classStart, 'Symfony\\Component\\HttpFoundation\\Request');
            }
        }
    }

    /**
     * Finds all public methods which end in Action and adds a Request $request argument if not already existing. Returns
     * if an action was updated to further add the namespace import.
     *
     * @param Tokens $tokens
     * @param TokensAnalyzer $tokensAnalyzer
     * @param int $classStart
     * @param int $classEnd
     *
     * @return bool
     */
    private function updateActions(Tokens $tokens, TokensAnalyzer $tokensAnalyzer, int $classStart, int $classEnd): bool
    {
        $argumentsAnalyzer = new ArgumentsAnalyzer();

        $updated = false;
        for ($index = $classEnd; $index > $classStart; --$index) {
            if (!$tokens[$index]->isGivenKind(T_FUNCTION)) {
                continue;
            }

            $methodNameToken = $tokens->getNextMeaningfulToken($index);
            if (null === $methodNameToken) {
                continue;
            }

            $methodName = $tokens[$methodNameToken];

            // only update methods ending in Action
            if (!$methodName->isGivenKind(T_STRING) || !preg_match('/Action$/', $methodName->getContent())) {
                continue;
            }

            $attributes = $tokensAnalyzer->getMethodAttributes($index);

            // only update public methods
            if (!(null === $attributes['visibility'] || T_PUBLIC === $attributes['visibility'])) {
                continue;
            }

            // do not touch abstract methods
            if (true === $attributes['abstract']) {
                continue;
            }

            $openParenthesis  = $tokens->getNextTokenOfKind($methodNameToken, ['(']);
            $closeParenthesis = $tokens->findBlockEnd(Tokens::BLOCK_TYPE_PARENTHESIS_BRACE, $openParenthesis);
            $arguments        = $argumentsAnalyzer->getArguments($tokens, $openParenthesis, $closeParenthesis);

            $requestNeeded = true;
            foreach ($arguments as $argument) {
                if ($tokens[$argument]->getContent() === '$request') {
                    $requestNeeded = false;
                }
            }

            if ($requestNeeded) {
                $requestArgument = [
                    new Token([T_STRING, 'Request']),
                    new Token([T_WHITESPACE, ' ']),
                    new Token([T_VARIABLE, '$request'])
                ];

                if (count($arguments) > 0) {
                    $requestArgument[] = new Token(',');
                    $requestArgument[] = new Token([T_WHITESPACE, ' ']);
                }

                $tokens->insertAt($openParenthesis + 1, $requestArgument);

                $updated = true;
            }
        }

        return $updated;
    }
}
