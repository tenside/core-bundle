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

use Composer\Util\RemoteFilesystem;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotAcceptableHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Tenside\CoreBundle\Security\UserInformation;
use Tenside\CoreBundle\Security\UserInformationInterface;
use Tenside\Core\Task\Composer\InstallTask;
use Tenside\Core\Util\JsonArray;
use Tenside\CoreBundle\Annotation\ApiDescription;

/**
 * Controller for manipulating the composer.json file.
 */
class InstallProjectController extends AbstractController
{
    /**
     * Configure tenside.
     *
     * NOTE: This method will become inaccessible after the first successful call.
     *
     * @param Request $request The request.
     *
     * @return JsonResponse
     *
     * @throws NotAcceptableHttpException When the configuration is already complete.
     *
     * @ApiDoc(
     *   section="install",
     *   statusCodes = {
     *     201 = "When everything worked out ok",
     *     406 = "When the configuration is already complete"
     *   }
     * )
     * @ApiDescription(
     *   request={
     *     "credentials" = {
     *       "description" = "The credentials of the admin user.",
     *       "children" = {
     *         "secret" = {
     *           "dataType" = "string",
     *           "description" = "The secret to use for encryption and signing.",
     *           "required" = true
     *         },
     *         "username" = {
     *           "dataType" = "string",
     *           "description" = "The name of the admin user.",
     *           "required" = true
     *         },
     *         "password" = {
     *           "dataType" = "string",
     *           "description" = "The password to use for the admin.",
     *           "required" = false
     *         }
     *       }
     *     },
     *     "configuration" = {
     *       "description" = "The application configuration.",
     *       "children" = {
     *         "php_cli" = {
     *           "dataType" = "string",
     *           "description" = "The PHP interpreter to run on command line."
     *         },
     *         "php_cli_arguments" = {
     *           "dataType" = "string",
     *           "description" = "Command line arguments to add."
     *         }
     *       }
     *     }
     *   },
     *   response={
     *     "token" = {
     *       "dataType" = "string",
     *       "description" = "The API token for the created user"
     *     }
     *   }
     * )
     */
    public function configureAction(Request $request)
    {
        if ($this->get('tenside.status')->isTensideConfigured()) {
            throw new NotAcceptableHttpException('Already configured.');
        }
        $inputData = new JsonArray($request->getContent());

        $secret = bin2hex(random_bytes(40));
        if ($inputData->has('credentials/secret')) {
            $secret = $inputData->get('credentials/secret');
        }

        // Add tenside configuration.
        $tensideConfig = $this->get('tenside.config');
        $tensideConfig->set('secret', $secret);

        if ($inputData->has('configuration')) {
            $this->handleConfiguration($inputData->get('configuration', true));
        }
        $user = $this->createUser($inputData->get('credentials/username'), $inputData->get('credentials/password'));

        return new JsonResponse(
            [
                'status' => 'OK',
                'token'  => $this->get('tenside.jwt_authenticator')->getTokenForData($user)
            ],
            JsonResponse::HTTP_CREATED
        );
    }

