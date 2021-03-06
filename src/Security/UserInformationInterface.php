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

namespace Tenside\CoreBundle\Security;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * This interface describes a user information.
 */
interface UserInformationInterface extends UserInterface
{
    /**
     * The key used for storing the roles,
     */
    const KEY_ROLES = 'acl';

    /**
     * No special role (used only for access check).
     */
    const ROLE_NONE = 0b0;

    /**
     * Perform package upgrades.
     */
    const ROLE_UPGRADE = 0b1;

    /**
     * Manipulate the package requirements.
     */
    const ROLE_MANIPULATE_REQUIREMENTS = 0b10;

    /**
     * Edit the composer.json.
     */
    const ROLE_EDIT_COMPOSER_JSON = 0b100;

    /**
     * Edit the AppKernel.
     */
    const ROLE_EDIT_APPKERNEL = 0b1000;

    /**
     * All access (aka admin).
     */
    const ROLE_ALL = 0b1111;

    /**
     * Check if the user has the given access level.
     *
     * @param int $role The role to check.
     *
     * @return bool
     */
    public function hasAccessLevel($role);

    /**
     * Returns the parameter keys.
     *
     * @return array An array of parameter keys
     *
     * @api
     */
    public function keys();

    /**
     * Returns the parameter values as associative array.
     *
     * @return array
     */
    public function values();

    /**
     * Returns a value by name.
     *
     * @param string $key     The value name.
     *
     * @param mixed  $default The default value.
     *
     * @return string|null The value if found, null otherwise.
     *
     * @api
     */
    public function get($key, $default = null);

    /**
     * Returns true if the value is defined.
     *
     * @param string $key The key.
     *
     * @return bool true if the value exists, false otherwise.
     *
     * @api
     */
    public function has($key);


    /**
     * String representation of this user information for use in logs.
     *
     * Examples may be: "user foo" or "token 0123456789".
     *
     * @return mixed
     */
    public function asString();
}
