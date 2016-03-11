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

use Symfony\Component\HttpFoundation\Request;
use Tenside\CoreBundle\Controller\VersionConstraintController;

/**
 * Test the version constraint validating controller.
 */
class VersionConstraintControllerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Test that checking a valid constraint is successful.
     *
     * @return void
     */
    public function testValidConstraint()
    {
        $controller = new VersionConstraintController();
        $response   = $controller->checkVersionConstraintAction(
            Request::create('', 'POST', [], [], [], [], json_encode(['constraint' => '1.0.0']))
        );

        $this->assertEquals(
            ['status' => 'ok'],
            json_decode($response->getContent(), true)
        );
        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * Test that checking an invalid constraint is successful.
     *
     * @return void
     */
    public function testInvalidConstraint()
    {
        $controller = new VersionConstraintController();
        $response   = $controller->checkVersionConstraintAction(
            Request::create('', 'POST', [], [], [], [], json_encode(['constraint' => 'xyz']))
        );

        $this->assertEquals(
            [
                'status' => 'error',
                'error'  => 'Could not parse version constraint xyz: Invalid version string "xyz"'
            ],
            json_decode($response->getContent(), true)
        );
        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * Test that checking without payload is bad request.
     *
     * @return void
     */
    public function testNoPayload()
    {
        $controller = new VersionConstraintController();
        $response   = $controller->checkVersionConstraintAction(
            Request::create('', 'POST')
        );

        $this->assertEquals(
            [
                'status' => 'error',
                'error'  => 'invalid payload'
            ],
            json_decode($response->getContent(), true)
        );
        $this->assertEquals(400, $response->getStatusCode());
    }

    /**
     * Test that checking without constraint in payload is bad request.
     *
     * @return void
     */
    public function testNoConstraint()
    {
        $controller = new VersionConstraintController();
        $response   = $controller->checkVersionConstraintAction(
            Request::create('', 'POST', [], [], [], [], json_encode([]))
        );

        $this->assertEquals(
            [
                'status' => 'error',
                'error'  => 'invalid payload'
            ],
            json_decode($response->getContent(), true)
        );
        $this->assertEquals(400, $response->getStatusCode());
    }
}
