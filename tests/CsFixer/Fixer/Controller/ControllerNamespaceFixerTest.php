<?php

namespace Pimcore\Tests\CsFixer\Fixer\Controller;

use Pimcore\CsFixer\Fixer\Controller\ControllerNamespaceFixer;

/**
 * @covers ControllerNamespaceFixer
 */
class ControllerNamespaceFixerTest extends AbstractControllerFixerTestCase
{
    /**
     * @dataProvider provideFixCases
     *
     * @param $expected
     * @param null $input
     */
    public function testFix($expected, $input = null)
    {
        $this->doTest($expected, $input);
    }

    public function provideFixCases()
    {
        $simpleControllerInput = <<<'EOF'
<?php

use Pimcore\Controller\FrontendController;

class TestController extends FrontendController
{
}
EOF;

        $simpleControllerExpected = <<<'EOF'
<?php

namespace AppBundle\Controller;

use Pimcore\Controller\FrontendController;

class TestController extends FrontendController
{
}
EOF;

        $noUseInput = <<<'EOF'
<?php

class TestController
{
}
EOF;

        $noUseExpected = <<<'EOF'
<?php

namespace AppBundle\Controller;

class TestController
{
}
EOF;

        $differentNamespaceIgnored = <<<'EOF'
<?php

namespace AppBundle\Foo;

class TestController
{
}
EOF;

        $alreadyNamespacedIgnored = $simpleControllerExpected;

        return [
            [
                $simpleControllerExpected,
                $simpleControllerInput
            ],
            [
                $noUseExpected,
                $noUseInput
            ],
            [
                $differentNamespaceIgnored,
                null
            ],
            [
                $alreadyNamespacedIgnored,
                null
            ]
        ];
    }
}
