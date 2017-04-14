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

namespace Pimcore\Cli\Console\Style;

use Symfony\Component\Console\Style\OutputStyle;
use Symfony\Requirements\Requirement;
use Symfony\Requirements\RequirementCollection;

class RequirementsFormatter
{
    /**
     * @var OutputStyle
     */
    private $io;

    /**
     * @param OutputStyle $io
     */
    public function __construct(OutputStyle $io)
    {
        $this->io = $io;
    }

    public function checkRequirements(RequirementCollection $requirements): bool
    {
        $io    = $this->io;
        $error = false;

        $messages = [];
        $messages = $this->checkCollection($requirements->getRequirements(), $messages, 'error', 'E', 'error');
        $messages = $this->checkCollection($requirements->getRecommendations(), $messages, 'warning', 'W', 'comment');

        if (empty($messages['error'])) {
            $error = true;
            $io->success('Your system is ready to run Pimcore 5 projects!');
        } else {
            $io->error('Your system is not ready to run Pimcore 5 projects');

            $io->section('Fix the following mandatory requirements');
            $io->listing($messages['error']);
        }

        if (!empty($messages['warning'])) {
            $io->section('Optional recommendations to improve your setup');
            $io->listing($messages['warning']);
        }

        return $error;
    }

    /**
     * @param Requirement[] $requirements
     * @param array $messages
     * @param string $messageKey
     * @param string $errorChar
     * @param string $style
     *
     * @return array
     */
    private function checkCollection(array $requirements, array $messages, string $messageKey, string $errorChar, string $style): array
    {
        foreach ($requirements as $requirement) {
            if ($requirement->isFulfilled()) {
                $this->io->write('<info>.</info>');
            } else {
                $this->io->write(sprintf('<%1$s>%2$s</%1$s>', $style, $errorChar));
                $messages[$messageKey][] = $this->getErrorMessage($requirement);
            }
        }

        return $messages;
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
