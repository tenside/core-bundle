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

namespace Tenside\CoreBundle\Test\DependencyInjection\Factory;

use Tenside\Core\Composer\ComposerJson;
use Tenside\CoreBundle\DependencyInjection\Factory\ComposerJsonFactory;
use Tenside\CoreBundle\Test\TestCase;
use Tenside\CoreBundle\Util\HomePathDeterminator;

/**
 * Test the composer.json factory.
 */
class ComposerJsonFactoryTest extends TestCase
{
    /**
     * Test that the factory creates a new instance.
     *
     * @return void
     */
    public function testCreate()
    {
        $home = $this->getMock(HomePathDeterminator::class, ['homeDir']);
        $home->method('homeDir')->willReturn($this->getTempDir());

        $composerJson = ComposerJsonFactory::create($home);

        $this->assertInstanceOf(ComposerJson::class, $composerJson);
        $this->assertEquals($this->getTempDir() . DIRECTORY_SEPARATOR . 'composer.json', $composerJson->getFilename());
    }
}
