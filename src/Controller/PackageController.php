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

use Composer\Package\AliasPackage;
use Composer\Package\PackageInterface;
use Composer\Repository\RepositoryInterface;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotAcceptableHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tenside\Core\Composer\PackageConverter;
use Tenside\Core\Util\JsonArray;
use Tenside\CoreBundle\Annotation\ApiDescription;

/**
 * List and manipulate the installed packages.
 */
class PackageController extends AbstractController
{
    /**
     * Retrieve the package list.
     *
     * @param Request $request The request to process.
     *
     * @return JsonResponse
     *
     * @ApiDoc(
     *   section="package",
     *   statusCodes = {
     *     200 = "When everything worked out ok"
     *   },
     *   authentication = true,
     *   authenticationRoles = {
     *     "ROLE_MANIPULATE_REQUIREMENTS"
     *   },
     *   filters = {
     *     {
     *       "name"="all",
     *       "description"="If present, all packages will get listed, only directly required ones otherwise."
     *     }
     *   }
     * )
     * @ApiDescription(
     *   response={
     *     "package name 1...n" = {
     *       "actualType" = "object",
     *       "subType" = "object",
     *       "description" = "The content of the packages",
     *       "children" = {
     *         "name" = {
     *           "dataType" = "string",
     *           "description" = "The name of the package"
     *         },
     *         "version" = {
     *           "dataType" = "string",
     *           "description" = "The version of the package"
     *         },
     *         "constraint" = {
     *           "dataType" = "string",
     *           "description" = "The constraint of the package (when package is installed)"
     *         },
     *         "type" = {
     *           "dataType" = "string",
     *           "description" = "The noted package type"
     *         },
     *         "locked" = {
     *           "dataType" = "string",
     *           "description" = "Flag if the package has been locked for updates"
     *         },
     *         "time" = {
     *           "dataType" = "datetime",
     *           "description" = "The release date"
     *         },
     *         "upgrade_version" = {
     *           "dataType" = "string",
     *           "description" = "The version available for upgrade (optional, if any)"
     *         },
     *         "description" = {
     *           "dataType" = "string",
     *           "description" = "The package description"
     *         },
     *         "license" = {
     *           "actualType" = "collection",
     *           "subType" = "string",
     *           "description" = "The licenses"
     *         },
     *         "keywords" = {
     *           "actualType" = "collection",
     *           "subType" = "string",
     *           "description" = "The keywords"
     *         },
     *         "homepage" = {
     *           "dataType" = "string",
     *           "description" = "The support website (optional, if any)"
     *         },
     *         "authors" = {
     *           "actualType" = "collection",
     *           "subType" = "object",
     *           "description" = "The authors",
     *           "children" = {
     *             "name" = {
     *               "dataType" = "string",
     *               "description" = "Full name of the author (optional, if any)"
     *             },
     *             "homepage" = {
     *               "dataType" = "string",
     *               "description" = "Email address of the author (optional, if any)"
     *             },
     *             "email" = {
     *               "dataType" = "string",
     *               "description" = "Homepage URL for the author (optional, if any)"
     *             },
     *             "role" = {
     *               "dataType" = "string",
     *               "description" = "Author's role in the project (optional, if any)"
     *             }
     *           }
     *         },
     *         "support" = {
     *           "actualType" = "collection",
     *           "subType" = "object",
     *           "description" = "The support options",
     *           "children" = {
     *             "email" = {
     *               "dataType" = "string",
     *               "description" = "Email address for support (optional, if any)"
     *             },
     *             "issues" = {
     *               "dataType" = "string",
     *               "description" = "URL to the issue tracker (optional, if any)"
     *             },
     *             "forum" = {
     *               "dataType" = "string",
     *               "description" = "URL to the forum (optional, if any)"
     *             },
     *             "wiki" = {
     *               "dataType" = "string",
     *               "description" = "URL to the wiki (optional, if any)"
     *             },
     *             "irc" = {
     *               "dataType" = "string",
     *               "description" = "IRC channel for support, as irc://server/channel (optional, if any)"
     *             },
     *             "source" = {
     *               "dataType" = "string",
     *               "description" = "URL to browse or download the sources (optional, if any)"
     *             },
     *             "docs" = {
     *               "dataType" = "string",
     *               "description" = "URL to the documentation (optional, if any)"
     *             },
     *           }
     *         },
     *         "extra" = {
     *             "dataType" = "collection",
     *             "description" = "The extra data from composer.json"
     *         },
     *         "abandoned" = {
     *           "dataType" = "boolean",
     *           "description" = "Flag if this package is abandoned"
     *         },
     *         "replacement" = {
     *           "dataType" = "string",
     *           "description" = "Replacement for this package (optional, if any)"
     *         }
     *       }
     *     }
     *   }
     * )
     */
    public function packageListAction(Request $request)
    {
        $composer  = $this->getComposer();
        $converter = new PackageConverter($composer->getPackage());
        $upgrades  = null;

        $packages = $converter->convertRepositoryToArray(
            $composer->getRepositoryManager()->getLocalRepository(),
            !$request->query->has('all'),
            $upgrades
        );
        foreach ($packages->getEntries('/') as $packageName) {
            $packages->set($packageName . '/installed', $packages->get($packageName . '/version'));
        }
        $packages = $packages->getData();
        ksort($packages);

        return new JsonResponse($packages, 200);
    }

