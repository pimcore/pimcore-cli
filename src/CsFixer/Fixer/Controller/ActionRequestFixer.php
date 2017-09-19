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

final class ActionRequestFixer extends AbstractFixer
{
    use FixerNameTrait;

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
    public function supports(\SplFileInfo $file)
    {
        $expectedExtension = '.php';

        if (!substr($file->getFilename(), -strlen($expectedExtension)) === $expectedExtension) {
            return false;
        }

        $baseName = $file->getBasename($expectedExtension);
        if (!preg_match('/Controller$/', $baseName)) {
            return false;
        }

        return true;
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

            // figure out where the classy starts
            $classStart = $tokens->getNextTokenOfKind($index, ['{']);

            // figure out where the classy ends
            $classEnd = $tokens->findBlockEnd(Tokens::BLOCK_TYPE_CURLY_BRACE, $classStart);

            $updated = $this->updateActions($tokens, $tokensAnalyzer, $classStart, $classEnd);

            if ($updated) {
                $this->addRequestImport($tokens, $classStart);
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

    private function addRequestImport(Tokens $tokens, int $classStart)
    {
        $namespaceStart = $this->getNamespaceStart($tokens, $classStart);
        if (null === $namespaceStart) {
            return;
        }

        $importTokens = [
            new Token([T_WHITESPACE, "\n"]),
            new Token([T_USE, 'use']),
            new Token([T_WHITESPACE, ' ']),
            new Token([T_STRING, 'Symfony']),
            new Token([T_NS_SEPARATOR, '\\']),
            new Token([T_STRING, 'Component']),
            new Token([T_NS_SEPARATOR, '\\']),
            new Token([T_STRING, 'HttpFoundation']),
            new Token([T_NS_SEPARATOR, '\\']),
            new Token([T_STRING, 'Request']),
            new Token(';')
        ];

        $uses = $this->getImportUses($tokens, $namespaceStart, $classStart);
        if (0 === count($uses)) {
            array_unshift($importTokens, new Token([T_WHITESPACE, "\n"]));
            $tokens->insertAt($namespaceStart, $importTokens);

            return;
        }

        $importString = $this->stringifyTokenSequence($importTokens);

        $hasImport      = false;
        $insertPosition = $namespaceStart;
        foreach ($uses as $use) {
            $useString = $this->stringifyTokenSequence($use['use']);

            // simple check if Request was already imported. this will fail for group imports or other
            // more sophicsticated notations, but handling all cases is overkill
            if (false !== strpos($useString, 'Symfony\\Component\\HttpFoundation\\Request')) {
                $hasImport = true;
                break;
            }

            $cmp = strcmp($importString, $useString);

            if ($cmp >= 0) {
                $insertPosition = $use['end'] + 1;
            } elseif ($cmp < 0) {
                break;
            }
        }

        if (!$hasImport) {
            $tokens->insertAt($insertPosition, $importTokens);
        }
    }

    /**
     * @param Token[] $tokens
     *
     * @return string
     */
    private function stringifyTokenSequence(array $tokens): string
    {
        $string = '';
        foreach ($tokens as $token) {
            $string .= $token->getContent();
        }

        return trim($string);
    }

    private function getNamespaceStart(Tokens $tokens, int $classStart)
    {
        $namespaceStart = null;
        for ($i = $classStart; $i >= 0; $i--) {
            if ($tokens[$i]->isGivenKind(T_NAMESPACE)) {
                $nextTokenIndex = $tokens->getNextTokenOfKind($i, [';', '{']);
                if (null !== $nextTokenIndex) {
                    $namespaceStart = $nextTokenIndex;
                }

                break;
            }

            if ($tokens[$i]->isGivenKind(T_OPEN_TAG)) {
                $namespaceStart = $i;
                break;
            }
        }

        return $namespaceStart;
    }

    private function getImportUses(Tokens $tokens, int $namespaceStart, int $classStart)
    {
        $uses = [];
        for ($index = $namespaceStart; $index <= $classStart; ++$index) {
            $token = $tokens[$index];

            if ($token->isGivenKind(T_USE)) {
                $useEnd = $tokens->getNextTokenOfKind($index, [';']);

                $use = [];
                for ($i = $index; $i <= $useEnd; $i++) {
                    $use[] = $tokens[$i];
                }

                $uses[] = [
                    'start' => $index,
                    'end'   => $useEnd,
                    'use'   => $use,
                ];
            }
        }

        return $uses;
    }
}
