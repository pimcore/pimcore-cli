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

namespace Pimcore\CsFixer\Console;

use PhpCsFixer\Cache\CacheManagerInterface;
use PhpCsFixer\Cache\Directory;
use PhpCsFixer\Cache\DirectoryInterface;
use PhpCsFixer\Cache\FileCacheManager;
use PhpCsFixer\Cache\FileHandler;
use PhpCsFixer\Cache\NullCacheManager;
use PhpCsFixer\Cache\Signature;
use PhpCsFixer\ConfigInterface;
use PhpCsFixer\ConfigurationException\InvalidConfigurationException;
use PhpCsFixer\Differ\DifferInterface;
use PhpCsFixer\Differ\NullDiffer;
use PhpCsFixer\Differ\SebastianBergmannDiffer;
use PhpCsFixer\Finder;
use PhpCsFixer\Fixer\FixerInterface;
use PhpCsFixer\FixerFactory;
use PhpCsFixer\Linter\Linter;
use PhpCsFixer\Linter\LinterInterface;
use PhpCsFixer\Report\ReporterFactory;
use PhpCsFixer\Report\ReporterInterface;
use PhpCsFixer\RuleSet;
use PhpCsFixer\StdinFileInfo;
use PhpCsFixer\ToolInfo;
use PhpCsFixer\WhitespacesFixerConfig;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder as SymfonyFinder;

/**
 * Copy with local adaptions from \PhpCsFixer\Console\ConfigurationResolver
 */
final class ConfigurationResolver
{
    const PATH_MODE_OVERRIDE = 'override';
    const PATH_MODE_INTERSECTION = 'intersection';

    /**
     * @var null|bool
     */
    private $allowRisky;

    /**
     * @var null|ConfigInterface
     */
    private $config;

    /**
     * @var string
     */
    private $cwd;

    /**
     * @var null|ReporterInterface
     */
    private $reporter;

    /**
     * @var null|bool
     */
    private $isStdIn;

    /**
     * @var null|bool
     */
    private $isDryRun;

    /**
     * @var null|FixerInterface[]
     */
    private $fixers;

    /**
     * @var array
     */
    private $options = [
        'allow-risky' => null,
        'config' => null,
        'dry-run' => null,
        'format' => null,
        'path' => [],
        'path-mode' => self::PATH_MODE_OVERRIDE,
        'using-cache' => null,
        'cache-file' => null,
        'rules' => null,
        'diff' => null,
        'verbosity' => null,
        'stop-on-violation' => null,
        'show-progress' => null,
    ];

    private $cacheFile;
    private $cacheManager;
    private $differ;
    private $directory;
    private $finder;
    private $format;
    private $linter;
    private $path;
    private $progress;
    private $ruleSet;
    private $usingCache;

    /**
     * @var FixerFactory
     */
    private $fixerFactory;

    /**
     * ConfigurationResolver constructor.
     *
     * @param ConfigInterface $config
     * @param array           $options
     * @param string          $cwd
     */
    public function __construct(
        ConfigInterface $config,
        array $options,
        $cwd
    ) {
        $this->cwd = $cwd;
        $this->config = $config;

        foreach ($options as $name => $value) {
            $this->setOption($name, $value);
        }
    }

    /**
     * @return string|null
     */
    public function getCacheFile()
    {
        if (!$this->getUsingCache()) {
            return null;
        }

        if (null === $this->cacheFile) {
            if (null === $this->options['cache-file']) {
                $this->cacheFile = $this->getConfig()->getCacheFile();
            } else {
                $this->cacheFile = $this->options['cache-file'];
            }
        }

        return $this->cacheFile;
    }

    /**
     * @return CacheManagerInterface
     */
    public function getCacheManager()
    {
        if (null === $this->cacheManager) {
            if ($this->getUsingCache() && (ToolInfo::isInstalledAsPhar() || ToolInfo::isInstalledByComposer())) {
                $this->cacheManager = new FileCacheManager(
                    new FileHandler($this->getCacheFile()),
                    new Signature(
                        PHP_VERSION,
                        ToolInfo::getVersion(),
                        $this->getRules()
                    ),
                    $this->isDryRun(),
                    $this->getDirectory()
                );
            } else {
                $this->cacheManager = new NullCacheManager();
            }
        }

        return $this->cacheManager;
    }

