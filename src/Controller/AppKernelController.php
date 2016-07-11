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

use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Tenside\Core\Util\PhpProcessSpawner;
use Tenside\CoreBundle\Annotation\ApiDescription;

/**
 * Controller for manipulating the AppKernel file.
 */
class AppKernelController extends AbstractController
{
    /**
     * Retrieve the AppKernel.php.
     *
     * @return Response
     *
     * @ApiDoc(
     *   section="files",
     *   statusCodes = {
     *     200 = "When everything worked out ok"
     *   },
     *   authentication = true,
     *   authenticationRoles = {
     *     "ROLE_EDIT_APP_KERNEL"
     *   }
     * )
     */
    public function getAppKernelAction()
    {
        return new Response(
            file_get_contents($this->getAppKernelPath()),
            200,
            ['Content-Type' => 'application/x-httpd-php-source']
        );
    }

    /**
     * Update the AppKernel.php with the given data if it is valid.
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
     *   },
     *   authentication = true,
     *   authenticationRoles = {
     *     "ROLE_EDIT_APP_KERNEL"
     *   }
     * )
     * @ApiDescription(
     *   response={
     *     "status" = {
     *       "dataType" = "string",
     *       "description" = "Either OK or ERROR"
     *     },
     *     "errors" = {
     *       "description" = "List of contained errors",
     *       "subType" = "object",
     *       "actualType" = "collection",
     *       "children" = {
     *         "line" = {
     *           "dataType" = "string",
     *           "description" = "The line number containing the error",
     *           "required" = true
     *         },
     *         "msg" = {
     *           "dataType" = "string",
     *           "description" = "The error message",
     *           "required" = true
     *         }
     *       }
     *     },
     *     "warnings" = {
     *       "description" = "List of contained warnings",
     *       "subType" = "object",
     *       "actualType" = "collection",
     *       "children" = {
     *         "line" = {
     *           "dataType" = "string",
     *           "description" = "The line number containing the warning",
     *           "required" = true
     *         },
     *         "msg" = {
     *           "dataType" = "string",
     *           "description" = "The error message",
     *           "required" = true
     *         }
     *       }
     *     }
     *   }
     * )
     */
    public function putAppKernelAction(Request $request)
    {
        $content = $request->getContent();
        $errors  = $this->checkAppKernel($content);

        if (!empty($errors['errors'])) {
            $errors['status'] = 'ERROR';
        } else {
            $errors['status'] = 'OK';

            $this->saveAppKernel($content);
        }

        return new JsonResponse($errors);
    }

    /**
     * Check the contents and return the error array.
     *
     * @param string $content The PHP content.
     *
     * @return array<string,string[]>
     */
    private function checkAppKernel($content)
    {
        if (substr($content, 0, 5) !== '<?php') {
            return [
                'errors' => [
                    [
                        'line' => '1',
                        'msg'  => 'AppKernel.php must start with "<?php" to work correctly'
                    ]
                ],
                'warnings' => []
            ];
        }

        $config = $this->getTensideConfig();

        $home    = $this->getTensideHome();
        $process = PhpProcessSpawner::create($config, $home)->spawn(
            [
                '-l',
            ]
        );

        $process->setInput($content);
        $process->run();

        if (!$process->isSuccessful()) {
            $output = $process->getErrorOutput() . PHP_EOL . $process->getOutput();
            if ((bool) preg_match(
                '/(?:Parse|Fatal) error:\s*syntax error,(.+?)\s+in\s+.+?\s*line\s+(\d+)/',
                $output,
                $match
            )) {
                return [
                    'errors' => [
                        [
                            'line' => (int) $match[2],
                            'msg'  => $match[1]
                        ]
                    ],
                    'warnings' => []
                ];
            }

            // This might expose sensitive data but as we are in authenticated context, this is ok.
            return [
                'errors' => [
                    [
                        'line' => '0',
                        'msg'  => $output
                    ]
                ],
                'warnings' => []
            ];
        }

        return [];
    }

    /**
     * Retrieve the path to AppKernel.php
     *
     * @return string
     */
    private function getAppKernelPath()
    {
        return $this->getTensideHome() . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'AppKernel.php';
    }

    /**
     * Retrieve a file object for the AppKernel.php.
     *
     * @param string $content The PHP content.
     *
     * @return void
     */
    private function saveAppKernel($content)
    {
        $file = new \SplFileObject($this->getAppKernelPath(), 'r+');
        $file->ftruncate(0);
        $file->fwrite($content);
        unset($file);
    }
}
