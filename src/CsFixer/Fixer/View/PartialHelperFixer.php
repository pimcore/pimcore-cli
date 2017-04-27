<?php

declare(strict_types=1);

namespace Pimcore\CsFixer\Fixer\View;

use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;

final class PartialHelperFixer extends AbstractViewHelperTemplatePathFixer
{
    /**
     * @inheritDoc
     */
    public function getDefinition()
    {
        return new FixerDefinition(
            'Updates calls to $this->partial() to use $this->render() instead and to use .html.php templates, correct casing and to echo the output',
            [new CodeSample('<?php $this->partial(\'includes/gallery.php\' ?>')]
        );
    }

    /**
     * @inheritDoc
     */
    protected function getSequence(): array
    {
        return [
            [T_VARIABLE, '$this'],
            [T_OBJECT_OPERATOR, '->'],
            [T_STRING, 'partial'],
            '('
        ];
    }

    /**
     * @inheritDoc
     */
    protected function processCandidate(Tokens $tokens, array $match, int $openParenthesis, int $closeParenthesis)
    {
        /** @var Token $token */
        foreach ($match as $i => $token) {
            // change call from partial() to render()
            if ($token->isGivenKind(T_STRING) && $token->getContent() === 'partial') {
                $token->setContent('render');
            }
        }

        parent::processCandidate($tokens, $match, $openParenthesis, $closeParenthesis);
    }
}
