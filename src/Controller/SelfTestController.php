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
use Tenside\CoreBundle\Annotation\ApiDescription;
use Tenside\Core\SelfTest\Cli\SelfTestCanSpawnProcesses;
use Tenside\Core\SelfTest\Cli\SelfTestCliArguments;
use Tenside\Core\SelfTest\Cli\SelfTestCliRuntime;
use Tenside\Core\SelfTest\Generic\SelfTestCalledViaHttps;
use Tenside\Core\SelfTest\Generic\SelfTestFileOwnerMatches;
use Tenside\Core\SelfTest\Php\SelfTestAllowUrlFopenEnabled;
use Tenside\Core\SelfTest\Php\SelfTestSuhosin;
use Tenside\Core\SelfTest\SelfTest;

/**
 * This class provides the self test entry points.
 */
class SelfTestController extends AbstractController
{
    /**
     * Retrieve the results of all tests.
     *
     * @return JsonResponse
     *
     * @ApiDoc(
     *   section="selftest",
     *   statusCodes = {
     *     200 = "When everything worked out ok"
     *   },
     *   authentication = true,
     *   authenticationRoles = {
     *     "ROLE_MANIPULATE_REQUIREMENTS"
     *   }
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
    public function getAllTestsAction()
    {
        $tester = $this->prepareTests();

        $data = ['results' => []];
        foreach ($tester->perform() as $result) {
            $data['results'][] = [
                'name'    => $this->testClassToSlug($result->getTestClass()),
                'state'   => $result->getState(),
                'message' => $result->getMessage(),
                'explain' => $result->getExplain(),
            ];
        }

        return JsonResponse::create($data);
    }

    /**
     * Retrieve the automatic generated tenside configuration.
     *
     * The automatic configuration consists of several values.
     *
     * @return JsonResponse
     *
     * @ApiDoc(
     *   section="selftest",
     *   statusCodes = {
     *     200 = "When everything worked out ok"
     *   },
     *   authentication = true,
     *   authenticationRoles = {
     *     "ROLE_MANIPULATE_REQUIREMENTS"
     *   }
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
        $tester = $this->prepareTests();
        $tester->perform();

        $config = $tester->getAutoConfig();
        $result = [];

        if ($phpCli = $config->getPhpInterpreter()) {
            $result['php_cli'] = $phpCli;
        }
        if ($phpArguments = $config->getCommandLineArguments()) {
            $result['php_cli_arguments'] = $phpArguments;
        }

        return JsonResponse::create($result);
    }

    /**
     * Create a slug from a test class.
     *
     * @param string $className The class name to convert.
     *
     * @return string
     */
    private function testClassToSlug($className)
    {
        $className = basename(str_replace('\\', '/', $className));

        if ('SelfTest' === substr($className, 0, 8)) {
            $className = substr($className, 8);
        }

        $className = strtolower(substr(preg_replace('#([A-Z])#', '-$1', $className), 1));

        return $className;
    }

    /**
     * Prepare the tests.
     *
     * @return SelfTest
     */
    private function prepareTests()
    {
        $tester = new SelfTest();

        $tester->addTest(new SelfTestCalledViaHttps());
        $tester->addTest(new SelfTestFileOwnerMatches());
        $tester->addTest(new SelfTestAllowUrlFopenEnabled());
        $tester->addTest(new SelfTestSuhosin());
        $tester->addTest(new SelfTestCanSpawnProcesses());
        $tester->addTest(new SelfTestCliRuntime());
        $tester->addTest(new SelfTestCliArguments());

        return $tester;
    }
}