    /**
     * Returns config instance.
     *
     * @return ConfigInterface
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * @return DifferInterface
     */
    public function getDiffer()
    {
        if (null === $this->differ) {
            $this->differ = false === $this->options['diff'] ? new NullDiffer() : new SebastianBergmannDiffer();
        }

        return $this->differ;
    }

    /**
     * @return DirectoryInterface
     */
    public function getDirectory()
    {
        if (null === $this->directory) {
            $path = $this->getCacheFile();
            $filesystem = new Filesystem();

            $absolutePath = $filesystem->isAbsolutePath($path)
                ? $path
                : $this->cwd.DIRECTORY_SEPARATOR.$path;

            $this->directory = new Directory(dirname($absolutePath));
        }

        return $this->directory;
    }

    /**
     * Returns fixers.
     *
     * @return FixerInterface[] An array of FixerInterface
     */
    public function getFixers()
    {
        if (null === $this->fixers) {
            $this->fixers = $this->createFixerFactory()
                ->useRuleSet($this->getRuleSet())
                ->setWhitespacesConfig(new WhitespacesFixerConfig($this->config->getIndent(), $this->config->getLineEnding()))
                ->getFixers();

            if (false === $this->getRiskyAllowed()) {
                $riskyFixers = array_map(
                    function (FixerInterface $fixer) {
                        return $fixer->getName();
                    },
                    array_filter(
                        $this->fixers,
                        function (FixerInterface $fixer) {
                            return $fixer->isRisky();
                        }
                    )
                );

                if (count($riskyFixers)) {
                    throw new InvalidConfigurationException(sprintf('The rules contain risky fixers (%s), but they are not allowed to run. Perhaps you forget to use --allow-risky option?', implode(', ', $riskyFixers)));
                }
            }
        }

        return $this->fixers;
    }

    /**
     * @return LinterInterface
     */
    public function getLinter()
    {
        if (null === $this->linter) {
            $this->linter = new Linter($this->getConfig()->getPhpExecutable());
        }

        return $this->linter;
    }

    /**
     * Returns path.
     *
     * @return string[]
     */
    public function getPath()
    {
        if (null === $this->path) {
            $filesystem = new Filesystem();
            $cwd = $this->cwd;

            if ('-' === $this->options['path']) {
                $this->path = [$this->options['path']];
            } else {
                $absolutePath = $filesystem->isAbsolutePath($this->options['path'])
                    ? $this->options['path']
                    : $cwd . DIRECTORY_SEPARATOR . $this->options['path'];

                if (!file_exists($absolutePath)) {
                    throw new InvalidConfigurationException(sprintf(
                        'The path "%s" is not readable.',
                        $this->options['path']
                    ));
                }

                $this->path = [$absolutePath];
            }
        }

        return $this->path;
    }

    /**
     * @throws InvalidConfigurationException
     *
     * @return string
     */
    public function getProgress()
    {
        if (null === $this->progress) {
            if (OutputInterface::VERBOSITY_VERBOSE <= $this->options['verbosity'] && 'txt' === $this->getFormat()) {
                $progressType = $this->options['show-progress'];
                $progressTypes = ['none', 'run-in', 'estimating'];

                if (null === $progressType) {
                    $progressType = $this->getConfig()->getHideProgress() ? 'none' : 'run-in';
                } elseif (!in_array($progressType, $progressTypes, true)) {
                    throw new InvalidConfigurationException(sprintf(
                        'The progress type "%s" is not defined, supported are "%s".',
                        $progressType,
                        implode('", "', $progressTypes)
                    ));
                }

                $this->progress = $progressType;
            } else {
                $this->progress = 'none';
            }
        }

        return $this->progress;
    }

