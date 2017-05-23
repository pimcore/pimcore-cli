#!/usr/bin/env php
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

// CLI has no memory/time limits
use Pimcore\Cli\Command;
use Pimcore\Cli\Console\Application;
use Stecman\Component\Symfony\Console\BashCompletion\CompletionCommand;

// only run in PHP >= 7
$requiredVersion = '7.0';
if (version_compare(PHP_VERSION, $requiredVersion, '<')) {
    file_put_contents('php://stderr', sprintf(
        "Pimcore CLI Tools require PHP 7.0 version or higher and your system has\n".
        "PHP %s version installed.\n\n".
        PHP_VERSION
    ));

    exit(1);
}

@ini_set('memory_limit', -1);
@ini_set('max_execution_time', -1);
@ini_set('max_input_time', -1);

require_once __DIR__ . '/../vendor/autoload.php';

$application = new Application('Pimcore CLI Tools');
$application->addCommands([
    new CompletionCommand(),
    new Command\VersionCommand(),
    new Command\Pimcore5\CheckRequirementsCommand(),
    new Command\Pimcore5\MigrateFilesystemCommand(),
    new Command\Pimcore5\MigrateAreabrickCommand(),
    new Command\Pimcore5\UpdateDbReferencesCommand(),
    new Command\Pimcore5\RenameViewsCommand(),
    new Command\Pimcore5\FixViewsCommand(),
    new Command\Pimcore5\FixConfigCommand(),
]);

$application->run();
