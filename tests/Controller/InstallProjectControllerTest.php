<?php

/**
 * This file is part of tenside/core-bundle.
 *
 * (c) Christian Schiffler <c.schiffler@cyberspectrum.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * This project is provided in good faith and hope to be usable by anyone.
 *
 * @package    tenside/core-bundle
 * @author     Christian Schiffler <c.schiffler@cyberspectrum.de>
 * @copyright  2015 Christian Schiffler <c.schiffler@cyberspectrum.de>
 * @license    https://github.com/tenside/core-bundle/blob/master/LICENSE MIT
 * @link       https://github.com/tenside/core-bundle
 * @filesource
 */

namespace Tenside\CoreBundle\Test\Controller;

use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Tenside\Core\Task\Composer\InstallTask;
use Tenside\Core\Util\HomePathDeterminator;
use Tenside\Core\Util\JsonArray;
use Tenside\Core\Util\JsonFile;
use Tenside\CoreBundle\Controller\InstallProjectController;
use Tenside\Core\Config\TensideJsonConfig;
use Tenside\CoreBundle\Util\InstallationStatusDeterminator;

/**
 * Test the create-project command of composers
 */
class InstallProjectControllerTest extends TestCase
{
    /**
     * Tests the create project when already installed.
     *
     * @return void
     *
     * @expectedException \Symfony\Component\HttpKernel\Exception\NotAcceptableHttpException
     */
    public function testAlreadyConfiguredException()
    {
        $container = new Container();

        $status = $this
            ->getMockBuilder(InstallationStatusDeterminator::class)
            ->setMethods(['isTensideConfigured'])
            ->disableOriginalConstructor()
            ->getMock();
        $status->method('isTensideConfigured')->willReturn(true);
        $container->set('tenside.status', $status);

        $controller = new InstallProjectController();
        $controller->setContainer($container);

        $controller->configureAction(new Request());
    }

    /**
     * Test that the configure action works.
     *
     * @return void
     */
    public function testConfigureAction()
    {
        $container = new Container();

        $status = $this
            ->getMockBuilder(InstallationStatusDeterminator::class)
            ->setMethods(['isTensideConfigured'])
            ->disableOriginalConstructor()
            ->getMock();
        $status->method('isTensideConfigured')->willReturn(false);
        $container->set('tenside.status', $status);

        $config = new TensideJsonConfig(new JsonFile($this->getTempDir() . DIRECTORY_SEPARATOR . 'tenside.json'));
        $container->set('tenside.config', $config);

        $encoder = $this
            ->getMockBuilder(UserPasswordEncoderInterface::class)
            ->setMethods(['encodePassword'])
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
        $encoder->method('encodePassword')->willReturnCallback(function () {
            return 'encoded-' . func_get_arg(1);
        });
        $container->set('security.password_encoder', $encoder);

        $userProvider = $this
            ->getMockBuilder(UserProviderInterface::class)
            ->setMethods(['addUser', 'refreshUser'])
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
        $userProvider->expects($this->once())->method('addUser')->willReturn($userProvider);
        $userProvider->expects($this->once())->method('refreshUser')->willReturnArgument(0);
        $container->set('tenside.user_provider', $userProvider);

        $authenticator = $this
            ->getMockBuilder('stdClass')
            ->setMethods(['getTokenForData'])
            ->getMock();
        $authenticator->expects($this->once())->method('getTokenForData')->willReturn('token-value');

        $container->set('tenside.jwt_authenticator', $authenticator);

        $controller = new InstallProjectController();
        $controller->setContainer($container);

        $request = Request::create(
            '/v1/install/configure',
            'GET',
            [],
            [],
            [],
            [],
            json_encode(
                [
                    'credentials' => [
                        'secret'   => 's3cret',
                        'username' => 'john.doe',
                        'password' => 'p4ssword'
                    ],
                    'configuration' => [
                        'php_cli'           => '/path/to/php',
                        'php_cli_arguments' => ['arg1', 'arg2']
                    ]
                ]
            )
        );

        $response = $controller->configureAction($request);
        $data     = json_decode($response->getContent(), true);
        $this->assertEquals('token-value', $data['token']);
        $this->assertEquals('OK', $data['status']);

        // Check that the config values got set.
        $this->assertEquals('s3cret', $config->get('secret'));
        $this->assertEquals('/path/to/php', $config->get('php_cli'));
        $this->assertEquals(['arg1', 'arg2'], $config->get('php_cli_arguments'));
    }

