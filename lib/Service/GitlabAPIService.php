<?php

/**
 * Nextcloud - gitlab
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier
 * @copyright Julien Veyssier 2020
 */

namespace OCA\Gitlab\Service;

use DateTime;
use Exception;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ServerException;
use OCA\Gitlab\AppInfo\Application;
use OCA\Gitlab\Db\GitlabAccount;
use OCA\Gitlab\Db\GitlabAccountMapper;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\IL10N;
use Psr\Log\LoggerInterface;

/**
 * Service to make requests to GitLab v3 (JSON) API
 */
class GitlabAPIService {

	private IClient $client;

	public function __construct(
		private LoggerInterface $logger,
		private IL10N $l10n,
		private ConfigService $config,
		IClientService $clientService,
		private GitlabAccountMapper $accountMapper,
		private ?string $userId,
	) {
		$this->client = $clientService->newClient();
	}

	private function getMyProjectsInfo(GitlabAccount $account): array {
		$params = [
			'membership' => 'true',
		];
		$projects = $this->request($account, $account->getUrl(), 'projects', $params);
		if (isset($projects['error'])) {
			return $projects;
		}
		$projectsInfo = [];
		foreach ($projects as $project) {
			$pid = $project['id'];
			$projectsInfo[$pid] = [
				'path_with_namespace' => $project['path_with_namespace'],
				'avatar_url' => $project['avatar_url'],
				'visibility' => $project['visibility'],
			];
		}
		return $projectsInfo;
	}

	/**
	 * @param int $offset
	 * @param int $limit
	 * @return array [perPage, page, leftPadding]
	 */
	public static function getGitLabPaginationValues(int $offset = 0, int $limit = 5): array {
		// compute pagination values
		// indexes offset => offset + limit
		if (($offset % $limit) === 0) {
			$perPage = $limit;
			// page number starts at 1
			$page = ($offset / $limit) + 1;
			return [$perPage, $page, 0];
		} else {
			$firstIndex = $offset;
			$lastIndex = $offset + $limit - 1;
			$perPage = $limit;
			// while there is no page that contains them'all
			while (intdiv($firstIndex, $perPage) !== intdiv($lastIndex, $perPage)) {
				$perPage++;
			}
			$page = intdiv($offset, $perPage) + 1;
			$leftPadding = $firstIndex % $perPage;

			return [$perPage, $page, $leftPadding];
		}
	}

	public function searchRepositories(GitlabAccount $account, string $term, int $offset = 0, int $limit = 5): array {
		[$perPage, $page, $leftPadding] = self::getGitLabPaginationValues($offset, $limit);
		$params = [
			'scope' => 'projects',
			'search' => $term,
			'sort' => 'desc',
			'per_page' => $perPage,
			'page' => $page,
		];
		$projects = $this->request($account, $account->getUrl(), 'search', $params);
		if (isset($projects['error'])) {
			return $projects;
		}
		return array_slice($projects, $leftPadding, $limit);
	}

	public function searchIssues(GitlabAccount $account, string $term, int $offset = 0, int $limit = 5): array {
		[$perPage, $page, $leftPadding] = self::getGitLabPaginationValues($offset, $limit);
		$params = [
			'scope' => 'issues',
			'search' => $term,
			'sort' => 'desc',
			'per_page' => $perPage,
			'page' => $page,
		];
		$issues = $this->request($account, $account->getUrl(), 'search', $params);
		if (isset($issues['error'])) {
			return $issues;
		}
		return array_slice($issues, $leftPadding, $limit);
	}

	public function searchMergeRequests(GitlabAccount $account, string $term, int $offset = 0, int $limit = 5): array {
		[$perPage, $page, $leftPadding] = self::getGitLabPaginationValues($offset, $limit);
		$params = [
			'scope' => 'merge_requests',
			'search' => $term,
			'sort' => 'desc',
			'per_page' => $perPage,
			'page' => $page,
		];
		$mergeRequests = $this->request($account, $account->getUrl(), 'search', $params);
		if (isset($mergeRequests['error'])) {
			return $mergeRequests;
		}
		return array_slice($mergeRequests, $leftPadding, $limit);
	}

