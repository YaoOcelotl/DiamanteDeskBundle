<?php
/*
 * Copyright (c) 2014 Eltrino LLC (http://eltrino.com)
 *
 * Licensed under the Open Software License (OSL 3.0).
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *    http://opensource.org/licenses/osl-3.0.php
 *
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@eltrino.com so we can send you a copy immediately.
 */
namespace Diamante\DeskBundle\Api\Internal;

use Diamante\DeskBundle\Api\BranchService;
use Diamante\DeskBundle\Api\Command;
use Diamante\DeskBundle\Model\Branch\BranchFactory;
use Diamante\DeskBundle\Infrastructure\Branch\BranchLogoHandler;
use Diamante\DeskBundle\Model\Branch\DuplicateBranchKeyException;
use Diamante\DeskBundle\Model\Branch\Logo;
use Diamante\DeskBundle\Model\Shared\Repository;
use Diamante\UserBundle\Api\UserService;
use Diamante\UserBundle\Model\User;
use Oro\Bundle\TagBundle\Entity\TagManager;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Diamante\DeskBundle\Model\Shared\Authorization\AuthorizationService;
use Oro\Bundle\SecurityBundle\Exception\ForbiddenException;
use Diamante\DeskBundle\Model\Branch\Branch;

class BranchServiceImpl implements BranchService
{
    /**
     * @var Repository
     */
    private $branchRepository;

    /**
     * @var BranchFactory
     */
    private $branchFactory;

    /**
     * @var \Diamante\DeskBundle\Infrastructure\Branch\BranchLogoHandler
     */
    private $branchLogoHandler;

    /**
     * @var \Oro\Bundle\TagBundle\Entity\TagManager
     */
    private $tagManager;

    /**
     * @var AuthorizationService
     */
    private $authorizationService;

    /**
     * @var UserService
     */
    private $userService;

    public function __construct(
        BranchFactory $branchFactory,
        Repository $branchRepository,
        BranchLogoHandler $branchLogoHandler,
        TagManager $tagManager,
        AuthorizationService $authorizationService,
        UserService $userService
    ) {
        $this->branchFactory     = $branchFactory;
        $this->branchRepository  = $branchRepository;
        $this->branchLogoHandler = $branchLogoHandler;
        $this->tagManager        = $tagManager;
        $this->authorizationService = $authorizationService;
        $this->userService       = $userService;
    }

    /**
     * Retrieves list of all Branches.
     *
     * @return Branch[]
     */
    public function getAllBranches()
    {
        $this->isGranted('VIEW', 'Entity:DiamanteDeskBundle:Branch');

        return $this->branchRepository->getAll();
    }

    /**
     * Retrieves Branch by id
     * @param $id
     * @return Branch
     */
    public function getBranch($id)
    {
        $this->isGranted('VIEW', 'Entity:DiamanteDeskBundle:Branch');
        $branch = $this->branchRepository->get($id);
        if (is_null($branch)) {
            throw new \RuntimeException('Branch loading failed. Branch not found.');
        }
        $this->tagManager->loadTagging($branch);
        return $branch;
    }

    /**
     * Create Branch
     * @param Command\BranchCommand $branchCommand
     * @return \Diamante\DeskBundle\Model\Branch\Branch
     * @throws DuplicateBranchKeyException
     */
    public function createBranch(Command\BranchCommand $branchCommand)
    {
        $this->isGranted('CREATE', 'Entity:DiamanteDeskBundle:Branch');

        $logo = null;

        if ($branchCommand->logoFile) {
            $logo = $this->handleLogoUpload($branchCommand->logoFile);
        }

        if ($branchCommand->defaultAssignee) {
            $assignee = $this->userService->getByUser(new User($branchCommand->defaultAssignee, User::TYPE_ORO));
        } else {
            $assignee = null;
        }

        $branch = $this->branchFactory
            ->create(
                $branchCommand->name,
                $branchCommand->description,
                $branchCommand->key,
                $assignee,
                $logo,
                $branchCommand->tags
            );

        $this->branchRepository->store($branch);
        $this->tagManager->saveTagging($branch);

        return $branch;
    }

