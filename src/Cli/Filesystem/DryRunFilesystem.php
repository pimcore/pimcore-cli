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

namespace Pimcore\Cli\Filesystem;

use Pimcore\Cli\Console\Style\PimcoreStyle;
use Pimcore\Cli\Filesystem\CommandCollector\CommandCollectorInterface;
use Pimcore\Cli\Traits\DryRunTrait;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

class DryRunFilesystem extends Filesystem
{
    use DryRunTrait;

    /**
     * @var PimcoreStyle
     */
    private $io;

    /**
     * @var bool
     */
    private $dryRun = false;

    /**
     * @var bool
     */
    private $printProperties = false;

    /**
     * @var bool
     */
    private $forceVerbose = false;

    /**
     * @var CommandCollectorInterface
     */
    private $commandCollector;

    public function __construct(
        PimcoreStyle $io,
        bool $dryRun = false,
        bool $printProperties = false,
        CommandCollectorInterface $commandCollector = null
    ) {
        $this->io               = $io;
        $this->dryRun           = $dryRun;
        $this->printProperties  = $printProperties;
        $this->commandCollector = $commandCollector;
    }

    /**
     * @param bool $forceVerbose
     */
    public function setForceVerbose(bool $forceVerbose)
    {
        $this->forceVerbose = $forceVerbose;
    }

    protected function isDryRun(): bool
    {
        return $this->dryRun;
    }

    private function writeCommandMessage(string $command, string $suffix = null)
    {
        if (null !== $this->commandCollector) {
            $this->commandCollector->collect($command);
        }

        if (!$this->isDryRun() && !$this->io->isVerbose() && !$this->forceVerbose) {
            return;
        }

        if (null !== $suffix) {
            $command = $command . $suffix;
        }

        $prefix = '';
        if ($this->isDryRun()) {
            $prefix = $this->prefixDryRun('');
        }

        $command = sprintf('%sfs> %s', $prefix, $command);

        $this->io->writeln($command);
    }

    /**
     * Copies a file.
     *
     * If the target file is older than the origin file, it's always overwritten.
     * If the target file is newer, it is overwritten only when the
     * $overwriteNewerFiles option is set to true.
     *
     * @param string $originFile        The original filename
     * @param string $targetFile        The target filename
     * @param bool $overwriteNewerFiles If true, target files newer than origin files are overwritten
     *
     * @throws FileNotFoundException When originFile doesn't exist
     * @throws IOException           When copy fails
     */
    public function copy($originFile, $targetFile, $overwriteNewerFiles = false)
    {
        $this->writeCommandMessage(sprintf(
            'cp %s %s',
            $originFile, $targetFile
        ), $this->formatPropertyString([
            'overwriteNewerFiles' => $overwriteNewerFiles,
        ]));

        if (!$this->dryRun) {
            parent::copy($originFile, $targetFile, $overwriteNewerFiles);
        }
    }

    /**
     * Sets access and modification time of file.
     *
     * @param string|array|\Traversable $files A filename, an array of files, or a \Traversable instance to create
     * @param int $time                        The touch time as a Unix timestamp
     * @param int $atime                       The access time as a Unix timestamp
     *
     * @throws IOException When touch fails
     */
    public function touch($files, $time = null, $atime = null)
    {
        foreach ($this->toIterator($files) as $file) {
            $this->writeCommandMessage(sprintf(
                'touch %s',
                $file
            ), $this->formatPropertyString([
                'time'  => $time,
                'atime' => $atime
            ]));
        }

        if (!$this->dryRun) {
            parent::touch($files, $time, $atime);
        }
    }

    /**
     * Removes files or directories.
     *
     * @param string|array|\Traversable $files A filename, an array of files, or a \Traversable instance to remove
     *
     * @throws IOException When removal fails
     */
    public function remove($files)
    {
        foreach ($this->toIterator($files) as $file) {
            $this->writeCommandMessage(sprintf(
                'rm %s',
                $file
            ));
        }

        if (!$this->dryRun) {
            parent::remove($files);
        }
    }

    /**
     * Change mode for an array of files or directories.
     *
     * @param string|array|\Traversable $files A filename, an array of files, or a \Traversable instance to change mode
     * @param int $mode                        The new mode (octal)
     * @param int $umask                       The mode mask (octal)
     * @param bool $recursive                  Whether change the mod recursively or not
     *
     * @throws IOException When the change fail
     */
    public function chmod($files, $mode, $umask = 0000, $recursive = false)
    {
        foreach ($this->toIterator($files) as $file) {
            $this->writeCommandMessage(sprintf(
                'chmod %s',
                $file
            ), $this->formatPropertyString([
                'mode'      => $mode,
                'umask'     => $umask,
                'recursive' => $recursive
            ]));
        }

        if (!$this->dryRun) {
            parent::chmod($files, $mode, $umask, $recursive);
        }
    }

    /**
     * Change the owner of an array of files or directories.
     *
     * @param string|array|\Traversable $files A filename, an array of files, or a \Traversable instance to change owner
     * @param string $user                     The new owner user name
     * @param bool $recursive                  Whether change the owner recursively or not
     *
     * @throws IOException When the change fail
     */
    public function chown($files, $user, $recursive = false)
    {
        foreach ($this->toIterator($files) as $file) {
            $this->writeCommandMessage(sprintf(
                'chown %s',
                $file
            ), $this->formatPropertyString([
                'user'      => $user,
                'recursive' => $recursive
            ]));
        }

        if (!$this->dryRun) {
            parent::chown($files, $user, $recursive);
        }
    }

