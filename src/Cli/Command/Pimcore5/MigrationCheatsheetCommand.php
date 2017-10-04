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
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MigrationCheatsheetCommand extends AbstractCommand
{
    protected function configure()
    {
        $this
            ->setName('help:pimcore5:migration-cheatsheet')
            ->setDescription('Shows migration cheatsheet from the documentation');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $code = $this->getCodeContent();
        if (null === $code) {
            return 1;
        }

        $this->io->block('Migration Cheatsheet', null, 'fg=black;bg=cyan', ' ', true);

        $this->io->writeln(<<<EOF
A typical migration scenario could look like the following. Several commands make assumptions regarding file naming which
may not fit your needs. Please check what has been done and revert what you don't need. This may get more flexible/configurable
in the future.

To introspect what is done by the commands you can use the following options:
EOF
);

        $this->io->newLine();
        $this->io->listing([
            'Every command comes with a <comment>--dry-run</comment> option which lets you inspect what the command would do',
            'The <info>process</info> commands support a <comment>--diff</comment> option which can be used to display the processed changes',
            'Commands doing filesystem operations support a <comment>--collect-commands</comment> option which can either be set to `shell` or to `git`.' . PHP_EOL . '   If the option is passed, a list of filesystem operations will be printed with the selected format.',
        ]);

        $this->io->writeln(str_repeat('-', 120));
        $this->io->newLine();

        $this->io->writeln($code);
        $this->io->newLine();
    }

    private function getFileContents()
    {
        // TODO fix in PHAR context
        return file_get_contents(__DIR__ . '/../../../../doc/pimcore_5_migration.md');
    }

    private function getCodeContent()
    {
        $lines = explode("\n", $this->getFileContents());

        $code = [];

        $hitSeparator = false;
        $hitCodeblock = false;

        foreach ($lines as $line) {
            $line = rtrim($line);

            if (!$hitSeparator || !$hitCodeblock) {
                if ('---' === $line) {
                    $hitSeparator = true;
                } elseif ('```' === $line) {
                    $hitCodeblock = true;
                }

                continue;
            }

            if ($hitCodeblock) {
                if ('```' === $line) {
                    break;
                }

                $code[] = $this->processCodeLine($line);
            }
        }

        if (empty($code)) {
            $this->io->error('Failed to load cheatsheet code from documentation.');

            return null;
        }

        return implode("\n", $code);
    }

    private function processCodeLine($line)
    {
        if (0 === strpos($line, '#')) {
            $line = sprintf('<comment>%s</comment>', $line);
        }

        return $line;
    }
}
