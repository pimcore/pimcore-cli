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

use Pimcore\CsFixer\Log\FixerLoggerInterface;
use Pimcore\CsFixer\Util\FixerResolver;

class FixViewsCommand extends AbstractCsFixerCommand
{
    /**
     * @inheritDoc
     */
    protected function configure()
    {
        $this
            ->setName('pimcore5:views:fix')
            ->setDescription('Changes common migration patterns in view files (e.g. strips leading slashes in template() calls)');

        parent::configure();
    }

    /**
     * @inheritDoc
     */
    protected function getCustomFixers(FixerLoggerInterface $logger): array
    {
        return FixerResolver::getCustomFixers($logger,'View');
    }
}
