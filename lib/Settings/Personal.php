<?php
namespace OCA\Gitlab\Settings;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use OCP\IL10N;
use OCP\IConfig;
use OCP\Settings\ISettings;
use OCP\Util;
use OCP\IURLGenerator;
use OCP\IInitialStateService;

use OCA\Gitlab\AppInfo\Application;

class Personal implements ISettings {

	private $request;
	private $config;
	private $dataDirPath;
	private $urlGenerator;
	private $l;

	public function __construct(string $appName,
								IL10N $l,
								IRequest $request,
								IConfig $config,
								IURLGenerator $urlGenerator,
								IInitialStateService $initialStateService,
								$userId) {
		$this->appName = $appName;
		$this->urlGenerator = $urlGenerator;
		$this->request = $request;
		$this->l = $l;
		$this->config = $config;
		$this->initialStateService = $initialStateService;
		$this->userId = $userId;
	}

	/**
	 * @return TemplateResponse
	 */
	public function getForm(): TemplateResponse {
		$token = $this->config->getUserValue($this->userId, Application::APP_ID, 'token', '');
		$searchEnabled = $this->config->getUserValue($this->userId, Application::APP_ID, 'search_enabled', '0');
		$searchIssuesEnabled = $this->config->getUserValue($this->userId, Application::APP_ID, 'search_issues_enabled', '0');
		$navigationEnabled = $this->config->getUserValue($this->userId, Application::APP_ID, 'navigation_enabled', '0');
		$userName = $this->config->getUserValue($this->userId, Application::APP_ID, 'user_name', '');
		$url = $this->config->getUserValue($this->userId, Application::APP_ID, 'url', 'https://gitlab.com') ?: 'https://gitlab.com';

		// for OAuth
		$clientID = $this->config->getAppValue(Application::APP_ID, 'client_id', '');
		// don't expose the client secret to users
		$clientSecret = ($this->config->getAppValue(Application::APP_ID, 'client_secret', '') !== '');
		$oauthUrl = $this->config->getAppValue(Application::APP_ID, 'oauth_instance_url', '');

		$userConfig = [
			'token' => $token,
			'url' => $url,
			'client_id' => $clientID,
			'client_secret' => $clientSecret,
			'oauth_instance_url' => $oauthUrl,
			'user_name' => $userName,
			'search_enabled' => ($searchEnabled === '1'),
			'search_issues_enabled' => ($searchIssuesEnabled === '1'),
			'navigation_enabled' => ($navigationEnabled === '1'),
		];
		$this->initialStateService->provideInitialState($this->appName, 'user-config', $userConfig);
		return new TemplateResponse(Application::APP_ID, 'personalSettings');
	}

	public function getSection(): string {
		return 'connected-accounts';
	}

	public function getPriority(): int {
		return 10;
	}
}