    /**
     * Test that the configure action works.
     *
     * @return void
     */
    public function testConfigureActionGeneratesSecret()
    {
        $container = new Container();

        $status = $this
            ->getMockBuilder(InstallationStatusDeterminator::class)
            ->setMethods(['isTensideConfigured'])
            ->disableOriginalConstructor()
            ->getMock();
        $status->method('isTensideConfigured')->willReturn(false);
        $container->set('tenside.status', $status);

        $config = new TensideJsonConfig(new JsonFile($this->getTempDir() . DIRECTORY_SEPARATOR . 'tenside.json'));
        $container->set('tenside.config', $config);

        $encoder = $this
            ->getMockBuilder(UserPasswordEncoderInterface::class)
            ->setMethods(['encodePassword'])
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
        $encoder->method('encodePassword')->willReturnCallback(function () {
            return 'encoded-' . func_get_arg(1);
        });
        $container->set('security.password_encoder', $encoder);

        $userProvider = $this
            ->getMockBuilder(UserProviderInterface::class)
            ->setMethods(['addUser', 'refreshUser'])
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
        $userProvider->expects($this->once())->method('addUser')->willReturn($userProvider);
        $userProvider->expects($this->once())->method('refreshUser')->willReturnArgument(0);
        $container->set('tenside.user_provider', $userProvider);

        $authenticator = $this
            ->getMockBuilder('stdClass')
            ->setMethods(['getTokenForData'])
            ->getMock();
        $authenticator->expects($this->once())->method('getTokenForData')->willReturn('token-value');

        $container->set('tenside.jwt_authenticator', $authenticator);

        $controller = new InstallProjectController();
        $controller->setContainer($container);

        $request = Request::create(
            '/v1/install/configure',
            'GET',
            [],
            [],
            [],
            [],
            json_encode(
                [
                    'credentials' => [
                        'username' => 'john.doe',
                        'password' => 'p4ssword'
                    ],
                    'configuration' => [
                        'php_cli'           => '/path/to/php',
                        'php_cli_arguments' => ['arg1', 'arg2']
                    ]
                ]
            )
        );

        $response = $controller->configureAction($request);
        $data     = json_decode($response->getContent(), true);
        $this->assertEquals('token-value', $data['token']);
        $this->assertEquals('OK', $data['status']);

        // Check that the config values got set.
        $this->assertNotEmpty($config->get('secret'));
    }

    /**
     * Tests the create project when already installed.
     *
     * @return void
     *
     * @expectedException \Symfony\Component\HttpKernel\Exception\NotAcceptableHttpException
     */
    public function testAlreadyInstalledException()
    {
        $this->provideFixture('composer.json');
        $this->provideFixture('tenside.json', 'tenside' . DIRECTORY_SEPARATOR . 'tenside.json');
        mkdir($this->getTempDir() . DIRECTORY_SEPARATOR . 'vendor');
        $controller = new InstallProjectController();
        $controller->setContainer($this->createDefaultContainer());

        $controller->createProjectAction(new Request());
    }

    /**
     * Tests the create project when not yet configured.
     *
     * @return void
     *
     * @expectedException \Symfony\Component\HttpKernel\Exception\NotAcceptableHttpException
     */
    public function testNotYetConfiguredException()
    {
        $controller = new InstallProjectController();
        $controller->setContainer($this->createDefaultContainer());

        $controller->createProjectAction(new Request());
    }

