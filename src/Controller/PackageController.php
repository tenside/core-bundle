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

use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\Package\AliasPackage;
use Composer\Package\PackageInterface;
use Composer\Repository\CompositeRepository;
use Composer\Repository\PlatformRepository;
use Composer\Repository\RepositoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotAcceptableHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tenside\Core\Composer\PackageConverter;
use Tenside\Core\Util\JsonArray;

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

        return new JsonResponse($packages->getData(), 200);
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
