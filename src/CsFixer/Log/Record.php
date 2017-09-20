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

namespace Pimcore\CsFixer\Log;

use Psr\Log\LoggerInterface;

final class Record
{
    /**
     * @var \DateTimeImmutable
     */
    private $dateTime;

    /**
     * @var string
     */
    private $level;

    /**
     * @var string
     */
    private $message;

    /**
     * @var array
     */
    private $context = [];

    public function __construct(\DateTimeImmutable $dateTime, string $level, string $message, array $context = [])
    {
        $this->dateTime = $dateTime;
        $this->level    = $level;
        $this->message  = $message;
        $this->context  = $context;
    }

    public function log(LoggerInterface $logger)
    {
        $logger->log($this->level, $this->message, $this->context);
    }

    public function getDateTime(): \DateTimeImmutable
    {
        return $this->dateTime;
    }

    public function getLevel(): string
    {
        return $this->level;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getContext(): array
    {
        return $this->context;
    }
}