    /**
     * @return ReporterInterface
     */
    public function getReporter()
    {
        if (null === $this->reporter) {
            $reporterFactory = ReporterFactory::create();
            $reporterFactory->registerBuiltInReporters();

            $format = $this->getFormat();

            try {
                $this->reporter = $reporterFactory->getReporter($format);
            } catch (\UnexpectedValueException $e) {
                $formats = $reporterFactory->getFormats();
                sort($formats);

                throw new InvalidConfigurationException(sprintf('The format "%s" is not defined, supported are %s.', $format, implode(', ', $formats)));
            }
        }

        return $this->reporter;
    }

    /**
     * @return bool
     */
    public function getRiskyAllowed()
    {
        if (null === $this->allowRisky) {
            if (null !== $this->options['allow-risky']) {
                $this->allowRisky = 'yes' === $this->options['allow-risky'];
            } else {
                $this->allowRisky = $this->getConfig()->getRiskyAllowed();
            }
        }

        return $this->allowRisky;
    }

    /**
     * Returns rules.
     *
     * @return array
     */
    public function getRules()
    {
        return $this->getRuleSet()->getRules();
    }

    /**
     * @return bool
     */
    public function getUsingCache()
    {
        if (null === $this->usingCache) {
            if (null === $this->options['using-cache']) {
                $this->usingCache = $this->getConfig()->getUsingCache();
            } else {
                $this->usingCache = 'yes' === $this->options['using-cache'];
            }
        }

        return $this->usingCache;
    }

    public function getFinder()
    {
        if (null === $this->finder) {
            $this->finder = $this->resolveFinder();
        }

        return $this->finder;
    }

    /**
     * Returns dry-run flag.
     *
     * @return bool
     */
    public function isDryRun()
    {
        if (null === $this->isDryRun) {
            if ($this->isStdIn()) {
                // Can't write to STDIN
                $this->isDryRun = true;
            } else {
                $this->isDryRun = $this->options['dry-run'];
            }
        }

        return $this->isDryRun;
    }

    public function shouldStopOnViolation()
    {
        return $this->options['stop-on-violation'];
    }

    /**
     * @return FixerFactory
     */
    private function createFixerFactory()
    {
        if (null === $this->fixerFactory) {
            $fixerFactory = new FixerFactory();
            $fixerFactory->registerCustomFixers($this->getConfig()->getCustomFixers());

            $this->fixerFactory = $fixerFactory;
        }

        return $this->fixerFactory;
    }

    /**
     * @return string
     */
    private function getFormat()
    {
        if (null === $this->format) {
            $this->format = null === $this->options['format']
                ? $this->getConfig()->getFormat()
                : $this->options['format'];
        }

        return $this->format;
    }

    private function getRuleSet()
    {
        if (null === $this->ruleSet) {
            $rules = $this->parseRules();
            $this->validateRules($rules);

            $this->ruleSet = new RuleSet($rules);
        }

        return $this->ruleSet;
    }

    /**
     * @return bool
     */
    private function isStdIn()
    {
        if (null === $this->isStdIn) {
            $this->isStdIn = 1 === count($this->options['path']) && '-' === $this->options['path'][0];
        }

        return $this->isStdIn;
    }

    /**
     * @param iterable $iterable
     *
     * @return \Traversable
     */
    private function iterableToTraversable($iterable)
    {
        return is_array($iterable) ? new \ArrayIterator($iterable) : $iterable;
    }

    /**
     * Compute rules.
     *
     * @return array
     */
    private function parseRules()
    {
        if (null === $this->options['rules']) {
            return $this->getConfig()->getRules();
        }

        $rules = trim($this->options['rules']);

        if ($rules[0] === '{') {
            $rules = json_decode($rules, true);
            if (JSON_ERROR_NONE !== json_last_error()) {
                throw new InvalidConfigurationException(sprintf('Invalid JSON rules input: %s.', json_last_error_msg()));
            }

            return $rules;
        }

        $rules = [];

        foreach (array_map('trim', explode(',', $this->options['rules'])) as $rule) {
            if ('' === $rule) {
                throw new InvalidConfigurationException('Empty rule name is not allowed.');
            }

            if ('-' === $rule[0]) {
                $rules[substr($rule, 1)] = false;
            } else {
                $rules[$rule] = true;
            }
        }

        return $rules;
    }

