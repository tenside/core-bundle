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

namespace Tenside\CoreBundle\Test\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Role\Role;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Tenside\Core\Config\TensideJsonConfig;
use Tenside\Core\Util\JsonFile;
use Tenside\CoreBundle\Security\JavascriptWebToken;
use Tenside\CoreBundle\Security\JWTAuthenticator;
use Tenside\CoreBundle\Security\UserInformationInterface;
use Tenside\CoreBundle\Test\TestCase;

/**
 * Test the application.
 */
class JWTAuthenticatorTest extends TestCase
{
    /**
     * Test creation with only the mandatory values.
     *
     * @return void
     */
    public function testCreate()
    {
        $config = new TensideJsonConfig(new JsonFile($this->getTempDir() . DIRECTORY_SEPARATOR . 'tenside.json'));
        $config->set('secret', 'very-secret-secret');

        $this->assertInstanceOf(JWTAuthenticator::class, new JWTAuthenticator($config));
    }

    /**
     * This method tests that onAuthenticationFailure() creates a proper response.
     *
     * @return void
     */
    public function testOnAuthenticationFailure()
    {
        $auth = new JWTAuthenticator(new TensideJsonConfig(new JsonFile($this->getTempDir() . DIRECTORY_SEPARATOR . 'tenside.json')));

        $response = $auth->onAuthenticationFailure(new Request(), new AuthenticationException('Cows can\'t fly!'));

        $this->assertInstanceOf(Response::class, $response);
        $this->assertContains('Cows can\'t fly!', $response->getContent());
        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    /**
     * This method tests that supportsToken() is successful for a JavascriptWebToken matching the provider key.
     *
     * @return void
     */
    public function testSupportsTokenSuccess()
    {
        $auth = new JWTAuthenticator(new TensideJsonConfig(new JsonFile($this->getTempDir() . DIRECTORY_SEPARATOR . 'tenside.json')));

        $this->assertTrue(
            $auth->supportsToken(new JavascriptWebToken('jwt-token-data', 'provider-key'), 'provider-key')
        );
    }

    /**
     * This method tests that supportsToken() fails for a JavascriptWebToken not matching the provider key.
     *
     * @return void
     */
    public function testSupportsTokenFailsOnProviderKeyMismatch()
    {
        $auth = new JWTAuthenticator(new TensideJsonConfig(new JsonFile($this->getTempDir() . DIRECTORY_SEPARATOR . 'tenside.json')));

        $this->assertFalse(
            $auth->supportsToken(new JavascriptWebToken('jwt-token-data', 'provider-key'), 'another-provider-key')
        );
    }

    /**
     * This method tests that supportsToken() fails for a generic TokenInterface not being JavascriptWebToken.
     *
     * @return void
     */
    public function testSupportsTokenFailsOnInvalidTokenClass()
    {
        $auth  = new JWTAuthenticator(new TensideJsonConfig(new JsonFile($this->getTempDir() . DIRECTORY_SEPARATOR . 'tenside.json')));
        $token = $this->getMockForAbstractClass(TokenInterface::class);

        $this->assertFalse(
            $auth->supportsToken($token, 'provider-key')
        );
    }

    /**
     * Test creation with only the mandatory values.
     *
     * @return void
     */
    public function testGenerateTokenForUser()
    {
        $config = new TensideJsonConfig(new JsonFile($this->getTempDir() . DIRECTORY_SEPARATOR . 'tenside.json'));
        $config->set('secret', 'very-secret-secret');

        $user = $this
            ->getMockBuilder(UserInformationInterface::class)
            ->setMethods(['values'])
            ->getMockForAbstractClass();

        $user->method('values')->willReturn(['key_1' => 'value_1', 'key_2' => 'value_2']);

        $auth  = new JWTAuthenticator($config);
        $time  = time();
        $token = $auth->getTokenForData($user, $expire = 10);

        $this->assertInternalType('string', $token);

        $decoded = \JWT::decode($token, 'very-secret-secret', ['HS256']);

        $this->assertTrue(property_exists($decoded, 'iat'));
        $this->assertTrue(property_exists($decoded, 'jti'));
        $this->assertTrue(property_exists($decoded, 'key_1'));
        $this->assertTrue(property_exists($decoded, 'key_2'));
        $this->assertTrue(property_exists($decoded, 'exp'));

        $this->assertEquals($decoded->key_1, 'value_1');
        $this->assertEquals($decoded->key_2, 'value_2');
        $this->assertEquals(
            $decoded->iat,
            $time,
            'iat must not differ more than one second (unit test too slow?)',
            1
        );
        $this->assertEquals(
            $decoded->exp,
            ($time + $expire),
            'exp must not differ more than one second (unit test too slow?)',
            1
        );

    }

    /**
     * Test creation with only the mandatory values.
     *
     * @return void
     */
    public function testGenerateTokenForUserRestrictedToDomain()
    {
        $config = new TensideJsonConfig(new JsonFile($this->getTempDir() . DIRECTORY_SEPARATOR . 'tenside.json'));
        $config->set('secret', 'very-secret-secret');
        $config->set('domain', 'example.org');

        $user = $this
            ->getMockBuilder(UserInformationInterface::class)
            ->setMethods(['values'])
            ->getMockForAbstractClass();

        $user->method('values')->willReturn(['key_1' => 'value_1', 'key_2' => 'value_2']);

        $auth  = new JWTAuthenticator($config);
        $time  = time();
        $token = $auth->getTokenForData($user, $expire = 10);

        $this->assertInternalType('string', $token);

        $decoded = \JWT::decode($token, 'very-secret-secret', ['HS256']);

        $this->assertTrue(property_exists($decoded, 'jti'));
        $this->assertTrue(property_exists($decoded, 'key_1'));
        $this->assertTrue(property_exists($decoded, 'key_2'));
        $this->assertTrue(property_exists($decoded, 'iat'));
        $this->assertTrue(property_exists($decoded, 'exp'));
        $this->assertTrue(property_exists($decoded, 'aud'));

        $this->assertEquals($decoded->key_1, 'value_1');
        $this->assertEquals($decoded->key_2, 'value_2');
        $this->assertEquals($decoded->aud, 'example.org');
        $this->assertEquals(
            $decoded->iat,
            $time,
            'iat must not differ more than one second (unit test too slow?)',
            1
        );
        $this->assertEquals(
            $decoded->exp,
            ($time + $expire),
            'exp must not differ more than one second (unit test too slow?)',
            1
        );
    }

    /**
     * Test successful token creation.
     *
     * @return void
     */
    public function testCreateTokenSuccess()
    {
        $config = new TensideJsonConfig(new JsonFile($this->getTempDir() . DIRECTORY_SEPARATOR . 'tenside.json'));
        $config->set('secret', 'very-secret-secret');

        $user = $this
            ->getMockBuilder(UserInformationInterface::class)
            ->setMethods(['values'])
            ->getMockForAbstractClass();

        $auth  = new JWTAuthenticator($config);
        $token = $auth->getTokenForData($user, 10);

        $request = Request::create('https://example.com/');
        $request->headers->set('Authorization', 'Bearer ' . $token);

        $this->assertInstanceOf(
            JavascriptWebToken::class,
            $tokenObject = $auth->createToken($request, 'provider-key')
        );

        $this->assertEquals('provider-key', $tokenObject->getProviderKey());
        $this->assertTrue(property_exists($tokenObject->getCredentials(), 'jti'));
        $this->assertTrue(property_exists($tokenObject->getCredentials(), 'iat'));
        $this->assertTrue(property_exists($tokenObject->getCredentials(), 'exp'));
    }

    /**
     * Test successful token creation without ttl.
     *
     * @return void
     */
    public function testCreateTokenSuccessWithUnlimitedLifetime()
    {
        $config = new TensideJsonConfig(new JsonFile($this->getTempDir() . DIRECTORY_SEPARATOR . 'tenside.json'));
        $config->set('secret', 'very-secret-secret');

        $user = $this
            ->getMockBuilder(UserInformationInterface::class)
            ->setMethods(['values'])
            ->getMockForAbstractClass();

        $auth  = new JWTAuthenticator($config);
        $token = $auth->getTokenForData($user, null);

        $request = Request::create('https://example.com/');
        $request->headers->set('Authorization', 'Bearer ' . $token);

        $this->assertInstanceOf(
            JavascriptWebToken::class,
            $tokenObject = $auth->createToken($request, 'provider-key')
        );

        $this->assertEquals('provider-key', $tokenObject->getProviderKey());
        $this->assertTrue(property_exists($tokenObject->getCredentials(), 'jti'));
        $this->assertTrue(property_exists($tokenObject->getCredentials(), 'iat'));
        $this->assertFalse(property_exists($tokenObject->getCredentials(), 'exp'));
    }

    /**
     * Test successful token creation.
     *
     * @return void
     */
    public function testCreateTokenIgnoresHeaderCase()
    {
        $config = new TensideJsonConfig(new JsonFile($this->getTempDir() . DIRECTORY_SEPARATOR . 'tenside.json'));
        $config->set('secret', 'very-secret-secret');

        $user = $this
            ->getMockBuilder(UserInformationInterface::class)
            ->setMethods(['values'])
            ->getMockForAbstractClass();

        $auth  = new JWTAuthenticator($config);
        $token = $auth->getTokenForData($user, 10);

        $request = Request::create('https://example.com/');
        $request->headers->set('Authorization', 'BeArEr ' . $token);

        $this->assertInstanceOf(
            JavascriptWebToken::class,
            $tokenObject = $auth->createToken($request, 'provider-key')
        );

        $this->assertEquals('provider-key', $tokenObject->getProviderKey());
        $this->assertTrue(property_exists($tokenObject->getCredentials(), 'jti'));
        $this->assertTrue(property_exists($tokenObject->getCredentials(), 'iat'));
        $this->assertTrue(property_exists($tokenObject->getCredentials(), 'exp'));
    }

    /**
     * Test token creation without secret.
     *
     * @return void
     */
    public function testCreateTokenReturnsNullWithoutSecret()
    {
        $auth    = new JWTAuthenticator(new TensideJsonConfig(new JsonFile($this->getTempDir() . DIRECTORY_SEPARATOR . 'tenside.json')));
        $request = Request::create('https://example.com/');
        $request->headers->set('Authorization', 'Bearer token');

        $this->assertNull($auth->createToken($request, 'provider-key'));
    }

    /**
     * Test token creation without header.
     *
     * @return void
     */
    public function testCreateTokenReturnsNullWithoutHeader()
    {
        $config = new TensideJsonConfig(new JsonFile($this->getTempDir() . DIRECTORY_SEPARATOR . 'tenside.json'));
        $config->set('secret', 'very-secret-secret');

        $auth = new JWTAuthenticator($config);
        $this->assertNull($auth->createToken(Request::create('https://example.com/'), 'provider-key'));
    }

    /**
     * Test token creation with unknown header content.
     *
     * @return void
     */
    public function testCreateTokenReturnsNullWithUnknownHeaderContent()
    {
        $config = new TensideJsonConfig(new JsonFile($this->getTempDir() . DIRECTORY_SEPARATOR . 'tenside.json'));
        $config->set('secret', 'very-secret-secret');

        $request = Request::create('https://example.com/');
        $request->headers->set('Authorization', 'Beer-Auth PLOP!');

        $auth = new JWTAuthenticator($config);
        $this->assertNull($auth->createToken($request, 'provider-key'));
    }

    /**
     * Test token creation with un-decode-able token.
     *
     * @return void
     */
    public function testCreateTokenReturnsNullWhenTokenCanNotBeDecoded()
    {
        $config = new TensideJsonConfig(new JsonFile($this->getTempDir() . DIRECTORY_SEPARATOR . 'tenside.json'));
        $config->set('secret', 'very-secret-secret');

        $request = Request::create('https://example.com/');
        $request->headers->set('Authorization', 'Bearer -broken-token-');

        $auth = new JWTAuthenticator($config);
        $this->assertNull($auth->createToken($request, 'provider-key'));
    }

    /**
     * Test token creation with token from another issuer.
     *
     * @return void
     */
    public function testCreateTokenReturnsNullWhenTokenIsNotFromUs()
    {
        $config = new TensideJsonConfig(new JsonFile($this->getTempDir() . DIRECTORY_SEPARATOR . 'tenside.json'));
        $config->set('secret', 'very-secret-secret');
        $config->set('domain', 'example.org');

        $tokenFromThem = \JWT::encode(['aud' => 'example.com'], 'very-secret-secret');

        $request = Request::create('https://example.com/');
        $request->headers->set('Authorization', 'Bearer ' . $tokenFromThem);

        $auth = new JWTAuthenticator($config);
        $this->assertNull($auth->createToken($request, 'provider-key'));
    }

    /**
     * Test token creation with token from undefined issuer.
     *
     * @return void
     */
    public function testCreateTokenReturnsNullWhenTokenIsNotFromUsButAnonymous()
    {
        $config = new TensideJsonConfig(new JsonFile($this->getTempDir() . DIRECTORY_SEPARATOR . 'tenside.json'));
        $config->set('secret', 'very-secret-secret');
        $config->set('domain', 'example.org');

        $tokenFromThem = \JWT::encode(['comment' => 'No aud here'], 'very-secret-secret');

        $request = Request::create('https://example.com/');
        $request->headers->set('Authorization', 'Bearer ' . $tokenFromThem);

        $auth = new JWTAuthenticator($config);
        $this->assertNull($auth->createToken($request, 'provider-key'));
    }

    /**
     * Test token creation with token from another issuer when we do not restrict.
     *
     * @return void
     */
    public function testCreateTokenReturnsNullWhenTokenIsNotAnonymousButWeDontRestrict()
    {
        $config = new TensideJsonConfig(new JsonFile($this->getTempDir() . DIRECTORY_SEPARATOR . 'tenside.json'));
        $config->set('secret', 'very-secret-secret');

        $tokenFromThem = \JWT::encode(['aud' => 'example.com'], 'very-secret-secret');

        $request = Request::create('https://example.com/');
        $request->headers->set('Authorization', 'Bearer ' . $tokenFromThem);

        $auth = new JWTAuthenticator($config);
        $this->assertNull($auth->createToken($request, 'provider-key'));
    }

    /**
     * Test that authenticating a valid token succeeds.
     *
     * @return void
     */
    public function testAuthenticateTokenSuccess()
    {
        $config = new TensideJsonConfig(new JsonFile($this->getTempDir() . DIRECTORY_SEPARATOR . 'tenside.json'));
        $config->set('secret', 'very-secret-secret');

        $auth = new JWTAuthenticator($config);

        $token = $this
            ->getMockBuilder(TokenInterface::class)
            ->setMethods(['getCredentials'])
            ->getMockForAbstractClass();
        $token->method('getCredentials')->willReturn((object) ['username' => 'user']);

        $user = $this
            ->getMockBuilder(UserInformationInterface::class)
            ->setMethods(['getRoles'])
            ->getMockForAbstractClass();
        $user->method('getRoles')->willReturn(['ROLE_1', 'ROLE_2']);

        $userProvider = $this
            ->getMockBuilder(UserProviderInterface::class)
            ->setMethods(['loadUserByUsername'])
            ->getMockForAbstractClass();
        $userProvider->method('loadUserByUsername')->willReturn($user);

        $authToken = $auth->authenticateToken($token, $userProvider, 'provider-key');

        $this->assertInstanceOf(JavascriptWebToken::class, $authToken);

        $this->assertSame($token->getCredentials(), $authToken->getCredentials());
        $this->assertEquals('provider-key', $authToken->getProviderKey());
        $this->assertSame($user, $authToken->getUser());
        /** @var Role[] $roles */
        $roles = $authToken->getRoles();
        $this->assertEquals(2, count($roles));
        $this->assertInstanceOf(Role::class, $roles[0]);
        $this->assertEquals('ROLE_1', $roles[0]->getRole());
        $this->assertInstanceOf(Role::class, $roles[1]);
        $this->assertEquals('ROLE_2', $roles[1]->getRole());
        $this->assertTrue($authToken->isAuthenticated());
    }

    /**
     * Test that authenticating a token without secret bails.
     *
     * @return void
     *
     * @expectedException \LogicException
     *
     * @expectedExceptionMessage Config does not contain a secret.
     */
    public function testAuthenticateTokenWithoutSecretFails()
    {
        $auth = new JWTAuthenticator(new TensideJsonConfig(new JsonFile($this->getTempDir() . DIRECTORY_SEPARATOR . 'tenside.json')));
        $auth->authenticateToken(
            $this->getMockForAbstractClass(TokenInterface::class),
            $this->getMockForAbstractClass(UserProviderInterface::class),
            'provider-key'
        );
    }

    /**
     * Test that authenticating a token with invalid credentials fails.
     *
     * @return void
     *
     * @expectedException \Symfony\Component\Security\Core\Exception\AuthenticationException
     *
     * @expectedExceptionMessage Invalid token - no or invalid credentials.
     */
    public function testAuthenticateTokenFailsWhenCredentialsAreInvalid()
    {
        $config = new TensideJsonConfig(new JsonFile($this->getTempDir() . DIRECTORY_SEPARATOR . 'tenside.json'));
        $config->set('secret', 'very-secret-secret');

        $auth = new JWTAuthenticator($config);

        $token = $this
            ->getMockBuilder(TokenInterface::class)
            ->setMethods(['getCredentials'])
            ->getMockForAbstractClass();
        $token->method('getCredentials')->willReturn(null);

        $userProvider = $this
            ->getMockForAbstractClass(UserProviderInterface::class);

        $auth->authenticateToken(
            $token,
            $userProvider,
            'provider-key'
        );
    }

    /**
     * Test that authenticating a token with valid credentials for which no user can be loaded fails.
     *
     * @return void
     *
     * @expectedException \Symfony\Component\Security\Core\Exception\AuthenticationException
     *
     * @expectedExceptionMessage Invalid token - could not derive user from credentials.
     */
    public function testAuthenticateTokenFailsWhenUserCanNotBeLoaded()
    {
        $config = new TensideJsonConfig(new JsonFile($this->getTempDir() . DIRECTORY_SEPARATOR . 'tenside.json'));
        $config->set('secret', 'very-secret-secret');

        $auth = new JWTAuthenticator($config);

        $token = $this
            ->getMockBuilder(TokenInterface::class)
            ->setMethods(['getCredentials'])
            ->getMockForAbstractClass();
        $token->method('getCredentials')->willReturn((object) ['username' => 'user']);

        $userProvider = $this
            ->getMockForAbstractClass(UserProviderInterface::class);

        $auth->authenticateToken(
            $token,
            $userProvider,
            'provider-key'
        );
    }
}
