<?php

namespace Pimcore\Tests\CsFixer\Fixer\View;

use Pimcore\CsFixer\Fixer\View\NavigationFixer;

/**
 * @covers NavigationFixer
 */
class NavigationFixerTest extends AbstractViewFixerTestCase
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
                '<?php $this->navigation(); ?>',
                '<?php $this->pimcoreNavigation(); ?>',
            ],
            [
                '<?php $this->navigation()->menu()->setUseTranslator(false)->renderPartial($mainNav, \'Navigation/partials/sidebar.html.php\'); ?>',
                '<?php $this->pimcoreNavigation()->menu()->setUseTranslator(false)->renderPartial($mainNav, \'Navigation/partials/sidebar.html.php\'); ?>',
            ],
        ];
    }
}
