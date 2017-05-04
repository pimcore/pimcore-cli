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
     * @var string
     */
    private $path;

    /**
     * @var array|null
     */
    private $data;

    /**
     * @param string $path Path to pimcore installation
     */
    public function __construct(string $path)
    {
        $this->path = $path;
    }

    /**
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * @return string
     */
    public function getVersion(): string
    {
        $versionData = $this->getVersionData();

        return (string)$versionData['version'];
    }

    /**
     * @return int
     */
    public function getRevision(): int
    {
        $versionData = $this->getVersionData();

        return (int)$versionData['revision'];
    }

    /**
     * @return array
     */
    public function getVersionData(): array
    {
        if (null !== $this->data) {
            return $this->data;
        }

        $this->checkPrerequisites();

        $process = $this->getProcess();
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException(sprintf('Failed to run version process: %s', $process->getErrorOutput()));
        }

        $version = $process->getOutput();
        $data    = json_decode($version, true);

        if (JSON_ERROR_NONE !== json_last_error()) {
            throw new \RuntimeException(sprintf('Failed to parse JSON version data: %s', json_last_error_msg()));
        }

        $this->data = $data;

        return $data;
    }

    /**
     * Reset cached data
     */
    public function reset()
    {
        $this->data = null;
    }

    private function checkPrerequisites()
    {
        $fs = new Filesystem();
        if (!$fs->exists($this->path)) {
            throw new \InvalidArgumentException(sprintf('Invalid path: %s', $this->path));
        }

        if (!$fs->exists($this->path . '/vendor/autoload.php')) {
            throw new \InvalidArgumentException('vendor/autoload.php not found. Is the installation set up properly?');
        }
    }

    private function getProcess(): PhpProcess
    {
        $code = <<<'EOF'
<?php
require_once __DIR__ . '/vendor/autoload.php';

$data = [
    'version'  => \Pimcore\Version::getVersion(),
    'revision' => \Pimcore\Version::getRevision() 
];

echo json_encode($data);
EOF;

        return new PhpProcess($code, $this->path);
    }
}
