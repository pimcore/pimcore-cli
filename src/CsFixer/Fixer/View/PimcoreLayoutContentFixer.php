<?php

namespace Pimcore\CsFixer\Fixer\View;

use PhpCsFixer\AbstractFixer;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;

final class PimcoreLayoutContentFixer extends AbstractFixer
{
    /**
     * @inheritDoc
     */
    public function getDefinition()
    {
        return new FixerDefinition(
            'Replace calls to $this->layout()->content with slots helper',
            [new CodeSample('<?= $this->layout()->content ?>')]
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
            [T_STRING, 'content'],
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
        $indexes = array_keys($match);

        $tokens->overrideRange($indexes[0], $indexes[count($indexes)-1], [
            new Token([T_VARIABLE, '$this']),
            new Token([T_OBJECT_OPERATOR, '->']),
            new Token([T_STRING, 'slots']),
            new Token('('),
            new Token(')'),
            new Token([T_OBJECT_OPERATOR, '->']),
            new Token([T_STRING, 'output']),
            new Token('('),
            new Token([T_CONSTANT_ENCAPSED_STRING, "'_content'"]),
            new Token(')'),
        ]);

        $prev      = $tokens->getPrevMeaningfulToken($indexes[0]);
        $prevToken = $tokens[$prev];

        if ($prevToken->isGivenKind(T_OPEN_TAG_WITH_ECHO)) {
            $tokens->overrideAt($prev, new Token([T_OPEN_TAG, '<?php ']));
            $tokens->removeTrailingWhitespace($prev);
        } elseif ($prevToken->isGivenKind(T_ECHO)) {
            $prevToken->clear();
            $tokens->removeTrailingWhitespace($prev);
        }
    }

    /**
     * @inheritDoc
     */
    public function isCandidate(Tokens $tokens)
    {
        return $tokens->isTokenKindFound(T_VARIABLE) && $tokens->isTokenKindFound(T_OBJECT_OPERATOR);
    }
}
