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

namespace Pimcore\Cli\Command\Config;

use Pimcore\Cli\Command\AbstractCommand;
use Pimcore\Cli\Traits\DryRunCommandTrait;
use Riimu\Kit\PHPEncoder\PHPEncoder;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

class DebugModeCommand extends AbstractCommand
{
    use DryRunCommandTrait;

    /**
     * @var string
     */
    private $filename = 'debug-mode.php';

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $configDir = getcwd() . '/var/config';

        if ($this->isValidConfigDir($configDir)) {
            $fs = new Filesystem();

            $configDir = $fs->makePathRelative($configDir, getcwd());
            $configDir = rtrim($configDir, '/');
        } else {
            $configDir = null;
        }

        $this
            ->setName('config:debug-mode')
            ->setDescription('Sets debug mode')
            ->addArgument(
                'config-dir',
                null === $configDir ? InputArgument::REQUIRED : InputArgument::OPTIONAL,
                'Path to config directory var/config',
                $configDir
            )
            ->addOption(
                'disable', 'd',
                InputOption::VALUE_NONE,
                'Disable debug mode'
            )
            ->addOption(
                'ip', 'i',
                InputOption::VALUE_REQUIRED,
                'Only enable debug mode for the given IP'
            );

        $this->configureDryRunOption();
    }

    private function isValidConfigDir($path): bool
    {
        $pathValid = file_exists($path) && is_dir($path) && is_writable($path);

        if ($pathValid) {
            $file = $path . '/' . $this->filename;
            if (file_exists($file)) {
                $pathValid = $pathValid && is_writable($file);
            }
        }

        return $pathValid;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $dir = $input->getArgument('config-dir');
        if (!$this->isValidConfigDir($dir)) {
            $this->io->error(sprintf('Config directory "%s" is invalid', $dir));

            return 1;
        }

        if ($ip = $input->getOption('ip')) {
            if (!filter_var($ip, FILTER_VALIDATE_IP)) {
                $this->io->error(sprintf('The IP address "%s" is not valid', $ip));

                return 2;
            }
        }

        $file = $dir . '/' . $this->filename;

        $config = $this->dumpConfig([
            'active' => $input->getOption('disable') ? false : true,
            'ip'     => !empty($ip) ? $ip : ''
        ]);

        try {
            if (!$this->isDryRun()) {
                $fs = new Filesystem();
                $fs->dumpFile($file, $config);
            }

            $this->io->writeln($this->dryRunMessage(sprintf('File "%s" was successfully written', $file)));
        } catch (\Exception $e) {
            $this->io->error($e->getMessage());

            return 3;
        }
    }

    private function dumpConfig(array $config): string
    {
        $encoder = new PHPEncoder();
        $encoded = $encoder->encode($config, [
            'array.inline' => false,
            'array.omit'   => false,
        ]);

        $result = '<?php' . "\n\n" . 'return ' . $encoded . ";\n";

        return $result;
    }
}
