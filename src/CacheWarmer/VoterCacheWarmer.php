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

namespace Tenside\CoreBundle\CacheWarmer;

use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;
use Symfony\Component\HttpKernel\CacheWarmer\WarmableInterface;
use Tenside\CoreBundle\Security\PermissionVoter;

/**
 * Generates the route access voter cache
 */
class VoterCacheWarmer implements CacheWarmerInterface
{
    /**
     * The voter.
     *
     * @var PermissionVoter
     */
    protected $voter;

    /**
     * Constructor.
     *
     * @param PermissionVoter $voter A voter instance.
     */
    public function __construct(PermissionVoter $voter)
    {
        $this->voter = $voter;
    }

    /**
     * Warms up the cache.
     *
     * @param string $cacheDir The cache directory.
     *
     * @return void
     */
    public function warmUp($cacheDir)
    {
        if ($this->voter instanceof WarmableInterface) {
            $this->voter->warmUp($cacheDir);
        }
    }

    /**
     * Checks whether this warmer is optional or not.
     *
     * @return bool always true
     */
    public function isOptional()
    {
        return true;
    }
}
