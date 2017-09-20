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

use PhpCsFixer\Config;
use PhpCsFixer\ConfigInterface;
use PhpCsFixer\Console\Output\ErrorOutput;
use PhpCsFixer\Console\Output\NullOutput;
use PhpCsFixer\Console\Output\ProcessOutput;
use PhpCsFixer\Error\ErrorsManager;
use PhpCsFixer\Fixer\DefinedFixerInterface;
use PhpCsFixer\Fixer\FixerInterface;
use PhpCsFixer\Report\ReportSummary;
use PhpCsFixer\Runner\Runner;
use Pimcore\Cli\Command\AbstractCommand;
use Pimcore\CsFixer\Console\ConfigurationResolver;
use Pimcore\CsFixer\Log\FixerLogger;
use Pimcore\CsFixer\Log\FixerLoggerInterface;
use Pimcore\CsFixer\Log\Record;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Stopwatch\Stopwatch;

/**
 * Runs PHP-CS-Fixer with our custom ruleset to ease view migration. This command
 * is heavily inspired by the FixCommand from the PHP-CS-Fixer package.
 */
abstract class AbstractCsFixerCommand extends AbstractCommand
{
    // Exit status 1 is reserved for environment constraints not matched.
    const EXIT_STATUS_FLAG_HAS_INVALID_FILES = 4;
    const EXIT_STATUS_FLAG_HAS_CHANGED_FILES = 8;
    const EXIT_STATUS_FLAG_HAS_INVALID_CONFIG = 16;
    const EXIT_STATUS_FLAG_HAS_INVALID_FIXER_CONFIG = 32;
    const EXIT_STATUS_FLAG_EXCEPTION_IN_APP = 64;

    /**
     * EventDispatcher instance.
     *
     * @var EventDispatcher
     */
    private $eventDispatcher;

    /**
     * ErrorsManager instance.
     *
     * @var ErrorsManager
     */
    private $errorsManager;

    /**
     * Stopwatch instance.
     *
     * @var Stopwatch
     */
    private $stopwatch;

    /**
     * Config instance.
     *
     * @var ConfigInterface
     */
    private $config;

    /**
     * @var FixerLoggerInterface
     */
    private $logger;

