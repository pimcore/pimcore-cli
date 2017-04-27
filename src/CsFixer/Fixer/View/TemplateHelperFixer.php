<?php

declare(strict_types=1);

namespace Pimcore\CsFixer\Fixer\View;

use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;

final class TemplateHelperFixer extends AbstractViewHelperTemplatePathFixer
{
    /**
     * @inheritDoc
     */
    public function getDefinition()
    {
        return new FixerDefinition(
            'Updates calls to $this->template() to use .html.php templates, correct casing and to echo the output',
            [new CodeSample('<?php $this->template(\'includes/gallery.php\' ?>')]
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
            [T_STRING, 'template'],
            '('
        ];
    }
}
