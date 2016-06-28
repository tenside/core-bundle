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
use Tenside\Core\Util\JsonArray;
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
     *   },
     *   parameters = {
     *     {
     *       "name": "ttl",
     *       "dataType" = "string",
     *       "format" = "\d+",
     *       "description" = "The amount of seconds the token shall be valid or -1 for unlimited (default: 3600).",
     *       "required" = false
     *     },
     *     {
     *       "name": "username",
     *       "dataType" = "string",
     *       "description" = "The username.",
     *       "required" = true
     *     },
     *     {
     *       "name": "password",
     *       "dataType" = "string",
     *       "description" = "The pssword.",
     *       "required" = true
     *     }
     *   }
     * )
     * @ApiDescription(
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

            $lifetime = $this->determineLifeTime($request);

            $token = $this->get('tenside.jwt_authenticator')->getTokenForData($user, $lifetime);
            return new JsonResponse(
                [
                    'status'    => 'OK',
                    'token'     => $token,
                    'acl'       => $user->getRoles(),
                    'username'  => $user->getUsername(),
                    'ttl'       => (null === $lifetime) ? 'unlimited' : date('r', (time() + $lifetime))
                ],
                JsonResponse::HTTP_OK,
                ['Authentication' => $token]
            );
        }

        return new JsonResponse(['status' => 'unauthorized'], JsonResponse::HTTP_UNAUTHORIZED);
    }

    /**
     * Determine the life time for the token.
     *
     * This examines the GET parameters if a field "ttl" has been set.
     * If not, it examines the JSON post data for a field named ttl.
     *
     * @param Request $request The request.
     *
     * @return int|null
     */
    private function determineLifeTime(Request $request)
    {
        if ($lifetime = $request->query->getInt('ttl')) {
            return $this->revertToNullOnMinusOne($lifetime);
        }

        try {
            $inputData = new JsonArray($request->getContent());
            if ($inputData->has('ttl')) {
                return $this->revertToNullOnMinusOne(intval($inputData->get('ttl')));
            }
        } catch (\Exception $e) {
            // Swallow exception, we need to return a defined result.
        }

        return 3600;
    }

    /**
     * Return the value if it is different than -1, null otherwise.
     *
     * @param int $lifetime The life time.
     *
     * @return null|int
     */
    private function revertToNullOnMinusOne($lifetime)
    {
        if (-1 === $lifetime) {
            return null;
        }

        return $lifetime;
    }
}
