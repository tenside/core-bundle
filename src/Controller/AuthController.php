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

namespace Tenside\CoreBundle\Controller;

use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Tenside\CoreBundle\Annotation\ApiDescription;
use Tenside\CoreBundle\Security\UserInformationInterface;

/**
 * The main entry point.
 */
class AuthController extends AbstractController
{
    /**
     * Try to validate the user from the request and return a jwt authentication result then.
     *
     * @param Request $request The request.
     *
     * @return JsonResponse
     *
     * @throws \RuntimeException For invalid user classes.
     *
     * @ApiDoc(
     *   section="auth",
     *   statusCodes = {
     *     200 = "When everything worked out ok",
     *     401 = "When the request was unauthorized."
     *   }
     * )
     * @ApiDescription(
     *   request={
     *    "ttl" = {
     *      "dataType" = "int",
     *      "description" = "The amount of seconds the token shall be valid or -1 for unlimited (default: 3600).",
     *      "required" = false
     *    }
     *   },
     *   response={
     *    "status" = {
     *      "dataType" = "choice",
     *      "description" = "OK or unauthorized",
     *      "format" = "['OK', 'unauthorized']",
     *    },
     *    "token" = {
     *      "dataType" = "string",
     *      "description" = "The JWT (only if status ok).",
     *    },
     *    "acl" = {
     *      "actualType" = "collection",
     *      "subType" = "string",
     *      "description" = "The roles of the authenticated user.",
     *    },
     *    "username" = {
     *      "actualType" = "string",
     *      "description" = "The username of the authenticated user.",
     *    },
     *   },
     * )
     */
    public function checkAuthAction(Request $request)
    {
        $user = $this->getUser();

        if (null !== $user) {
            if (!$user instanceof UserInformationInterface) {
                throw new \RuntimeException('Invalid user object');
            }

            $lifetime = $request->get('ttl', 3600);
            if (-1 === $lifetime) {
                $lifetime = null;
            }

            $token = $this->get('tenside.jwt_authenticator')->getTokenForData($user, $lifetime);
            return new JsonResponse(
                [
                    'status'    => 'OK',
                    'token'     => $token,
                    'acl'       => $user->getRoles(),
                    'username'  => $user->getUsername()
                ],
                JsonResponse::HTTP_OK,
                ['Authentication' => $token]
            );
        }

        return new JsonResponse(['status' => 'unauthorized'], JsonResponse::HTTP_UNAUTHORIZED);
    }
}
