<?php

/**
 * This file is part of tenside/core.
 *
 * (c) Christian Schiffler <c.schiffler@cyberspectrum.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * This project is provided in good faith and hope to be usable by anyone.
 *
 * @package    tenside/core
 * @author     Christian Schiffler <c.schiffler@cyberspectrum.de>
 * @copyright  2015 Christian Schiffler <c.schiffler@cyberspectrum.de>
 * @license    https://github.com/tenside/core/blob/master/LICENSE MIT
 * @link       https://github.com/tenside/core
 * @filesource
 */

namespace Tenside\CoreBundle\Test\Controller;

use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Tenside\Core\Task\Composer\ComposerTaskFactory;
use Tenside\Core\Util\HomePathDeterminator;
use Tenside\CoreBundle\DependencyInjection\Factory\ComposerJsonFactory;
use Tenside\CoreBundle\DependencyInjection\Factory\TaskListFactory;
use Tenside\CoreBundle\DependencyInjection\Factory\TensideJsonConfigFactory;
use Tenside\CoreBundle\Util\InstallationStatusDeterminator;
use Tenside\CoreBundle\Test\TestCase as BaseTestCase;

/**
 * Base test case for testing controllers.
 */
class TestCase extends BaseTestCase
{
    /**
     * Create the default container containing all basic services.
     *
     * @param array $services Array of services to provide.
     *
     * @return Container
     */
    protected function createDefaultContainer($services = [])
    {
        $container = new Container();

        foreach ($services as $name => $service) {
            $container->set($name, $service);
        }

        if (!$container->has('event_dispatcher')) {
            $container->set('event_dispatcher', new EventDispatcher());
        }

        if (!$container->has('tenside.home')) {
            $home = $this->getMock(HomePathDeterminator::class, ['homeDir']);
            $home->method('homeDir')->willReturn($this->getTempDir());
            $container->set('tenside.home', $home);
        }

        if (!$container->has('tenside.config')) {
            $container->set('tenside.config', TensideJsonConfigFactory::create($container->get('tenside.home')));
        }

        if (!$container->has('tenside.taskfactory')) {
            $container->set(
                'tenside.taskfactory',
                new ComposerTaskFactory($container->get('tenside.home'))
            );
        }

        if (!$container->has('tenside.tasks')) {
            $container->set(
                'tenside.tasks',
                TaskListFactory::create($container->get('tenside.home'), $container->get('tenside.taskfactory'))
            );
        }

        if (!$container->has('tenside.composer_json')) {
            $container->set('tenside.composer_json', ComposerJsonFactory::create($container->get('tenside.home')));
        }

        if (!$container->has('tenside.status')) {
            $tenside = new InstallationStatusDeterminator($container->get('tenside.home'));
            $container->set('tenside.status', $tenside);
        }

        return $container;
    }
}
