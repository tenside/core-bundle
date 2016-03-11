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

namespace Tenside\CoreBundle\Controller;

use Composer\Semver\VersionParser;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Tenside\Core\Util\JsonArray;
use Tenside\CoreBundle\Annotation\ApiDescription;

/**
 * Validation of version constraints.
 */
class VersionConstraintController extends Controller
{
    /**
     * Try to validate the version constraint.
     *
     * @param Request $request The request.
     *
     * @return JsonResponse
     *
     * @throws \RuntimeException For invalid user classes.
     *
     * @ApiDoc(
     *   section="misc",
     *   statusCodes = {
     *     200 = "When everything worked out ok",
     *     400 = "When the request payload was invalid."
     *   }
     * )
     * @ApiDescription(
     *   request={
     *     "constraint" = {
     *       "description" = "The constraint to test.",
     *       "dataType" = "string",
     *       "required" = true
     *     }
     *   },
     *   response={
     *    "status" = {
     *      "dataType" = "choice",
     *      "description" = "ok or error",
     *      "format" = "['ok', 'error']",
     *    },
     *    "error" = {
     *      "dataType" = "string",
     *      "description" = "The error message (if any).",
     *    }
     *   }
     * )
     */
    public function checkVersionConstraintAction(Request $request)
    {
        try {
            $inputData = new JsonArray($request->getContent());
        } catch (\Exception $exception) {
            return new JsonResponse(
                [
                    'status' => 'error',
                    'error'  => 'invalid payload'
                ],
                JsonResponse::HTTP_BAD_REQUEST
            );
        }

        $versionParser = new VersionParser();

        if (!$inputData->has('constraint')) {
            return new JsonResponse(
                [
                    'status' => 'error',
                    'error'  => 'invalid payload'
                ],
                JsonResponse::HTTP_BAD_REQUEST
            );
        }

        try {
            $versionParser->parseConstraints($inputData->get('constraint'));
        } catch (\Exception $exception) {
            return new JsonResponse(
                [
                    'status' => 'error',
                    'error'  => $exception->getMessage()
                ],
                JsonResponse::HTTP_OK
            );
        }

        return new JsonResponse(
            [
                'status' => 'ok',
            ],
            JsonResponse::HTTP_OK
        );
    }
}
