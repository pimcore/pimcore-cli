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

use Pimcore\Cli\Util\VersionReader;
use Symfony\Component\Console\Style\OutputStyle;

class VersionFormatter
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

    /**
     * @param VersionReader $versionReader
     */
    public function formatVersions(VersionReader $versionReader)
    {
        $entries = [
            sprintf('Version:  <info>%s</info>', $versionReader->getVersion()),
            sprintf('Revision: <info>%d</info>', $versionReader->getRevision()),
        ];

        $this->io->listing($entries);
    }
}
