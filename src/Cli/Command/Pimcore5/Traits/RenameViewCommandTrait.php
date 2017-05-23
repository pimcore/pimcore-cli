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

namespace Pimcore\Cli\Command\Pimcore5\Traits;

use Pimcore\Cli\Command\AbstractCommand;
use Pimcore\Cli\Util\TextUtils;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

trait RenameViewCommandTrait
{
    protected function configureViewRenameOptions()
    {
        /** @var $this AbstractCommand */
        $this
            ->addOption(
                'no-rename-filename', 'R',
                InputOption::VALUE_NONE,
                'Do not convert filenames from dashed-case to camelCase'
            )
            ->addOption(
                'no-rename-first-directory', 'D',
                InputOption::VALUE_NONE,
                'Do not rename first directory to uppercase'
            );

        return $this;
    }

    protected function processPath(InputInterface $input, string $path): string
    {
        $path      = str_replace('\\', DIRECTORY_SEPARATOR, $path);
        $pathParts = explode(DIRECTORY_SEPARATOR, $path);

        if (!$input->getOption('no-rename-first-directory') && count($pathParts) > 1) {
            $pathParts[0] = TextUtils::dashesToCamelCase($pathParts[0], true);
        }

        $filename = array_pop($pathParts);
        $filename = $this->processFilenameExtension($input, $filename);

        $pathParts[] = $filename;

        $path = implode(DIRECTORY_SEPARATOR, $pathParts);

        return $path;
    }

    private function processFilenameExtension(InputInterface $input, string $filename): string
    {
        // normalize extension to html.php if not already done
        $filename = preg_replace('/(?<!\.html)(\.php)/', '.html.php', $filename);

        // temporarily remove extension again
        $filename = preg_replace('/\.html\.php$/', '', $filename);

        if (!$input->getOption('no-rename-filename')) {
            $filename = TextUtils::dashesToCamelCase($filename);
        }

        // re-add extension
        $filename = $filename . '.html.php';

        return $filename;
    }
}
