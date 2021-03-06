<?php

namespace Pimcore\Tests\CsFixer\Fixer\Controller;

use Pimcore\CsFixer\Fixer\Controller\ActionRequestFixer;

/**
 * @covers ActionRequestFixer
 */
class ActionRequestFixerTest extends AbstractControllerFixerTestCase
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
        $emptyActionInput = <<<'EOF'
<?php

namespace AppBundle\Controller;

use Pimcore\Controller\FrontendController;
use Xyz\Foo\Foo;

class TestController extends FrontendController
{
    public function fooAction()
    {
    }
}
EOF;

        $emptyActionExpected = <<<'EOF'
<?php

namespace AppBundle\Controller;

use Pimcore\Controller\FrontendController;
use Symfony\Component\HttpFoundation\Request;
use Xyz\Foo\Foo;

class TestController extends FrontendController
{
    public function fooAction(Request $request)
    {
    }
}
EOF;

        $argumentActionInput = <<<'EOF'
<?php

namespace AppBundle\Controller;

use Pimcore\Controller\FrontendController;

class TestController extends FrontendController
{
    public function fooAction($foo, $bar)
    {
    }
}
EOF;

        $argumentActionExpected = <<<'EOF'
<?php

namespace AppBundle\Controller;

use Pimcore\Controller\FrontendController;
use Symfony\Component\HttpFoundation\Request;

class TestController extends FrontendController
{
    public function fooAction(Request $request, $foo, $bar)
    {
    }
}
EOF;

        $noNamespaceInput = <<<'EOF'
<?php

use Pimcore\Controller\FrontendController;

class TestController extends FrontendController
{
    public function fooAction($foo, $bar)
    {
    }
}
EOF;

        $noNamespaceExpected = <<<'EOF'
<?php

use Pimcore\Controller\FrontendController;
use Symfony\Component\HttpFoundation\Request;

class TestController extends FrontendController
{
    public function fooAction(Request $request, $foo, $bar)
    {
    }
}
EOF;

        $noUseInput = <<<'EOF'
<?php

namespace AppBundle\Controller;

class TestController
{
    public function fooAction($foo, $bar)
    {
    }
}
EOF;

        $noUseExpected = <<<'EOF'
<?php

namespace AppBundle\Controller;

use Symfony\Component\HttpFoundation\Request;

class TestController
{
    public function fooAction(Request $request, $foo, $bar)
    {
    }
}
EOF;

        $noNamespaceNoUseInput = <<<'EOF'
<?php

class TestController
{
    public function fooAction($foo, $bar)
    {
    }
}
EOF;

        $noNamespaceNoUseExpected = <<<'EOF'
<?php

use Symfony\Component\HttpFoundation\Request;

class TestController
{
    public function fooAction(Request $request, $foo, $bar)
    {
    }
}
EOF;

        $alreadyExistingNamespaceIgnoredInput = <<<'EOF'
<?php

namespace AppBundle\Controller;

use Pimcore\Controller\FrontendController;
use Symfony\Component\HttpFoundation\Request;

class TestController extends FrontendController
{
    public function fooAction()
    {
    }
}
EOF;

        $alreadyExistingNamespaceIgnoredExpected = <<<'EOF'
<?php

namespace AppBundle\Controller;

use Pimcore\Controller\FrontendController;
use Symfony\Component\HttpFoundation\Request;

class TestController extends FrontendController
{
    public function fooAction(Request $request)
    {
    }
}
EOF;

        $alreadyExistingArgumentIgnored = $alreadyExistingNamespaceIgnoredExpected;

        $privateActionIgnored = <<<'EOF'
<?php

namespace AppBundle\Controller;

use Pimcore\Controller\FrontendController;

class TestController extends FrontendController
{
    private function fooAction()
    {
    }
}
EOF;

        $nonActionMethodIgnored = <<<'EOF'
<?php

namespace AppBundle\Controller;

use Pimcore\Controller\FrontendController;

class TestController extends FrontendController
{
    public function foo()
    {
    }
}
EOF;

        $nonControllerClassIgnored = <<<'EOF'
<?php

namespace AppBundle\Controller;

class TestPresenter
{
    public function fooAction()
    {
    }
}
EOF;

        return [
            [
                $emptyActionExpected,
                $emptyActionInput
            ],
            [
                $argumentActionExpected,
                $argumentActionInput
            ],
            [
                $noNamespaceExpected,
                $noNamespaceInput
            ],
            [
                $noUseExpected,
                $noUseInput
            ],
            [
                $noNamespaceNoUseExpected,
                $noNamespaceNoUseInput,
            ],
            [
                $alreadyExistingNamespaceIgnoredExpected,
                $alreadyExistingNamespaceIgnoredInput
            ],
            [
                $alreadyExistingArgumentIgnored,
                null
            ],
            [
                $privateActionIgnored,
                null
            ],
            [
                $nonActionMethodIgnored,
                null
            ],
            [
                $nonControllerClassIgnored,
                null
            ],
        ];
    }
}