    /**
     * Retrieve a package.
     *
     * @param string $vendor  The name of the vendor.
     *
     * @param string $package The name of the package.
     *
     * @return JsonResponse
     *
     * @throws NotFoundHttpException When the package has not been found.
     *
     * @ApiDoc(
     *   section="package",
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
     *     "name" = {
     *       "dataType" = "string",
     *       "description" = "The name of the package"
     *     },
     *     "version" = {
     *       "dataType" = "string",
     *       "description" = "The version of the package"
     *     },
     *     "constraint" = {
     *       "dataType" = "string",
     *       "description" = "The constraint of the package (when package is installed)"
     *     },
     *     "type" = {
     *       "dataType" = "string",
     *       "description" = "The noted package type"
     *     },
     *     "locked" = {
     *       "dataType" = "string",
     *       "description" = "Flag if the package has been locked for updates"
     *     },
     *     "time" = {
     *       "dataType" = "datetime",
     *       "description" = "The release date"
     *     },
     *     "upgrade_version" = {
     *       "dataType" = "string",
     *       "description" = "The version available for upgrade (optional, if any)"
     *     },
     *     "description" = {
     *       "dataType" = "string",
     *       "description" = "The package description"
     *     },
     *     "license" = {
     *       "actualType" = "collection",
     *       "subType" = "string",
     *       "description" = "The licenses"
     *     },
     *     "keywords" = {
     *       "actualType" = "collection",
     *       "subType" = "string",
     *       "description" = "The keywords"
     *     },
     *     "homepage" = {
     *       "dataType" = "string",
     *       "description" = "The support website (optional, if any)"
     *     },
     *     "authors" = {
     *       "actualType" = "collection",
     *       "subType" = "object",
     *       "description" = "The authors",
     *       "children" = {
     *         "name" = {
     *           "dataType" = "string",
     *           "description" = "Full name of the author (optional, if any)"
     *         },
     *         "homepage" = {
     *           "dataType" = "string",
     *           "description" = "Email address of the author (optional, if any)"
     *         },
     *         "email" = {
     *           "dataType" = "string",
     *           "description" = "Homepage URL for the author (optional, if any)"
     *         },
     *         "role" = {
     *           "dataType" = "string",
     *           "description" = "Author's role in the project (optional, if any)"
     *         }
     *       }
     *     },
     *     "support" = {
     *       "actualType" = "collection",
     *       "subType" = "object",
     *       "description" = "The support options",
     *       "children" = {
     *         "email" = {
     *           "dataType" = "string",
     *           "description" = "Email address for support (optional, if any)"
     *         },
     *         "issues" = {
     *           "dataType" = "string",
     *           "description" = "URL to the issue tracker (optional, if any)"
     *         },
     *         "forum" = {
     *           "dataType" = "string",
     *           "description" = "URL to the forum (optional, if any)"
     *         },
     *         "wiki" = {
     *           "dataType" = "string",
     *           "description" = "URL to the wiki (optional, if any)"
     *         },
     *         "irc" = {
     *           "dataType" = "string",
     *           "description" = "IRC channel for support, as irc://server/channel (optional, if any)"
     *         },
     *         "source" = {
     *           "dataType" = "string",
     *           "description" = "URL to browse or download the sources (optional, if any)"
     *         },
     *         "docs" = {
     *           "dataType" = "string",
     *           "description" = "URL to the documentation (optional, if any)"
     *         },
     *       }
     *     },
     *     "abandoned" = {
     *       "dataType" = "boolean",
     *       "description" = "Flag if this package is abandoned"
     *     },
     *     "replacement" = {
     *       "dataType" = "string",
     *       "description" = "Replacement for this package (optional, if any)"
     *     }
     *   }
     * )
     */
    public function getPackageAction($vendor, $package)
    {
        $packageName = $vendor . '/' . $package;
        $composer    = $this->getComposer();

        if ($package   = $this->findPackage($packageName, $composer->getRepositoryManager()->getLocalRepository())) {
            $converter = new PackageConverter($composer->getPackage());
            return new JsonResponse($converter->convertPackageToArray($package), 200);
        }

        throw new NotFoundHttpException('Package ' . $packageName . ' not found.');
    }

