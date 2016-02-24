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

namespace Tenside\CoreBundle\DependencyInjection\Factory;

use Tenside\Core\Task\TaskFactoryInterface;
use Tenside\Core\Task\TaskList;
use Tenside\CoreBundle\Util\HomePathDeterminator;

/**
 * This class creates a composerJson instance.
 *
 * @internal
 */
class TaskListFactory
{
    /**
     * Create an instance.
     *
     * @param HomePathDeterminator $home    The home determinator.
     *
     * @param TaskFactoryInterface $factory The event dispatcher.
     *
     * @return TaskList
     */
    public static function create(HomePathDeterminator $home, TaskFactoryInterface $factory)
    {
        return new TaskList($home->tensideDataDir(), $factory);
    }
}
