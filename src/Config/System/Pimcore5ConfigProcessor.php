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

namespace Pimcore\Config\System;

use Riimu\Kit\PHPEncoder\PHPEncoder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Processor;

class Pimcore5ConfigProcessor
{
    public function readConfig(string $file): array
    {
        if (!file_exists($file)) {
            throw new \InvalidArgumentException(sprintf('File "%" does not exist', $file));
        }

        $file = new \SplFileInfo($file);
        if ($file->getExtension() !== 'php') {
            throw new \InvalidArgumentException('File must be a PHP file');
        }

        $config = include $file->getRealPath();
        if (false === $config || !is_array($config)) {
            throw new \RuntimeException('Failed to read config file');
        }

        return $config;
    }

    public function dumpConfig(array $config): string
    {
        $encoder = new PHPEncoder();
        $encoded = $encoder->encode($config, [
            'array.inline' => false,
            'array.omit'   => false,
        ]);

        $result = '<?php' . "\n\n" . 'return ' . $encoded . ";\n";

        return $result;
    }

    public function processConfig(array $config): array
    {
        $processor     = new Processor();
        $configuration = new SmtpNodeConfiguration();

        $config = $this->processSmtpConfig($processor, $configuration, $config, 'email');
        $config = $this->processSmtpConfig($processor, $configuration, $config, 'newsletter');

        return $config;
    }

    private function processSmtpConfig(Processor $processor, ConfigurationInterface $configuration, array $config, string $key): array
    {
        $existingConfig = isset($config[$key]) && isset($config[$key]['smtp']) ? $config[$key]['smtp'] : [];

        $toProcess = [
            $existingConfig
        ];

        $processed = $processor->processConfiguration(
            $configuration,
            $toProcess
        );

        $config[$key]['smtp'] = $processed;

        return $config;
    }
}
