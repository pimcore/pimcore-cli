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

use Psr\Log\LogLevel;

final class FixerLogger implements FixerLoggerInterface
{
    /**
     * @var \DateTimeZone
     */
    private $timezone;

    /**
     * @var array
     */
    private $records = [];

    /**
     * @var array
     */
    private $levels = [
        LogLevel::EMERGENCY,
        LogLevel::ALERT,
        LogLevel::CRITICAL,
        LogLevel::ERROR,
        LogLevel::WARNING,
        LogLevel::NOTICE,
        LogLevel::INFO,
        LogLevel::DEBUG
    ];

    private function addRecord(\SplFileInfo $file, string $level, string $message, array $context = [])
    {
        if (!in_array($level, $this->levels)) {
            throw new \InvalidArgumentException(sprintf('The log level "%s" is invalid', $level));
        }

        if (null === $this->timezone) {
            $this->timezone = new \DateTimeZone(date_default_timezone_get() ?: 'UTC');
        }

        $dateTime = new \DateTimeImmutable('now', $this->timezone);
        $dateTime->setTimezone($this->timezone);

        $record = [
            'message'  => $message,
            'context'  => $context,
            'level'    => $level,
            'datetime' => $dateTime,
        ];

        $recordValue = new Record($dateTime, $level, $message, $context);

        $fileIdentifier = $this->getFileIdentifier($file);
        if (!isset($this->records[$fileIdentifier])) {
            $this->records[$fileIdentifier] = [];
        }

        $this->records[$fileIdentifier][] = $recordValue;
    }

    private function getFileIdentifier(\SplFileInfo $file)
    {
        return $file->getRealPath();
    }

    public function hasRecords(): bool
    {
        return count($this->records) > 0;
    }

    public function getRecords(\SplFileInfo $file = null): array
    {
        if (null === $file) {
            return $this->records;
        }

        $fileIdentifier = $this->getFileIdentifier($file);
        if (isset($this->records[$fileIdentifier])) {
            return $this->records[$fileIdentifier];
        }

        return [];
    }

    public function log(\SplFileInfo $file, string $level, string $message, array $context = [])
    {
        $this->addRecord($file, $level, $message, $context);
    }

    public function emergency(\SplFileInfo $file, string $message, array $context = [])
    {
        $this->addRecord($file, LogLevel::EMERGENCY, $message, $context);
    }

    public function alert(\SplFileInfo $file, string $message, array $context = [])
    {
        $this->addRecord($file, LogLevel::ALERT, $message, $context);
    }

    public function critical(\SplFileInfo $file, string $message, array $context = [])
    {
        $this->addRecord($file, LogLevel::CRITICAL, $message, $context);
    }

    public function error(\SplFileInfo $file, string $message, array $context = [])
    {
        $this->addRecord($file, LogLevel::ERROR, $message, $context);
    }

    public function warning(\SplFileInfo $file, string $message, array $context = [])
    {
        $this->addRecord($file, LogLevel::WARNING, $message, $context);
    }

    public function notice(\SplFileInfo $file, string $message, array $context = [])
    {
        $this->addRecord($file, LogLevel::NOTICE, $message, $context);
    }

    public function info(\SplFileInfo $file, string $message, array $context = [])
    {
        $this->addRecord($file, LogLevel::INFO, $message, $context);
    }

    public function debug(\SplFileInfo $file, string $message, array $context = [])
    {
        $this->addRecord($file, LogLevel::DEBUG, $message, $context);
    }
}
