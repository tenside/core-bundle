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

namespace Tenside\CoreBundle\Test\Util;

use Tenside\Core\Config\TensideJsonConfig;
use Tenside\CoreBundle\Util\UriSigner;

/**
 * This tests the uri signer class to ensure it behaves exactly like its ancestor from Symfony.
 */
class UriSignerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Mock a config containing the secret.
     *
     * @param string $secret The secret to use.
     *
     * @return TensideJsonConfig
     */
    private function mockConfig($secret)
    {
        $mock = $this
            ->getMockBuilder(TensideJsonConfig::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mock->method('getSecret')->willReturn($secret);

        return $mock;
    }

    /**
     * Test signing.
     *
     * @return void
     */
    public function testSign()
    {
        $signer = new UriSigner($this->mockConfig('foobar'));

        $this->assertContains('?_hash=', $signer->sign('http://example.com/foo'));
        $this->assertContains('&_hash=', $signer->sign('http://example.com/foo?foo=bar'));
    }

    /**
     * Test checking of the signed url.
     *
     * @return void
     */
    public function testCheck()
    {
        $signer = new UriSigner($this->mockConfig('foobar'));

        $this->assertFalse($signer->check('http://example.com/foo?_hash=foo'));
        $this->assertFalse($signer->check('http://example.com/foo?foo=bar&_hash=foo'));
        $this->assertFalse($signer->check('http://example.com/foo?foo=bar&_hash=foo&bar=foo'));

        $this->assertTrue($signer->check($signer->sign('http://example.com/foo')));
        $this->assertTrue($signer->check($signer->sign('http://example.com/foo?foo=bar')));

        $this->assertEquals(
            $signer->sign('http://example.com/foo?foo=bar&bar=foo'),
            $signer->sign('http://example.com/foo?bar=foo&foo=bar')
        );
    }

    /**
     * Test that it also works with argument separator "&amp;".
     *
     * @return void
     */
    public function testCheckWithDifferentArgSeparator()
    {
        $this->iniSet('arg_separator.output', '&amp;');
        $signer = new UriSigner($this->mockConfig('foobar'));

        $this->assertSame(
            'http://example.com/foo?baz=bay&foo=bar&_hash=rIOcC%2FF3DoEGo%2FvnESjSp7uU9zA9S%2F%2BOLhxgMexoPUM%3D',
            $signer->sign('http://example.com/foo?foo=bar&baz=bay')
        );
        $this->assertTrue($signer->check($signer->sign('http://example.com/foo?foo=bar&baz=bay')));
    }
}
