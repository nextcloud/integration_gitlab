<?php
/**
 * Nextcloud - gitlab
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 * @copyright Julien Veyssier 2020
 */

namespace OCA\Gitlab\Controller;

use OCP\App\IAppManager;
use OCP\Files\IAppData;
use OCP\AppFramework\Http\DataDisplayResponse;

use OCP\IURLGenerator;
use OCP\IConfig;
use OCP\IServerContainer;
use OCP\IL10N;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\RedirectResponse;

use OCP\AppFramework\Http\ContentSecurityPolicy;

use OCP\ILogger;
use OCP\IRequest;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;

use OCA\Gitlab\Service\GitlabAPIService;
use OCA\Gitlab\AppInfo\Application;

class GitlabAPIController extends Controller {

    private $userId;
    private $config;
    private $dbconnection;
    private $dbtype;

    public function __construct($AppName,
                                IRequest $request,
                                IServerContainer $serverContainer,
                                IConfig $config,
                                IL10N $l10n,
                                IAppManager $appManager,
                                IAppData $appData,
                                ILogger $logger,
                                GitlabAPIService $gitlabAPIService,
                                $userId) {
        parent::__construct($AppName, $request);
        $this->userId = $userId;
        $this->l10n = $l10n;
        $this->appData = $appData;
        $this->serverContainer = $serverContainer;
        $this->config = $config;
        $this->logger = $logger;
        $this->gitlabAPIService = $gitlabAPIService;
        $this->accessToken = $this->config->getUserValue($this->userId, Application::APP_ID, 'token', '');
        $this->gitlabUrl = $this->config->getUserValue($this->userId, Application::APP_ID, 'url', 'https://gitlab.com');
        $this->gitlabUrl = $this->gitlabUrl && $this->gitlabUrl !== '' ? $this->gitlabUrl : 'https://gitlab.com';
    }

    /**
     * get notification list
     * @NoAdminRequired
     */
    public function getGitlabUrl(): DataResponse {
        return new DataResponse($this->gitlabUrl);
    }

    /**
     * get gitlab user avatar
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function getGitlabAvatar(string $url): DataDisplayResponse {
        $response = new DataDisplayResponse($this->gitlabAPIService->getGitlabAvatar($url));
        $response->cacheFor(60*60*24);
        return $response;
    }

    /**
     * get event list
     * @NoAdminRequired
     */
    public function getEvents(?string $since = null): DataResponse {
        if ($this->accessToken === '') {
            return new DataResponse('', 400);
        }
        $result = $this->gitlabAPIService->getEvents($this->gitlabUrl, $this->accessToken, $since);
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
     */
    public function getTodos(?string $since = null): DataResponse {
        if ($this->accessToken === '') {
            return new DataResponse('', 400);
        }
        $result = $this->gitlabAPIService->getTodos($this->gitlabUrl, $this->accessToken, $since);
        if (!isset($result['error'])) {
            $response = new DataResponse($result);
        } else {
            $response = new DataResponse($result, 401);
        }
        return $response;
    }

}
