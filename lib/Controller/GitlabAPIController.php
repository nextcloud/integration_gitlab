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

use Exception;
use OCA\Gitlab\Db\GitlabAccountMapper;
use OCA\Gitlab\Service\GitlabAPIService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\IRequest;
use OCP\IURLGenerator;
use Psr\Log\LoggerInterface;

class GitlabAPIController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private IURLGenerator $urlGenerator,
		private GitlabAPIService $gitlabAPIService,
		private string $userId,
		private GitlabAccountMapper $accountMapper,
		private LoggerInterface $logger,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * get gitlab user avatar
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @param int $userId
	 * @return DataDisplayResponse|RedirectResponse|DataResponse
	 * @throws Exception
	 */
	public function getUserAvatar(int $accountId, int $userId) {
		try {
			$account = $this->accountMapper->findById($this->userId, $accountId);
			if ($account->getClearToken() === '') {
				return new DataResponse('', 400);
			}

			$result = $this->gitlabAPIService->getUserAvatar($account, $account->getUrl(), $userId);
			if (isset($result['userInfo'])) {
				$userName = $result['userInfo']['name'] ?? '??';
				$fallbackAvatarUrl = $this->urlGenerator->linkToRouteAbsolute('core.GuestAvatar.getAvatar', ['guestName' => $userName, 'size' => 44]);
				return new RedirectResponse($fallbackAvatarUrl);
			}

			$response = new DataDisplayResponse($result['avatarContent']);
			$response->cacheFor(60 * 60 * 24);
			return $response;
		} catch (DoesNotExistException $e) {
			$this->logger->error('Requested Gitlab account with id ' . $accountId . 'not found');
			return new DataResponse([], 404);
		} catch (\OCP\DB\Exception $e) {
			$this->logger->error('Failed to query Gitlab account with id ' . $accountId . ': ' . $e->getMessage(), ['exception' => $e]);
			return new DataResponse([], 500);
		}
	}

	/**
	 * get gitlab project avatar
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @param int $projectId
	 * @return DataDisplayResponse|RedirectResponse|DataResponse
	 * @throws Exception
	 */
	public function getProjectAvatar(int $accountId, int $projectId) {
		try {
			$account = $this->accountMapper->findById($this->userId, $accountId);
			if ($account->getClearToken() === '') {
				return new DataResponse('', 400);
			}

			$result = $this->gitlabAPIService->getProjectAvatar($account, $account->getUrl(), $projectId);
			if (isset($result['projectInfo'])) {
				$projectName = $result['projectInfo']['name'] ?? '??';
				$fallbackAvatarUrl = $this->urlGenerator->linkToRouteAbsolute('core.GuestAvatar.getAvatar', ['guestName' => $projectName, 'size' => 44]);
				return new RedirectResponse($fallbackAvatarUrl);
			}

			$response = new DataDisplayResponse($result['avatarContent']);
			$response->cacheFor(60 * 60 * 24);
			return $response;
		} catch (DoesNotExistException $e) {
			$this->logger->error('Requested Gitlab account with id ' . $accountId . 'not found');
			return new DataResponse([], 404);
		} catch (\OCP\DB\Exception $e) {
			$this->logger->error('Failed to query Gitlab account with id ' . $accountId . ': ' . $e->getMessage(), ['exception' => $e]);
			return new DataResponse([], 500);
		}
	}

	/**
	 * get todo list
	 * @NoAdminRequired
	 *
	 * @param string|null $since
	 * @return DataResponse
	 * @throws Exception
	 */
	public function getTodos(int $accountId, ?string $since = null, ?string $groupId = null): DataResponse {
		try {
			$account = $this->accountMapper->findById($this->userId, $accountId);
			if ($account->getClearToken() === '') {
				return new DataResponse('', 400);
			}

			$result = $this->gitlabAPIService->getTodos($account, $since, $groupId);
			if (isset($result['error'])) {
				return new DataResponse($result, 401);
			}

			return new DataResponse($result);
		} catch (DoesNotExistException $e) {
			$this->logger->error('Requested Gitlab account with id ' . $accountId . 'not found');
			return new DataResponse([], 404);
		} catch (\OCP\DB\Exception $e) {
			$this->logger->error('Failed to query Gitlab account with id ' . $accountId . ': ' . $e->getMessage(), ['exception' => $e]);
			return new DataResponse([], 500);
		}
	}

	public function getProjectsList(int $accountId): DataResponse {
		try {
			$account = $this->accountMapper->findById($this->userId, $accountId);
			if ($account->getClearToken() === '') {
				return new DataResponse('', 400);
			}

			$result = $this->gitlabAPIService->getProjectsList($account);
			if (isset($result['error'])) {
				return new DataResponse($result, 401);
			}

			return new DataResponse($result);
		} catch (DoesNotExistException $e) {
			$this->logger->error('Requested Gitlab account with id ' . $accountId . 'not found');
			return new DataResponse([], 404);
		} catch (\OCP\DB\Exception $e) {
			$this->logger->error('Failed to query Gitlab account with id ' . $accountId . ': ' . $e->getMessage(), ['exception' => $e]);
			return new DataResponse([], 500);
		}
	}

	public function getGroupsList(int $accountId): DataResponse {
		try {
			$account = $this->accountMapper->findById($this->userId, $accountId);
			if ($account->getClearToken() === '') {
				return new DataResponse('', 400);
			}

			$result = $this->gitlabAPIService->getGroupsList($account);
			if (isset($result['error'])) {
				return new DataResponse($result, 401);
			}

			return new DataResponse($result);
		} catch (DoesNotExistException $e) {
			$this->logger->error('Requested Gitlab account with id ' . $accountId . 'not found');
			return new DataResponse([], 404);
		} catch (\OCP\DB\Exception $e) {
			$this->logger->error('Failed to query Gitlab account with id ' . $accountId . ': ' . $e->getMessage(), ['exception' => $e]);
			return new DataResponse([], 500);
		}
	}
}
