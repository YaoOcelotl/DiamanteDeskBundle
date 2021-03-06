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
namespace Diamante\DeskBundle\Tests\Functional\Api;

use Diamante\ApiBundle\Routine\Tests\ApiTestCase;
use Diamante\ApiBundle\Routine\Tests\Command\ApiCommand;
use FOS\Rest\Util\Codes;

class OroBranchApiTest extends ApiTestCase
{
    /**
     * @var ApiCommand
     */
    protected $command;

    public function setUp()
    {
        parent::setUp();
        $this->command = new ApiCommand();
    }

    /**
     * @return int
     */
    public function testCreateBranch()
    {
        $this->command->requestParameters = array(
            'name'        => 'Test Branch',
            'description' => 'Test Description',
            'tags'        => array('Test Tag'),
            'key'         => 'BRANCHTEST'
        );
        $response = $this->post('diamante_branch_api_service_oro_create_branch', $this->command);

        return $this->getArray($response)['id'];
    }

    public function testListBranches()
    {
        $this->getAll('diamante_branch_api_service_oro_list_all_branches');
    }

    /**
     * @depends testCreateBranch
     *
     * @param int $id
     */
    public function testGetBranch($id)
    {
        $this->command->urlParameters = array('id' => $id);
        $this->get('diamante_branch_api_service_oro_get_branch', $this->command);
    }

    /**
     * @depends testCreateBranch
     *
     * @param int $id
     */
    public function testUpdateBranch($id)
    {
        $this->command->urlParameters = array('id' => $id);
        $this->command->requestParameters = array(
            'name'        => 'Test Branch PUT',
            'description' => 'Test Description',
            'tags'        => array('Test Tag')
        );
        $this->put('diamante_branch_api_service_oro_update_properties', $this->command);

        $this->command->urlParameters = array('id' => $id);
        $this->command->requestParameters['name'] = 'Test Branch PATCH';
        $this->patch('diamante_branch_api_service_oro_update_properties', $this->command);
    }

    /**
     * @depends testCreateBranch
     *
     * @param int $id
     */
    public function testDeleteBranch($id)
    {
        $this->command->urlParameters = array('id' => $id);
        $this->delete('diamante_branch_api_service_oro_delete_branch', $this->command);
        $this->get('diamante_branch_api_service_oro_get_branch', $this->command, Codes::HTTP_NOT_FOUND);
    }
}