    /**
     * Update the information of a package in the composer.json.
     *
     * Note that the payload name of the package must match the vendor and package passed as parameter.
     *
     * @param string  $vendor  The name of the vendor.
     *
     * @param string  $package The name of the package.
     *
     * @param Request $request The request to process.
     *
     * @return JsonResponse
     *
     * @throws NotAcceptableHttpException When the passed payload is invalid.
     * @throws NotFoundHttpException When the package has not been found.
     *
     * @ApiDoc(
     *   section="package",
     *   statusCodes = {
     *     200 = "When everything worked out ok"
     *   },
     *   authentication = true,
     *   authenticationRoles = {
     *     "ROLE_MANIPULATE_REQUIREMENTS"
     *   }
     * )
     *
     * @ApiDescription(
     *   request={
     *     "name" = {
     *       "dataType" = "string",
     *       "description" = "The name of the package",
     *       "required" = true
     *     },
     *     "constraint" = {
     *       "dataType" = "string",
     *       "description" = "The constraint of the package (when package is installed)",
     *       "required" = true
     *     },
     *     "locked" = {
     *       "dataType" = "string",
     *       "description" = "Flag if the package has been locked for updates",
     *       "required" = true
     *     },
     *   },
     *   response={
     *     "name" = {
     *       "dataType" = "string",
     *       "description" = "The name of the package"
     *     },
     *     "version" = {
     *       "dataType" = "string",
     *       "description" = "The version of the package"
     *     },
     *     "constraint" = {
     *       "dataType" = "string",
     *       "description" = "The constraint of the package (when package is installed)"
     *     },
     *     "type" = {
     *       "dataType" = "string",
     *       "description" = "The noted package type"
     *     },
     *     "locked" = {
     *       "dataType" = "string",
     *       "description" = "Flag if the package has been locked for updates"
     *     },
     *     "time" = {
     *       "dataType" = "datetime",
     *       "description" = "The release date"
     *     },
     *     "upgrade_version" = {
     *       "dataType" = "string",
     *       "description" = "The version available for upgrade (optional, if any)"
     *     },
     *     "description" = {
     *       "dataType" = "string",
     *       "description" = "The package description"
     *     },
     *     "license" = {
     *       "actualType" = "collection",
     *       "subType" = "string",
     *       "description" = "The licenses"
     *     },
     *     "keywords" = {
     *       "actualType" = "collection",
     *       "subType" = "string",
     *       "description" = "The keywords"
     *     },
     *     "homepage" = {
     *       "dataType" = "string",
     *       "description" = "The support website (optional, if any)"
     *     },
     *     "authors" = {
     *       "actualType" = "collection",
     *       "subType" = "object",
     *       "description" = "The authors",
     *       "children" = {
     *         "name" = {
     *           "dataType" = "string",
     *           "description" = "Full name of the author (optional, if any)"
     *         },
     *         "homepage" = {
     *           "dataType" = "string",
     *           "description" = "Email address of the author (optional, if any)"
     *         },
     *         "email" = {
     *           "dataType" = "string",
     *           "description" = "Homepage URL for the author (optional, if any)"
     *         },
     *         "role" = {
     *           "dataType" = "string",
     *           "description" = "Author's role in the project (optional, if any)"
     *         }
     *       }
     *     },
     *     "support" = {
     *       "actualType" = "collection",
     *       "subType" = "object",
     *       "description" = "The support options",
     *       "children" = {
     *         "email" = {
     *           "dataType" = "string",
     *           "description" = "Email address for support (optional, if any)"
     *         },
     *         "issues" = {
     *           "dataType" = "string",
     *           "description" = "URL to the issue tracker (optional, if any)"
     *         },
     *         "forum" = {
     *           "dataType" = "string",
     *           "description" = "URL to the forum (optional, if any)"
     *         },
     *         "wiki" = {
     *           "dataType" = "string",
     *           "description" = "URL to the wiki (optional, if any)"
     *         },
     *         "irc" = {
     *           "dataType" = "string",
     *           "description" = "IRC channel for support, as irc://server/channel (optional, if any)"
     *         },
     *         "source" = {
     *           "dataType" = "string",
     *           "description" = "URL to browse or download the sources (optional, if any)"
     *         },
     *         "docs" = {
     *           "dataType" = "string",
     *           "description" = "URL to the documentation (optional, if any)"
     *         },
     *       }
     *     },
     *     "abandoned" = {
     *       "dataType" = "boolean",
     *       "description" = "Flag if this package is abandoned"
     *     },
     *     "replacement" = {
     *       "dataType" = "string",
     *       "description" = "Replacement for this package (optional, if any)"
     *     }
     *   }
     * )
     */
    public function putPackageAction($vendor, $package, Request $request)
    {
        $packageName = $vendor . '/' . $package;
        $info        = new JsonArray($request->getContent());
        $name        = $info->get('name');

        if (!($info->has('name') && $info->has('locked') && $info->has('constraint'))) {
            throw new NotAcceptableHttpException('Invalid package information.');
        }

        if ($name !== $packageName) {
            throw new NotAcceptableHttpException('Package name mismatch ' . $packageName . ' vs. ' . $name . '.');
        }

        $composer = $this->getComposer();
        $json     = $this->get('tenside.composer_json');

        $package = $this->findPackage($name, $composer->getRepositoryManager()->getLocalRepository());

        if (null === $package) {
            throw new NotFoundHttpException('Package ' . $packageName . ' not found.');
        }

        $json->setLock($package, $info->get('locked'));
        return $this->forward('TensideCoreBundle:Package:getPackage');
    }

    /**
     * Search the repository for a package.
     *
     * @param string              $name       The pretty name of the package to search.
     *
     * @param RepositoryInterface $repository The repository to be searched.
     *
     * @return null|PackageInterface
     */
    private function findPackage($name, RepositoryInterface $repository)
    {
        /** @var PackageInterface[] $packages */
        $packages = $repository->findPackages($name);

        while (!empty($packages) && $packages[0] instanceof AliasPackage) {
            array_shift($packages);
        }

        if (empty($packages)) {
            return null;
        }

        return $packages[0];
    }
}
