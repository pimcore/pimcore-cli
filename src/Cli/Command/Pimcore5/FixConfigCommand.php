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
use Pimcore\Cli\Traits\DryRunCommandTrait;
use Pimcore\Config\System\Pimcore5ConfigProcessor;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

class FixConfigCommand extends AbstractCommand
{
    use DryRunCommandTrait;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('pimcore5:config:fix')
            ->setDescription('Updates system.php to match Pimcore 5 structure')
            ->addArgument('config-file', InputArgument::REQUIRED, 'Path to system.php');

        $this->configureDryRunOption();
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $file = $input->getArgument('config-file');

        $this->io->comment(sprintf('Updating config file "%s" to match Pimcore 5 requirements', $file));

        $processor = new Pimcore5ConfigProcessor();

        try {
            $config = $processor->readConfig($file);
            $config = $processor->processConfig($config);
            $result = $processor->dumpConfig($config);

            $this->io->writeln($this->dryRunMessage(sprintf('Writing processed config to <info>%s</info>', $file)));

            if (!$this->isDryRun()) {
                $fs = new Filesystem();
                $fs->dumpFile($file, $result);
            }

            $this->io->writeln($this->dryRunMessage(sprintf('File "%s" was successfully processed', $file)));
        } catch (\Exception $e) {
            $this->io->error($e->getMessage());

            return 1;
        }
    }
}
