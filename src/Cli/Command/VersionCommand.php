<?php
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

namespace Pimcore\Cli\Command;

use Pimcore\Cli\Console\Style\VersionFormatter;
use Pimcore\Cli\Util\VersionReader;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

class VersionCommand extends AbstractCommand
{
    protected function configure()
    {
        $this
            ->setName('info:version')
            ->addArgument(
                'path', InputArgument::REQUIRED,
                'Path to Pimcore installation'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $fs = new Filesystem();

        $path = $input->getArgument('path');
        if (!$fs->exists($path)) {
            $this->io->error(sprintf('Given path %s does not exist', $path));

            return 1;
        }

        $versionReader = new VersionReader($path);

        $this->io->title(sprintf('Version info for installation <comment>%s</comment>', realpath($versionReader->getPath())));

        $formatter = new VersionFormatter($this->io);
        $formatter->formatVersions($versionReader);
    }
}
