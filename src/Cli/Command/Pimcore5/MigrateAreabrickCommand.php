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

use Doctrine\Common\Util\Inflector;
use gossi\codegen\generator\CodeGenerator;
use gossi\codegen\model\PhpClass;
use gossi\codegen\model\PhpMethod;
use Pimcore\Cli\Command\AbstractCommand;
use Pimcore\Cli\Filesystem\DryRunFilesystem;
use Pimcore\Cli\Traits\DryRunCommandTrait;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

class MigrateAreabrickCommand extends AbstractCommand
{
    use DryRunCommandTrait;

    /**
     * @var Filesystem
     */
    private $fs;

    /**
     * @var array
     */
    private $validTemplateLocations = ['bundle', 'global'];

    /**
     * @var array
     */
    private $supportedXmlProperties = [
        'id',
        'name',
        'icon',
        'description',
        'version'
    ];

    /**
     * @var bool
     */
    private $hasWarnings = false;

    protected function configure()
    {
        $this
            ->setName('pimcore5:migrate:areabrick')
            ->setDescription('Migrate an areabrick to Pimcore 5 format')
            ->addArgument(
                'xml-file', InputArgument::REQUIRED,
                'Path to XML file'
            )
            ->addArgument(
                'app-dir', InputArgument::REQUIRED,
                'app/ directory'
            )
            ->addArgument(
                'src-dir', InputArgument::REQUIRED,
                'src/ directory'
            )
            ->addOption(
                'bundle', 'b',
                InputOption::VALUE_REQUIRED,
                'Bundle namespace to use (you can use / instead of \\)',
                'AppBundle'
            )
            ->addOption(
                'template-location', 't',
                InputOption::VALUE_REQUIRED,
                sprintf('Template location (can be one of: %s)', implode(', ', $this->validTemplateLocations)),
                'global'
            );

        $this->configureDryRunOption();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->hasWarnings = false;

        $io = $this->io;
        $fs = $this->fs = new DryRunFilesystem($io, $this->isDryRun());

        $xmlFile = $input->getArgument('xml-file');
        if (!$fs->exists($xmlFile) || !is_file($xmlFile)) {
            throw new \InvalidArgumentException('Invalid XML file: file does not exist');
        }

        $appDir = $input->getArgument('app-dir');
        if (!$fs->exists($appDir) || !is_dir($appDir)) {
            throw new \InvalidArgumentException('Given app directory does not exist');
        }

        $srcDir = $input->getArgument('src-dir');
        if (!$fs->exists($srcDir) || !is_dir($srcDir)) {
            throw new \InvalidArgumentException('Given src directory does not exist');
        }

        $appDir = rtrim(realpath($appDir), DIRECTORY_SEPARATOR);
        $srcDir = rtrim(realpath($srcDir), DIRECTORY_SEPARATOR);

        $fileInfo = new \SplFileInfo($xmlFile);
        if ('area.xml' !== $fileInfo->getFilename()) {
            throw new \InvalidArgumentException('Invalid XML file: file must be named area.xml');
        }

        $templateLocation = $input->getOption('template-location');
        if (!in_array($templateLocation, $this->validTemplateLocations)) {
            throw new \InvalidArgumentException('Invalid template location. Must be one of ' . implode(', ', $this->validTemplateLocations));
        }

        $bundle = $input->getOption('bundle');
        $bundle = str_replace('/', '\\', $bundle);
        $bundle = rtrim($bundle, '\\');

        if (!preg_match('/Bundle$/', $bundle)) {
            throw new \InvalidArgumentException('Invalid bundle name. Must end in "Bundle"');
        }

        $bundleDir = $srcDir . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, explode('\\', $bundle));

        $xml  = $this->readXml($fileInfo->getRealPath());
        $info = [];

        foreach ($xml as $key => $value) {
            if (in_array($key, $this->supportedXmlProperties)) {
                $info[$key] = $value;
            } else {
                $this->io->note(sprintf('Unsupported XML property %s - please migrate the property manually!', $key));

                $this->hasWarnings = true;
            }
        }

        $files = $this->findAreaFiles(dirname($fileInfo->getRealPath()));
        $class = $this->buildClass($info, $bundle, $templateLocation);

        $brickId = $this->toBrickId($class->getName());

        $this->writeClassFile($class, $bundleDir);
        $this->migrateAreaFiles($brickId, $files, $bundleDir, $appDir, $templateLocation);

