<?php

declare(strict_types=1);

namespace Pimcore\CsFixer\Fixer\View;

use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;

final class NavigationSetPartialHelperFixer extends AbstractViewHelperTemplatePathFixer
{
    /**
     * @inheritDoc
     */
    public function getDefinition()
    {
        // this does not check/handle an echo call as we don't know if navigation should be echoed at this point

        return new FixerDefinition(
            'Updates calls to $navigation->setPartial() to use .html.php templates and correct casing',
            [new CodeSample('<?php $navigation->setPartial(\'includes/navigation.php\' ?>')]
        );
    }

    /**
     * @inheritDoc
     */
    protected function getSequence(): array
    {
        return [
            [T_OBJECT_OPERATOR, '->'],
            [T_STRING, 'setPartial'],
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
}
