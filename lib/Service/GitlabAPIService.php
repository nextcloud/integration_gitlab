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
use OCP\ILogger;

class GitlabAPIService {

    private $l10n;
    private $logger;

    /**
     * Service to make requests to Gitlab v3 (JSON) API
     */
    public function __construct (
        string $appName,
        ILogger $logger,
        IL10N $l10n
    ) {
        $this->appName = $appName;
        $this->l10n = $l10n;
        $this->logger = $logger;
    }

    private function getMyProjectsInfo($url, $accessToken) {
        $params = [
            'membership' => 'true',
        ];
        $projects = $this->request($url, $accessToken, 'projects', $params);
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

    public function getEvents($url, $accessToken, $since = null) {
        // first get list of the projects i'm member of
        $projectsInfo = $this->getMyProjectsInfo($url, $accessToken);
        // get current user ID
        $user = $this->request($url, $accessToken, 'user');

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
        // merge requests merged
        $params['target_type'] = 'merge_request';
        $params['action'] = 'merged';
        $result = array_merge($result, $this->request($url, $accessToken, 'events', $params));
        // issues created
        $params['target_type'] = 'issue';
        $params['action'] = 'created';
        $result = array_merge($result, $this->request($url, $accessToken, 'events', $params));
        // issues closed
        $params['target_type'] = 'issue';
        $params['action'] = 'closed';
        $result = array_merge($result, $this->request($url, $accessToken, 'events', $params));
        // issue comments
        $params['target_type'] = 'note';
        $params['action'] = 'commented';
        $result = array_merge($result, $this->request($url, $accessToken, 'events', $params));

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

    public function getTodos($url, $accessToken, $since = null) {
        $params = [
            'action' => ['assigned', 'mentioned', 'build_failed', 'marked', 'approval_required', 'unmergeable', 'directly_addressed'],
            'state' => 'pending',
        ];
        $result = $this->request($url, $accessToken, 'todos', $params);
        if (!is_array($result)) {
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
        } else {
            // take 7 most recent if no date filter
            $result = array_slice($result, 0, 7);
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
                $result[$k]['project']['avatar_url'] = $projectInfo['avatar_url'];
                // cache result
                $projectsInfo[$pid] = [
                    'avatar_url' => $projectInfo['avatar_url']
                ];
            }
        }

        return $result;
    }

    public function getGitlabAvatar($url) {
        return file_get_contents($url);
    }

    public function request($url, $accessToken, $endPoint, $params = [], $method = 'GET') {
        try {
            $options = [
                'http' => [
                    'header'  => 'Authorization: Bearer ' . $accessToken .
                        "\r\nUser-Agent: Nextcloud Gitlab integration",
                    'method' => $method,
                ]
            ];

            $url = $url . '/api/v4/' . $endPoint;
            if (count($params) > 0) {
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
                if ($method === 'GET') {
                    $url .= '?' . $paramsContent;
                } else {
                    $options['http']['content'] = $paramsContent;
                }
            }

            $context = stream_context_create($options);
            $result = file_get_contents($url, false, $context);
            if (!$result) {
                return $this->l10n->t('Bad credentials');
            } else {
                return json_decode($result, true);
            }
        } catch (\Exception $e) {
            $this->logger->warning('Gitlab API error : '.$e, array('app' => $this->appName));
            return $e;
        }
    }

}
