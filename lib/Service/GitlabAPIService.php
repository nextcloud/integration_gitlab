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

use OCP\IL10N;
use Psr\Log\LoggerInterface;
use OCP\Http\Client\IClientService;

class GitlabAPIService {

	private $l10n;
	private $logger;

	/**
	 * Service to make requests to GitLab v3 (JSON) API
	 */
	public function __construct (
		string $appName,
		LoggerInterface $logger,
		IL10N $l10n,
		IClientService $clientService
	) {
		$this->appName = $appName;
		$this->l10n = $l10n;
		$this->logger = $logger;
		$this->clientService = $clientService;
		$this->client = $clientService->newClient();
	}

	/**
	 * @param string $url
	 * @param string $accessToken
	 * @return array
	 */
	private function getMyProjectsInfo(string $url, string $accessToken): array {
		$params = [
			'membership' => 'true',
		];
		$projects = $this->request($url, $accessToken, 'projects', $params);
		if (isset($projects['error'])) {
			return $projects;
		}
		$projectsInfo = [];
		foreach ($projects as $project) {
			$pid = $project['id'];
			$projectsInfo[$pid] = [
				'path_with_namespace' => $project['path_with_namespace'],
				'avatar_url' => $project['avatar_url'],
			];
		}
		return $projectsInfo;
	}

	/**
	 * @param string $url
	 * @param string $accessToken
	 * @param string $term
	 * @param int $offset
	 * @param int $limit
	 * @return array
	 */
	public function searchRepositories(string $url, string $accessToken, string $term, int $offset = 0, int $limit = 5): array {
		$params = [
			'scope' => 'projects',
			'search' => $term,
		];
		$projects = $this->request($url, $accessToken, 'search', $params);
		if (isset($projects['error'])) {
			return $projects;
		}
		$a = usort($projects, function($a, $b) {
			$a = new \Datetime($a['last_activity_at']);
			$ta = $a->getTimestamp();
			$b = new \Datetime($b['last_activity_at']);
			$tb = $b->getTimestamp();
			return ($ta > $tb) ? -1 : 1;
		});
		//$a = usort($projects, function($a, $b) {
		//	$sa = intval($a['star_count']);
		//	$sb = intval($b['star_count']);
		//	return ($sa > $sb) ? -1 : 1;
		//});
		$projects = array_slice($projects, $offset, $limit);
		return $projects;
	}

	/**
	 * @param string $url
	 * @param string $accessToken
	 * @param string $term
	 * @param int $offset
	 * @param int $limit
	 * @return array
	 */
	public function searchIssues(string $url, string $accessToken, string $term, int $offset = 0, int $limit = 5): array {
		$params = [
			'scope' => 'issues',
			'search' => $term,
		];
		$issues = $this->request($url, $accessToken, 'search', $params);
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
		$mergeRequests = $this->request($url, $accessToken, 'search', $params);
		if (isset($mergeRequests['error'])) {
			return $mergeRequests;
		}
		foreach ($mergeRequests as $k => $mergeRequest) {
			$mergeRequests[$k]['type'] = 'merge_request';
		}

		$results = array_merge($issues, $mergeRequests);

		$a = usort($results, function($a, $b) {
			$a = new \Datetime($a['updated_at']);
			$ta = $a->getTimestamp();
			$b = new \Datetime($b['updated_at']);
			$tb = $b->getTimestamp();
			return ($ta > $tb) ? -1 : 1;
		});
		$results = array_slice($results, $offset, $limit);
		return $results;
	}

