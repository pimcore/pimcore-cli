<?php

namespace Pimcore\CsFixer\Fixer\Controller;

use PhpCsFixer\AbstractFixer;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;
use Pimcore\CsFixer\Fixer\Traits\FixerNameTrait;
use Pimcore\CsFixer\Fixer\Traits\SupportsControllerTrait;
use Pimcore\CsFixer\Tokenizer\ImportsModifier;

final class ControllerBaseClassFixer extends AbstractFixer
{
    use FixerNameTrait;
    use SupportsControllerTrait;

    /**
     * @inheritDoc
     */
    public function getDefinition()
    {
        return new FixerDefinition(
            'Changes controller base class to FrontendController',
            [new CodeSample('class TestController extends Website_Controller_Action {}')]
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
        $importsModifier = new ImportsModifier($tokens);

        $classIndexes = [];
        for ($index = $tokens->getSize() - 1; $index > 0; --$index) {
            if ($tokens[$index]->isGivenKind(T_CLASS)) {
                $classIndexes[] = $index;
            }
        }

        foreach ($classIndexes as $index) {
            $className = $tokens->getNextMeaningfulToken($index);
            if (!$className || !$this->isValidControllerName($tokens[$className]->getContent())) {
                continue;
            }

            // figure out where the classy starts
            $classStart = $tokens->getNextTokenOfKind($index, ['{']);

            // back up class definition to keep a reference to any class hierarchies
            $backup = $this->backupClassDefinition($tokens, $index, $classStart);

            if ($this->updateClassDefinition($tokens, $className, $classStart)) {
                // insert backup as comment
                $tokens->insertAt($backup[0], [
                    new Token([T_COMMENT, $backup[1]]),
                    new Token([T_WHITESPACE, "\n"])
                ]);

                // make sure the FrontendController is imported
                $importsModifier->addImport($classStart, 'Pimcore\Controller\FrontendController');
            }
        }
    }

    /**
     * Change extends clause to FrontendController or add an extend if not existing yet
     *
     * @param Tokens $tokens
     * @param int $className
     * @param int $classStart
     *
     * @return bool
     */
    private function updateClassDefinition(Tokens $tokens, int $className, int $classStart)
    {
        $extends = null;
        for ($i = $className; $i < $classStart; ++$i) {
            if ($tokens[$i]->isGivenKind(T_EXTENDS)) {
                $extends = $i;
                break;
            }
        }

        if (null !== $extends) {
            $parentClass = $tokens->getNextMeaningfulToken($extends);

            // already set - abort
            if ($tokens[$parentClass]->getContent() === 'FrontendController') {
                return false;
            }

            $tokens->offsetSet($parentClass, new Token([T_STRING, 'FrontendController']));
        } else {
            $tokens->insertAt($className + 1, [
                new Token([T_WHITESPACE, ' ']),
                new Token([T_EXTENDS, 'extends']),
                new Token([T_STRING, 'FrontendController'])
            ]);
        }

        return true;
    }

    /**
     * Backup class definition as comment
     *
     * @param Tokens $tokens
     * @param int $classIndex
     * @param int $classStart
     *
     * @return array
     */
    private function backupClassDefinition(Tokens $tokens, int $classIndex, int $classStart)
    {
        $startIndex = $classIndex;

        $previousToken = $tokens->getPrevMeaningfulToken($classIndex);
        if ($tokens[$previousToken]->isGivenKind(T_FINAL)) {
            $startIndex = $previousToken;
        }

        if (!$this->isNewlineToken($tokens[$startIndex - 1])) {
            // no newline before [final] class Foo - abort here
            throw new \UnexpectedValueException(sprintf(
                'Expected a newline before class definition but got "%s"',
                $tokens[$startIndex - 1]->getName()
            ));
        }

        $comment = '// ';
        for ($k = $startIndex; $k <= $classStart - 1; $k++) {
            $comment .= str_replace("\n", ' ', $tokens[$k]->getContent());
        }

        // remove extra whitespace
        $comment = preg_replace('/ +/', ' ', $comment);
        $comment = trim($comment);

        return [
            $startIndex,
            $comment
        ];
    }

    private function isNewlineToken(Token $token): bool
    {
        return ($token->isWhitespace() && false !== strpos($token->getContent(), "\n"));
    }
}
