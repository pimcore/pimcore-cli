<?php

namespace Pimcore\Tests\CsFixer\Fixer\View;

use Pimcore\CsFixer\Fixer\View\NavigationRenderPartialHelperFixer;

/**
 * @covers NavigationRenderPartialHelperFixer
 */
class NavigationRenderPartialHelperFixerTest extends AbstractViewFixerTestCase
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
                '<?php $this->navigation()->menu()->setUseTranslator(false)->renderPartial($mainNav, \'Navigation/partials/sidebar.html.php\'); ?>',
                '<?php $this->navigation()->menu()->setUseTranslator(false)->renderPartial($mainNav, \'/navigation/partials/sidebar.php\'); ?>',
            ],
        ];
    }
}
