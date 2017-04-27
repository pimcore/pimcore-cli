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
        $argument  = $this->extractArgument($tokens, $arguments, 0);

        if (count($argument) === 1) {
            // easiest scenario - we have a single string argument
            if ($argument[0]->isGivenKind(T_CONSTANT_ENCAPSED_STRING)) {
                list($path, $quote) = $this->extractQuotedString($argument[0]->getContent());

                $path = ltrim($path, '/');
                $path = $this->changeTemplatePathExtension($path);
                $path = $this->changeTemplatePathCasing($path);

                $argument[0]->setContent($this->quoteString($path, $quote));
            }

            // no string argument -> we skip this candidate as we don't know what to do
            // TODO trigger warning?
        } elseif (count($argument) > 1) {

            // multiple tokens in first argument (e.g. concatenated strings or method call
            // handle first token if it is a string
            if ($argument[0]->isGivenKind(T_CONSTANT_ENCAPSED_STRING)) {
                list($path, $quote) = $this->extractQuotedString($argument[0]->getContent());

                $path = ltrim($path, '/');
                $path = $this->changeTemplatePathCasing($path, true, false);

                $argument[0]->setContent($this->quoteString($path, $quote));
            }

            // handle last argument if it is a string
            $lastArgument = $argument[count($argument) - 1];
            if ($lastArgument->isGivenKind(T_CONSTANT_ENCAPSED_STRING)) {
                list($path, $quote) = $this->extractQuotedString($lastArgument->getContent());

                $path = $this->changeTemplatePathExtension($path);
                $path = $this->changeTemplatePathCasing($path, false);

                $lastArgument->setContent($this->quoteString($path, $quote));
            }
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
     * Changes extension from .php to .html php
     *
     * @param string $string
     *
     * @return string
     */
    private function changeTemplatePathExtension(string $string): string
    {
        // replace .php with .html.php
        $string = preg_replace('/(?<!\.html)(\.php)/', '.html.php', $string);

        return $string;
    }

    /**
     * Changes the first segment of the path to CamelCase and the filename to camelCase.html.php
     *
     * @param string $string
     * @param bool $handleFirstSegment
     * @param bool $handleFilename
     *
     * @return string
     */
    private function changeTemplatePathCasing(string $string, $handleFirstSegment = true, $handleFilename = true): string
    {
        $parts = explode('/', $string);

        // first part of path to uppercase CamelCase
        if ($handleFirstSegment && count($parts) > 1) {
            $parts[0] = TextUtils::dashesToCamelCase($parts[0], true);
        }

        if ($handleFilename) {
            // filename to camelCase
            $filename = array_pop($parts);
            $filename = preg_replace('/\.html\.php$/', '', $filename); // temporarily remove extension again
            $filename = TextUtils::dashesToCamelCase($filename);
            $filename = $filename . '.html.php';

            $parts[] = $filename;
        }

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
    private function extractArgument(Tokens $tokens, array $arguments, int $argumentIndex): array
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