    /**
     * Change the group of an array of files or directories.
     *
     * @param string|array|\Traversable $files A filename, an array of files, or a \Traversable instance to change group
     * @param string $group                    The group name
     * @param bool $recursive                  Whether change the group recursively or not
     *
     * @throws IOException When the change fail
     */
    public function chgrp($files, $group, $recursive = false)
    {
        foreach ($this->toIterator($files) as $file) {
            $this->writeCommandMessage(sprintf(
                'chgrp %s',
                $file
            ), $this->formatPropertyString([
                'group'     => $group,
                'recursive' => $recursive
            ]));
        }

        if (!$this->dryRun) {
            parent::chgrp($files, $group, $recursive);
        }
    }

    /**
     * Creates a symbolic link or copy a directory.
     *
     * @param string $originDir   The origin directory path
     * @param string $targetDir   The symbolic link name
     * @param bool $copyOnWindows Whether to copy files if on Windows
     *
     * @throws IOException When symlink fails
     */
    public function symlink($originDir, $targetDir, $copyOnWindows = false)
    {
        $this->writeCommandMessage(sprintf(
            'ln -s %s %s',
            $originDir,
            $targetDir
        ), $this->formatPropertyString([
            'copyOnWindows' => $copyOnWindows,
        ]));

        if (!$this->dryRun) {
            parent::symlink($originDir, $targetDir, $copyOnWindows);
        }
    }

    /**
     * Creates a hard link, or several hard links to a file.
     *
     * @param string $originFile           The original file
     * @param string|string[] $targetFiles The target file(s)
     *
     * @throws FileNotFoundException When original file is missing or not a file
     * @throws IOException           When link fails, including if link already exists
     */
    public function hardlink($originFile, $targetFiles)
    {
        foreach ($this->toIterator($targetFiles) as $targetFile) {
            $this->writeCommandMessage(sprintf(
                'ln %s %s',
                $originFile,
                $targetFile
            ));
        }

        if (!$this->dryRun) {
            parent::hardlink($originFile, $targetFiles);
        }
    }

    /**
     * Creates a temporary file with support for custom stream wrappers.
     *
     * @param string $dir    The directory where the temporary filename will be created
     * @param string $prefix The prefix of the generated temporary filename
     *                       Note: Windows uses only the first three characters of prefix
     *
     * @return string The new temporary filename (with path), or throw an exception on failure
     */
    public function tempnam($dir, $prefix)
    {
        if ($this->dryRun) {
            throw new \RuntimeException('tempnam() is not supported in dry-run mode');
        }

        return parent::tempnam($dir, $prefix);
    }

    /**
     * Atomically dumps content into a file.
     *
     * @param string $filename The file to be written to
     * @param string $content  The data to write into the file
     *
     * @throws IOException If the file cannot be written to.
     */
    public function dumpFile($filename, $content)
    {
        $this->writeCommandMessage(sprintf(
            'dumpFile(%s, ...)',
            $filename
        ));

        if (!$this->dryRun) {
            parent::dumpFile($filename, $content);
        }
    }

    /**
     * Creates a directory recursively.
     *
     * @param string|array|\Traversable $dirs The directory path
     * @param int                       $mode The directory mode
     *
     * @throws IOException On any directory creation failure
     */
    public function mkdir($dirs, $mode = 0777)
    {
        foreach ($this->toIterator($dirs) as $dir) {
            $this->writeCommandMessage(sprintf(
                'mkdir %s',
                $dir
            ), $this->formatPropertyString([
                'mode' => $mode,
            ]));
        }

        if (!$this->dryRun) {
            parent::mkdir($dirs, $mode);
        }
    }

    /**
     * Renames a file or a directory.
     *
     * @param string $origin    The origin filename or directory
     * @param string $target    The new filename or directory
     * @param bool   $overwrite Whether to overwrite the target if it already exists
     *
     * @throws IOException When target file or directory already exists
     * @throws IOException When origin cannot be renamed
     */
    public function rename($origin, $target, $overwrite = false)
    {
        $this->writeCommandMessage(sprintf(
            'mv %s %s',
            $origin, $target
        ), $this->formatPropertyString([
            'overwrite' => $overwrite,
        ]));

        if (!$this->dryRun) {
            parent::rename($origin, $target, $overwrite);
        }
    }

    /**
     * Mirrors a directory to another.
     *
     * @param string $originDir       The origin directory
     * @param string $targetDir       The target directory
     * @param \Traversable $iterator  A Traversable instance
     * @param array $options          An array of boolean options
     *                                Valid options are:
     *                                - $options['override'] Whether to override an existing file on copy or not (see
     *                                copy())
     *                                - $options['copy_on_windows'] Whether to copy files instead of links on Windows
     *                                (see symlink())
     *                                - $options['delete'] Whether to delete files that are not in the source directory
     *                                (defaults to false)
     *
     * @throws IOException When file type is unknown
     */
    public function mirror($originDir, $targetDir, \Traversable $iterator = null, $options = [])
    {
        if ($this->dryRun) {
            throw new \RuntimeException('mirror() is not supported in dry-run mode');
        }

        parent::mirror($originDir, $targetDir, $iterator, $options);
    }

    /**
     * @param mixed $files
     *
     * @return \Traversable
     */
    private function toIterator($files)
    {
        if (!$files instanceof \Traversable) {
            $files = new \ArrayObject(is_array($files) ? $files : [$files]);
        }

        return $files;
    }

    private function formatPropertyString(array $properties): string
    {
        if (!$this->printProperties) {
            return '';
        }

        $result = [];
        foreach ($properties as $key => $value) {
            if (null !== $value) {
                if (is_bool($value)) {
                    $value = $value ? 'true' : 'false';
                }

                $result[] = sprintf('%s: %s', $key, $value);
            }
        }

        if (count($result) > 0) {
            return ' (' . implode(', ', $result) . ')';
        }

        return '';
    }
}
