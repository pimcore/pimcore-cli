<?php

declare(strict_types=1);

namespace Pimcore\CsFixer\Fixer\View;

use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;
use Pimcore\CsFixer\Fixer\AbstractFunctionReferenceFixer;
use Pimcore\CsFixer\Fixer\Traits\FixerNameTrait;

final class NavigationFixer extends AbstractFunctionReferenceFixer
{
    use FixerNameTrait;

    /**
     * @inheritDoc
     */
    public function getDefinition()
    {
        return new FixerDefinition(
            'Replaces calls to $this->pimcoreNavigation() with $this->navigation()',
            [new CodeSample('<?php $this->pimcoreNavigation() ?>')]
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
            [T_STRING, 'pimcoreNavigation'],
            '('
        ];

        $candidates = $this->findFunctionCallCandidates($tokens, $sequence);

        foreach ($candidates as $candidate) {
            /** @var Token[] $match */
            list($match, $openParenthesis, $closeParenthesis) = $candidate;

            $indexes = array_keys($match);
            $tokens->offsetSet($indexes[2], new Token([$match[$indexes[2]]->getId(), 'navigation']));

        }
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
