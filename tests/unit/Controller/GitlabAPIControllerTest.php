<?php

// SPDX-FileCopyrightText: Sami Finnilä <sami.finnila@nextcloud.com>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Gitlab\Tests;

use OCA\Gitlab\AppInfo\Application;
use OCA\Gitlab\Controller\GitlabAPIController;
use OCA\Gitlab\Service\GitlabAPIService;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserManager;
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

	private $gitlabApiController;
	private $gitlabApiService;
	private $iClient;
	private $config;
	private $eventsId = 1;

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

		$this->gitlabApiService = new GitlabAPIService(
			self::APP_NAME,
			\OC::$server->get(\Psr\Log\LoggerInterface::class),
			$this->createMock(IL10N::class),
			\OC::$server->get(IConfig::class),
			$clientService,
		);

		$this->gitlabApiController = new GitlabAPIController(
			self::APP_NAME,
			$this->createMock(IRequest::class),
			\OC::$server->get(IConfig::class),
			\OC::$server->get(IURLGenerator::class),
			$this->gitlabApiService,
			self::TEST_USER1
		);

		$this->config = \OC::$server->get(IConfig::class);
		$this->config->setUserValue(self::TEST_USER1, Application::APP_ID, 'token', self::API_TOKEN);
	}

	public function testGetGitlabUrl(): void {
		$expected = 'https://gitlab.com';
		$this->config->setUserValue(self::TEST_USER1, Application::APP_ID, 'url', $expected);
		$response = $this->gitlabApiController->getGitlabUrl();
		$this->assertEquals($expected, $response->getData());
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

		$result = $this->gitlabApiController->getUserAvatar(1);

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

		$result = $this->gitlabApiController->getProjectAvatar(1);

		$this->assertEquals(200, $result->getStatus());
		$this->assertEquals($secondResponse, $result->getData());
	}

	private function getEventsResponse(string $actionName, string $targetType): string {
		// Not really necessary for now but might be useful in the future:
		// Here we recycle the events.json file contents and just change the action name,
		// target type, id and project id for each event type. All other fields remain constant.
		$response = json_decode(file_get_contents(__DIR__.'/data/events.json'), true);

		$projectId = 1;

		foreach($response as $key => $event) {
			$response[$key]['action_name'] = $actionName;
			$response[$key]['target_type'] = $targetType;
			$response[$key]['project_id'] = $projectId++;
			$response[$key]['id'] = $this->eventsId++;
		}

		return json_encode($response);
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

	public function testGetEvents(): void {
		// The get events method is a bit more complicated to test
		// since it has to make multiple requests to get all the events
		// - First it retrieves info for all projects the user is a member of
		// - Then it retrieves the current user id
		// - Then it retrevies the following events:
		//   - Created merge request
		//   - Merged merge request
		//   - Created issues
		//   - Closed issues
		//   - Issue comments
		// - Then it filters out the events that were created by the user
		// - Then it sorts the events by date
		// - Finally it adds the project path and avatar url to each event (under keys 'project_path' and 'project_avatar_url')

		$since = '2017-01-01T00:00:00.000Z';
		$baseUrl = 'https://gitlab.com/api/v4';
		$arguments = []; // Arguments for the repeated calls to the iClient in the GitlabAPIService::request method
		$responses = []; // Responses for the repeated calls to the iClient in the GitlabAPIService::request method

		// Get list of all projects the user is a member of
		$arguments[] = [$baseUrl.'/projects?'.http_build_query(['membership' => 'true']), ['headers' => self::DEFAULT_HEADERS]];
		$iResponse = $this->createMock(IResponse::class);
		$iResponse->method('getBody')->willReturn($this->getProjectsResponse(20));
		$iResponse->method('getStatusCode')->willReturn(200);
		$responses[] = $iResponse;

		// Get current user id
		$arguments[] = [$baseUrl.'/user', ['headers' => self::DEFAULT_HEADERS]];
		$iResponse = $this->createMock(IResponse::class);
		$iResponse->method('getBody')->willReturn(file_get_contents(__DIR__.'/data/user.json'));
		$iResponse->method('getStatusCode')->willReturn(200);
		$responses[] = $iResponse;

		$params = [
			'scope' => 'all',
			'after' => '2016-12-31', // The since date without the time part -1day
			'target_type' => 'merge_request',
			'action' => 'created',
		];

		// Get created merge requests
		$arguments[] = [$baseUrl.'/events?'.http_build_query($params), ['headers' => self::DEFAULT_HEADERS]];
		$iResponse = $this->createMock(IResponse::class);
		$iResponse->method('getBody')->willReturn($this->getEventsResponse('created', 'merge_request'));
		$iResponse->method('getStatusCode')->willReturn(200);
		$responses[] = $iResponse;

		// Get merged merge requests
		$params['action'] = 'merged';
		$params['target_type'] = 'merge_request';
		$arguments[] = [$baseUrl.'/events?'.http_build_query($params), ['headers' => self::DEFAULT_HEADERS]];
		$iResponse = $this->createMock(IResponse::class);
		$iResponse->method('getBody')->willReturn($this->getEventsResponse('merged', 'merge_request'));
		$iResponse->method('getStatusCode')->willReturn(200);
		$responses[] = $iResponse;

		// Get created issues
		$params['action'] = 'created';
		$params['target_type'] = 'issue';
		$arguments[] = [$baseUrl.'/events?'.http_build_query($params), ['headers' => self::DEFAULT_HEADERS]];
		$iResponse = $this->createMock(IResponse::class);
		$iResponse->method('getBody')->willReturn($this->getEventsResponse('created', 'issue'));
		$iResponse->method('getStatusCode')->willReturn(200);
		$responses[] = $iResponse;

		// Get closed issues
		$params['action'] = 'closed';
		$params['target_type'] = 'issue';
		$arguments[] = [$baseUrl.'/events?'.http_build_query($params), ['headers' => self::DEFAULT_HEADERS]];
		$iResponse = $this->createMock(IResponse::class);
		$iResponse->method('getBody')->willReturn($this->getEventsResponse('closed', 'issue'));
		$iResponse->method('getStatusCode')->willReturn(200);
		$responses[] = $iResponse;

		// Get issue comments
		$params['action'] = 'commented';
		$params['target_type'] = 'note';
		$arguments[] = [$baseUrl.'/events?'.http_build_query($params), ['headers' => self::DEFAULT_HEADERS]];
		$iResponse = $this->createMock(IResponse::class);
		$iResponse->method('getBody')->willReturn($this->getEventsResponse('commented', 'note'));
		$iResponse->method('getStatusCode')->willReturn(200);
		$responses[] = $iResponse;

		// Ok, so the results are in. Now the api service method should filter out the events that were created by us (user id 1, 1/3rd of the events in ./data/events.json)
		// and also filter out the events that were created before the since date (1/3rd of the events in ./data/events.json).
		// So we should be left with 5 events.
		// The ids of the events should be 2, 5, 8, 11 and 14.

		$this->iClient->expects($this->exactly(7))
			->method('get')
			->withConsecutive(...$arguments)
			->willReturnOnConsecutiveCalls(...$responses);

		$result = $this->gitlabApiController->getEvents($since);

		//The result should be a DataResponse with status 200 and the data should be an array with 5 elements
		$this->assertEquals(200, $result->getStatus());
		$this->assertEquals(5, count($result->getData()));

		// The ids of the events should be 2, 5, 8, 11 and 14.
		$events = $result->getData();
		// Sort the events by id
		usort($events, function ($a, $b) {
			return $a['id'] <=> $b['id'];
		});

		// Check that the ids are correct
		$n = 0;
		foreach($events as $event) {
			$this->assertEquals($n++ * 3 + 2, $event['id']);

			// Id 1 must not be present as author
			$this->assertNotEquals(1, $event['author_id']);

			// Date must be after the since date
			$sinceDateTime = new \DateTimeImmutable($since);
			$eventDateTime = new \DateTimeImmutable($event['created_at']);
			$this->assertGreaterThan($sinceDateTime, $eventDateTime);

			// Also, check that the project path and avatar url are set
			$this->assertArrayHasKey('project_path', $event);
			$this->assertEquals('example/example'.strval($event['project_id']), $event['project_path']);
			$this->assertArrayHasKey('project_avatar_url', $event);
			$this->assertEquals('http://example.com/uploads/project/avatar/'.strval($event['project_id']).'/uploads/avatar.png', $event['project_avatar_url']);
		}
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

		$result = $this->gitlabApiController->getTodos($since);

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

	public function testMarkTodoAsDone(): void {
		$baseUrl = 'https://gitlab.com/api/v4';
		$todoId = 1;

		// Load the todos.json file and take one item:
		$todo = json_decode(file_get_contents(__DIR__.'/data/todos.json'), true);
		$todo = array_pop($todo);
		// Set the state to done
		$todo['state'] = 'done';

		// Mark todo as done
		$iResponse = $this->createMock(IResponse::class);
		$iResponse->method('getBody')->willReturn(json_encode($todo));
		$iResponse->method('getStatusCode')->willReturn(200);

		$this->iClient->expects($this->exactly(1))
			->method('post')
			->withConsecutive([$baseUrl.'/todos/'.$todoId.'/mark_as_done', ['headers' => self::DEFAULT_HEADERS]])
			->willReturnOnConsecutiveCalls($iResponse);

		$result = $this->gitlabApiController->markTodoAsDone($todoId);

		// The result should be a DataResponse with status 200 and the data should have 11 keys
		$this->assertEquals(200, $result->getStatus());
		$this->assertEquals(11, count($result->getData()));
		$this->assertEquals($todo, $result->getData());
	}
}
