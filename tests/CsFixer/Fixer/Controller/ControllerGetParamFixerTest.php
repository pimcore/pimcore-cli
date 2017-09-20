<?php

namespace Pimcore\Tests\CsFixer\Fixer\Controller;

use Pimcore\CsFixer\Fixer\Controller\ControllerGetParamFixer;

/**
 * @covers ControllerGetParamFixer
 */
class ControllerGetParamFixerTest extends AbstractControllerFixerTestCase
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
        $insideActionInput = <<<'EOF'
<?php

namespace AppBundle\Controller;

class TestController
{
    public function fooAction(Request $request)
    {
        $anotherValue = 123;
    
        $foo = $this->getParam('foo');
        $bar = $this->getParam('bar', 'default value');
        $baz = $this->getParam('baz', $anotherValue);
        $inga = $this->getParam('inga', $this->getAnotherValue());
    }
    
    private function getAnotherValue()
    {
        return 'bazinga';
    }
}
EOF;

        $insideActionExpected = <<<'EOF'
<?php

namespace AppBundle\Controller;

class TestController
{
    public function fooAction(Request $request)
    {
        $anotherValue = 123;
    
        $foo = $request->get('foo');
        $bar = $request->get('bar', 'default value');
        $baz = $request->get('baz', $anotherValue);
        $inga = $request->get('inga', $this->getAnotherValue());
    }
    
    private function getAnotherValue()
    {
        return 'bazinga';
    }
}
EOF;

        $alreadyMigratedIgnored = $insideActionExpected;

        $insideOtherMethodIgnored = <<<'EOF'
<?php

namespace AppBundle\Controller;

class TestController
{   
    public function getAValue()
    {
        $foo = $this->getParam('foo');
    }

    private function getAnotherValue()
    {
        $bar = $this->getParam('bar');
    }
}
EOF;

        $outsideClassScopeIgnored = <<<'EOF'
<?php

$this->getParam('foo');
EOF;

        return [
            [
                $insideActionExpected,
                $insideActionInput
            ],
            [
                $alreadyMigratedIgnored,
                null
            ],
            [
                $insideOtherMethodIgnored,
                null
            ],
            [
                $outsideClassScopeIgnored,
                null
            ]
        ];
    }
}
