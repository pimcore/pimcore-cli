<?php
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
use Pimcore\Cli\Filesystem\DryRunFilesystem;
use Pimcore\Cli\Traits\DryRunCommandTrait;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;

class MigrateViewsCommand extends AbstractCommand
{
    use DryRunCommandTrait;

    protected function configure()
    {
        $this
            ->setName('pimcore5:migrate:views')
            ->addArgument('sourceDir', InputArgument::REQUIRED)
            ->addArgument('targetDir', InputArgument::REQUIRED)
            ->addOption(
                'renameFirstDirectory', 'r',
                InputOption::VALUE_NONE
            )
        ;

        $this->configureDryRunOption();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $sourceDir = $input->getArgument('sourceDir');
        $targetDir = $input->getArgument('targetDir');

        $sourceDir = $sourceDir ? realpath($sourceDir) : null;
        if (!($sourceDir && file_exists($sourceDir) && is_dir($sourceDir))) {
            throw new \InvalidArgumentException('Invalid source directory');
        }

        if (file_exists($targetDir)) {
            throw new \InvalidArgumentException('Target directory already exists');
        }

        $targetDir = rtrim($targetDir, '/');

        $finder = new Finder();
        $finder
            ->files()
            ->in($sourceDir)
            ->name('*.php');

        $fs = new DryRunFilesystem($this->io, $this->isDryRun());
        $fs->mkdir($targetDir);

        foreach ($finder as $file) {
            $relativePath = str_replace($sourceDir . '/', '', $file->getRealPath());

            $pathParts = explode('/', $relativePath);

            if ($input->getOption('renameFirstDirectory') && count($pathParts) > 1) {
                $pathParts[0] = $this->dashesToCamelCase($pathParts[0], true);
            }

            $filename = array_pop($pathParts);
            $filename = $this->dashesToCamelCase($filename);
            $filename = preg_replace('/\.php$/', '.html.php', $filename);

            $pathParts[] = $filename;

            $targetPath = $targetDir . '/'. implode('/', $pathParts);

            $fs->copy($file->getRealPath(), $targetPath);
        }
    }

    protected function dashesToCamelCase(string $string, bool $capitalizeFirstCharacter = false): string
    {
        $str = str_replace('-', '', ucwords($string, '-'));

        if (!$capitalizeFirstCharacter) {
            $str = lcfirst($str);
        }

        return $str;
    }
}
