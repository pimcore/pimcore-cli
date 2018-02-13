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

namespace Pimcore\Cli\SelfUpdate;

use Composer\Semver\Comparator;

class Updater extends \Humbug\SelfUpdate\Updater
{
    protected function newVersionAvailable()
    {
        $this->newVersion = $this->strategy->getCurrentRemoteVersion($this);
        $this->oldVersion = $this->strategy->getCurrentLocalVersion($this);

        if (!empty($this->oldVersion) && !empty($this->newVersion) && ($this->newVersion !== $this->oldVersion)) {
            return Comparator::greaterThan($this->newVersion, $this->oldVersion);
        }

        return false;
    }
}
