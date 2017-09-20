<?php

namespace Pimcore\Tests\CsFixer\Fixer\Controller;

use Pimcore\CsFixer\Fixer\Controller\ControllerBaseClassFixer;

/**
 * @covers ControllerBaseClassFixer
 */
class ControllerBaseClassFixerTest extends AbstractControllerFixerTestCase
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

namespace AppBundle\Controller;

class TestController extends Website_Controller_Action
{
}
EOF;

        $simpleControllerExpected = <<<'EOF'
<?php

namespace AppBundle\Controller;

use Pimcore\Controller\FrontendController;

// class TestController extends Website_Controller_Action
class TestController extends FrontendController
{
}
EOF;

        $finalControllerInput = <<<'EOF'
<?php

namespace AppBundle\Controller;

final class TestController extends Website_Controller_Action
{
}
EOF;

        $finalControllerExpected = <<<'EOF'
<?php

namespace AppBundle\Controller;

use Pimcore\Controller\FrontendController;

// final class TestController extends Website_Controller_Action
final class TestController extends FrontendController
{
}
EOF;

        $noNamespaceInput = <<<'EOF'
<?php

class TestController extends Website_Controller_Action
{
}
EOF;

        $noNamespaceExpected = <<<'EOF'
<?php

use Pimcore\Controller\FrontendController;

// class TestController extends Website_Controller_Action
class TestController extends FrontendController
{
}
EOF;

        $simpleControllerInlineInput = <<<'EOF'
<?php

namespace AppBundle\Controller;

class TestController extends Website_Controller_Action {}
EOF;

        $simpleControllerInlineExpected = <<<'EOF'
<?php

namespace AppBundle\Controller;

use Pimcore\Controller\FrontendController;

// class TestController extends Website_Controller_Action
class TestController extends FrontendController {}
EOF;

        $interfaceControllerInput = <<<'EOF'
<?php

namespace AppBundle\Controller;

class TestController extends Website_Controller_Action implements FooInterface, BarInterface
{
}
EOF;

        $interfaceControllerExpected = <<<'EOF'
<?php

namespace AppBundle\Controller;

use Pimcore\Controller\FrontendController;

// class TestController extends Website_Controller_Action implements FooInterface, BarInterface
class TestController extends FrontendController implements FooInterface, BarInterface
{
}
EOF;

        $multilineControllerInput = <<<'EOF'
<?php

namespace AppBundle\Controller;

final class TestController
    extends Website_Controller_Action
    implements
        FooInterface,
        BarInterface
{
}
EOF;

        $multilineControllerExpected = <<<'EOF'
<?php

namespace AppBundle\Controller;

use Pimcore\Controller\FrontendController;

// final class TestController extends Website_Controller_Action implements FooInterface, BarInterface
final class TestController
    extends FrontendController
    implements
        FooInterface,
        BarInterface
{
}
EOF;

        $nonControllerClassIgnored = <<<'EOF'
<?php

namespace AppBundle\Controller;

class TestPresenter extends Website_Controller_Action
{
    public function fooAction()
    {
    }
}
EOF;

        $alreadyMigratedIgnored = <<<'EOF'
<?php

namespace AppBundle\Controller;

use Pimcore\Controller\FrontendController;

class TestController extends FrontendController
{
}
EOF;

        return [
            [
                $simpleControllerExpected,
                $simpleControllerInput
            ],
            [
                $finalControllerExpected,
                $finalControllerInput
            ],
            [
                $noNamespaceExpected,
                $noNamespaceInput
            ],
            [
                $simpleControllerInlineExpected,
                $simpleControllerInlineInput
            ],
            [
                $interfaceControllerExpected,
                $interfaceControllerInput
            ],
            [
                $multilineControllerExpected,
                $multilineControllerInput
            ],
            [
                $nonControllerClassIgnored,
                null
            ],
            [
                $alreadyMigratedIgnored,
                null
            ]
        ];
    }
}
