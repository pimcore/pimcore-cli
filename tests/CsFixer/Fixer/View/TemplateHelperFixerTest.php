<?php

namespace Pimcore\Tests\CsFixer\Fixer\View;

use Pimcore\CsFixer\Fixer\View\SetLayoutFixer;

/**
 * @covers SetLayoutFixer
 */
class TemplateHelperFixerTest extends AbstractViewFixerTestCase
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
                '<?php echo $this->template(\'Includes/galleryRow.html.php\'); ?>',
                '<?php echo $this->template(\'/includes/gallery-row.php\'); ?>',
            ],
            [
                '<?= $this->template(\'Includes/galleryRow.html.php\'); ?>',
                '<?= $this->template(\'/includes/gallery-row.php\'); ?>',
            ],
            [
                '<?= $this->template(\'Includes/galleryRow.html.php\'); ?>',
                '<?php $this->template(\'/includes/gallery-row.php\'); ?>',
            ],
            [
                '<?php echo $this->template(\'Includes/galleryRow.html.php\'); ?>',
                '<?php echo $this->template(\'/includes/gallery-row.php\'); ?>',
            ],
            [
                '<?= $this->template(\'Includes/galleryRow.html.php\', [1, 2, 3], true, \'foo\'); ?>',
                '<?= $this->template(\'/includes/gallery-row.php\', [1, 2, 3], true, \'foo\'); ?>',
            ]
        ];
    }
}
