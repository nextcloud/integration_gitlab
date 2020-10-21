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
use Psr\Log\LoggerInterface;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\RedirectResponse;

use OCP\AppFramework\Http\ContentSecurityPolicy;

use OCP\IRequest;
use OCP\IDBConnection;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;

use OCA\Gitlab\Service\GitlabAPIService;
use OCA\Gitlab\AppInfo\Application;

class ConfigController extends Controller {

	private $userId;
	private $config;
	private $dbconnection;
	private $dbtype;

	public function __construct($AppName,
								IRequest $request,
								IServerContainer $serverContainer,
								IConfig $config,
								IAppManager $appManager,
								IAppData $appData,
								IDBConnection $dbconnection,
								IURLGenerator $urlGenerator,
								IL10N $l,
								LoggerInterface $logger,
								GitlabAPIService $gitlabAPIService,
								$userId) {
		parent::__construct($AppName, $request);
		$this->l = $l;
		$this->userId = $userId;
		$this->appData = $appData;
		$this->serverContainer = $serverContainer;
		$this->config = $config;
		$this->dbconnection = $dbconnection;
		$this->urlGenerator = $urlGenerator;
		$this->logger = $logger;
		$this->gitlabAPIService = $gitlabAPIService;
	}

	/**
	 * set config values
	 * @NoAdminRequired
	 *
	 * @param array $values
	 * @return DataResponse
	 */
	public function setConfig(array $values): DataResponse {
		foreach ($values as $key => $value) {
			$this->config->setUserValue($this->userId, Application::APP_ID, $key, $value);
		}
		$result = [];

		if (isset($values['token'])) {
			if ($values['token'] && $values['token'] !== '') {
				$gitlabUrl = $this->config->getUserValue($this->userId, Application::APP_ID, 'url', 'https://gitlab.com');
				$gitlabUrl = $gitlabUrl && $gitlabUrl !== '' ? $gitlabUrl : 'https://gitlab.com';
				$userName = $this->storeUserInfo($gitlabUrl, $values['token']);
				$result['user_name'] = $userName;
			} else {
				$this->config->setUserValue($this->userId, Application::APP_ID, 'user_id', '');
				$this->config->setUserValue($this->userId, Application::APP_ID, 'user_name', '');
				$result['user_name'] = '';
			}
		}
		return new DataResponse($result);
	}

	/**
	 * set admin config values
	 *
	 * @param array $values
	 * @return DataResponse
	 */
	public function setAdminConfig(array $values): DataResponse {
		foreach ($values as $key => $value) {
			$this->config->setAppValue(Application::APP_ID, $key, $value);
		}
		$response = new DataResponse(1);
		return $response;
	}

	/**
	 * receive oauth code and get oauth access token
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @param string $code
	 * @param string $state
	 * @return RedirectResponse
	 */
	public function oauthRedirect(string $code = '', string $state = ''): RedirectResponse {
		$configState = $this->config->getUserValue($this->userId, Application::APP_ID, 'oauth_state', '');
		$clientID = $this->config->getAppValue(Application::APP_ID, 'client_id', '');
		$clientSecret = $this->config->getAppValue(Application::APP_ID, 'client_secret', '');

		// anyway, reset state
		$this->config->setUserValue($this->userId, Application::APP_ID, 'oauth_state', '');

		if ($clientID and $clientSecret and $configState !== '' and $configState === $state) {
			$redirect_uri = $this->config->getUserValue($this->userId, Application::APP_ID, 'redirect_uri', '');
			$gitlabUrl = $this->config->getUserValue($this->userId, Application::APP_ID, 'url', 'https://gitlab.com');
			$gitlabUrl = $gitlabUrl && $gitlabUrl !== '' ? $gitlabUrl : 'https://gitlab.com';
			$result = $this->gitlabAPIService->requestOAuthAccessToken($gitlabUrl, [
				'client_id' => $clientID,
				'client_secret' => $clientSecret,
				'code' => $code,
				'redirect_uri' => $redirect_uri,
				'grant_type' => 'authorization_code'
			], 'POST');
			if (isset($result['access_token'])) {
				$accessToken = $result['access_token'];
				$this->config->setUserValue($this->userId, Application::APP_ID, 'token', $accessToken);
				$this->storeUserInfo($gitlabUrl, $accessToken);
				return new RedirectResponse(
					$this->urlGenerator->linkToRoute('settings.PersonalSettings.index', ['section' => 'connected-accounts']) .
					'?gitlabToken=success'
				);
			}
			$result = $this->l->t('Error getting OAuth access token. ' . $result['error']);
		} else {
			$result = $this->l->t('Error during OAuth exchanges');
		}
		return new RedirectResponse(
			$this->urlGenerator->linkToRoute('settings.PersonalSettings.index', ['section' => 'connected-accounts']) .
			'?gitlabToken=error&message=' . urlencode($result)
		);
	}

	/**
	 * @param string $gitlabUrl
	 * @param string $accessToken
	 * @return string
	 */
	private function storeUserInfo(string $gitlabUrl, string $accessToken): string {
		$info = $this->gitlabAPIService->request($gitlabUrl, $accessToken, 'user');
		if (isset($info['username']) && isset($info['id'])) {
			$this->config->setUserValue($this->userId, Application::APP_ID, 'user_id', $info['id']);
			$this->config->setUserValue($this->userId, Application::APP_ID, 'user_name', $info['username']);
			return $info['username'];
		} else {
			$this->config->setUserValue($this->userId, Application::APP_ID, 'user_id', '');
			$this->config->setUserValue($this->userId, Application::APP_ID, 'user_name', '');
			return '';
		}
	}
}
