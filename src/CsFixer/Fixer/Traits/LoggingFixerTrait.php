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

namespace Pimcore\CsFixer\Fixer\Traits;

use Pimcore\CsFixer\Log\FixerLogger;
use Pimcore\CsFixer\Log\FixerLoggerInterface;

trait LoggingFixerTrait
{
    /**
     * @var FixerLoggerInterface
     */
    private $logger;

    public function setLogger(FixerLoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    protected function getLogger(): FixerLoggerInterface
    {
        if (null === $this->logger) {
            $this->logger = new FixerLogger();
        }

        return $this->logger;
    }
}