	/**
	 * @param string $url
	 * @param string $accessToken
	 * @param ?string $since
	 * @return array
	 */
	public function getEvents(string $url, string $accessToken, ?string $since = null): array {
		// first get list of the projects i'm member of
		$projectsInfo = $this->getMyProjectsInfo($url, $accessToken);
		if (isset($projectsInfo['error'])) {
			return $projectsInfo;
		}
		// get current user ID
		$user = $this->request($url, $accessToken, 'user');
		if (isset($user['error'])) {
			return $user;
		}

		// then get many things
		$params = [
			'scope' => 'all',
		];
		if (is_null($since)) {
			$twoWeeksEarlier = new \DateTime();
			$twoWeeksEarlier->sub(new \DateInterval('P14D'));
			$params['after'] = $twoWeeksEarlier->format('Y-m-d');
		} else {
			// we get a full ISO date, the API only wants a day (non inclusive)
			$sinceDate = new \DateTimeImmutable($since);
			$sinceTimestamp = $sinceDate->getTimestamp();
			$minusOneDayDate = $sinceDate->sub(new \DateInterval('P1D'));
			$params['after'] = $minusOneDayDate->format('Y-m-d');
		}
		// merge requests created
		$params['target_type'] = 'merge_request';
		$params['action'] = 'created';
		$result = $this->request($url, $accessToken, 'events', $params);
		if (isset($result['error'])) {
			return $result;
		}
		// merge requests merged
		$params['target_type'] = 'merge_request';
		$params['action'] = 'merged';
		$mrm = $this->request($url, $accessToken, 'events', $params);
		if (isset($mrm['error'])) {
			return $mrm;
		}
		$result = array_merge($result, $mrm);
		// issues created
		$params['target_type'] = 'issue';
		$params['action'] = 'created';
		$ic = $this->request($url, $accessToken, 'events', $params);
		if (isset($ic['error'])) {
			return $ic;
		}
		$result = array_merge($result, $ic);
		// issues closed
		$params['target_type'] = 'issue';
		$params['action'] = 'closed';
		$icl = $this->request($url, $accessToken, 'events', $params);
		if (isset($icl['error'])) {
			return $icl;
		}
		$result = array_merge($result, $icl);
		// issue comments
		$params['target_type'] = 'note';
		$params['action'] = 'commented';
		$ico = $this->request($url, $accessToken, 'events', $params);
		if (isset($ico['error'])) {
			return $ico;
		}
		$result = array_merge($result, $ico);

		// filter merged results by date
		if (!is_null($since)) {
			$result = array_filter($result, function($elem) use ($sinceTimestamp) {
				$date = new \Datetime($elem['created_at']);
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
		$a = usort($result, function($a, $b) {
			$a = new \Datetime($a['created_at']);
			$ta = $a->getTimestamp();
			$b = new \Datetime($b['created_at']);
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
	 * @param string $accessToken
	 * @param int $id
	 * @return array
	 */
	public function markTodoAsDone(string $url, string $accessToken, int $id): array {
		return $this->request($url, $accessToken, 'todos/' . $id . '/mark_as_done', [], 'POST');
	}

	/**
	 * @param string $url
	 * @param string $accessToken
	 * @param ?string $since
	 * @return array
	 */
	public function getTodos(string $url, string $accessToken, ?string $since = null): array {
		$params = [
			'action' => ['assigned', 'mentioned', 'build_failed', 'marked', 'approval_required', 'unmergeable', 'directly_addressed'],
			'state' => 'pending',
		];
		$result = $this->request($url, $accessToken, 'todos', $params);
		if (isset($result['error'])) {
			return $result;
		}

		// filter results by date
		if (!is_null($since)) {
			// we get a full ISO date, the API only wants a day (non inclusive)
			$sinceDate = new \DateTime($since);
			$sinceTimestamp = $sinceDate->getTimestamp();

			$result = array_filter($result, function($elem) use ($sinceTimestamp) {
				$date = new \Datetime($elem['updated_at']);
				$ts = $date->getTimestamp();
				return $ts > $sinceTimestamp;
			});
		}

		// make sure it's an array and not a hastable
		$result = array_values($result);

		// add project avatars to results
		$projectsInfo = $this->getMyProjectsInfo($url, $accessToken);
		foreach ($result as $k => $todo) {
			$pid = $todo['project']['id'];
			if (array_key_exists($pid, $projectsInfo)) {
				$result[$k]['project']['avatar_url'] = $projectsInfo[$pid]['avatar_url'];
			} else {
				// get the project avatar
				$projectInfo = $this->request($url, $accessToken, 'projects/' . $pid);
				if (isset($projectInfo['error'])) {
					return $projectInfo;
				}
				$result[$k]['project']['avatar_url'] = $projectInfo['avatar_url'];
				// cache result
				$projectsInfo[$pid] = [
					'avatar_url' => $projectInfo['avatar_url']
				];
			}
		}

		return $result;
	}

	/**
	 * @param string $url
	 * @param string $gitlabUrl
	 * @param string $accessToken
	 * @return array
	 */
	public function getGitlabAvatar(string $avatarUrl, string $gitlabUrl, string $accessToken): array {
		$gUrl = parse_url($gitlabUrl);
		$aUrl = parse_url($avatarUrl);
		if ($gUrl && $aUrl) {
			$gitlabHost = $gUrl['host'];
			$avatarHost = $aUrl['host'];
			if ($gitlabHost === $avatarHost || preg_match('/\.gitlab-static\.net$/', $avatarHost)) {
				return $this->simpleRequestString($avatarUrl, $accessToken);
			}
		}
		return ['error' => 'Unauthorize hostname'];
	}

	/**
	 * @param string $url
	 * @param string $accessToken
	 * @param string $endPoint
	 * @param array $params
	 * @param string $method
	 * @return array
	 */
	public function simpleRequestString(string $url, string $accessToken, array $params = [], string $method = 'GET'): array {
		try {
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
			}
			$body = $response->getBody();
			$respCode = $response->getStatusCode();

			if ($respCode >= 400) {
				return ['error' => $this->l10n->t('Bad credentials')];
			} else {
				return ['content' => $body];
			}
		} catch (\Exception $e) {
			$this->logger->warning('GitLab API error : '.$e->getMessage(), array('app' => $this->appName));
			return ['error' => $e->getMessage()];
		}
	}

	/**
	 * @param string $url
	 * @param string $accessToken
	 * @param string $endPoint
	 * @param array $params
	 * @param string $method
	 * @return array
	 */
	public function request(string $url, string $accessToken, string $endPoint, array $params = [], string $method = 'GET'): array {
		try {
			$url = $url . '/api/v4/' . $endPoint;
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
			}
			$body = $response->getBody();
			$respCode = $response->getStatusCode();

			if ($respCode >= 400) {
				return ['error' => $this->l10n->t('Bad credentials')];
			} else {
				return json_decode($body, true);
			}
		} catch (\Exception $e) {
			$this->logger->warning('GitLab API error : '.$e->getMessage(), array('app' => $this->appName));
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
			}
			$body = $response->getBody();
			$respCode = $response->getStatusCode();

			if ($respCode >= 400) {
				return ['error' => $this->l10n->t('OAuth access token refused')];
			} else {
				return json_decode($body, true);
			}
		} catch (\Exception $e) {
			$this->logger->warning('GitLab OAuth error : '.$e->getMessage(), array('app' => $this->appName));
			return ['error' => $e->getMessage()];
		}
	}
}
