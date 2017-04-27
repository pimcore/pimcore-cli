<?php

declare(strict_types=1);

namespace Pimcore\CsFixer\Fixer\View;

use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;

final class NavigationRenderPartialHelperFixer extends AbstractViewHelperTemplatePathFixer
{
    /**
     * @inheritDoc
     */
    public function getDefinition()
    {
        // this does not check/handle an echo call as we don't know if navigation should be echoed at this point

        return new FixerDefinition(
            'Updates calls to $navigation->renderPartial() to use .html.php templates and correct casing',
            [new CodeSample('<?php $navigation->renderPartial($mainNav, \'includes/navigation.php\' ?>')]
        );
    }

    /**
     * @inheritDoc
     */
    protected function getSequence(): array
    {
        return [
            [T_OBJECT_OPERATOR, '->'],
            [T_STRING, 'renderPartial'],
            '('
        ];
    }

    /**
     * @inheritDoc
     */
    protected function needsEchoOutput(): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    protected function getPathArgumentIndex(): int
    {
        return 1;
    }
}
