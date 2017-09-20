<?php

namespace Pimcore\CsFixer\Fixer\Controller;

use PhpCsFixer\AbstractFixer;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;
use PhpCsFixer\Tokenizer\TokensAnalyzer;
use Pimcore\CsFixer\Fixer\Traits\FixerNameTrait;
use Pimcore\CsFixer\Fixer\Traits\SupportsControllerTrait;
use Pimcore\CsFixer\Tokenizer\Controller\ActionAnalyzer;
use Pimcore\CsFixer\Tokenizer\FunctionAnalyzer;

final class ControllerGetParamFixer extends AbstractFixer
{
    use FixerNameTrait;
    use SupportsControllerTrait;

    /**
     * @inheritDoc
     */
    public function getDefinition()
    {
        return new FixerDefinition(
            'Change calls to $this->getParam() to $request->get()',
            [new CodeSample('<?php $this->getParam()')]
        );
    }

    /**
     * @inheritDoc
     */
    public function isCandidate(Tokens $tokens)
    {
        return
            $tokens->isTokenKindFound(T_CLASS)
            && $tokens->isTokenKindFound(T_VARIABLE)
            && $tokens->isTokenKindFound(T_OBJECT_OPERATOR)
            && $tokens->isTokenKindFound(T_CONSTANT_ENCAPSED_STRING);
    }

    protected function getSequence(): array
    {
        return [
            [T_VARIABLE, '$this'],
            [T_OBJECT_OPERATOR, '->'],
            [T_STRING, 'getParam'],
            '('
        ];
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

            $classStart = $tokens->getNextTokenOfKind($index, ['{']);
            $classEnd   = $tokens->findBlockEnd(Tokens::BLOCK_TYPE_CURLY_BRACE, $classStart);

            $this->processClass($tokens, $tokensAnalyzer, $classStart, $classEnd);
        }
    }

    private function processClass(Tokens $tokens, TokensAnalyzer $tokensAnalyzer, int $classStart, int $classEnd)
    {
        $actionAnalyzer = new ActionAnalyzer();

        for ($index = $classEnd; $index > $classStart; --$index) {
            if (!$tokens[$index]->isGivenKind(T_FUNCTION)) {
                continue;
            }

            if (!$actionAnalyzer->isValidAction($tokens, $tokensAnalyzer, $index)) {
                continue;
            }

            $methodNameToken = $tokens->getNextMeaningfulToken($index);
            if (null === $methodNameToken) {
                continue;
            }

            $methodStart = $tokens->getNextTokenOfKind($methodNameToken, ['{']);
            $methodEnd   = $tokens->findBlockEnd(Tokens::BLOCK_TYPE_CURLY_BRACE, $methodStart);

            if (null === $methodStart || null === $methodEnd) {
                throw new \UnexpectedValueException(sprintf('Failed to find method end for method "%s"', $tokens[$methodNameToken]->getContent()));
            }

            $this->processAction($tokens, $methodStart, $methodEnd);
        }
    }

    private function processAction(Tokens $tokens, int $methodStart, int $methodEnd)
    {
        $functionAnalyzer = new FunctionAnalyzer();

        $sequence   = $this->getSequence();
        $candidates = $functionAnalyzer->findFunctionCallCandidates($tokens, $sequence, $methodStart, $methodEnd);

        foreach ($candidates as $candidate) {
            list($match, $openParenthesis, $closeParenthesis) = $candidate;

            $this->processCandidate($tokens, $match, $openParenthesis, $closeParenthesis);
        }
    }

    /**
     * @param Tokens $tokens
     * @param Token[] $match
     * @param int $openParenthesis
     * @param int $closeParenthesis
     */
    private function processCandidate(Tokens $tokens, array $match, int $openParenthesis, int $closeParenthesis)
    {
        $indexes = array_keys($match);

        // replace $this->getParam() with $request->get()
        // we assume the $request was already added to the action method in ActionRequestFixer
        $tokens->offsetSet($indexes[0], new Token([T_VARIABLE, '$request']));
        $tokens->offsetSet($indexes[2], new Token([T_STRING, 'get']));
    }
}
