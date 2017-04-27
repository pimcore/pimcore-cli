<?php

namespace Pimcore\Tests\CsFixer\Fixer\View;

use Pimcore\CsFixer\Fixer\View\NavigationRenderPartialHelperFixer;

/**
 * @covers NavigationRenderPartialHelperFixer
 */
class NavigationSetPartialHelperFixerTest extends AbstractViewFixerTestCase
{
    /**
     * @dataProvider provideFixCases
     */
    public function testFix($expected, $input = null)
    {
        $this->doTest($expected, $input);
    }

    public function provideFixCases()
    {
        return [
            [
                '<?php $this->navigation($navigation)->menu()->setUseTranslator(false)->setPartial(\'Navigation/partials/sidebar.html.php\'); ?>',
                '<?php $this->navigation($navigation)->menu()->setUseTranslator(false)->setPartial(\'/navigation/partials/sidebar.php\'); ?>',
            ],
        ];
    }
}
