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

namespace Pimcore\Cli\Console;

use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\ProcessBuilder;

class Application extends BaseApplication
{
    /**
     * @param string $name
     */
    public function __construct($name = 'UNKNOWN')
    {
        parent::__construct($name, $this->fetchVersion());
    }

    /**
     * Get current version from git describe if not in phar context
     *
     * @param bool $readFromPhar
     *
     * @return string
     */
    public function fetchVersion(bool $readFromPhar = false): string
    {
        $packageVersion  = '@package_version@';

        if ($this->isPhar()) {
            if ($readFromPhar) {
                return $this->getPharVersion(true);
            }
        } else {
            $gitDir = __DIR__ . '/../../../.git';

            if (file_exists($gitDir)) {
                $process = new Process(sprintf('git describe --tags'));
                $process->setEnv([
                    'GIT_DIR' => $gitDir
                ]);

                $process->run();
                if ($process->isSuccessful()) {
                    $packageVersion = $process->getOutput();
                }
            }
        }

        return $packageVersion;
    }

    /**
     * Check if we're running in phar mode
     *
     * @return bool
     */
    public function isPhar(): bool
    {
        $pharPath = \Phar::running();

        return !empty($pharPath);
    }

    /**
     * @param bool $spawnProcess
     *
     * @return string
     */
    public function getPharVersion(bool $spawnProcess = false): string
    {
        if (!$this->isPhar()) {
            throw new \RuntimeException('Can only be called from a PHAR file.');
        }

        if (!$spawnProcess) {
            return $this->getVersion();
        } else {
            $process = $this->runPharCommand('--version');
            if (!$process->isSuccessful()) {
                throw new \RuntimeException($process->getErrorOutput());
            }

            $output  = trim($process->getOutput());
            $matches = [];

            if (preg_match('/version (v.*)$/', $output, $matches)) {
                return $matches[1];
            } else {
                return 'UNKNOWN';
            }
        }
    }

    /**
     * @param string $command
     * @param array $args
     *
     * @return Process
     */
    public function runPharCommand(string $command, array $args = []): Process
    {
        if (!$this->isPhar()) {
            throw new \RuntimeException('Can only be called from a PHAR file.');
        }

        $pharPath = \Phar::running(false);

        $processArgs = array_merge([$pharPath, $command], $args);
        $process     = (new ProcessBuilder($processArgs))->getProcess();
        $process->run();

        return $process;
    }

    /**
     * Resolves file path on filesystem or inside PHAR
     *
     * @param string $path
     *
     * @return string
     */
    public function getFilePath($path)
    {
        if ($this->isPhar()) {
            $phar = new \Phar(\Phar::running());

            if (!isset($phar[$path])) {
                throw new \InvalidArgumentException(sprintf('Path "%s" does not exist inside PHAR file', $path));
            }

            return $phar[$path]->getPathName();
        } else {
            $realPath = realpath(__DIR__ . '/../../../' . ltrim($path, '/'));

            if (!$realPath || !file_exists($realPath)) {
                throw new \InvalidArgumentException(sprintf('Path "%s" does not exist on filesystem', $path));
            }

            return $realPath;
        }
    }
}
