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

use Pimcore\Cli\Pimcore5\Pimcore5Requirements;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\OutputStyle;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Requirements\Requirement;

class CheckRequirementsCommand extends Command
{
    /**
     * @var array
     */
    private $messages;

    protected function configure()
    {
        $this->setName('pimcore5:check-requirements');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $requirements = new Pimcore5Requirements();

        $io = new SymfonyStyle($input, $output);
        $io->title('Pimcore 5 Requirements Checker');

        $this->messages = [
            'error'   => [],
            'warning' => [],
        ];

        $this->outputIniPath($requirements, $io);

        $io->text('> Checking Pimcore 5 requirements:');

        $this->checkCollection($requirements->getRequirements(), $io, 'error', 'E', 'error');
        $this->checkCollection($requirements->getRecommendations(), $io, 'warning', 'W', 'comment');

        if (empty($this->messages['error'])) {
            $io->success('Your system is ready to run Pimcore 5 projects!');
        } else {
            $io->error('Your system is not ready to run Pimcore 5 projects');

            $io->section('Fix the following mandatory requirements');
            $io->listing($this->messages['error']);
        }

        if (!empty($this->messages['warning'])) {
            $io->section('Optional recommendations to improve your setup');
            $io->listing($this->messages['warning']);
        }
    }

    private function outputIniPath(Pimcore5Requirements $requirements, OutputStyle $io)
    {
        $iniPath = $requirements->getPhpIniPath();

        $io->text('> PHP is using the following php.ini file:');

        if ($iniPath) {
            $io->writeln(sprintf('  <info>%s</info>', $iniPath));
        } else {
            $io->writeln(sprintf('  <comment>%s</comment>', 'WARNING: No configuration file (php.ini) used by PHP!'));
        }
    }

    /**
     * @param Requirement[] $requirements
     * @param OutputStyle $io
     * @param string $messageKey
     * @param string $errorChar
     * @param string $style
     */
    private function checkCollection(array $requirements, OutputStyle $io, string $messageKey, string $errorChar, string $style)
    {
        foreach ($requirements as $requirement) {
            if ($requirement->isFulfilled()) {
                $io->write('<info>.</info>');
            } else {
                $io->write(sprintf('<%1$s>%2$s</%1$s>', $style, $errorChar));
                $this->messages[$messageKey][] = $this->getErrorMessage($requirement);
            }
        }
    }

    /**
     * @param Requirement $requirement
     * @param int $lineSize
     *
     * @return string
     */
    private function getErrorMessage(Requirement $requirement, int $lineSize = 70): string
    {
        if ($requirement->isFulfilled()) {
            return '';
        }

        $errorMessage = wordwrap($requirement->getTestMessage(), $lineSize - 3, PHP_EOL . '   ') . PHP_EOL;
        $errorMessage .= '   > ' . wordwrap($requirement->getHelpText(), $lineSize - 5, PHP_EOL . '   > ') . PHP_EOL;

        return $errorMessage;
    }
}