	public function getTodos(GitlabAccount $account, ?string $since = null, ?string $groupId = null): array {
		$params = [
			'state' => 'pending',
		];
		if ($groupId) {
			$params['group_id'] = $groupId;
		}
		$result = $this->request($account, $account->getUrl(), 'todos', $params);
		if (isset($result['error'])) {
			return $result;
		}

		// filter results by date
		if (!is_null($since)) {
			// we get a full ISO date, the API only wants a day (non inclusive)
			$sinceDate = new DateTime($since);
			$sinceTimestamp = $sinceDate->getTimestamp();

			$result = array_filter($result, function ($elem) use ($sinceTimestamp) {
				$date = new DateTime($elem['updated_at']);
				$ts = $date->getTimestamp();
				return $ts > $sinceTimestamp;
			});
		}

		// make sure it's an array and not a hastable
		$result = array_values($result);

		// add project avatars to results
		$projectsInfo = $this->getMyProjectsInfo($account);
		foreach ($result as $k => $todo) {
			$pid = $todo['project']['id'];
			if (array_key_exists($pid, $projectsInfo)) {
				$result[$k]['project']['avatar_url'] = $projectsInfo[$pid]['avatar_url'];
				$result[$k]['project']['visibility'] = $projectsInfo[$pid]['visibility'];
			} else {
				// get the project avatar
				$projectInfo = $this->request($account, $account->getUrl(), 'projects/' . $pid);
				if (isset($projectInfo['error'])) {
					return $projectInfo;
				}
				$result[$k]['project']['avatar_url'] = $projectInfo['avatar_url'];
				$result[$k]['project']['visibility'] = $projectInfo['visibility'];
				// cache result
				$projectsInfo[$pid] = [
					'avatar_url' => $projectInfo['avatar_url'],
					'visibility' => $projectInfo['visibility'],
				];
			}
		}

		return $result;
	}

	public function getProjectsList(GitLabAccount $account, ?string $since = null): array {
		$params = [
			'membership' => 'true',
		];
		if ($since) {
			$params['updated_after'] = $since;
		}

		return $this->request($account, $account->getUrl(), 'projects', $params);
	}

	public function getGroupsList(GitLabAccount $account): array {
		$params = [
			'membership' => 'true',
		];
		return $this->request($account, $account->getUrl(), 'groups', $params);
	}

	public function getUserAvatar(GitlabAccount $account, string $baseUrl, int $gitlabUserId): array {
		$userInfo = $this->request($account, $baseUrl, 'users/' . $gitlabUserId);
		if (!isset($userInfo['error']) && isset($userInfo['avatar_url'])) {
			return ['avatarContent' => $this->client->get($userInfo['avatar_url'])->getBody()];
		}
		return ['userInfo' => $userInfo];
	}

	public function getProjectAvatar(GitlabAccount $account, string $baseUrl, int $projectId): array {
		$projectInfo = $this->request($account, $baseUrl, 'projects/' . $projectId);
		if (!isset($projectInfo['error']) && isset($projectInfo['avatar_url'])) {
			return ['avatarContent' => $this->client->get($projectInfo['avatar_url'])->getBody()];
		}
		return ['projectInfo' => $projectInfo];
	}

	public function getProjectInfo(?GitlabAccount $account, string $baseUrl, string $owner, string $repo): array {
		return $this->request($account, $baseUrl, 'projects/' . urlencode($owner . '/' . $repo));
	}

	public function getProjectLabels(GitlabAccount $account, string $baseUrl, int $projectId): array {
		return $this->request($account, $baseUrl, 'projects/' . $projectId . '/labels');
	}

