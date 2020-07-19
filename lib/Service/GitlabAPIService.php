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

    public function getNotifications($url, $accessToken, $since = null) {
        // first get list of the projects i'm member of
        $params = [
            'membership' => 'true',
        ];
        $projects = $this->request($url, $accessToken, 'projects', $params);
        // build a project ID conversion hashtable
        $pidToPath = [];
        foreach ($projects as $project) {
            $pid = $project['id'];
            $path = $project['path_with_namespace'];
            $pidToPath[$pid] = $path;
        }
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
            $result[$k]['project_path'] = $pidToPath[$pid];
        }
        return $result;
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
                $paramsContent = http_build_query($params);
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
