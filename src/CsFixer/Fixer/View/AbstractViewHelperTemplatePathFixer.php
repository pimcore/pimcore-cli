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

use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;
use Pimcore\Cli\Util\TextUtils;
use Pimcore\CsFixer\Fixer\AbstractFunctionReferenceFixer;
use Pimcore\CsFixer\Fixer\Traits\FixerNameTrait;

abstract class AbstractViewHelperTemplatePathFixer extends AbstractFunctionReferenceFixer
{
    use FixerNameTrait;

    /**
     * @inheritDoc
     */
    public function isRisky()
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function supports(\SplFileInfo $file)
    {
        $expectedExtension = '.html.php';

        return substr($file->getFilename(), -strlen($expectedExtension)) === $expectedExtension;
    }

    /**
     * @inheritDoc
     */
    public function isCandidate(Tokens $tokens)
    {
        return $tokens->isTokenKindFound(T_VARIABLE)
            && $tokens->isTokenKindFound(T_OBJECT_OPERATOR)
            && $tokens->isTokenKindFound(T_CONSTANT_ENCAPSED_STRING);
    }

    /**
     * The sequence to look for. Must end in opening parenthesis!
     *
     * @return array
     */
    abstract protected function getSequence(): array;

    /**
     * Get argument index of the path argument
     *
     * @return int
     */
    protected function getPathArgumentIndex(): int
    {
        return 0;
    }

    protected function applyFix(\SplFileInfo $file, Tokens $tokens)
    {
        $sequence   = $this->getSequence();
        $candidates = $this->findFunctionCallCandidates($tokens, $sequence);

        foreach ($candidates as $candidate) {
            list($match, $openParenthesis, $closeParenthesis) = $candidate;

            $this->processCandidate($tokens, $match, $openParenthesis, $closeParenthesis);
        }
    }

    /**
     * @param Tokens $tokens
     * @param Token[] $match
     * @param int $openParenthesis
     * @param int $closeParenthesis
     */
    protected function processCandidate(Tokens $tokens, array $match, int $openParenthesis, int $closeParenthesis)
    {
        $this->processPathArgument($tokens, $match, $openParenthesis, $closeParenthesis);
        $this->processEchoOutput($tokens, $match);
    }

    /**
     * Make sure the output is echoed
     *
     * @param Tokens $tokens
     * @param array $match
     */
    protected function processEchoOutput(Tokens $tokens, array $match)
    {
        $indexes = array_keys($match);
        $prev    = $tokens->getPrevMeaningfulToken($indexes[0]);

        if (null === $prev) {
            return;
        }

        $prevToken = $tokens[$prev];

        // we're ok
        if ($prevToken->isGivenKind([T_OPEN_TAG_WITH_ECHO, T_ECHO])) {
            return;
        }

        // <?php $this->template() -> <?= $this->template()
        if ($prevToken->isGivenKind(T_OPEN_TAG)) {
            $tokens->overrideAt($prev, new Token([T_OPEN_TAG_WITH_ECHO, '<?=']));
            $tokens->insertAt($prev + 1, new Token([T_WHITESPACE, ' ']));
        } else {
            // <?php foo(); $this->template() -> <?php foo; echo $this->template()
            $tokens->insertAt($prev + 1, [
                new Token([T_WHITESPACE, ' ']),
                new Token([T_ECHO, 'echo']),
            ]);
        }
    }

    /**
     * Extract the path argument at the given index and try to apply the follwing normalizations:
     *
     *  - Strip leading slash
     *  - Change first path segment to CamelCase (first char uppercase)
     *  - Change filename to camelCase and change extension to .html.php
     *
     * @param Tokens $tokens
     * @param Token[] $match
     * @param int $openParenthesis
     * @param int $closeParenthesis
     */
    protected function processPathArgument(Tokens $tokens, array $match, int $openParenthesis, int $closeParenthesis)
    {
        $arguments      = $this->getArguments($tokens, $openParenthesis, $closeParenthesis);
        $argumentTokens = $this->extractArgumentTokens($tokens, $arguments, $this->getPathArgumentIndex());

        $pathCasingToken = null;
        $filenameToken   = null;

        if (count($argumentTokens) === 1) {
            // easiest scenario - we have a single string argument
            if ($argumentTokens[0]->isGivenKind(T_CONSTANT_ENCAPSED_STRING)) {
                $pathCasingToken = $argumentTokens[0];
                $filenameToken   = $argumentTokens[0];
            }

            // no string argument -> we skip this candidate as we don't know what to do
            // TODO trigger warning?
        } elseif (count($argumentTokens) > 1) {
            // multiple tokens in first argument (e.g. concatenated strings or method call
            // handle first token if it is a string
            if ($argumentTokens[0]->isGivenKind(T_CONSTANT_ENCAPSED_STRING)) {
                $pathCasingToken = $argumentTokens[0];
            }

            // handle last argument if it is a string
            $lastToken = $argumentTokens[count($argumentTokens) - 1];
            if ($lastToken->isGivenKind(T_CONSTANT_ENCAPSED_STRING)) {
                $filenameToken = $lastToken;
            }
        }

        if (null != $pathCasingToken) {
            list($path, $quote) = TextUtils::extractQuotedString($pathCasingToken->getContent());

            $path = $this->normalizeFirstTemplatePathSegment($path);
            $pathCasingToken->setContent(TextUtils::quoteString($path, $quote));
        }

        if (null != $filenameToken) {
            list($path, $quote) = TextUtils::extractQuotedString($filenameToken->getContent());

            $path = $this->normalizeTemplatePathFilename($path);
            $filenameToken->setContent(TextUtils::quoteString($path, $quote));
        }
    }

    /**
     * Changes the first segment of the path to CamelCase
     *
     * @param string $string
     *
     * @return string
     */
    protected function normalizeFirstTemplatePathSegment(string $string): string
    {
        $string = ltrim($string, '/');
        $parts  = explode('/', $string);

        // first part of path to uppercase CamelCase
        if (count($parts) > 1) {
            $parts[0] = TextUtils::dashesToCamelCase($parts[0], true);
        }

        $string = implode('/', $parts);

        return $string;
    }

    /**
     * Changes the filename to camelCase.html.php
     *
     * @param string $string
     *
     * @return string
     */
    protected function normalizeTemplatePathFilename(string $string): string
    {
        $parts = explode('/', $string);

        // filename to camelCase
        $filename = array_pop($parts);

        // normalize extension to html.php if not alread done
        $filename = preg_replace('/(?<!\.html)(\.php)/', '.html.php', $filename);

        // temporarily remove extension again
        $filename = preg_replace('/\.html\.php$/', '', $filename);

        $filename = TextUtils::dashesToCamelCase($filename);

        // re-add extension
        $filename = $filename . '.html.php';

        $parts[] = $filename;

        $string = implode('/', $parts);

        return $string;
    }
}