	public function getIssueInfo(?GitlabAccount $account, string $baseUrl, int $projectId, int $issueId): array {
		return $this->request($account, $baseUrl, 'projects/' . $projectId . '/issues/' . $issueId);
	}

	public function getIssueCommentInfo(?GitlabAccount $account, string $baseUrl, int $projectId, int $issueId, int $commentId): array {
		return $this->request($account, $baseUrl, 'projects/' . $projectId . '/issues/' . $issueId . '/notes/' . $commentId);
	}

	public function getPrInfo(?GitlabAccount $account, string $baseUrl, int $projectId, int $prId): array {
		return $this->request($account, $baseUrl, 'projects/' . $projectId . '/merge_requests/' . $prId);
	}

	public function getPrCommentInfo(?GitlabAccount $account, string $baseUrl, int $projectId, int $prId, int $commentId): array {
		return $this->request($account, $baseUrl, 'projects/' . $projectId . '/merge_requests/' . $prId . '/notes/' . $commentId);
	}

	public function request(?GitlabAccount $account, string $baseUrl, string $endPoint, array $params = [], string $method = 'GET'): array {
		if ($account !== null && $this->userId !== null) {
			$this->checkTokenExpiration($account);
		}
		try {
			$url = $baseUrl . '/api/v4/' . $endPoint;
			$options = [
				'headers' => [
					'User-Agent' => 'Nextcloud GitLab integration'
				],
			];

			// try anonymous request if no user (public page) or user not connected to a gitlab account
			if ($account !== null) {
				$accessToken = $account->getClearToken();
				if ($accessToken !== '') {
					$options['headers']['Authorization'] = 'Bearer ' . $accessToken;
				}
			}

			if (count($params) > 0) {
				if ($method === 'GET') {
					// manage array parameters
					$paramsContent = '';
					foreach ($params as $key => $value) {
						if (is_array($value)) {
							foreach ($value as $oneArrayValue) {
								$paramsContent .= $key . '[]=' . urlencode($oneArrayValue) . '&';
							}
							unset($params[$key]);
						}
					}
					$paramsContent .= http_build_query($params);

					$url .= '?' . $paramsContent;
				} else {
					$options['body'] = $params;
				}
			}

			if ($method === 'GET') {
				$response = $this->client->get($url, $options);
			} elseif ($method === 'POST') {
				$response = $this->client->post($url, $options);
			} elseif ($method === 'PUT') {
				$response = $this->client->put($url, $options);
			} elseif ($method === 'DELETE') {
				$response = $this->client->delete($url, $options);
			} else {
				return ['error' => $this->l10n->t('Bad HTTP method'), 'code' => 405];
			}
			$body = $response->getBody();
			$respCode = $response->getStatusCode();

			if ($respCode >= 400) {
				return ['error' => $this->l10n->t('Bad credentials'), 'code' => $respCode];
			} else {
				return json_decode($body, true);
			}
		} catch (ServerException|ClientException $e) {
			$this->logger->info('GitLab API error : ' . $e->getMessage(), ['app' => Application::APP_ID]);
			if ($e->getCode() == 401) {
				return ['error' => $this->l10n->t('Bad credentials'), 'code' => 401];
			}
			return ['error' => 'Gitlab API error, please check the server logs for more details', 'code' => $e->getCode()];
		} catch (ConnectException $e) {
			$this->logger->info('GitLab API error : ' . $e->getMessage(), ['app' => Application::APP_ID]);
			return ['error' => 'Connection error, please check the server logs for more details', 'code' => 500];
		}
	}

	private function checkTokenExpiration(GitlabAccount $account): void {
		if ($account->getClearRefreshToken() && $account->getTokenExpiresAt()) {
			$nowTs = (new DateTime())->getTimestamp();
			// if token expires in less than a minute or is already expired
			if ($nowTs > $account->getTokenExpiresAt() - 60) {
				$this->refreshToken($account);
			}
		}
	}

