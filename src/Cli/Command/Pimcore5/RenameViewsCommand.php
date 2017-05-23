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

namespace Pimcore\Cli\Command\Pimcore5;

use Pimcore\Cli\Command\AbstractCommand;
use Pimcore\Cli\Command\Pimcore5\Traits\RenameViewCommandTrait;
use Pimcore\Cli\Filesystem\DryRunFilesystem;
use Pimcore\Cli\Traits\DryRunCommandTrait;
use Pimcore\Cli\Util\FileUtils;
use Pimcore\Cli\Util\TextUtils;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;

class RenameViewsCommand extends AbstractCommand
{
    use RenameViewCommandTrait;
    use DryRunCommandTrait;

    protected function configure()
    {
        $this
            ->setName('pimcore5:views:rename')
            ->setDescription('Rename view files (change extension and file casing)')
            ->addArgument('sourceDir', InputArgument::REQUIRED)
            ->addArgument('targetDir', InputArgument::REQUIRED)
            ->addOption(
                'copy', 'c',
                InputOption::VALUE_NONE,
                'Copy files instead of moving them'
            )
            ->addOption(
                'no-type-header', 'T',
                InputOption::VALUE_NONE,
                'Do not add typehint header'
            )
            ->configureViewRenameOptions()
            ->configureDryRunOption();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $sourceDir = $input->getArgument('sourceDir');
        $targetDir = $input->getArgument('targetDir');

        $sourceDir = $sourceDir ? realpath($sourceDir) : null;
        if (!($sourceDir && file_exists($sourceDir) && is_dir($sourceDir))) {
            throw new \InvalidArgumentException('Invalid source directory');
        }

        $sourceDir = rtrim($sourceDir, DIRECTORY_SEPARATOR);
        $targetDir = rtrim($targetDir, DIRECTORY_SEPARATOR);

        $finder = new Finder();
        $finder
            ->files()
            ->in($sourceDir)
            ->name('*.php');

        $fs = new DryRunFilesystem($this->io, $this->isDryRun());
        $fs->mkdir($targetDir);

        $addTypehintHeader = !$input->getOption('no-type-header');

        foreach ($finder as $file) {
            $relativePath = str_replace($sourceDir . DIRECTORY_SEPARATOR, '', $file->getRealPath());
            $updatedPath  = $this->processPath($input, $relativePath);
            $targetPath   = $targetDir . DIRECTORY_SEPARATOR . $updatedPath;

            if ($fs->exists($targetPath)) {
                $this->io->writeln(sprintf('<comment>WARNING:</comment> File %s already exists, skipping...', $targetPath));
                continue;
            }

            if ($input->getOption('copy')) {
                $fs->copy($file->getRealPath(), $targetPath);
            } else {
                $fs->mkdir(dirname($targetPath));
                $fs->rename($file->getRealPath(), $targetPath);
            }

            if (!$addTypehintHeader) {
                continue;
            }

            if ($this->isDryRun()) {
                if ($addTypehintHeader) {
                    $this->io->writeln($this->dryRunMessage('Would add typehint headers'));
                }
            } else {
                $content        = FileUtils::getFileContents($targetPath);
                $updatedContent = $content;

                if ($addTypehintHeader) {
                    $updatedContent = $this->addTypehintHeader($updatedContent);
                }

                if ($updatedContent !== $content) {
                    if (false === @file_put_contents($targetPath, $updatedContent)) {
                        throw new \RuntimeException(sprintf('Failed to write file "%s".', $targetPath));
                    }
                }
            }

            if ($this->io->isVerbose()) {
                $this->io->writeln('');
            }
        }
    }

    /**
     * Add typehint header if it is not found
     *
     * TODO move this to the fix command
     *
     * @param string $content
     *
     * @return string
     */
    private function addTypehintHeader(string $content): string
    {
        $header = <<<'EOF'
<?php
/**
 * @var \Pimcore\Templating\PhpEngine $this
 * @var \Pimcore\Templating\PhpEngine $view
 * @var \Pimcore\Templating\GlobalVariables $app
 */
?>
EOF;

        // trim every line and remove empty ones
        $filter = function (array $input): array {
            $input = array_map(function ($item) {
                return trim($item);
            }, $input);

            $input = array_filter($input, function ($item) {
                return !empty($item);
            });

            return array_values($input);
        };

        // build compare arrays
        $checkHeader  = explode("\n", TextUtils::normalizeLineEndings($header));
        $checkContent = explode("\n", TextUtils::normalizeLineEndings($content));
        array_splice($checkContent, count($checkHeader));

        $checkHeader  = $filter($checkHeader);
        $checkContent = $filter($checkContent);

        if ($checkContent !== $checkHeader) {
            $content = $header . "\n\n" . $content;
        }

        return $content;
    }
}
