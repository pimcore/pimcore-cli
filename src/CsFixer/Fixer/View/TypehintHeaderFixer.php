<?php

declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Enterprise License (PEL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 * @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 * @license    http://www.pimcore.org/license     GPLv3 and PEL
 */

namespace Pimcore\CsFixer\Fixer\View;

use PhpCsFixer\AbstractFixer;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;
use Pimcore\CsFixer\Fixer\Traits\FixerNameTrait;

final class TypehintHeaderFixer extends AbstractFixer
{
    use FixerNameTrait;

    private $comment = <<<'EOL'
/**
 * @var \Pimcore\Templating\PhpEngine $this
 * @var \Pimcore\Templating\PhpEngine $view
 * @var \Pimcore\Templating\GlobalVariables $app
 */
EOL;

    /**
     * @inheritDoc
     */
    public function getDefinition()
    {
        return new FixerDefinition(
            'Adds a type hint header annotation block to each template file',
            []
        );
    }

    /**
     * @inheritDoc
     */
    public function isCandidate(Tokens $tokens)
    {
        return true;
    }

    public function supports(\SplFileInfo $file)
    {
        $expectedExtension = '.html.php';

        return substr($file->getFilename(), -strlen($expectedExtension)) === $expectedExtension;
    }

    protected function applyFix(\SplFileInfo $file, Tokens $tokens)
    {
        $blocks = array_values($tokens->findGivenKind(T_DOC_COMMENT));

        /** @var Token $block */
        foreach ($blocks as $block) {
            if ($block->getContent() === $this->comment) {
                // docblock was already found - abort
                return;
            }
        }

        $insert = [
            new Token([T_OPEN_TAG, "<?php\n"]),
            new Token([T_DOC_COMMENT, $this->comment]),
            new Token([T_WHITESPACE, "\n"]),
            new Token([T_CLOSE_TAG, "?>\n"]),
        ];

        $tokens->insertAt(0, $insert);
    }
}