    /**
     * Create a project.
     *
     * NOTE: This method will become inaccessible after the returned task has been run successfully.
     *
     * @param Request $request The request.
     *
     * @return JsonResponse
     *
     * @throws NotAcceptableHttpException When the installation is already complete.
     *
     * @ApiDoc(
     *   section="install",
     *   statusCodes = {
     *     201 = "When everything worked out ok",
     *     406 = "When the installation is already been completed"
     *   },
     * )
     * @ApiDescription(
     *   request={
     *     "project" = {
     *       "description" = "The project to install.",
     *       "children" = {
     *         "name" = {
     *           "dataType" = "string",
     *           "description" = "The name of the project to install.",
     *           "required" = true
     *         },
     *         "version" = {
     *           "dataType" = "string",
     *           "description" = "The version of the project to install (optional).",
     *           "required" = false
     *         }
     *       }
     *     }
     *   },
     *   response={
     *     "task" = {
     *       "dataType" = "string",
     *       "description" = "The id of the created install task"
     *     }
     *   }
     * )
     */
    public function createProjectAction(Request $request)
    {
        $status = $this->get('tenside.status');
        if (!$status->isTensideConfigured()) {
            throw new NotAcceptableHttpException('Need to configure first.');
        }

        $this->checkUninstalled();
        $result = [];
        $header = [];

        $installDir = $this->getTensideHome();
        $inputData  = new JsonArray($request->getContent());
        $taskData   = new JsonArray();

        $taskData->set(InstallTask::SETTING_DESTINATION_DIR, $installDir);
        $taskData->set(InstallTask::SETTING_PACKAGE, $inputData->get('project/name'));
        if ($version = $inputData->get('project/version')) {
            $taskData->set(InstallTask::SETTING_VERSION, $version);
        }

        $taskId             = $this->getTensideTasks()->queue('install', $taskData);
        $result['task']     = $taskId;
        $header['Location'] = $this->generateUrl(
            'task_get',
            ['taskId' => $taskId],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        return new JsonResponse(
            [
                'status' => 'OK',
                'task'   => $taskId
            ],
            JsonResponse::HTTP_CREATED,
            $header
        );
    }

    /**
     * This is a gateway to the self test controller available only at install time.
     *
     * This is just here as the other route is protected with login.
     *
     * NOTE: This method will become inaccessible as soon as the installation is complete.
     *
     * @return JsonResponse
     *
     * @ApiDoc(
     *   section="install",
     *   description="Install time - self test.",
     *   statusCodes = {
     *     201 = "When everything worked out ok",
     *     406 = "When the installation is already complete"
     *   },
     * )
     * @ApiDescription(
     *   response={
     *     "results" = {
     *       "actualType" = "collection",
     *       "subType" = "object",
     *       "description" = "The test results.",
     *       "children" = {
     *         "name" = {
     *           "dataType" = "string",
     *           "description" = "The name of the test"
     *         },
     *         "state" = {
     *           "dataType" = "choice",
     *           "description" = "The test result state.",
     *           "format" = "[FAIL|SKIPPED|SUCCESS|WARNING]"
     *         },
     *         "message" = {
     *           "dataType" = "string",
     *           "description" = "The detailed message of the test result."
     *         },
     *         "explain" = {
     *           "dataType" = "string",
     *           "description" = "Optional description that could hint any problems and/or explain the error further."
     *         }
     *       }
     *     }
     *   }
     * )
     */
    public function getSelfTestAction()
    {
        $this->checkUninstalled();

        return $this->forward('TensideCoreBundle:SelfTest:getAllTests');
    }

    /**
     * Install time gateway to the auto config.
     *
     * This is just here as the other route is protected with login.
     *
     * NOTE: This method will become inaccessible as soon as the installation is complete.
     *
     * @return JsonResponse
     *
     * @ApiDoc(
     *   section="install",
     *   description="Install time - auto config.",
     *   statusCodes = {
     *     201 = "When everything worked out ok",
     *     406 = "When the installation is already complete"
     *   },
     * )
     * @ApiDescription(
     *   response={
     *     "php_cli" = {
     *       "dataType" = "string",
     *       "description" = "The PHP interpreter to run on command line."
     *     },
     *     "php_cli_arguments" = {
     *       "dataType" = "string",
     *       "description" = "Command line arguments to add."
     *     }
     *   }
     * )
     */
    public function getAutoConfigAction()
    {
        $this->checkUninstalled();

        return $this->forward('TensideCoreBundle:SelfTest:getAutoConfig');
    }

    /**
     * Retrieve the available versions of a package.
     *
     * NOTE: This method will become inaccessible as soon as the installation is complete.
     *
     * @param string $vendor  The vendor name of the package.
     *
     * @param string $project The name of the package.
     *
     * @return JsonResponse
     *
     * @ApiDoc(
     *   section="install",
     *   statusCodes = {
     *     201 = "When everything worked out ok",
     *     406 = "When the installation is already complete"
     *   },
     * )
     * @ApiDescription(
     *   response={
     *     "versions" = {
     *       "actualType" = "collection",
     *       "subType" = "object",
     *       "description" = "The list of versions",
     *       "children" = {
     *         "name" = {
     *           "dataType" = "string",
     *           "description" = "The name of the package"
     *         },
     *         "version" = {
     *           "dataType" = "string",
     *           "description" = "The version of the package"
     *         },
     *         "version_normalized" = {
     *           "dataType" = "string",
     *           "description" = "The normalized version of the package"
     *         },
     *         "reference" = {
     *           "dataType" = "string",
     *           "description" = "The optional reference"
     *         }
     *       }
     *     }
     *   }
     * )
     */
    public function getProjectVersionsAction($vendor, $project)
    {
        $this->checkUninstalled();

        $url     = sprintf('https://packagist.org/packages/%s/%s.json', $vendor, $project);
        $rfs     = new RemoteFilesystem($this->getInputOutput());
        $results = $rfs->getContents($url, $url);
        $data    = new JsonArray($results);

        $versions = [];

        foreach ($data->get('package/versions') as $information) {
            $version = [
                'name'               => $information['name'],
                'version'            => $information['version'],
                'version_normalized' => $information['version_normalized'],
            ];

            $normalized = $information['version'];
            if ('dev-' === substr($normalized, 0, 4)) {
                if (isset($information['extra']['branch-alias'][$normalized])) {
                    $version['version_normalized'] = $information['extra']['branch-alias'][$normalized];
                }
            }

            if (isset($information['source']['reference'])) {
                $version['reference'] = $information['source']['reference'];
            } elseif (isset($information['dist']['reference'])) {
                $version['reference'] = $information['dist']['reference'];
            }

            $versions[] = $version;
        }

        return new JsonResponse(
            [
                'status' => 'OK',
                'versions' => $versions
            ]
        );
    }

    /**
     * Check if installation is new, partial or complete.
     *
     * @return JsonResponse
     *
     * @ApiDoc(
     *   section="install",
     *   description="This method provides information about the installation.",
     *   statusCodes = {
     *     201 = "When everything worked out ok",
     *     406 = "When the installation is already complete"
     *   },
     * )
     * @ApiDescription(
     *   response={
     *     "state" = {
     *       "children" = {
     *         "tenside_configured" = {
     *           "dataType" = "bool",
     *           "description" = "Flag if tenside has been completely configured."
     *         },
     *         "project_created" = {
     *           "dataType" = "bool",
     *           "description" = "Flag determining if a composer.json is present."
     *         },
     *         "project_installed" = {
     *           "dataType" = "bool",
     *           "description" = "Flag determining if the composer project has been installed (vendor present)."
     *         }
     *       }
     *     },
     *     "status" = {
     *       "dataType" = "string",
     *       "description" = "Either OK or ERROR"
     *     },
     *     "message" = {
     *       "dataType" = "string",
     *       "description" = "The API error message if any (only present when status is ERROR)"
     *     }
     *   }
     * )
     */
    public function getInstallationStateAction()
    {
        $status = $this->get('tenside.status');

        return new JsonResponse(
            [
                'state'  => [
                    'tenside_configured' => $status->isTensideConfigured(),
                    'project_created'    => $status->isProjectPresent(),
                    'project_installed'  => $status->isProjectInstalled(),
                ],
                'status' => 'OK'
            ]
        );
    }

    /**
     * Ensure that we are not installed yet.
     *
     * @return void
     *
     * @throws NotAcceptableHttpException When the installation is already complete.
     */
    private function checkUninstalled()
    {
        if ($this->get('tenside.status')->isComplete()) {
            throw new NotAcceptableHttpException('Already installed in ' . $this->getTensideHome());
        }
    }

    /**
     * Add an user to the database.
     *
     * @param string $username The username.
     *
     * @param string $password The password.
     *
     * @return UserInformation
     */
    private function createUser($username, $password)
    {
        $user = new UserInformation(
            [
                'username' => $username,
                'acl'      => UserInformationInterface::ROLE_ALL
            ]
        );

        $user->set('password', $this->get('security.password_encoder')->encodePassword($user, $password));

        $user = $this->get('tenside.user_provider')->addUser($user)->refreshUser($user);

        return $user;
    }

    /**
     * Absorb the passed configuration.
     *
     * @param array $configuration The configuration to absorb.
     *
     * @return void
     */
    private function handleConfiguration($configuration)
    {
        $tensideConfig = $this->get('tenside.config');

        if (isset($configuration['php_cli'])) {
            $tensideConfig->setPhpCliBinary($configuration['php_cli']);
        }

        if (isset($configuration['php_cli_arguments'])) {
            $tensideConfig->setPhpCliArguments($configuration['php_cli_arguments']);
        }

        if (isset($configuration['php_cli_environment'])) {
            $tensideConfig->setPhpCliEnvironment($configuration['php_cli_environment']);
        }

        if (isset($configuration['php_force_background'])) {
            $tensideConfig->setForceToBackground($configuration['php_force_background']);
        }

        if (isset($configuration['php_can_fork'])) {
            $tensideConfig->setForkingAvailable($configuration['php_can_fork']);
        }
    }
}
