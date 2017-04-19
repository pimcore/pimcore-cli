<?php

namespace Pimcore\Tests\CsFixer\Fixer\View;

use Pimcore\CsFixer\Fixer\View\PimcoreSetLayoutFixer;

/**
 * @covers PimcoreSetLayoutFixer
 */
class PimcoreSetLayoutFixerTest extends AbstractViewFixerTestCase
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
                '<?php $this->extend(\'layout.html.php\'); ?>',
                '<?php $this->layout()->setLayout(\'layout\'); ?>',
            ],
            [
                '<?php $this->extend(\'layout.html.php\'); ?>',
                '<?php $this->layout()->setLayout(\'layout\', true); ?>',
            ],
            [
                '<?php $this->extend(\'layout.html.php\'); ?>',
                '<?php $this->layout()->setLayout(\'layout\', false); ?>',
            ],
            [
                '<?php $this->extend(\'lay\' . \'out\' . \'.html.php\'); ?>',
                '<?php $this->layout()->setLayout(\'lay\' . \'out\'); ?>',
            ],
        ];
    }
}
