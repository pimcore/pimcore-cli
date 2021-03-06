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
use Pimcore\Cli\Console\Style\RequirementsFormatter;
use Pimcore\Cli\Pimcore5\Pimcore5Requirements;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CheckRequirementsCommand extends AbstractCommand
{
    protected function configure()
    {
        $this
            ->setName('pimcore5:check-requirements')
            ->setDescription('Checks if the current environment is able to run Pimcore 5');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $requirements = new Pimcore5Requirements();

        $this->io->title('Pimcore 5 Requirements Checker');

        $this->outputIniPath($requirements);

        $this->io->text('> Checking Pimcore 5 requirements:');

        $formatter = new RequirementsFormatter($this->io);
        $result    = $formatter->checkRequirements($requirements);

        if (!$result) {
            return 1;
        }
    }

    private function outputIniPath(Pimcore5Requirements $requirements)
    {
        $iniPath = $requirements->getPhpIniPath();

        $this->io->text('> PHP is using the following php.ini file:');

        if ($iniPath) {
            $this->io->writeln(sprintf('  <info>%s</info>', $iniPath));
        } else {
            $this->io->writeln(sprintf('  <comment>%s</comment>', 'WARNING: No configuration file (php.ini) used by PHP!'));
        }
    }
}
