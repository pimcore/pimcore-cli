<?php

namespace Pimcore\Tests\CsFixer\Fixer\View;

use Pimcore\CsFixer\Fixer\View\PartialHelperFixer;

/**
 * @covers PartialHelperFixer
 */
class PartialHelperFixerTest extends AbstractViewFixerTestCase
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
                '<?php echo $this->render(\'Includes/galleryRow.html.php\'); ?>',
                '<?php echo $this->partial(\'/includes/gallery-row.php\'); ?>',
            ],
            [
                '<?= $this->render(\'Includes/galleryRow.html.php\'); ?>',
                '<?= $this->partial(\'/includes/gallery-row.php\'); ?>',
            ],
            // make sure a echo is inserted if missing
            [
                '<?= $this->render(\'Includes/galleryRow.html.php\'); ?>',
                '<?php $this->partial(\'/includes/gallery-row.php\'); ?>',
            ],
            // make sure echo is also inserted when not directly after open tag
            [
                '<?php somethingElse(); echo \'foo\'; echo $this->render(\'Includes/galleryRow.html.php\'); ?>',
                '<?php somethingElse(); echo \'foo\'; $this->partial(\'/includes/gallery-row.php\'); ?>',
            ],
            // make sure other parameters are kept
            [
                '<?= $this->render(\'Includes/galleryRow.html.php\', [1, 2, 3], true, \'foo\'); ?>',
                '<?= $this->partial(\'/includes/gallery-row.php\', [1, 2, 3], true, \'foo\'); ?>',
            ]
        ];
    }
}
