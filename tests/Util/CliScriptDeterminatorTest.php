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

use Tenside\CoreBundle\Util\CliScriptDeterminator;

/**
 * Test cli script determinator
 */
class CliScriptDeterminatorTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Test all methods.
     *
     * @return void
     */
    public function testAll()
    {
        $determinator = new CliScriptDeterminator('a-scriptname');

        $this->assertEquals('a-scriptname', $determinator->cliExecutable());
        $this->assertEquals('a-scriptname', $determinator->cliExecutable());
    }
}
