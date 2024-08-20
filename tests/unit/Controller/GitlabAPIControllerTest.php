<?php

// SPDX-FileCopyrightText: Sami Finnilä <sami.finnila@nextcloud.com>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Gitlab\Tests;

use OCA\Gitlab\Controller\GitlabAPIController;
use OCA\Gitlab\Db\GitlabAccount;
use OCA\Gitlab\Db\GitlabAccountMapper;
use OCA\Gitlab\Service\ConfigService;
use OCA\Gitlab\Service\GitlabAPIService;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;
use Test\TestCase;
use Test\Util\User\Dummy;

/**
 * @group DB
 */
class GitlabAPIControllerTest extends TestCase {
	public const APP_NAME = 'integration_gitlab';
	public const TEST_USER1 = 'testuser1';
	public const API_TOKEN = 'testtoken';
	public const DEFAULT_HEADERS = ['User-Agent' => 'Nextcloud GitLab integration', 'Authorization' => 'Bearer '.self::API_TOKEN];

	private GitlabAPIController $gitlabApiController;
	private GitlabAPIService $gitlabApiService;
	private IClient $iClient;
	private ConfigService $config;

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();
		$backend = new Dummy();
		$backend->createUser(self::TEST_USER1, self::TEST_USER1);
		\OC::$server->get(IUserManager::class)->registerBackend($backend);
	}

	public static function tearDownAfterClass(): void {
		$backend = new Dummy();
		$backend->deleteUser(self::TEST_USER1);
		\OC::$server->get(IUserManager::class)->removeBackend($backend);
		parent::tearDownAfterClass();
	}

	protected function setUp(): void {
		parent::setUp();

		$this->loginAsUser(self::TEST_USER1);

		// We'll hijack the client service to return a mock client
		// so we can control the response
		$clientService = $this->createMock(IClientService::class);
		$this->iClient = $this->createMock(IClient::class);
		$clientService->method('newClient')->willReturn($this->iClient);

		$account = new GitlabAccount();
		$account->setUrl('https://gitlab.com');
		$account->setToken(self::API_TOKEN);

		$accountMapper = $this->createMock(GitlabAccountMapper::class);
		$accountMapper->method('findById')->willReturn($account);

		$this->gitlabApiService = new GitlabAPIService(
			\OC::$server->get(\Psr\Log\LoggerInterface::class),
			$this->createMock(IL10N::class),
			\OC::$server->get(ConfigService::class),
			$clientService,
			$accountMapper,
			self::TEST_USER1,
		);

		$this->gitlabApiController = new GitlabAPIController(
			self::APP_NAME,
			$this->createMock(IRequest::class),
			\OC::$server->get(IURLGenerator::class),
			$this->gitlabApiService,
			self::TEST_USER1,
			$accountMapper,
			$this->createMock(LoggerInterface::class),
		);

		$this->config = \OC::$server->get(ConfigService::class);
	}

	public function testGetUserAvatar(): void {
		$firstUrl = 'https://gitlab.com/api/v4/users/1';
		$secondUrl = 'https://gitlab.com/avatar.jpg';

		$firstResponse = file_get_contents(__DIR__.'/data/users_with_id.json');
		$secondResponse = file_get_contents(__DIR__.'/data/avatar.jpg');

		$options = [
			'headers' => self::DEFAULT_HEADERS,
		];

		$firstIResponse = $this->createMock(IResponse::class);
		$firstIResponse->method('getBody')->willReturn($firstResponse);
		$firstIResponse->method('getStatusCode')->willReturn(200);

		$secondIResponse = $this->createMock(IResponse::class);
		$secondIResponse->method('getBody')->willReturn($secondResponse);
		$secondIResponse->method('getStatusCode')->willReturn(200);

		$this->iClient->expects($this->exactly(2))
			->method('get')
			->withConsecutive(
				[$firstUrl, $options],
				[$secondUrl, []] // Without options since this is just a request for the avatar.jpg
			)
			->willReturnOnConsecutiveCalls(
				$firstIResponse,
				$secondIResponse
			);

		$result = $this->gitlabApiController->getUserAvatar(1, 1);

		$this->assertEquals(200, $result->getStatus());
		$this->assertEquals($secondResponse, $result->getData());
	}

	public function testGetProjectAvatar(): void {
		$firstUrl = 'https://gitlab.com/api/v4/projects/1';
		$secondUrl = 'http://example.com/uploads/project/avatar/3/uploads/avatar.png';

		$firstResponse = file_get_contents(__DIR__.'/data/projects_with_id.json');
		$secondResponse = file_get_contents(__DIR__.'/data/avatar.jpg');

		$options = [
			'headers' => self::DEFAULT_HEADERS,
		];

		$firstIResponse = $this->createMock(IResponse::class);
		$firstIResponse->method('getBody')->willReturn($firstResponse);
		$firstIResponse->method('getStatusCode')->willReturn(200);

		$secondIResponse = $this->createMock(IResponse::class);
		$secondIResponse->method('getBody')->willReturn($secondResponse);
		$secondIResponse->method('getStatusCode')->willReturn(200);

		$this->iClient->expects($this->exactly(2))
			->method('get')
			->withConsecutive(
				[$firstUrl, $options],
				[$secondUrl, []] // Without options since this is just a request for the avatar.jpg
			)
			->willReturnOnConsecutiveCalls(
				$firstIResponse,
				$secondIResponse
			);

		$result = $this->gitlabApiController->getProjectAvatar(1, 1);

		$this->assertEquals(200, $result->getStatus());
		$this->assertEquals($secondResponse, $result->getData());
	}

	private function getProjectsResponse(int $numResponses): string {
		// Here we recycle the projects.json file contents and just change the project id for each project.
		// All other fields remain constant.
		$response = json_decode(file_get_contents(__DIR__.'/data/projects.json'), true);

		$projectId = 1;

		$response[0]['id'] = $projectId;
		$response[0]['path_with_namespace'] = 'example/example'.strval($projectId);
		$response[0]['avatar_url'] = 'http://example.com/uploads/project/avatar/'.strval($projectId).'/uploads/avatar.png';
		$projectId++;

		// Duplicate the first element $numResponses-1 times
		while(--$numResponses) {
			$response[] = $response[0];
			// Get the last element key
			end($response);
			$key = key($response);

			$response[$key]['id'] = $projectId;
			$response[$key]['path_with_namespace'] = 'example/example'.strval($projectId);
			$response[$key]['avatar_url'] = 'http://example.com/uploads/project/avatar/'.strval($projectId).'/uploads/avatar.png';
			$projectId++;
		}

		return json_encode($response);
	}

	public function testGetTodos(): void {
		$baseUrl = 'https://gitlab.com/api/v4';
		$since = '2017-01-01T00:00:00.000Z';

		$arguments = []; // Arguments for the repeated calls to the iClient in the GitlabAPIService::request method
		$responses = []; // Responses for the repeated calls to the iClient in the GitlabAPIService::request method

		// Get todos
		$arguments[] = [$baseUrl.'/todos?'.http_build_query(['state' => 'pending']), ['headers' => self::DEFAULT_HEADERS]];
		$iResponse = $this->createMock(IResponse::class);
		$iResponse->method('getBody')->willReturn(file_get_contents(__DIR__.'/data/todos.json'));
		$iResponse->method('getStatusCode')->willReturn(200);
		$responses[] = $iResponse;

		// Get projects of the user
		$arguments[] = [$baseUrl.'/projects?'.http_build_query(['membership' => 'true']), ['headers' => self::DEFAULT_HEADERS]];
		$iResponse = $this->createMock(IResponse::class);
		$iResponse->method('getBody')->willReturn($this->getProjectsResponse(20));
		$iResponse->method('getStatusCode')->willReturn(200);
		$responses[] = $iResponse;

		// Get the projects/id for the project id 999 since it is not in the projects list of the user
		$arguments[] = [$baseUrl.'/projects/999', ['headers' => self::DEFAULT_HEADERS]];
		$iResponse = $this->createMock(IResponse::class);
		$iResponse->method('getBody')->willReturn(file_get_contents(__DIR__.'/data/projects_with_id.json'));
		$iResponse->method('getStatusCode')->willReturn(200);
		$responses[] = $iResponse;

		$this->iClient->expects($this->exactly(3))
			->method('get')
			->withConsecutive(...$arguments)
			->willReturnOnConsecutiveCalls(...$responses);

		$result = $this->gitlabApiController->getTodos(1, $since);

		// The result should be a DataResponse with status 200 and the data should be an array with 2 elements
		// since 1 element is filtered out because of the since date
		$this->assertEquals(200, $result->getStatus());
		$this->assertEquals(2, count($result->getData()));

		// The todo ids should be 98 and 99:
		$todos = $result->getData();
		// Sort the todos by id
		usort($todos, function ($a, $b) {
			return $a['id'] <=> $b['id'];
		});
		$this->assertEquals(98, $todos[0]['id']);
		$this->assertEquals(99, $todos[1]['id']);

		// Also, check that the avatar url and visibility are set for each todo's project
		foreach($todos as $todo) {
			$this->assertArrayHasKey('avatar_url', $todo['project']);
			$this->assertArrayHasKey('visibility', $todo['project']);
		}
	}
}
