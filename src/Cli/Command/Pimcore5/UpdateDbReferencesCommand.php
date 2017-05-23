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

namespace Pimcore\Cli\Command\Pimcore5;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Pimcore\Cli\Command\AbstractCommand;
use Pimcore\Cli\Command\Pimcore5\Traits\RenameViewCommandTrait;
use Pimcore\Cli\Traits\DryRunCommandTrait;
use Pimcore\Config\System\Pimcore5ConfigProcessor;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class UpdateDbReferencesCommand extends AbstractCommand
{
    use RenameViewCommandTrait;
    use DryRunCommandTrait;

    private $tables = [
        'documents_email',
        'documents_newsletter',
        'documents_page',
        'documents_printpage',
        'documents_snippet',
    ];

    /**
     * @inheritDoc
     */
    protected function configure()
    {
        $this
            ->setName('pimcore5:views:update-db-references')
            ->setDescription('Update template references in database (change extension and file casing)')
            ->addOption(
                'config-file', 'c',
                InputOption::VALUE_REQUIRED,
                'Path to system.php',
                getcwd() . '/var/config/system.php'
            )
            ->configureViewRenameOptions()
            ->configureDryRunOption();
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $db = $this->getDbConnection($input);
        foreach ($this->tables as $table) {
            $this->processTable($input, $db, $table);
        }
    }

    private function processTable(InputInterface $input, Connection $db, string $table)
    {
        $selectStmt = $db->executeQuery('SELECT id, template FROM ' . $table . ' WHERE template IS NOT NULL AND template <> ""');
        $rows       = $selectStmt->fetchAll();

        if (count($rows) === 0) {
            return;
        }

        $this->io->comment(sprintf('Processing table <info>%s</info> with <comment>%d</comment> rows', $table, count($rows)));

        $updateStmt = $db->prepare('UPDATE ' . $table . ' SET template = :template WHERE id = :id');

        foreach ($rows as $row) {
            $template = $row['template'];
            $template = ltrim($template, '/');
            $template = $this->processPath($input, $template);

            if ($template === $row['template']) {
                continue;
            }

            $this->io->getOutput()->writeln($this->dryRunMessage(sprintf(
                'Updating template ID <info>%d</info> from template <comment>%s</comment> to <comment>%s</comment>',
                $row['id'],
                $row['template'],
                $template
            )));

            if (!$this->isDryRun()) {
                $result = $updateStmt->execute([
                    'id'       => $row['id'],
                    'template' => $template,
                ]);

                if (!$result) {
                    $this->io->error(sprintf('Failed to update template for %s %d', $table, $row['id']));
                }
            }
        }

        $this->io->writeln('');
    }

    /**
     * @param InputInterface $input
     *
     * @return Connection
     */
    private function getDbConnection(InputInterface $input): Connection
    {
        $processor = new Pimcore5ConfigProcessor();
        $config    = $processor->readConfig($input->getOption('config-file'));
        $dbConfig  = $config['database']['params'];

        $params = [
            'dbname'   => $dbConfig['dbname'],
            'user'     => $dbConfig['username'],
            'password' => $dbConfig['password'],
            'host'     => $dbConfig['host'],
            'port'     => $dbConfig['port'],
            'driver'   => 'pdo_mysql',
        ];

        $conn = DriverManager::getConnection($params, new Configuration());

        return $conn;
    }
}