    /**
     * Update Branch
     *
     * @param Command\BranchCommand $branchCommand
     * @return int
     */
    public function updateBranch(Command\BranchCommand $branchCommand)
    {
        $this->isGranted('EDIT', 'Entity:DiamanteDeskBundle:Branch');

        /**
         * @var $branch \Diamante\DeskBundle\Model\Branch\Branch
         */
        $branch = $this->branchRepository->get($branchCommand->id);

        if ($branchCommand->defaultAssignee) {
            $assignee = $this->userService->getByUser(new User($branchCommand->defaultAssignee, User::TYPE_ORO));
        } else {
            $assignee = null;
        }

        /** @var \Symfony\Component\HttpFoundation\File\File $file */
        $file = null;

        if($branchCommand->isRemoveLogo()) {
            $this->branchLogoHandler->remove($branch->getLogo());
            $file = new Logo();
        } elseif ($branchCommand->logoFile) {
            if ($branch->getLogo()) {
                $this->branchLogoHandler->remove($branch->getLogo());
            }
            $logo = $this->handleLogoUpload($branchCommand->logoFile);
            $file = new Logo($logo->getFilename());
        }

        $branch->update($branchCommand->name, $branchCommand->description, $assignee, $file);
        $this->branchRepository->store($branch);

        //TODO: Refactor tag manipulations.
        $this->tagManager->deleteTaggingByParams($branch->getTags(), get_class($branch), $branch->getId());
        $tags = $branchCommand->tags;
        $tags['owner'] = $tags['all'];
        $branch->setTags($tags);
        $this->tagManager->saveTagging($branch);

        return $branch->getId();
    }

    /**
     * Update certain properties of the Branch
     * @param Command\UpdatePropertiesCommand $command
     * @return Branch
     */
    public function updateProperties(Command\UpdatePropertiesCommand $command)
    {
        $this->isGranted('EDIT', 'Entity:DiamanteDeskBundle:Branch');

        /**
         * @var $branch \Diamante\DeskBundle\Model\Branch\Branch
         */
        $branch = $this->branchRepository->get($command->id);
        if (is_null($branch)) {
            throw new \RuntimeException('Branch loading failed, branch not found. ');
        }

        foreach ($command->properties as $name => $value) {
            $branch->updateProperty($name, $value);
        }

        $this->branchRepository->store($branch);

        return $branch;
    }

    /**
     * Delete Branch
     * @param int $branchId
     * @return void
     */
    public function deleteBranch($branchId)
    {
        $this->isGranted('DELETE', 'Entity:DiamanteDeskBundle:Branch');

        $branch = $this->branchRepository->get($branchId);
        if (is_null($branch)) {
            throw new \RuntimeException('Branch loading failed, branch not found. ');
        }
        if ($branch->getLogo()) {
            $this->branchLogoHandler->remove($branch->getLogo());
        }
        $this->branchRepository->remove($branch);
    }

    /**
     * @param UploadedFile $file
     * @return \Symfony\Component\HttpFoundation\File\File
     */
    private function handleLogoUpload(UploadedFile $file)
    {
        return $this->branchLogoHandler->upload($file);
    }

    /**
     * Verify permissions through Oro Platform security bundle
     *
     * @param $operation
     * @param $entity
     * @throws \Oro\Bundle\SecurityBundle\Exception\ForbiddenException
     */
    private function isGranted($operation, $entity)
    {
        if (!$this->authorizationService->isActionPermitted($operation, $entity)) {
            throw new ForbiddenException("Not enough permissions.");
        }
    }

    /**
     * @return Repository
     */
    protected function getBranchRepository()
    {
        return $this->branchRepository;
    }
}
