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

use Tenside\Core\Util\HomePathDeterminator;
use Tenside\CoreBundle\Test\TestCase;
use Tenside\CoreBundle\Util\InstallationStatusDeterminator;

/**
 * Test installation status determinator
 */
class InstallationStatusDeterminatorTest extends TestCase
{
    /**
     * Test all methods return false when nothing in dir.
     *
     * @return void
     */
    public function testEverythingIsFalseForEmptyDir()
    {
        $home = $this->getMock(HomePathDeterminator::class, ['homeDir']);
        $home->method('homeDir')->willReturn($this->getTempDir());

        /** @var HomePathDeterminator $home */
        $determinator = new InstallationStatusDeterminator($home);

        $this->assertFalse($determinator->isTensideConfigured());
        $this->assertFalse($determinator->isProjectPresent());
        $this->assertFalse($determinator->isProjectInstalled());
        $this->assertFalse($determinator->isComplete());
    }

    /**
     * Test isTensideConfigured works correctly.
     *
     * @return void
     */
    public function testIsTensideConfigured()
    {
        $home = $this->getMock(HomePathDeterminator::class, ['homeDir']);
        $home->method('homeDir')->willReturn($this->getTempDir());

        /** @var HomePathDeterminator $home */
        $this->createFixture('tenside' . DIRECTORY_SEPARATOR . 'tenside.json', '{}');

        $determinator = new InstallationStatusDeterminator($home);

        $this->assertTrue($determinator->isTensideConfigured());

        // Remove the file to ensure that the class caches the value.
        unlink($this->getTempDir() . DIRECTORY_SEPARATOR . 'tenside' . DIRECTORY_SEPARATOR . 'tenside.json');

        $this->assertTrue($determinator->isTensideConfigured());
    }

    /**
     * Test isProjectPresent works correctly.
     *
     * @return void
     */
    public function testIsProjectPresent()
    {
        $home = $this->getMock(HomePathDeterminator::class, ['homeDir']);
        $home->method('homeDir')->willReturn($this->getTempDir());

        /** @var HomePathDeterminator $home */
        $this->createFixture('composer.json', '{}');

        $determinator = new InstallationStatusDeterminator($home);

        $this->assertTrue($determinator->isProjectPresent());

        // Remove the file to ensure that the class caches the value.
        unlink($this->getTempDir() . DIRECTORY_SEPARATOR . 'composer.json');

        $this->assertTrue($determinator->isProjectPresent());
    }

    /**
     * Test isProjectInstalled works correctly.
     *
     * @return void
     */
    public function testIsProjectInstalled()
    {
        $home = $this->getMock(HomePathDeterminator::class, ['homeDir']);
        $home->method('homeDir')->willReturn($this->getTempDir());

        /** @var HomePathDeterminator $home */
        mkdir($this->getTempDir() . DIRECTORY_SEPARATOR . 'vendor');

        $determinator = new InstallationStatusDeterminator($home);

        $this->assertTrue($determinator->isProjectInstalled());

        // Remove the dir to ensure that the class caches the value.
        rmdir($this->getTempDir() . DIRECTORY_SEPARATOR . 'vendor');

        $this->assertTrue($determinator->isProjectInstalled());
    }

    /**
     * Test isComplete works correctly.
     *
     * @return void
     */
    public function testIsComplete()
    {
        $determinator = $this
            ->getMockBuilder(InstallationStatusDeterminator::class)
            ->setMethods(['isTensideConfigured', 'isProjectPresent', 'isProjectInstalled'])
            ->disableOriginalConstructor()
            ->getMock();
        $determinator->method('isTensideConfigured')->willReturn(true);
        $determinator->method('isProjectPresent')->willReturn(true);
        $determinator->method('isProjectInstalled')->willReturn(true);
        
        $this->assertTrue($determinator->isComplete());
    }
}