    public function __construct()
    {
        parent::__construct();

        $this->logger          = new FixerLogger();
        $this->config          = $this->buildConfig();
        $this->errorsManager   = new ErrorsManager();
        $this->eventDispatcher = new EventDispatcher();
        $this->stopwatch       = new Stopwatch();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setDefinition(
                [
                    new InputArgument('path', InputArgument::REQUIRED, 'The path.'),
                    new InputOption('path-mode', '', InputOption::VALUE_REQUIRED, 'Specify path mode (can be override or intersection).', 'override'),
                    new InputOption('allow-risky', '', InputOption::VALUE_REQUIRED, 'Are risky fixers allowed (can be yes or no).'),
                    new InputOption('dry-run', '', InputOption::VALUE_NONE, 'Only shows which files would have been modified.'),
                    new InputOption('using-cache', '', InputOption::VALUE_REQUIRED, 'Does cache should be used (can be yes or no).'),
                    new InputOption('cache-file', '', InputOption::VALUE_REQUIRED, 'The path to the cache file.'),
                    new InputOption('diff', '', InputOption::VALUE_NONE, 'Also produce diff for each file.'),
                    new InputOption('format', '', InputOption::VALUE_REQUIRED, 'To output results in other formats.'),
                    new InputOption('stop-on-violation', '', InputOption::VALUE_NONE, 'Stop execution on first violation.'),
                    new InputOption('show-progress', '', InputOption::VALUE_REQUIRED, 'Type of progress indicator (none, run-in, or estimating).'),
                ]
            );

        $this->addOption(
            'list-fixers', null,
            InputOption::VALUE_NONE,
            'Lists all fixers which would be applied'
        );

        $this->addOption(
            'exclude-fixer', 'x',
            InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
            'Prevent a certain fixer from being executed'
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $verbosity = $output->getVerbosity();

        $exclude = [];
        if (!empty($excludeFixers = $input->getOption('exclude-fixer'))) {
            $exclude = array_map(function ($exclude) {
                if (!preg_match('@^Pimcore/@', $exclude)) {
                    $exclude = 'Pimcore/' . $exclude;
                }

                return $exclude;
            }, $excludeFixers);
        }

        $resolver = new ConfigurationResolver(
            $this->config,
            [
                'allow-risky'       => $input->getOption('allow-risky'),
                'exclude'           => $exclude,
                'dry-run'           => $input->getOption('dry-run'),
                'path'              => $input->getArgument('path'),
                'path-mode'         => $input->getOption('path-mode'),
                'using-cache'       => $input->getOption('using-cache'),
                'cache-file'        => $input->getOption('cache-file'),
                'format'            => $input->getOption('format'),
                'diff'              => $input->getOption('diff'),
                'stop-on-violation' => $input->getOption('stop-on-violation'),
                'verbosity'         => $verbosity,
                'show-progress'     => $input->getOption('show-progress'),
            ],
            getcwd()
        );

        if ($input->getOption('list-fixers')) {
            $this->listFixers($resolver->getFixers());

            return 0;
        }

        $reporter = $resolver->getReporter();

        $stdErr = $output instanceof ConsoleOutputInterface
            ? $output->getErrorOutput()
            : ('txt' === $reporter->getFormat() ? $output : null);

        if (null !== $stdErr) {
            if (extension_loaded('xdebug')) {
                $stdErr->writeln(sprintf($stdErr->isDecorated() ? '<bg=yellow;fg=black;>%s</>' : '%s', 'You are running the fix command which uses php-cs-fixer with xdebug enabled. This has a major impact on runtime performance.'));
            }

            if ($resolver->getUsingCache()) {
                $cacheFile = $resolver->getCacheFile();
                if (is_file($cacheFile)) {
                    $stdErr->writeln(sprintf('Using cache file "%s".', $cacheFile));
                }
            }
        }

        $progressType = $resolver->getProgress();

        /** @var Finder $finder */
        $finder = $resolver->getFinder();

        if ('none' === $progressType || null === $stdErr) {
            $progressOutput = new NullOutput();
        } elseif ('run-in' === $progressType) {
            $progressOutput = new ProcessOutput($stdErr, $this->eventDispatcher, null, null);
        } else {
            $finder         = new \ArrayIterator(iterator_to_array($finder));
            $count          = count($finder);
            $progressOutput = new ProcessOutput($stdErr, $this->eventDispatcher, $count, $count);
        }

        $runner = new Runner(
            $finder,
            $resolver->getFixers(),
            $resolver->getDiffer(),
            'none' !== $progressType ? $this->eventDispatcher : null,
            $this->errorsManager,
            $resolver->getLinter(),
            $resolver->isDryRun(),
            $resolver->getCacheManager(),
            $resolver->getDirectory(),
            $resolver->shouldStopOnViolation()
        );

        $this->stopwatch->start('fixFiles');
        $changed = $runner->fix();
        $this->stopwatch->stop('fixFiles');

        $progressOutput->printLegend();

        $fixEvent = $this->stopwatch->getEvent('fixFiles');

        $reportSummary = new ReportSummary(
            $changed,
            $fixEvent->getDuration(),
            $fixEvent->getMemory(),
            OutputInterface::VERBOSITY_VERBOSE <= $output->getVerbosity(),
            $resolver->isDryRun(),
            $output->isDecorated()
        );

        $output->isDecorated()
            ? $output->write($reporter->generate($reportSummary))
            : $output->write($reporter->generate($reportSummary), false, OutputInterface::OUTPUT_RAW);

        $invalidErrors   = $this->errorsManager->getInvalidErrors();
        $exceptionErrors = $this->errorsManager->getExceptionErrors();
        $lintErrors      = $this->errorsManager->getLintErrors();

        if (null !== $stdErr) {
            $errorOutput = new ErrorOutput($stdErr);

            if (count($invalidErrors) > 0) {
                $errorOutput->listErrors('linting before fixing', $invalidErrors);
            }

            if (count($exceptionErrors) > 0) {
                $errorOutput->listErrors('fixing', $exceptionErrors);
            }

            if (count($lintErrors) > 0) {
                $errorOutput->listErrors('linting after fixing', $lintErrors);
            }
        }

        $this->printLogSummary();

        return $this->calculateExitStatus(
            $resolver->isDryRun(),
            count($changed) > 0,
            count($invalidErrors) > 0,
            count($exceptionErrors) > 0
        );
    }

    /**
     * @param FixerInterface[] $fixers
     */
    private function listFixers(array $fixers)
    {
        $table = new Table($this->io);
        $table->setHeaders([
            'Name',
            'Priority',
            'Description'
        ]);

        foreach ($fixers as $fixer) {
            $name = preg_replace('@^Pimcore/@', '', $fixer->getName());

            $description = '';
            if ($fixer instanceof DefinedFixerInterface) {
                $description = $fixer->getDefinition()->getSummary();

                if (!empty($fixer->getDefinition()->getDescription())) {
                    $description .= "\n" . $fixer->getDefinition()->getDescription();
                }
            }

            $table->addRow([
                $name,
                $fixer->getPriority(),
                $description
            ]);
        }

        $table->render();
    }

    private function printLogSummary()
    {
        if (!$this->logger->hasRecords()) {
            return;
        }

        $this->io->section('Log messages');

        $consoleLogger = new ConsoleLogger($this->io, [
            LogLevel::EMERGENCY => OutputInterface::VERBOSITY_NORMAL,
            LogLevel::ALERT     => OutputInterface::VERBOSITY_NORMAL,
            LogLevel::CRITICAL  => OutputInterface::VERBOSITY_NORMAL,
            LogLevel::ERROR     => OutputInterface::VERBOSITY_NORMAL,
            LogLevel::WARNING   => OutputInterface::VERBOSITY_NORMAL,
            LogLevel::NOTICE    => OutputInterface::VERBOSITY_NORMAL,
            LogLevel::INFO      => OutputInterface::VERBOSITY_NORMAL,
            LogLevel::DEBUG     => OutputInterface::VERBOSITY_NORMAL,
        ]);

        $i = 1;
        foreach ($this->logger->getRecords() as $fileName => $records) {
            $this->io->writeln(sprintf('%d) %s', $i++, $fileName));
            $this->io->newLine();

            /** @var Record $record */
            foreach ($records as $record) {
                $record->log($consoleLogger);
            }

            $this->io->newLine();
        }
    }

    private function buildConfig(): ConfigInterface
    {
        $fixers = $this->getCustomFixers($this->logger);

        $rules = [];
        foreach ($fixers as $fixer) {
            $rules[$fixer->getName()] = true;
        }

        $config = new Config();
        $config->setCacheFile('.pimcore_cli.fix.cache');
        $config->registerCustomFixers($fixers);
        $config->setRules($rules);

        return $config;
    }

    /**
     * Returns custom fixers which should be used for the running command
     *
     * @param FixerLoggerInterface $logger
     *
     * @return array
     */
    abstract protected function getCustomFixers(FixerLoggerInterface $logger): array;

    /**
     * @param bool $isDryRun
     * @param bool $hasChangedFiles
     * @param bool $hasInvalidErrors
     * @param bool $hasExceptionErrors
     *
     * @return int
     */
    private function calculateExitStatus($isDryRun, $hasChangedFiles, $hasInvalidErrors, $hasExceptionErrors)
    {
        $exitStatus = 0;

        if ($isDryRun) {
            if ($hasChangedFiles) {
                $exitStatus |= self::EXIT_STATUS_FLAG_HAS_CHANGED_FILES;
            }

            if ($hasInvalidErrors) {
                $exitStatus |= self::EXIT_STATUS_FLAG_HAS_INVALID_FILES;
            }
        }

        if ($hasExceptionErrors) {
            $exitStatus |= self::EXIT_STATUS_FLAG_EXCEPTION_IN_APP;
        }

        return $exitStatus;
    }
}