    /**
     * @param array $rules
     *
     * @throws InvalidConfigurationException
     */
    private function validateRules(array $rules)
    {
        /**
         * Create a ruleset that contains all configured rules, even when they originally have been disabled.
         *
         * @see RuleSet::resolveSet()
         */
        $ruleSet = RuleSet::create(array_map(function () {
            return true;
        }, $rules));

        /* @var string[] $availableFixers */
        $configuredFixers = array_keys($ruleSet->getRules());

        /* @var string[] $availableFixers */
        $availableFixers = array_map(function (FixerInterface $fixer) {
            return $fixer->getName();
        }, $this->createFixerFactory()->getFixers());

        $unknownFixers = array_diff(
            $configuredFixers,
            $availableFixers
        );

        if (count($unknownFixers)) {
            throw new InvalidConfigurationException(sprintf(
                'The rules contain unknown fixers (%s).',
                implode(', ', $unknownFixers)
            ));
        }
    }

    /**
     * Apply path on config instance.
     */
    private function resolveFinder()
    {
        if ($this->isStdIn()) {
            return new \ArrayIterator([new StdinFileInfo()]);
        }

        $modes = [self::PATH_MODE_OVERRIDE, self::PATH_MODE_INTERSECTION];

        if (!in_array(
            $this->options['path-mode'],
            $modes,
            true
        )) {
            throw new InvalidConfigurationException(sprintf(
                'The path-mode "%s" is not defined, supported are %s.',
                $this->options['path-mode'],
                implode(', ', $modes)
            ));
        }

        $isIntersectionPathMode = self::PATH_MODE_INTERSECTION === $this->options['path-mode'];

        $paths = array_filter(array_map(
            function ($path) {
                return realpath($path);
            },
            $this->getPath()
        ));

        if (!count($paths)) {
            if ($isIntersectionPathMode) {
                return new \ArrayIterator([]);
            }

            return $this->iterableToTraversable($this->getConfig()->getFinder());
        }

        $pathsByType = [
            'file' => [],
            'dir' => [],
        ];

        foreach ($paths as $path) {
            if (is_file($path)) {
                $pathsByType['file'][] = $path;
            } else {
                $pathsByType['dir'][] = $path.DIRECTORY_SEPARATOR;
            }
        }

        $nestedFinder = null;
        $currentFinder = $this->iterableToTraversable($this->getConfig()->getFinder());

        try {
            $nestedFinder = $currentFinder instanceof \IteratorAggregate ? $currentFinder->getIterator() : $currentFinder;
        } catch (\Exception $e) {
        }

        if ($isIntersectionPathMode) {
            if (null === $nestedFinder) {
                throw new InvalidConfigurationException(
                    'Cannot create intersection with not-fully defined Finder in configuration file.'
                );
            }

            return new \CallbackFilterIterator(
                $nestedFinder,
                function (\SplFileInfo $current) use ($pathsByType) {
                    $currentRealPath = $current->getRealPath();

                    if (in_array($currentRealPath, $pathsByType['file'], true)) {
                        return true;
                    }

                    foreach ($pathsByType['dir'] as $path) {
                        if (0 === strpos($currentRealPath, $path)) {
                            return true;
                        }
                    }

                    return false;
                }
            );
        }

        if ($currentFinder instanceof SymfonyFinder && null === $nestedFinder) {
            // finder from configuration Symfony finder and it is not fully defined, we may fulfill it
            return $currentFinder->in($pathsByType['dir'])->append($pathsByType['file']);
        }

        return Finder::create()->in($pathsByType['dir'])->append($pathsByType['file']);
    }

    /**
     * Set option that will be resolved.
     *
     * @param string $name
     * @param mixed  $value
     */
    private function setOption($name, $value)
    {
        if (!array_key_exists($name, $this->options)) {
            throw new InvalidConfigurationException(sprintf('Unknown option name: "%s".', $name));
        }

        $this->options[$name] = $value;
    }
}
