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

    public function getNotifications($accessToken, $since = null, $participating = null) {
        $params = [];
        if (!is_null($since)) {
            $params['since'] = $since;
        }
        if (!is_null($participating)) {
            $params['participating'] = $participating ? 'true' : 'false';
        }
        $result = $this->request($accessToken, 'notifications', $params);
        return $result;
    }

    public function request($accessToken, $endPoint, $params = [], $method = 'GET') {
        try {
            $options = [
                'http' => [
                    'header'  => 'Authorization: Basic ' . base64_encode('USERID_NOT_NEEDED:' . $accessToken) .
                        "\r\nUser-Agent: Nextcloud Gitlab integration",
                    'method' => $method,
                ]
            ];

            $url = 'https://api.gitlab.com/notifications';
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
