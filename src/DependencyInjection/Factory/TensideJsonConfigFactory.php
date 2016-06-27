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

use Tenside\Core\Config\TensideJsonConfig;
use Tenside\Core\Util\HomePathDeterminator;
use Tenside\Core\Util\JsonFile;

/**
 * This class creates a config instance.
 *
 * @internal
 */
class TensideJsonConfigFactory
{
    /**
     * Create an instance.
     *
     * @param HomePathDeterminator $home The home determinator.
     *
     * @return TensideJsonConfig
     */
    public static function create(HomePathDeterminator $home)
    {
        return new TensideJsonConfig(new JsonFile($home->tensideDataDir() . DIRECTORY_SEPARATOR . 'tenside.json'));
    }
}
