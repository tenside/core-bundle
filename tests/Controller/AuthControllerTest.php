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
 * @author     Yanick Witschi <yanick.witschi@terminal42.ch>
 * @copyright  2015 Christian Schiffler <c.schiffler@cyberspectrum.de>
 * @license    https://github.com/tenside/core-bundle/blob/master/LICENSE MIT
 * @link       https://github.com/tenside/core-bundle
 * @filesource
 */

namespace Tenside\CoreBundle\Test\Controller;

use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Tenside\CoreBundle\Security\JWTAuthenticator;
use Tenside\CoreBundle\Security\UserInformation;
use Tenside\CoreBundle\Controller\AuthController;

/**
 * Test the composer.json manipulation controller.
 */
class AuthControllerTest extends TestCase
{
    /**
     * Build the container for authentication.
     *
     * @return Container
     *
     * @SuppressWarnings(PHPMD.UnusedLocalVariable)
     */
    private function buildContainer()
    {
        $jwtAuth = $this
            ->getMockBuilder(JWTAuthenticator::class)
            ->setMethods(['getTokenForData'])
            ->disableOriginalConstructor()
            ->getMock();

        $jwtAuth->method('getTokenForData')->willReturnCallback(function ($userData, $lifetime) {
            return 'Auth-Token' . $lifetime;
        });

        $container = $this->createDefaultContainer(
            [
                'security.token_storage' => new TokenStorage(),
                'tenside.jwt_authenticator' => $jwtAuth
            ]
        );

        return $container;
    }

    /**
     * Test the posting of an auth request.
     *
     * @param UserInformation $data The user data to return from auth providers.
     *
     * @param null|int        $ttl  The ttl for the token.
     *
     * @return JsonResponse|Response
     */
    private function handleAuth($data, $ttl = null)
    {
        $controller = $this->getMock(AuthController::class, ['getUser']);
        $controller->method('getUser')->willReturn($data);
        /** @var AuthController $controller */
        $controller->setContainer($this->buildContainer());

        $parameters = [];
        if (null !== $ttl) {
            $parameters = ['ttl' => $ttl];
        }
        $request = new Request($parameters);

        return $controller->checkAuthAction($request);
    }

    /**
     * Test unauthorized response for null data.
     *
     * @return void
     */
    public function testGetUnauthenticated()
    {
        $response = $this->handleAuth(null);

        $this->assertEquals(
            ['status' => 'unauthorized'],
            json_decode($response->getContent(), true)
        );
        $this->assertEquals(401, $response->getStatusCode());
    }

    /**
     * Test authorized response for valid user data.
     *
     * @return void
     */
    public function testPostValidCredentials()
    {
        $response = $this->handleAuth(new UserInformation(['acl' => 7, 'username' => 'foobar']));
        $result   = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('token', $result);
        $this->assertArrayHasKey('acl', $result);
        $this->assertArrayHasKey('username', $result);
        $this->assertEquals('OK', $result['status']);
        $this->assertEquals('foobar', $result['username']);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Auth-Token3600', $result['token']);
    }

    /**
     * Test authorized response for valid user data with valid ttl.
     *
     * @return void
     */
    public function testPostValidCredentialsWithTtl()
    {
        $response = $this->handleAuth(new UserInformation(['acl' => 7, 'username' => 'foobar']), 3600);
        $result   = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('token', $result);
        $this->assertArrayHasKey('acl', $result);
        $this->assertArrayHasKey('username', $result);
        $this->assertEquals('OK', $result['status']);
        $this->assertEquals('foobar', $result['username']);
        $this->assertEquals(200, $response->getStatusCode());

    }

    /**
     * Test authorized response for valid user data with unlimited ttl.
     *
     * @return void
     */
    public function testPostValidCredentialsWithUnlimitedTtl()
    {
        $response = $this->handleAuth(new UserInformation(['acl' => 7, 'username' => 'foobar']), -1);
        $result   = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('token', $result);
        $this->assertArrayHasKey('acl', $result);
        $this->assertArrayHasKey('username', $result);
        $this->assertEquals('OK', $result['status']);
        $this->assertEquals('foobar', $result['username']);
        $this->assertEquals(200, $response->getStatusCode());

    }

    /**
     * Test that an invalid user object raises an exception.
     *
     * @return void
     *
     * @expectedException \RuntimeException
     */
    public function testBailOnInvalidUser()
    {
        $controller = $this->getMock(AuthController::class, ['getUser']);
        $controller->method('getUser')->willReturn(new \stdClass());

        $controller->checkAuthAction(new Request());
    }
}
