<?php
/**
 * Nextcloud - gitlab
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <julien-nc@posteo.net>
 * @copyright Julien Veyssier 2020
 */

namespace OCA\Gitlab\Controller;

use OCA\Gitlab\AppInfo\Application;
use OCA\Gitlab\Service\GitlabAPIService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\RedirectResponse;

use OCP\IConfig;
use OCP\IRequest;
use OCP\IURLGenerator;

class GitlabAPIController extends Controller {

	private string $accessToken;

	public function __construct(string                   $appName,
		IRequest                 $request,
		private IConfig          $config,
		private IURLGenerator    $urlGenerator,
		private GitlabAPIService $gitlabAPIService,
		private ?string          $userId) {
		parent::__construct($appName, $request);
		$this->accessToken = $this->config->getUserValue($this->userId, Application::APP_ID, 'token');
	}

	/**
	 * get gitlab user avatar
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @param int $userId
	 * @return DataDisplayResponse|RedirectResponse
	 * @throws \Exception
	 */
	public function getUserAvatar(int $userId) {
		$result = $this->gitlabAPIService->getUserAvatar($this->userId, $userId);
		if (isset($result['userInfo'])) {
			$userName = $result['userInfo']['name'] ?? '??';
			$fallbackAvatarUrl = $this->urlGenerator->linkToRouteAbsolute('core.GuestAvatar.getAvatar', ['guestName' => $userName, 'size' => 44]);
			return new RedirectResponse($fallbackAvatarUrl);
		} else {
			$response = new DataDisplayResponse($result['avatarContent']);
			$response->cacheFor(60 * 60 * 24);
			return $response;
		}
	}

	/**
	 * get gitlab project avatar
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @param int $projectId
	 * @return DataDisplayResponse|RedirectResponse
	 * @throws \Exception
	 */
	public function getProjectAvatar(int $projectId) {
		$result = $this->gitlabAPIService->getProjectAvatar($this->userId, $projectId);
		if (isset($result['projectInfo'])) {
			$projectName = $result['projectInfo']['name'] ?? '??';
			$fallbackAvatarUrl = $this->urlGenerator->linkToRouteAbsolute('core.GuestAvatar.getAvatar', ['guestName' => $projectName, 'size' => 44]);
			return new RedirectResponse($fallbackAvatarUrl);
		} else {
			$response = new DataDisplayResponse($result['avatarContent']);
			$response->cacheFor(60 * 60 * 24);
			return $response;
		}
	}

	/**
	 * get event list
	 * @NoAdminRequired
	 *
	 * @param string|null $since
	 * @return DataResponse
	 * @throws \Exception
	 */
	public function getEvents(?string $since = null): DataResponse {
		if ($this->accessToken === '') {
			return new DataResponse('', 400);
		}
		$result = $this->gitlabAPIService->getEvents($this->userId, $since);
		if (!isset($result['error'])) {
			$response = new DataResponse($result);
		} else {
			$response = new DataResponse($result, 401);
		}
		return $response;
	}

	/**
	 * get todo list
	 * @NoAdminRequired
	 *
	 * @param string|null $since
	 * @return DataResponse
	 * @throws \Exception
	 */
	public function getTodos(?string $since = null): DataResponse {
		if ($this->accessToken === '') {
			return new DataResponse('', 400);
		}
		$result = $this->gitlabAPIService->getTodos($this->userId, $since);
		if (!isset($result['error'])) {
			$response = new DataResponse($result);
		} else {
			$response = new DataResponse($result, 401);
		}
		return $response;
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int $id
	 * @return DataResponse
	 * @throws \Exception
	 */
	public function markTodoAsDone(int $id): DataResponse {
		if ($this->accessToken === '') {
			return new DataResponse('', 400);
		}
		$result = $this->gitlabAPIService->markTodoAsDone($this->userId, $id);
		if (!isset($result['error'])) {
			$response = new DataResponse($result);
		} else {
			$response = new DataResponse($result, 401);
		}
		return $response;
	}
}
