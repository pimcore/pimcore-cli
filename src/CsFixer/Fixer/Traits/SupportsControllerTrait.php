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

trait SupportsControllerTrait
{
    /**
     * @inheritDoc
     */
    public function supports(\SplFileInfo $file)
    {
        $expectedExtension = '.php';

        if (!substr($file->getFilename(), -strlen($expectedExtension)) === $expectedExtension) {
            return false;
        }

        $baseName = $file->getBasename($expectedExtension);
        if (!$this->isValidControllerName($baseName)) {
            return false;
        }

        return true;
    }

    public function isValidControllerName(string $name): bool
    {
        return (bool) preg_match('/Controller$/', $name);
    }
}
