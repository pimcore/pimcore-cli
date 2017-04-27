<?php

declare(strict_types=1);

namespace Pimcore\CsFixer\Fixer\View;

use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;
use Pimcore\Cli\Util\TextUtils;
use Pimcore\CsFixer\Fixer\AbstractFunctionReferenceFixer;
use Pimcore\CsFixer\Fixer\Traits\FixerNameTrait;

final class TemplateHelperFixer extends AbstractFunctionReferenceFixer
{
    use FixerNameTrait;

    /**
     * @inheritDoc
     */
    public function getDefinition()
    {
        return new FixerDefinition(
            'Updates calls to $this->template() to use .html.php templates, to use correct casing and to echo the output',
            [new CodeSample('<?php $this->template(\'includes/gallery.php\' ?>')]
        );
    }

    /**
     * @inheritDoc
     */
    public function isRisky()
    {
        return false;
    }

    protected function applyFix(\SplFileInfo $file, Tokens $tokens)
    {
        $sequence = [
            [T_VARIABLE, '$this'],
            [T_OBJECT_OPERATOR, '->'],
            [T_STRING, 'template'],
            '('
        ];

        $candidates = $this->findFunctionCallCandidates($tokens, $sequence);

        foreach ($candidates as $candidate) {
            list($match, $openParenthesis, $closeParenthesis) = $candidate;

            $this->processMatch($tokens, $match, $openParenthesis, $closeParenthesis);
        }
    }

    /**
     * @param Tokens $tokens
     * @param Token[] $match
     * @param int $openParenthesis
     * @param int $closeParenthesis
     */
    private function processMatch(Tokens $tokens, array $match, int $openParenthesis, int $closeParenthesis)
    {
        $arguments = $this->getArguments($tokens, $openParenthesis, $closeParenthesis);
        $argumentTokens  = $this->extractArgumentTokens($tokens, $arguments, 0);

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
            list($path, $quote) = $this->extractQuotedString($pathCasingToken->getContent());

            $path = $this->changeTemplatePathFirstSegment($path);
            $pathCasingToken->setContent($this->quoteString($path, $quote));
        }

        if (null != $filenameToken) {
            list($path, $quote) = $this->extractQuotedString($filenameToken->getContent());

            $path = $this->changeTemplatePathFilename($path);
            $filenameToken->setContent($this->quoteString($path, $quote));
        }

        $this->fixOpenTag($tokens, $match);
    }

    private function fixOpenTag(Tokens $tokens, array $match)
    {
        $indexes   = array_keys($match);
        $prev      = $tokens->getPrevMeaningfulToken($indexes[0]);

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
     * Extract quote and raw content from quoted string
     *
     * @param string $string
     *
     * @return array
     */
    private function extractQuotedString(string $string): array
    {
        $chars = str_split($string);

        $startQuote = array_shift($chars);

        if (!in_array($startQuote, ["'", '"'])) {
            throw new \InvalidArgumentException(sprintf('String does not start with a quote. First char is "%s"', $startQuote));
        }

        $endQuote = array_pop($chars);
        if ($endQuote !== $startQuote) {
            throw new \InvalidArgumentException(sprintf('Start and end quotes do not match. Start: "%s" End: "%s"', $startQuote, $endQuote));
        }

        $string = implode('', $chars);

        return [$string, $startQuote];
    }

    private function quoteString(string $string, string $quote): string
    {
        return $quote . $string . $quote;
    }

    /**
     * Changes the first segment of the path to CamelCase
     *
     * @param string $string
     *
     * @return string
     */
    private function changeTemplatePathFirstSegment(string $string): string
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
    private function changeTemplatePathFilename(string $string): string
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

    /**
     * @param Tokens $tokens
     * @param array $arguments
     * @param int $argumentIndex
     *
     * @return Token[]
     */
    private function extractArgumentTokens(Tokens $tokens, array $arguments, int $argumentIndex): array
    {
        $indexes = array_keys($arguments);

        if (!isset($indexes[$argumentIndex])) {
            throw new \InvalidArgumentException(sprintf('Argument at index %d does not exist', $argumentIndex));
        }

        // arguments is an array indexed by start -> end
        $startIndex = $indexes[$argumentIndex];
        $endIndex   = $arguments[$startIndex];

        $argument = [];
        for ($i = $startIndex; $i <= $endIndex; $i++) {
            $argument[] = $tokens[$i];
        }

        return $argument;
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
        return $tokens->isTokenKindFound(T_VARIABLE) && $tokens->isTokenKindFound(T_OBJECT_OPERATOR);
    }
}
