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

use Composer\IO\BufferIO;
use Composer\Util\ConfigValidator;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Tenside\CoreBundle\Annotation\ApiDescription;

/**
 * Controller for manipulating the composer.json file.
 */
class ComposerJsonController extends AbstractController
{
    /**
     * Retrieve the composer.json.
     *
     * @return Response
     *
     * @ApiDoc(
     *   section="files",
     *   statusCodes = {
     *     200 = "When everything worked out ok"
     *   }
     * )
     */
    public function getComposerJsonAction()
    {
        return new Response($this->get('tenside.composer_json'));
    }

    /**
     * Update the composer.json with the given data if it is valid.
     *
     * The whole submitted data is used as file.
     *
     * @param Request $request The request to process.
     *
     * @return JsonResponse
     *
     * @ApiDoc(
     *   section="files",
     *   statusCodes = {
     *     200 = "When everything worked out ok"
     *   }
     * )
     * @ApiDescription(
     *   response={
     *     "status" = {
     *       "dataType" = "string",
     *       "description" = "Either OK or ERROR"
     *     },
     *     "error" = {
     *       "description" = "List of contained errors",
     *       "subType" = "string",
     *       "actualType" = "collection"
     *     },
     *     "warning" = {
     *       "description" = "List of contained warnings",
     *       "subType" = "string",
     *       "actualType" = "collection"
     *     }
     *   }
     * )
     */
    public function putComposerJsonAction(Request $request)
    {
        $errors = $this->checkComposerJson($request->getContent());

        if (!empty($errors['error'])) {
            $errors['status'] = 'ERROR';
        } else {
            $errors['status'] = 'OK';

            $file = $this->get('tenside.composer_json');
            $file->load($request->getContent());
            $file->save();
        }

        return new JsonResponse($errors);
    }

    /**
     * Check the json contents and return the error array.
     *
     * @param string $content The Json content.
     *
     * @return array<string,string[]>
     */
    private function checkComposerJson($content)
    {
        $tempFile = $this->get('tenside.home')->tensideDataDir() . '/composer.json.tmp';
        file_put_contents($tempFile, $content);

        $validator = new ConfigValidator(new BufferIO());

        list($errors, $publishErrors, $warnings) = $validator->validate($tempFile);
        unlink($tempFile);

        $errors = array_merge($errors, $publishErrors);

        $errors   = str_replace(dirname($tempFile), '', $errors);
        $warnings = str_replace(dirname($tempFile), '', $warnings);

        return [
            'error'   => $errors,
            'warning' => $warnings,
        ];
    }
}