	private function refreshToken(GitlabAccount $account): bool {
		$adminOauthUrl = $this->config->getAdminOauthUrl();
		$refreshToken = $account->getClearRefreshToken();
		if (!$refreshToken) {
			$this->logger->error('No GitLab refresh token found', ['app' => Application::APP_ID]);
			return false;
		}
		$result = $this->requestOAuthAccessToken($adminOauthUrl, [
			'client_id' => $this->config->getAdminClientId(),
			'client_secret' => $this->config->getAdminClientSecret(),
			'grant_type' => 'refresh_token',
			'redirect_uri' => $this->config->getUserRedirectUri($this->userId),
			'refresh_token' => $refreshToken,
		], 'POST');
		if (isset($result['access_token'])) {
			$this->logger->info('GitLab access token successfully refreshed', ['app' => Application::APP_ID]);
			$accessToken = $result['access_token'];
			$refreshToken = $result['refresh_token'];
			$account->setUrl($adminOauthUrl);
			$account->setEncryptedToken($accessToken);
			$account->setEncryptedRefreshToken($refreshToken);
			if (isset($result['expires_in'])) {
				$nowTs = (new DateTime())->getTimestamp();
				$expiresAt = $nowTs + (int)$result['expires_in'];
				$account->setTokenExpiresAt($expiresAt);
			}
			$this->accountMapper->update($account);
			return true;
		} else {
			// impossible to refresh the token
			$this->logger->error(
				'Token is not valid anymore. Impossible to refresh it. '
					. $result['error'] . ' '
					. $result['error_description'] ?: '[no error description]',
				['app' => Application::APP_ID]
			);
			return false;
		}
	}

	public function revokeOauthToken(GitlabAccount $account): array {
		try {
			$url = $this->config->getAdminOauthUrl() . '/oauth/revoke';
			$options = [
				'headers' => [
					'User-Agent' => 'Nextcloud GitLab integration',
					'Content-Type' => 'application/json',
				],
				'body' => json_encode([
					'client_id' => $this->config->getAdminClientId(),
					'client_secret' => $this->config->getAdminClientSecret(),
					'token' => $account->getClearToken(),
				]),
			];

			$response = $this->client->post($url, $options);
			$respCode = $response->getStatusCode();

			if ($respCode >= 400) {
				return ['error' => $this->l10n->t('Bad credentials')];
			} else {
				return [];
			}
		} catch (Exception $e) {
			$this->logger->warning('GitLab API error : ' . $e->getMessage(), ['app' => Application::APP_ID]);
			return ['error' => $e->getMessage()];
		}
	}

	/**
	 * @param string $url
	 * @param array $params
	 * @param string $method
	 * @return array
	 */
	public function requestOAuthAccessToken(string $url, array $params = [], string $method = 'GET'): array {
		try {
			$url = $url . '/oauth/token';
			$options = [
				'headers' => [
					'User-Agent' => 'Nextcloud GitLab integration',
				]
			];

			if (count($params) > 0) {
				if ($method === 'GET') {
					$paramsContent = http_build_query($params);
					$url .= '?' . $paramsContent;
				} else {
					$options['body'] = $params;
				}
			}

			if ($method === 'GET') {
				$response = $this->client->get($url, $options);
			} elseif ($method === 'POST') {
				$response = $this->client->post($url, $options);
			} elseif ($method === 'PUT') {
				$response = $this->client->put($url, $options);
			} elseif ($method === 'DELETE') {
				$response = $this->client->delete($url, $options);
			} else {
				return ['error' => $this->l10n->t('Bad HTTP method')];
			}
			$body = $response->getBody();
			$respCode = $response->getStatusCode();

			if ($respCode >= 400) {
				return ['error' => $this->l10n->t('OAuth access token refused')];
			} else {
				return json_decode($body, true);
			}
		} catch (Exception $e) {
			$this->logger->warning('GitLab OAuth error : ' . $e->getMessage(), ['app' => Application::APP_ID]);
			return ['error' => $e->getMessage()];
		}
	}
}