    /**
     * Tests the create project action.
     *
     * @return void
     */
    public function testInstalledProject()
    {
        $container = new Container();

        $status = $this
            ->getMockBuilder(InstallationStatusDeterminator::class)
            ->setMethods(['isTensideConfigured', 'isProjectPresent', 'isProjectInstalled'])
            ->disableOriginalConstructor()
            ->getMock();
        $status->method('isTensideConfigured')->willReturn(true);
        $status->method('isProjectPresent')->willReturn(false);
        $status->method('isProjectInstalled')->willReturn(false);
        $container->set('tenside.status', $status);

        $taskFile = null;

        $taskList = $this
            ->getMockBuilder('stdClass')
            ->setMethods(['queue'])
            ->getMock();
        $taskList->expects($this->once())->method('queue')->willReturnCallback(function () use (&$taskFile) {
            $taskFile = func_get_arg(1);
            return '$taskId$';
        });
        $container->set('tenside.tasks', $taskList);

        $home = $this
            ->getMockBuilder(HomePathDeterminator::class)
            ->setMethods(['homeDir'])
            ->getMock();
        $home->method('homeDir')->willReturn($this->getTempDir());
        $container->set('tenside.home', $home);

        $controller = $this->getMock(
            InstallProjectController::class,
            ['generateUrl']
        );
        $controller->method('generateUrl')->willReturn('http://url/to/task');

        /** @var $controller InstallProjectController */

        $controller->setContainer($container);

        $request = new Request([], [], [], [], [], [], json_encode(['project' =>
            [
                'name'      => 'contao/standard-edition',
                'version'   => '4.0.0',
            ]
        ]));

        $response = $controller->createProjectAction($request);
        $data     = json_decode($response->getContent(), true);

        $this->assertEquals('http://url/to/task', $response->headers->get('Location'));
        $this->assertEquals('$taskId$', $data['task']);

        $this->assertInstanceOf(JsonArray::class, $taskFile);
        $this->assertEquals($this->getTempDir(), $taskFile->get(InstallTask::SETTING_DESTINATION_DIR));
        $this->assertEquals('contao/standard-edition', $taskFile->get(InstallTask::SETTING_PACKAGE));
        $this->assertEquals('4.0.0', $taskFile->get(InstallTask::SETTING_VERSION));
    }

    /**
     * Test that the self test forwards to the self test controller.
     *
     * @return void
     */
    public function testGetSelfTestAction()
    {
        $controller = $this->getMock(
            InstallProjectController::class,
            ['forward']
        );
        $controller
            ->expects($this->once())
            ->method('forward')
            ->with('TensideCoreBundle:SelfTest:getAllTests');

        $container = new Container();

        $status = $this
            ->getMockBuilder(InstallationStatusDeterminator::class)
            ->setMethods(['isTensideConfigured', 'isProjectPresent', 'isProjectInstalled'])
            ->disableOriginalConstructor()
            ->getMock();
        $status->method('isTensideConfigured')->willReturn(false);
        $status->method('isProjectPresent')->willReturn(false);
        $status->method('isProjectInstalled')->willReturn(false);
        $container->set('tenside.status', $status);

        /** @var $controller InstallProjectController */
        $controller->setContainer($container);

        $controller->getSelfTestAction();
    }

