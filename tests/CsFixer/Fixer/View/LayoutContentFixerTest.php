<?php

namespace Pimcore\Tests\CsFixer\Fixer\View;

use Pimcore\CsFixer\Fixer\View\LayoutContentFixer;

/**
 * @covers LayoutContentFixer
 */
class LayoutContentFixerTest extends AbstractViewFixerTestCase
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
                '<?php $this->slots()->output(\'_content\'); ?>',
                '<?php $this->layout()->content; ?>',
            ],
            [
                '<?php $this->slots()->output(\'_content\'); $this->slots()->output(\'_content\'); ?>',
                '<?php $this->layout()->content; $this->layout()->content; ?>',
            ],
            [
                '<?php $this->slots()->output(\'_content\') ?>',
                '<?php echo $this->layout()->content ?>',
            ],
            [
                '<?php $this->slots()->output(\'_content\') ?>',
                '<?= $this->layout()->content ?>',
            ],
        ];
    }
}
