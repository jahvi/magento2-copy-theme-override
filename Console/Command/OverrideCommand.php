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

    protected $_moduleDirResolver;
    protected $_templateResolver;
    protected $_design;
    protected $_componentRegistrar;
    protected $_rulePool;
    protected $_directoryList;
    protected $_writeFactory;

    public function __construct(
        ReverseResolver $dirResolver,
        Resolver $templateResolver,
        State $state,
        DesignInterface $design,
        ComponentRegistrarInterface $componentRegistrar,
        RulePool $rulePool,
        DirectoryList $directoryList,
        WriteFactory $writeFactory
    )
    {
        $this->_moduleDirResolver = $dirResolver;
        $this->_templateResolver = $templateResolver;
        $this->_design = $design;
        $this->_componentRegistrar = $componentRegistrar;
        $this->_rulePool = $rulePool;
        $this->_directoryList = $directoryList;
        $this->_writeFactory = $writeFactory;

        $state->setAreaCode('frontend');

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
        $originalFile = $input->getArgument(self::FILE_ARGUMENT);
        $originalFile = $this->normalizeFileName($originalFile);

        $moduleName = $this->getModuleFromFile($originalFile);

        $this->_design->setDefaultDesignTheme();
        $theme = $this->_design->getDesignTheme();

        $themeDir = $this->_componentRegistrar->getPath(
            ComponentRegistrar::THEME,
            $theme->getFullPath()
        );

        $targetFile = $this->getTargetFileInfo($originalFile, $moduleName, $themeDir);

        $sourceWriter      = $this->_writeFactory->create(dirname($originalFile));
        $destinationWriter = $this->_writeFactory->create($targetFile['folder']);

        $sourceWriter->copyFile(basename($originalFile), $targetFile['name'], $destinationWriter);

        $output->writeln("<info>File copied to: {$targetFile['folder']}/{$targetFile['name']}</info>");
    }

    protected function normalizeFileName($filePath)
    {
        $rootDir = $this->_directoryList->getRoot();

        $appName   = basename($rootDir);
        $parentDir = dirname($rootDir);
        $filePath  = explode("/{$appName}/", $filePath)[1];

        return "{$parentDir}/{$appName}/{$filePath}";
    }

    protected function getModuleFromFile($file)
    {
        if ($moduleName = $this->_moduleDirResolver->getModuleName($file)) {
            return $moduleName;
        }

        if (preg_match('/\/[A-Z]\w+_[A-Z]\w+\//', $file, $matches)) {
            $moduleName = str_replace('/', '', $matches[0]);
            return $moduleName;
        }

        return '';
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