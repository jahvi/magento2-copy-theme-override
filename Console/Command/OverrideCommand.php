<?php
namespace Jahvi\CopyThemeOverride\Console\Command;

use Magento\Framework\App\State;
use Magento\Framework\View\DesignInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Magento\Framework\Module\Dir\ReverseResolver;
use Symfony\Component\Console\Input\InputArgument;
use Magento\Framework\App\Filesystem\DirectoryList;
use Symfony\Component\Console\Input\InputInterface;
use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\View\Design\Fallback\RulePool;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Framework\Filesystem\Directory\WriteFactory;
use Magento\Framework\View\Element\Template\File\Resolver;
use Magento\Framework\Component\ComponentRegistrarInterface;

class OverrideCommand extends Command
{
    const FILE_ARGUMENT = 'file';

    protected $moduleDirResolver;
    protected $templateResolver;
    protected $design;
    protected $componentRegistrar;
    protected $rulePool;
    protected $directoryList;
    protected $writeFactory;

    public function __construct(
        ReverseResolver $dirResolver,
        Resolver $templateResolver,
        State $state,
        DesignInterface $design,
        ComponentRegistrarInterface $componentRegistrar,
        RulePool $rulePool,
        DirectoryList $directoryList,
        WriteFactory $writeFactory
    ) {
        $this->moduleDirResolver = $dirResolver;
        $this->templateResolver = $templateResolver;
        $this->design = $design;
        $this->componentRegistrar = $componentRegistrar;
        $this->rulePool = $rulePool;
        $this->directoryList = $directoryList;
        $this->writeFactory = $writeFactory;
        $this->state = $state;

        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('dev:copy-theme-override')
            ->setDescription('Copy file into current active theme for overriding')
            ->setDefinition([
                new InputArgument(
                    self::FILE_ARGUMENT,
                    InputArgument::REQUIRED,
                    'File'
                ),
            ]);

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->state->setAreaCode(\Magento\Framework\App\Area::AREA_FRONTEND);

        $originalFile = $input->getArgument(self::FILE_ARGUMENT);
        $originalFile = $this->normalizeFileName($originalFile);

        $moduleName = $this->getModuleFromFile($originalFile);

        $this->design->setDefaultDesignTheme();
        $theme = $this->design->getDesignTheme();

        $themeDir = $this->componentRegistrar->getPath(
            ComponentRegistrar::THEME,
            $theme->getFullPath()
        );

        $targetFile        = $this->getTargetFileInfo($originalFile, $moduleName, $themeDir);
        $sourceWriter      = $this->writeFactory->create(dirname($originalFile));
        $destinationWriter = $this->writeFactory->create($targetFile['folder']);
        $targetFilePath    = "{$targetFile['folder']}/{$targetFile['name']}";

        if (file_exists($targetFilePath)) {
            return $output->writeln("<error>File already exists in: $targetFilePath</error>");
        }

        $sourceWriter->copyFile(basename($originalFile), $targetFile['name'], $destinationWriter);
        return $output->writeln("<info>File copied to: $targetFilePath</info>");
    }

    protected function normalizeFileName($filePath)
    {
        $rootDir = $this->directoryList->getRoot();

        $appName   = basename($rootDir);
        $parentDir = dirname($rootDir);
        $filePath  = explode("/{$appName}/", $filePath)[1];

        return "{$parentDir}/{$appName}/{$filePath}";
    }

    protected function getModuleFromFile($file)
    {
        if ($moduleName = $this->moduleDirResolver->getModuleName($file)) {
            return $moduleName;
        }

        if (preg_match('/\/[A-Z]\w+_[A-Z]\w+\//', $file, $matches)) {
            $moduleName = str_replace('/', '', $matches[0]);
            return $moduleName;
        }

        return false;
    }

    protected function getTargetFileInfo($file, $moduleName, $themeDir)
    {
        $data = [];

        if (strpos($file, '/templates/') !== false) {
            $data['name']   = explode('/templates/', $file)[1];
            $data['folder'] = "$themeDir/$moduleName/templates";
        } elseif (strpos($file, '/web/') !== false) {
            $targetDirName = $moduleName ? "$moduleName/web" : 'web';

            $data['name']   = explode('/web/', $file)[1];
            $data['folder'] = "$themeDir/$targetDirName";
        }

        return $data;
    }
}
