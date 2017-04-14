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

namespace Pimcore\Cli\Util;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\PhpProcess;

class VersionReader
{
    /**
     * @var array
     */
    private $cache = [];

    /**
     * @param string $path
     *
     * @return string
     */
    public function getVersion(string $path): string
    {
        $versionData = $this->getVersionData($path);

        return (string)$versionData['version'];
    }

    /**
     * @param string $path
     *
     * @return int
     */
    public function getRevision(string $path): int
    {
        $versionData = $this->getVersionData($path);

        return (int)$versionData['revision'];
    }

    /**
     * @param string|null $path
     */
    public function resetCache(string $path = null)
    {
        if (null !== $path) {
            if (isset($this->cache[$path])) {
                unset($this->cache[$path]);
            }
        } else {
            $this->cache = [];
        }
    }

    /**
     * @param string $path Path to pimcore installation
     *
     * @return array
     */
    public function getVersionData(string $path): array
    {
        if (isset($this->cache[$path])) {
            return $this->cache[$path];
        }

        $fs = new Filesystem();
        if (!$fs->exists($path)) {
            throw new \InvalidArgumentException(sprintf('Invalid path: %s', $path));
        }

        if (!$fs->exists($path . '/vendor/autoload.php')) {
            throw new \InvalidArgumentException('vendor/autoload.php not found. Is the installation set up properly?');
        }

        $process = $this->getProcess($path);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException(sprintf('Failed to run version process: %s', $process->getErrorOutput()));
        }

        $version = $process->getOutput();
        $info    = json_decode($version, true);

        if (JSON_ERROR_NONE !== json_last_error()) {
            throw new \RuntimeException(sprintf('Failed to parse JSON version info: %s', json_last_error_msg()));
        }

        $this->cache[$path] = $info;

        return $info;
    }

    private function getProcess(string $path): PhpProcess
    {
        $code = <<<'EOF'
<?php
require_once __DIR__ . '/vendor/autoload.php';

$info = [
    'version'  => \Pimcore\Version::getVersion(),
    'revision' => \Pimcore\Version::getRevision() 
];

echo json_encode($info);
EOF;

        return new PhpProcess($code, $path);
    }
}
