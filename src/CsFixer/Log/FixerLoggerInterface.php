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

interface FixerLoggerInterface
{
    public function log(\SplFileInfo $file, string $level, string $message, array $context = []);

    public function emergency(\SplFileInfo $file, string $message, array $context = []);

    public function alert(\SplFileInfo $file, string $message, array $context = []);

    public function critical(\SplFileInfo $file, string $message, array $context = []);

    public function error(\SplFileInfo $file, string $message, array $context = []);

    public function warning(\SplFileInfo $file, string $message, array $context = []);

    public function notice(\SplFileInfo $file, string $message, array $context = []);

    public function info(\SplFileInfo $file, string $message, array $context = []);

    public function debug(\SplFileInfo $file, string $message, array $context = []);
}