        if ($this->hasWarnings) {
            $this->io->block('Completed with warnings (see above)', 'WARNING', 'fg=black;bg=yellow', ' ', true);
        } else {
            $this->io->success('All done!');
        }
    }

    private function buildClass(array $info, string $bundle, string $templateLocation): PhpClass
    {
        if (!isset($info['id'])) {
            throw new \RuntimeException('Missing ID property');
        }

        $className = $this->toClassName($info['id']);
        if ($newBrickId = $this->toBrickId($className) !== $info['id']) {
            $this->io->note(sprintf('
                The generated brick ID will change from %s to %s. Please register the brick manually on the container (see documentation) to keep using %1$s',
                    $info['id'], $newBrickId)
            );

            $this->hasWarnings = true;
        }

        $class = new PhpClass($className);
        $class
            ->addUseStatement('\\Pimcore\\Extension\\Document\\Areabrick\\AbstractTemplateAreabrick')
            ->setParentClassName('AbstractTemplateAreabrick')
            ->setDocblock('')
            ->setQualifiedName($bundle . '\\Document\\Areabrick\\' . $className);

        if ('global' === $templateLocation) {
            $class->setMethod(
                PhpMethod::create('getTemplateLocation')
                    ->setBody(sprintf('return static::TEMPLATE_LOCATION_%s;', strtoupper($templateLocation)))
                    ->setDocblock('@inheritdoc')
            );
        }

        foreach (['name', 'description', 'version', 'icon'] as $property) {
            if (isset($info[$property]) && !empty($info[$property])) {
                $class->setMethod(
                    PhpMethod::create('get' . ucfirst($property))
                        ->setBody(sprintf("return '%s';", $info[$property]))
                        ->setDocblock('@inheritdoc')
                );
            }
        }

        return $class;
    }

    private function writeClassFile(PhpClass $class, string $bundleDir)
    {
        $classDir  = $bundleDir . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, ['Document', 'Areabrick']);
        $classFile = $classDir . DIRECTORY_SEPARATOR . $class->getName() . '.php';

        $generator = new CodeGenerator();

        $code      = $generator->generate($class);
        $code      = str_replace("\t", '    ', $code);
        $code      = $code . PHP_EOL;
        $code      = '<?php' . PHP_EOL . PHP_EOL . $code;

        $this->io->writeln($this->dryRunMessage(sprintf('Creating class %s in %s', $class->getName(), $classFile)));
        $this->fs->dumpFile($classFile, $code);
    }

    private function findAreaFiles($path): array
    {
        $files = new Finder();
        $files
            ->files()
            ->in($path)
            ->notName('area.xml');

        $supported        = ['view.php', 'edit.php'];
        $publicExtensions = ['css', 'png', 'jpg', 'gif'];

        $result = [
            'public' => [],
            'views'  => []
        ];

        foreach ($files as $file) {
            if (in_array($file->getExtension(), $publicExtensions)) {
                $result['public'][] = $file;
            } elseif (in_array($file->getFilename(), $supported)) {
                $result['views'][] = $file;
            } else {
                $this->io->note(sprintf('File %s is not supported. Please migrate file manually.'));

                $this->hasWarnings = true;
            }
        }

        return $result;
    }

    private function migrateAreaFiles(string $brickId, array $files, string $bundleDir, string $appDir, string $templateLocation)
    {
        /** @var \SplFileInfo[] $typeFiles */
        foreach ($files as $type => $typeFiles) {
            $typePath = null;
            if ($type === 'public') {
                $typePath = $bundleDir . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, ['Resources', 'public', 'areas', $brickId]);
            } elseif ($type === 'views') {
                $typePath = implode(DIRECTORY_SEPARATOR, ['Resources', 'views', 'Areas', $brickId]);

                if ($templateLocation === 'global') {
                    $typePath = $appDir . DIRECTORY_SEPARATOR . $typePath;
                } elseif ($templateLocation === 'bundle') {
                    $typePath = $bundleDir . DIRECTORY_SEPARATOR . $typePath;
                }
            }

            if (null === $typePath) {
                throw new \RuntimeException('Could not resolve type path for type ' . $type);
            }

            foreach ($typeFiles as $typeFile) {
                $filename = $typeFile->getFilename();
                if ('views' === $type) {
                    $filename = preg_replace('/\.php$/', '.html.php', $filename);
                }

                $sourceFile = $typeFile->getRealPath();
                $targetFile = $typePath . DIRECTORY_SEPARATOR . $filename;

                $this->fs->copy($sourceFile, $targetFile);
            }
        }
    }


    private function readXml(string $xmlFile): array
    {
        $xml  = simplexml_load_file($xmlFile);
        $data = json_decode(json_encode($xml), true);

        if (!$data) {
            throw new \RuntimeException('Failed to parse XML file');
        }

        return $data;
    }

    /**
     * gallery-teaser-row -> GalleryTeaserRow
     *
     * @param string $brickId
     *
     * @return string
     */
    private function toClassName(string $brickId): string
    {
        return Inflector::classify($brickId);
    }

    /**
     * GalleryTeaserRow -> gallery-teaser-row
     *
     * @param string $className
     *
     * @return string
     */
    private function toBrickId(string $className): string
    {
        $id = Inflector::tableize($className);
        $id = str_replace('_', '-', $id);

        return $id;
    }
}
