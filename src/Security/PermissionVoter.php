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

namespace Tenside\CoreBundle\Security;

use Symfony\Component\Config\ConfigCacheFactory;
use Symfony\Component\Config\ConfigCacheFactoryInterface;
use Symfony\Component\Config\ConfigCacheInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\CacheWarmer\WarmableInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * This class checks the permissions of the authenticated user against the current request.
 */
class PermissionVoter implements VoterInterface, WarmableInterface
{
    /**
     * The router.
     *
     * @var RouterInterface
     */
    private $router;

    /**
     * The request stack.
     *
     * @var RequestStack
     */
    private $requestStack;

    /**
     * The options.
     *
     * @var array
     */
    protected $options = array();

    /**
     * The config cache.
     *
     * @var ConfigCacheFactoryInterface|null
     */
    private $configCacheFactory;

    /**
     * Create a new instance.
     *
     * @param RouterInterface $router       The router component.
     *
     * @param RequestStack    $requestStack The request stack.
     */
    public function __construct(RouterInterface $router, RequestStack $requestStack, $options)
    {
        $this->router       = $router;
        $this->requestStack = $requestStack;
        $this->options      = $options;
    }

    /**
     * {@inheritDoc}
     */
    public function supportsAttribute($attribute)
    {
        return 'ROLE_CHECK' === $attribute;
    }

    /**
     * {@inheritDoc}
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function supportsClass($class)
    {
        return true;
    }

    /**
     * {@inheritDoc}
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function vote(TokenInterface $token, $object, array $attributes)
    {
        if (!(($object instanceof Request) || $this->supportsAnyAttribute($attributes))) {
            return VoterInterface::ACCESS_ABSTAIN;
        }

        $requiredRole = $this->getRequiredRole($object);

        if (null === $requiredRole) {
            return VoterInterface::ACCESS_ABSTAIN;
        }

        $user = $token->getUser();

        if (!$user instanceof UserInformationInterface) {
            return VoterInterface::ACCESS_DENIED;
        }

        foreach ($user->getRoles() as $role) {
            if (strtoupper($role) == strtoupper($requiredRole)) {
                return VoterInterface::ACCESS_GRANTED;
            }
        }

        return VoterInterface::ACCESS_DENIED;
    }

    /**
     * {@inheritdoc}
     */
    public function warmUp($cacheDir)
    {
        $currentDir = $this->options['cache_dir'];

        // force cache generation
        $this->options['cache_dir'] = $cacheDir;
        $this->getRouteRoles();

        $this->options['cache_dir'] = $currentDir;
    }

    /**
     * Test if we support any of the attributes.
     *
     * @param string[] $attributes The attributes to test.
     *
     * @return bool
     */
    private function supportsAnyAttribute($attributes)
    {
        foreach ($attributes as $attribute) {
            if ($this->supportsAttribute($attribute)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Provides the ConfigCache factory implementation, falling back to a default implementation if necessary.
     *
     * @return ConfigCacheFactoryInterface $configCacheFactory
     */
    private function getConfigCacheFactory()
    {
        if (null === $this->configCacheFactory) {
            $this->configCacheFactory = new ConfigCacheFactory($this->options['debug']);
        }

        return $this->configCacheFactory;
    }

    /**
     * Get the required roles from cache if possible.
     *
     * @return array
     */
    private function getRouteRoles()
    {
        $router = $this->router;

        $cache = $this->getConfigCacheFactory()->cache(
            $this->options['cache_dir'].'/tenside_roles.php',
            function (ConfigCacheInterface $cache) use ($router) {
                $routes = $router->getRouteCollection();
                $roles  = [];
                foreach ($routes as $name => $route) {
                    if ($requiredRole = $route->getOption('required_role')) {
                        $roles[$name] = $requiredRole;
                    }
                }

                $cache->write('<?php return ' . var_export($roles, true) . ';', $routes->getResources());
            }
        );

        return require_once $cache->getPath();
    }

    /**
     * Retrieve the required role for the current request (if any).
     *
     * @param mixed $object The object passed to the voter.
     *
     * @return string|null
     */
    private function getRequiredRole($object)
    {
        if (!(($request = $object) instanceof Request)) {
            $request = $this->requestStack->getCurrentRequest();
        }

        $routes = $this->getRouteRoles();

        if (isset($routes[$request->get('_route')])) {
            return $routes[$request->get('_route')];
        }

        return null;
    }
}
