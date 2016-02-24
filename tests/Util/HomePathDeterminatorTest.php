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

use Tenside\CoreBundle\Util\HomePathDeterminator;
use Tenside\CoreBundle\Test\TestCase;

/**
 * Test home path determinator.
 */
class HomePathDeterminatorTest extends TestCase
{
    /**
     * Test that the environment variable overrides the detection.
     *
     * @return void
     */
    public function testComposerEnvironmentOverride()
    {
        $keepEnv = getenv('COMPOSER');
        putenv('COMPOSER=/path/to/tenside/composer.json');
        $determinator = new HomePathDeterminator();
        $detectedHome = $determinator->homeDir();

        putenv('COMPOSER' . ($keepEnv ? '=' . $keepEnv : ''));

        $this->assertEquals('/path/to/tenside', $detectedHome);
    }

    /**
     * Test that the environment variable overrides the detection.
     *
     * @return void
     */
    public function testHomeIsCwd()
    {
        $keepEnv = getenv('COMPOSER');
        putenv('COMPOSER');
        mkdir($this->getTempDir() . DIRECTORY_SEPARATOR . 'web');
        chdir($this->getTempDir() . DIRECTORY_SEPARATOR . 'web');
        $determinator = new HomePathDeterminator();
        $detectedHome = $determinator->homeDir();

        putenv('COMPOSER' . ($keepEnv ? '=' . $keepEnv : ''));

        $this->assertEquals($this->getTempDir(), $detectedHome);
    }
}
