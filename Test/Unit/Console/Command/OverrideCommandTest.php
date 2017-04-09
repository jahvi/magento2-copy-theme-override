<?php
namespace Jahvi\CopyThemeOverride\Test\Unit\Console\Command;

use Magento\Framework\App\State;
use Magento\Framework\View\DesignInterface;
use Magento\Framework\Module\Dir\ReverseResolver;
use Symfony\Component\Console\Tester\CommandTester;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\View\Design\Fallback\RulePool;
use Magento\Framework\Filesystem\Directory\WriteFactory;
use Magento\Framework\View\Element\Template\File\Resolver;
use Jahvi\CopyThemeOverride\Console\Command\OverrideCommand;
use Magento\Framework\Component\ComponentRegistrarInterface;

class OverrideCommandTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var ReverseResolver | \PHPUnit_Framework_MockObject_MockObject
     */
    protected $dirResolver;

    /**
     * @var Resolver | \PHPUnit_Framework_MockObject_MockObject
     */
    protected $templateResolver;

    /**
     * @var State | \PHPUnit_Framework_MockObject_MockObject
     */
    protected $appState;

    /**
     * @var DesignInterface | \PHPUnit_Framework_MockObject_MockObject
     */
    protected $design;

    /**
     * @var ComponentRegistrarInterface | \PHPUnit_Framework_MockObject_MockObject
     */
    protected $componentRegistrar;

    /**
     * @var RulePool | \PHPUnit_Framework_MockObject_MockObject
     */
    protected $rulePool;

    /**
     * @var DirectoryList | \PHPUnit_Framework_MockObject_MockObject
     */
    protected $directoryList;

    /**
     * @var WriteFactory | \PHPUnit_Framework_MockObject_MockObject
     */
    protected $writeFactory;

    /**
     * @var OverrideCommand
     */
    protected $command;

    protected function setUp()
    {
        $this->dirResolver = $this->getMockBuilder('Magento\Framework\Module\Dir\ReverseResolver')
            ->disableOriginalConstructor()
            ->getMock();

        $this->templateResolver = $this->getMockBuilder('Magento\Framework\View\Element\Template\File\Resolver')
            ->disableOriginalConstructor()
            ->getMock();

        $this->appState = $this->getMockBuilder('Magento\Framework\App\State')
            ->disableOriginalConstructor()
            ->getMock();

        $this->design = $this->getMockBuilder('Magento\Framework\View\DesignInterface')
            ->getMockForAbstractClass();

        $this->componentRegistrar = $this->getMockBuilder('Magento\Framework\Component\ComponentRegistrarInterface')
            ->getMockForAbstractClass();

        $this->rulePool = $this->getMockBuilder('Magento\Framework\View\Design\Fallback\RulePool')
            ->disableOriginalConstructor()
            ->getMock();

        $this->directoryList = $this->getMockBuilder('Magento\Framework\App\Filesystem\DirectoryList')
            ->disableOriginalConstructor()
            ->getMock();

        $this->writeFactory = $this->getMockBuilder('Magento\Framework\Filesystem\Directory\WriteFactory')
            ->disableOriginalConstructor()
            ->getMock();

        $this->command = new OverrideCommand(
            $this->dirResolver,
            $this->templateResolver,
            $this->appState,
            $this->design,
            $this->componentRegistrar,
            $this->rulePool,
            $this->directoryList,
            $this->writeFactory
        );
    }

    public function testExecute()
    {
        $fileName = '/path/to/local/magento/Module_Test/templates/test.php';

        $theme = $this->getMockBuilder('Magento\Theme\Model\Theme')
            ->disableOriginalConstructor()
            ->setMethods(['getFullPath'])
            ->getMock();

        $writeSrc = $this->getMockBuilder('Magento\Framework\Filesystem\File\Write')
            ->disableOriginalConstructor()
            ->setMethods(['copyFile'])
            ->getMock();

        $writeDest = $this->getMockBuilder('Magento\Framework\Filesystem\File\Write')
            ->disableOriginalConstructor()
            ->getMock();

        $this->directoryList->expects($this->once())
            ->method('getRoot')
            ->willReturn('/var/www/magento');

        $this->design->expects($this->once())
            ->method('getDesignTheme')
            ->willReturn($theme);

        $theme->expects($this->any())
            ->method('getFullPath')
            ->willReturn('frontend/Foo/bar');

        $this->componentRegistrar->expects($this->once())
            ->method('getPath')
            ->with('theme', 'frontend/Foo/bar')
            ->willReturn('/path/to/local/magento/frontend/Foo/bar');

        $this->writeFactory->expects($this->at(0))
            ->method('create')
            ->with('/var/www/magento/Module_Test/templates')
            ->willReturn($writeSrc);

        $this->writeFactory->expects($this->at(1))
            ->method('create')
            ->with('/path/to/local/magento/frontend/Foo/bar/Module_Test/templates')
            ->willReturn($writeDest);

        $writeSrc->expects($this->once())
            ->method('copyFile')
            ->with(
                'test.php',
                'test.php',
                $writeDest
            )
            ->willReturn(true);

        $commandTester = new CommandTester($this->command);
        $commandTester->execute([OverrideCommand::FILE_ARGUMENT => $fileName]);

        $this->assertContains(
            'File copied to: /path/to/local/magento/frontend/Foo/bar/Module_Test/templates/test.php',
            $commandTester->getDisplay()
        );
    }

    public function testNormalizeFileName()
    {
        $this->directoryList->expects($this->once())
            ->method('getRoot')
            ->willReturn('/var/www/magento');

        $fileName = $this->callProtectedMethod(
            $this->command,
            'normalizeFileName',
            ['/foo/bar/magento/test.php']
        );

        $this->assertEquals('/var/www/magento/test.php', $fileName);
    }

    public function testGetModuleFromExtension()
    {
        $fileName = '/var/www/magento/module-test/test.php';

        $this->dirResolver->expects($this->once())
            ->method('getModuleName')
            ->with($fileName)
            ->willReturn('Module_Test');

        $moduleName = $this->callProtectedMethod(
            $this->command,
            'getModuleFromFile',
            [$fileName]
        );

        $this->assertEquals('Module_Test', $moduleName);
    }

    public function testGetModuleFromTheme()
    {
        $fileName = '/var/www/magento/Module_Test/test.php';

        $this->dirResolver->expects($this->once())
            ->method('getModuleName')
            ->with($fileName)
            ->willReturn(null);

        $moduleName = $this->callProtectedMethod(
            $this->command,
            'getModuleFromFile',
            [$fileName]
        );

        $this->assertEquals('Module_Test', $moduleName);
    }

    public function testGetTemplateTargetFileInfo()
    {
        $fileName   = '/var/www/magento/Module_Test/templates/test.php';
        $themeDir   = '/var/www/magento/app/design/frontend/Foo/bar';
        $moduleName = 'Module_Test';

        $fileInfo = $this->callProtectedMethod(
            $this->command,
            'getTargetFileInfo',
            [$fileName, $moduleName, $themeDir]
        );

        $expectedFileInfo = [
            'name'   => 'test.php',
            'folder' => '/var/www/magento/app/design/frontend/Foo/bar/Module_Test/templates',
        ];

        $this->assertEquals($expectedFileInfo, $fileInfo);
    }

    public function testGetWebTargetFileInfo()
    {
        $fileName   = '/var/www/magento/Module_Test/view/frontend/web/css/source/test.less';
        $themeDir   = '/var/www/magento/app/design/frontend/Foo/bar';
        $moduleName = 'Module_Test';

        $fileInfo = $this->callProtectedMethod(
            $this->command,
            'getTargetFileInfo',
            [$fileName, $moduleName, $themeDir]
        );

        $expectedFileInfo = [
            'name'   => 'css/source/test.less',
            'folder' => '/var/www/magento/app/design/frontend/Foo/bar/Module_Test/web',
        ];

        $this->assertEquals($expectedFileInfo, $fileInfo);
    }

    public function testGetThemeWebTargetFileInfo()
    {
        $fileName   = '/var/www/magento/theme-frontend-test/web/css/source/test.less';
        $themeDir   = '/var/www/magento/app/design/frontend/Foo/bar';
        $moduleName = false;

        $fileInfo = $this->callProtectedMethod(
            $this->command,
            'getTargetFileInfo',
            [$fileName, $moduleName, $themeDir]
        );

        $expectedFileInfo = [
            'name'   => 'css/source/test.less',
            'folder' => '/var/www/magento/app/design/frontend/Foo/bar/web',
        ];

        $this->assertEquals($expectedFileInfo, $fileInfo);
    }

    private function callProtectedMethod($object, $method, array $args = [])
    {
        $class = new \ReflectionClass(get_class($object));
        $method = $class->getMethod($method);
        $method->setAccessible(true);
        return $method->invokeArgs($object, $args);
    }
}