    /**
     * Test that the self test does not forward to the self test controller when the installation is complete.
     *
     * @return void
     *
     * @expectedException \Symfony\Component\HttpKernel\Exception\NotAcceptableHttpException
     */
    public function testGetSelfTestActionBailsOnCompleteInstallation()
    {
        $controller = $this->getMock(
            InstallProjectController::class,
            ['forward']
        );

        $container = new Container();

        $status = $this
            ->getMockBuilder(InstallationStatusDeterminator::class)
            ->setMethods(['isTensideConfigured', 'isProjectPresent', 'isProjectInstalled'])
            ->disableOriginalConstructor()
            ->getMock();
        $status->method('isTensideConfigured')->willReturn(true);
        $status->method('isProjectPresent')->willReturn(true);
        $status->method('isProjectInstalled')->willReturn(true);
        $container->set('tenside.status', $status);

        $home = $this
            ->getMockBuilder(HomePathDeterminator::class)
            ->setMethods(['homeDir'])
            ->getMock();
        $home->method('homeDir')->willReturn($this->getTempDir());
        $container->set('tenside.home', $home);

        /** @var $controller InstallProjectController */
        $controller->setContainer($container);

        $controller->getSelfTestAction();
    }

    /**
     * Test that the auto config forwards to the self test controller.
     *
     * @return void
     */
    public function testAutoConfigAction()
    {
        $controller = $this->getMock(
            InstallProjectController::class,
            ['forward']
        );
        $controller
            ->expects($this->once())
            ->method('forward')
            ->with('TensideCoreBundle:SelfTest:getAutoConfig');

        $container = new Container();

        $status = $this
            ->getMockBuilder(InstallationStatusDeterminator::class)
            ->setMethods(['isTensideConfigured', 'isProjectPresent', 'isProjectInstalled'])
            ->disableOriginalConstructor()
            ->getMock();
        $status->method('isTensideConfigured')->willReturn(false);
        $status->method('isProjectPresent')->willReturn(false);
        $status->method('isProjectInstalled')->willReturn(false);
        $container->set('tenside.status', $status);

        /** @var $controller InstallProjectController */
        $controller->setContainer($container);

        $controller->getAutoConfigAction();
    }

    /**
     * Test that the auto config does not forward to the self test controller when the installation is complete.
     *
     * @return void
     *
     * @expectedException \Symfony\Component\HttpKernel\Exception\NotAcceptableHttpException
     */
    public function testAutoConfigActionBailsOnCompleteInstallation()
    {
        $controller = $this->getMock(
            InstallProjectController::class,
            ['forward']
        );

        $container = new Container();

        $status = $this
            ->getMockBuilder(InstallationStatusDeterminator::class)
            ->setMethods(['isTensideConfigured', 'isProjectPresent', 'isProjectInstalled'])
            ->disableOriginalConstructor()
            ->getMock();
        $status->method('isTensideConfigured')->willReturn(true);
        $status->method('isProjectPresent')->willReturn(true);
        $status->method('isProjectInstalled')->willReturn(true);
        $container->set('tenside.status', $status);

        $home = $this
            ->getMockBuilder(HomePathDeterminator::class)
            ->setMethods(['homeDir'])
            ->getMock();
        $home->method('homeDir')->willReturn($this->getTempDir());
        $container->set('tenside.home', $home);

        /** @var $controller InstallProjectController */
        $controller->setContainer($container);

        $controller->getAutoConfigAction();
    }

    /**
     * Test that the auto config forwards to the self test controller.
     *
     * @return void
     */
    public function testGetInstallationStateAction()
    {
        $controller = new InstallProjectController();
        $container  = new Container();

        $status = $this
            ->getMockBuilder(InstallationStatusDeterminator::class)
            ->setMethods(['isTensideConfigured', 'isProjectPresent', 'isProjectInstalled'])
            ->disableOriginalConstructor()
            ->getMock();
        $status->method('isTensideConfigured')->willReturn(false);
        $status->method('isProjectPresent')->willReturn(false);
        $status->method('isProjectInstalled')->willReturn(false);
        $container->set('tenside.status', $status);

        /** @var $controller InstallProjectController */
        $controller->setContainer($container);

        $response = $controller->getInstallationStateAction();

        $data = json_decode($response->getContent(), true);

        $this->assertFalse($data['state']['tenside_configured']);
        $this->assertFalse($data['state']['project_created']);
        $this->assertFalse($data['state']['project_installed']);
        $this->assertEquals('OK', $data['status']);
    }
}
