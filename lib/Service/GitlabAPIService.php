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

use DateInterval;
use Datetime;
use DateTimeImmutable;
use Exception;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ServerException;
use OCA\Gitlab\AppInfo\Application;
use OCP\IConfig;
use OCP\IL10N;
use Psr\Log\LoggerInterface;
use OCP\Http\Client\IClientService;

class GitlabAPIService {
	/**
	 * @var string
	 */
	private $appName;
	/**
	 * @var LoggerInterface
	 */
	private $logger;
	/**
	 * @var IL10N
	 */
	private $l10n;
	/**
	 * @var \OCP\Http\Client\IClient
	 */
	private $client;
	/**
	 * @var IConfig
	 */
	private $config;

	/**
	 * Service to make requests to GitLab v3 (JSON) API
	 */
	public function __construct (string $appName,
								LoggerInterface $logger,
								IL10N $l10n,
								IConfig $config,
								IClientService $clientService) {
		$this->appName = $appName;
		$this->logger = $logger;
		$this->l10n = $l10n;
		$this->client = $clientService->newClient();
		$this->config = $config;
	}

	/**
	 * @param string $userId
	 * @param string $url
	 * @return array
	 */
	private function getMyProjectsInfo(string $userId, string $url): array {
		$params = [
			'membership' => 'true',
		];
		$projects = $this->request($userId, $url, 'projects', $params);
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
	 * @param string $userId
	 * @param string $url
	 * @param string $term
	 * @param int $offset
	 * @param int $limit
	 * @return array
	 * @throws Exception
	 */
	public function searchRepositories(string $userId, string $url, string $term, int $offset = 0, int $limit = 5): array {
		$params = [
			'scope' => 'projects',
			'search' => $term,
		];
		$projects = $this->request($userId, $url, 'search', $params);
		if (isset($projects['error'])) {
			return $projects;
		}
		usort($projects, function($a, $b) {
			$a = new Datetime($a['last_activity_at']);
			$ta = $a->getTimestamp();
			$b = new Datetime($b['last_activity_at']);
			$tb = $b->getTimestamp();
			return ($ta > $tb) ? -1 : 1;
		});
		//$a = usort($projects, function($a, $b) {
		//	$sa = intval($a['star_count']);
		//	$sb = intval($b['star_count']);
		//	return ($sa > $sb) ? -1 : 1;
		//});
		return array_slice($projects, $offset, $limit);
	}

	/**
	 * @param string $userId
	 * @param string $url
	 * @param string $term
	 * @param int $offset
	 * @param int $limit
	 * @return array
	 * @throws Exception
	 */
	public function searchIssues(string $userId, string $url, string $term, int $offset = 0, int $limit = 5): array {
		$params = [
			'scope' => 'issues',
			'search' => $term,
		];
		$issues = $this->request($userId, $url, 'search', $params);
		if (isset($issues['error'])) {
			return $issues;
		}
		foreach ($issues as $k => $issue) {
			$issues[$k]['type'] = 'issue';
		}

		$params = [
			'scope' => 'merge_requests',
			'search' => $term,
		];
		$mergeRequests = $this->request($userId, $url, 'search', $params);
		if (isset($mergeRequests['error'])) {
			return $mergeRequests;
		}
		foreach ($mergeRequests as $k => $mergeRequest) {
			$mergeRequests[$k]['type'] = 'merge_request';
		}

		$results = array_merge($issues, $mergeRequests);

		usort($results, function($a, $b) {
			$a = new Datetime($a['updated_at']);
			$ta = $a->getTimestamp();
			$b = new Datetime($b['updated_at']);
			$tb = $b->getTimestamp();
			return ($ta > $tb) ? -1 : 1;
		});
		return array_slice($results, $offset, $limit);
	}

	/**
	 * @param string $userId
	 * @param string $url
	 * @param ?string $since
	 * @return array
	 * @throws Exception
	 */
	public function getEvents(string $userId, string $url, ?string $since = null): array {
		// first get list of the projects i'm member of
		$projectsInfo = $this->getMyProjectsInfo($userId, $url);
		if (isset($projectsInfo['error'])) {
			return $projectsInfo;
		}
		// get current user ID
		$user = $this->request($userId, $url, 'user');
		if (isset($user['error'])) {
			return $user;
		}

		// then get many things
		$params = [
			'scope' => 'all',
		];
		if (is_null($since)) {
			$twoWeeksEarlier = new DateTime();
			$twoWeeksEarlier->sub(new DateInterval('P14D'));
			$params['after'] = $twoWeeksEarlier->format('Y-m-d');
		} else {
			// we get a full ISO date, the API only wants a day (non inclusive)
			$sinceDate = new DateTimeImmutable($since);
			$sinceTimestamp = $sinceDate->getTimestamp();
			$minusOneDayDate = $sinceDate->sub(new DateInterval('P1D'));
			$params['after'] = $minusOneDayDate->format('Y-m-d');
		}
		// merge requests created
		$params['target_type'] = 'merge_request';
		$params['action'] = 'created';
		$result = $this->request($userId, $url, 'events', $params);
		if (isset($result['error'])) {
			return $result;
		}
		// merge requests merged
		$params['target_type'] = 'merge_request';
		$params['action'] = 'merged';
		$mrm = $this->request($userId, $url, 'events', $params);
		if (isset($mrm['error'])) {
			return $mrm;
		}
		$result = array_merge($result, $mrm);
		// issues created
		$params['target_type'] = 'issue';
		$params['action'] = 'created';
		$ic = $this->request($userId, $url, 'events', $params);
		if (isset($ic['error'])) {
			return $ic;
		}
		$result = array_merge($result, $ic);
		// issues closed
		$params['target_type'] = 'issue';
		$params['action'] = 'closed';
		$icl = $this->request($userId, $url, 'events', $params);
		if (isset($icl['error'])) {
			return $icl;
		}
		$result = array_merge($result, $icl);
		// issue comments
		$params['target_type'] = 'note';
		$params['action'] = 'commented';
		$ico = $this->request($userId, $url, 'events', $params);
		if (isset($ico['error'])) {
			return $ico;
		}
		$result = array_merge($result, $ico);

		// filter merged results by date
		if (!is_null($since)) {
			$result = array_filter($result, function($elem) use ($sinceTimestamp) {
				$date = new Datetime($elem['created_at']);
				$ts = $date->getTimestamp();
				return $ts > $sinceTimestamp;
			});
		}

		// avoid what has been done by me
		$result = array_filter($result, function($elem) use ($user) {
			return $elem['author_id'] !== $user['id'];
		});
		// make sure it's an array and not a hastable
		$result = array_values($result);

		// sort merged results by date
		usort($result, function($a, $b) {
			$a = new Datetime($a['created_at']);
			$ta = $a->getTimestamp();
			$b = new Datetime($b['created_at']);
			$tb = $b->getTimestamp();
			return ($ta > $tb) ? -1 : 1;
		});

		// add project path in results
		foreach ($result as $k => $r) {
			$pid = $r['project_id'];
			$result[$k]['project_path'] = $projectsInfo[$pid]['path_with_namespace'];
			$result[$k]['project_avatar_url'] = $projectsInfo[$pid]['avatar_url'];
		}
		return $result;
	}

	/**
	 * @param string $url
	 * @param int $id
	 * @return array
	 */
	public function markTodoAsDone(string $userId, string $url, int $id): array {
		return $this->request($userId, $url, 'todos/' . $id . '/mark_as_done', [], 'POST');
	}

	/**
	 * @param string $url
	 * @param ?string $since
	 * @return array
	 */
	public function getTodos(string $userId, string $url, ?string $since = null): array {
		$params = [
			'action' => ['assigned', 'mentioned', 'build_failed', 'marked', 'approval_required', 'unmergeable', 'directly_addressed'],
			'state' => 'pending',
		];
		$result = $this->request($userId, $url, 'todos', $params);
		if (isset($result['error'])) {
			return $result;
		}

		// filter results by date
		if (!is_null($since)) {
			// we get a full ISO date, the API only wants a day (non inclusive)
			$sinceDate = new DateTime($since);
			$sinceTimestamp = $sinceDate->getTimestamp();

			$result = array_filter($result, function($elem) use ($sinceTimestamp) {
				$date = new Datetime($elem['updated_at']);
				$ts = $date->getTimestamp();
				return $ts > $sinceTimestamp;
			});
		}

		// make sure it's an array and not a hastable
		$result = array_values($result);

		// add project avatars to results
		$projectsInfo = $this->getMyProjectsInfo($userId, $url);
		foreach ($result as $k => $todo) {
			$pid = $todo['project']['id'];
			if (array_key_exists($pid, $projectsInfo)) {
				$result[$k]['project']['avatar_url'] = $projectsInfo[$pid]['avatar_url'];
				$result[$k]['project']['visibility'] = $projectsInfo[$pid]['visibility'];
			} else {
				// get the project avatar
				$projectInfo = $this->request($userId, $url, 'projects/' . $pid);
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

	/**
	 * @param int $userId
	 * @param string $gitlabUrl
	 * @return array
	 */
	public function getUserAvatar(string $userId, int $gitlabUserId, string $gitlabUrl): array {
		$userInfo = $this->request($userId, $gitlabUrl, 'users/' . $gitlabUserId);
		if (!isset($userInfo['error']) && isset($userInfo['avatar_url'])) {
			return ['avatarContent' => $this->client->get($userInfo['avatar_url'])->getBody()];
		}
		return ['userInfo' => $userInfo];
	}

	/**
	 * @param int $projectId
	 * @param string $gitlabUrl
	 * @return array
	 */
	public function getProjectAvatar(string $userId, int $projectId, string $gitlabUrl): array {
		$projectInfo = $this->request($userId, $gitlabUrl, 'projects/' . $projectId);
		if (!isset($projectInfo['error']) && isset($projectInfo['avatar_url'])) {
			return ['avatarContent' => $this->client->get($projectInfo['avatar_url'])->getBody()];
		}
		return ['projectInfo' => $projectInfo];
	}

	/**
	 * @param string $url
	 * @param string $endPoint
	 * @param array $params
	 * @param string $method
	 * @return array
	 */
	public function request(string $userId, string $baseUrl, string $endPoint, array $params = [], string $method = 'GET'): array {
		$this->checkTokenExpiration($userId, $baseUrl);
		$accessToken = $this->config->getUserValue($userId, Application::APP_ID, 'token');
		try {
			$url = $baseUrl . '/api/v4/' . $endPoint;
			$options = [
				'headers' => [
					'Authorization'  => 'Bearer ' . $accessToken,
					'User-Agent' => 'Nextcloud GitLab integration'
				],
			];

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
			} else if ($method === 'POST') {
				$response = $this->client->post($url, $options);
			} else if ($method === 'PUT') {
				$response = $this->client->put($url, $options);
			} else if ($method === 'DELETE') {
				$response = $this->client->delete($url, $options);
			} else {
				return ['error' => $this->l10n->t('Bad HTTP method')];
			}
			$body = $response->getBody();
			$respCode = $response->getStatusCode();

			if ($respCode >= 400) {
				return ['error' => $this->l10n->t('Bad credentials')];
			} else {
				return json_decode($body, true);
			}
		} catch (ServerException | ClientException $e) {
			$response = $e->getResponse();
			$refreshToken = $this->config->getUserValue($userId, Application::APP_ID, 'refresh_token');
			if ($response->getStatusCode() === 401 && $refreshToken) {
				// try to refresh the token
				$this->logger->info('Trying to REFRESH the access token', ['app' => $this->appName]);
				if ($this->refreshToken($userId, $baseUrl)) {
					// retry the request with new access token
					return $this->request($userId, $baseUrl, $endPoint, $params, $method);
				} else {
					return ['error' => 'No GitLab refresh token, impossible to refresh the token'];
				}
			}
			$this->logger->warning('GitLab API error : '.$e->getMessage(), ['app' => $this->appName]);
			return ['error' => 'Authentication to ' . $baseUrl . ' failed'];
		} catch (ConnectException $e) {
			$this->logger->warning('GitLab API error : '.$e->getMessage(), ['app' => $this->appName]);
			return ['error' => $e->getMessage()];
		}
	}

	private function checkTokenExpiration(string $userId, string $url): void {
		$refreshToken = $this->config->getUserValue($userId, Application::APP_ID, 'refresh_token');
		$expireAt = $this->config->getUserValue($userId, Application::APP_ID, 'token_expires_at');
		if ($refreshToken !== '' && $expireAt !== '') {
			$nowTs = (new Datetime())->getTimestamp();
			$expireAt = (int) $expireAt;
			// if token expires in less than a minute or is already expired
			if ($nowTs > $expireAt - 60) {
				$this->refreshToken($userId, $url);
			}
		}
	}

	private function refreshToken(string $userId, string $url): bool {
		$clientID = $this->config->getAppValue(Application::APP_ID, 'client_id');
		$clientSecret = $this->config->getAppValue(Application::APP_ID, 'client_secret');
		$redirect_uri = $this->config->getUserValue($userId, Application::APP_ID, 'redirect_uri');
		$refreshToken = $this->config->getUserValue($userId, Application::APP_ID, 'refresh_token');
		if (!$refreshToken) {
			$this->logger->error('No GitLab refresh token found', ['app' => $this->appName]);
			return false;
		}
		$result = $this->requestOAuthAccessToken($url, [
			'client_id' => $clientID,
			'client_secret' => $clientSecret,
			'grant_type' => 'refresh_token',
			'redirect_uri' => $redirect_uri,
			'refresh_token' => $refreshToken,
		], 'POST');
		if (isset($result['access_token'])) {
			$this->logger->info('GitLab access token successfully refreshed', ['app' => $this->appName]);
			$accessToken = $result['access_token'];
			$refreshToken = $result['refresh_token'];
			$this->config->setUserValue($userId, Application::APP_ID, 'token', $accessToken);
			$this->config->setUserValue($userId, Application::APP_ID, 'refresh_token', $refreshToken);
			if (isset($result['expires_in'])) {
				$nowTs = (new Datetime())->getTimestamp();
				$expiresAt = $nowTs + (int) $result['expires_in'];
				$this->config->setUserValue($userId, Application::APP_ID, 'token_expires_at', $expiresAt);
			}
			return true;
		} else {
			// impossible to refresh the token
			$this->logger->error(
				'Token is not valid anymore. Impossible to refresh it. '
					. $result['error'] . ' '
					. $result['error_description'] ?? '[no error description]',
				['app' => $this->appName]
			);
			return false;
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
					'User-Agent'  => 'Nextcloud GitLab integration',
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
			} else if ($method === 'POST') {
				$response = $this->client->post($url, $options);
			} else if ($method === 'PUT') {
				$response = $this->client->put($url, $options);
			} else if ($method === 'DELETE') {
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
			$this->logger->warning('GitLab OAuth error : '.$e->getMessage(), array('app' => $this->appName));
			return ['error' => $e->getMessage()];
		}
	}
}
