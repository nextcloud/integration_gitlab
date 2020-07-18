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
        $result = $this->request($url, $accessToken, 'projects', $params);
        // build a project ID conversion hashtable
        // TODO

        // then get many things
        $params = [
            'scope' => 'all',
        ];
        if (!is_null($since)) {
            $params['after'] = $since;
        }
        // merge requests
        $params['target_type'] = 'merge_request';
        $params['action'] = 'created';
        $result = $this->request($url, $accessToken, 'events', $params);
        // issue comments
        $params['target_type'] = 'note';
        $params['action'] = 'commented';
        $result = array_merge($result, $this->request($url, $accessToken, 'events', $params));

        $a = usort($result, function($a, $b) {
            $a = new \Datetime($a['created_at']);
            $ta = $a->getTimestamp();
            $b = new \Datetime($b['created_at']);
            $tb = $b->getTimestamp();
            return ($ta > $tb) ? -1 : 1;
        });
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
