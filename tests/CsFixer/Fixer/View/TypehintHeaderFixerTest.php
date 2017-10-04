<?php

namespace Pimcore\Tests\CsFixer\Fixer\View;

use Pimcore\CsFixer\Fixer\View\TypehintHeaderFixer;

/**
 * @covers TypehintHeaderFixer
 */
class TypehintHeaderFixerTest extends AbstractViewFixerTestCase
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
        $normalViewInput = <<<'EOF'
<?php $this->template("/includes/content-headline.php"); ?>

<?= $this->areablock("content"); ?>
EOF;

        $normalViewExpected = <<<'EOF'
<?php
/**
 * @var \Pimcore\Templating\PhpEngine $this
 * @var \Pimcore\Templating\PhpEngine $view
 * @var \Pimcore\Templating\GlobalVariables $app
 */
?>
<?php $this->template("/includes/content-headline.php"); ?>

<?= $this->areablock("content"); ?>
EOF;

        $onlyPhpInput = <<<'EOF'
<?php

echo time();
EOF;

        $onlyPhpExpected = <<<'EOF'
<?php
/**
 * @var \Pimcore\Templating\PhpEngine $this
 * @var \Pimcore\Templating\PhpEngine $view
 * @var \Pimcore\Templating\GlobalVariables $app
 */
?>
<?php

echo time();
EOF;



        $noPhpInput = <<<'EOF'
FOO
BAR
BAZ
EOF;

        $noPhpExpected = <<<'EOF'
<?php
/**
 * @var \Pimcore\Templating\PhpEngine $this
 * @var \Pimcore\Templating\PhpEngine $view
 * @var \Pimcore\Templating\GlobalVariables $app
 */
?>
FOO
BAR
BAZ
EOF;

        return [
            [
                $normalViewExpected,
                $normalViewInput
            ],
            [
                $onlyPhpExpected,
                $onlyPhpInput
            ],
            [
                $noPhpExpected,
                $noPhpInput
            ],
            // test a block is not added twice
            [$normalViewExpected, null],
            [$onlyPhpExpected, null],
            [$noPhpExpected,null],
        ];
    }
}
