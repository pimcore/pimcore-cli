<?php

namespace Pimcore\CsFixer\Fixer\Controller;

use PhpCsFixer\AbstractFixer;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;
use Pimcore\CsFixer\Fixer\Traits\FixerNameTrait;
use Pimcore\CsFixer\Fixer\Traits\LoggingFixerTrait;
use Pimcore\CsFixer\Fixer\Traits\SupportsControllerTrait;
use Pimcore\CsFixer\Log\LoggingFixerInterface;
use Pimcore\CsFixer\Tokenizer\TokenInsertManipulator;

final class ControllerNamespaceFixer extends AbstractFixer implements LoggingFixerInterface
{
    use FixerNameTrait;
    use SupportsControllerTrait;
    use LoggingFixerTrait;

    /**
     * @inheritDoc
     */
    public function getDefinition()
    {
        return new FixerDefinition(
            'Adds namespace to controllers without a namespace',
            [new CodeSample('<?php namespace AppBundle\Controller')]
        );
    }

    /**
     * @inheritDoc
     */
    public function isCandidate(Tokens $tokens)
    {
        return $tokens->isTokenKindFound(T_CLASS) && !$tokens->isTokenKindFound(T_NAMESPACE);
    }

    protected function applyFix(\SplFileInfo $file, Tokens $tokens)
    {
        $newNamespaceIndex = null;
        for ($index = 0; $index < $tokens->getSize() - 1; $index++) {
            if ($tokens[$index]->isGivenKind(T_USE) || $tokens[$index]->isGivenKind(Token::getClassyTokenKinds())) {
                $newNamespaceIndex = $index - 1;
                break;
            }
        }

        if (null === $newNamespaceIndex) {
            return;
        }

        $this->getLogger()->info($file, 'Adding namespace {namespace}', ['namespace' => 'AppBundle\\Controller']);

        $namespace = [
            new Token([T_NAMESPACE, 'namespace']),
            new Token([T_WHITESPACE, ' ']),
            new Token([T_STRING, 'AppBundle']),
            new Token([T_NS_SEPARATOR, '\\']),
            new Token([T_STRING, 'Controller']),
            new Token(';'),
        ];

        $manipulator = new TokenInsertManipulator($tokens);
        $manipulator->insertAtIndex($newNamespaceIndex, $namespace, 1, 1);
    }
}
