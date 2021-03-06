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

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Tenside\CoreBundle\Controller\ComposerJsonController;

/**
 * Test the composer.json manipulation controller.
 */
class ComposerJsonControllerTest extends TestCase
{
    /**
     * Test retrieval of the composer json.
     *
     * @return void
     */
    public function testGet()
    {
        $this->provideFixture('composer.json');
        $controller = new ComposerJsonController();
        $controller->setContainer($this->createDefaultContainer());
        $response = $controller->getComposerJsonAction();

        $this->assertEquals(
            file_get_contents($this->getFixturesDirectory() . DIRECTORY_SEPARATOR . 'composer.json'),
            $response->getContent()
        );
    }

    /**
     * Test the putting of a composer.json file.
     *
     * @param string $data The composer.json content.
     *
     * @return JsonResponse|Response
     */
    private function handlePostData($data)
    {
        $this->provideFixture('composer.json');
        mkdir($this->getTempDir() . DIRECTORY_SEPARATOR .'tenside');

        $controller = new ComposerJsonController();
        $controller->setContainer($this->createDefaultContainer());

        $request = new Request([], [], [], [], [], [], json_encode($data));

        return $controller->putComposerJsonAction($request);
    }

    /**
     * Test posting of a composer.json.
     *
     * @return void
     */
    public function testPost()
    {
        $response = $this->handlePostData(
            [
                'name'        => 'some/website',
                'description' => 'some description',
                'license'     => 'MIT'
            ]
        );

        $result = json_decode($response->getContent(), true);

        $this->assertEmpty($result['warnings']);
        $this->assertEmpty($result['errors']);
        $this->assertEquals('OK', $result['status']);
    }

    /**
     * Test posting of a composer.json that contains a warning.
     *
     * @return void
     */
    public function testPostWithWarning()
    {
        $response = $this->handlePostData(
            [
                'name'        => 'some/website',
                'description' => 'some description',
            ]
        );

        $result = json_decode($response->getContent(), true);

        $this->assertNotEmpty($result['warnings']);
        $this->assertEquals('OK', $result['status']);
    }

    /**
     * Test posting of a composer.json containing errors.
     *
     * @return void
     */
    public function testPostWithError()
    {
        $response = $this->handlePostData(
            [
                'description' => 'some description',
                'license'     => 'MIT'
            ]
        );

        $result = json_decode($response->getContent(), true);

        $this->assertEmpty($result['warnings']);
        $this->assertNotEmpty($result['errors']);
        $this->assertEquals('ERROR', $result['status']);
    }
}
