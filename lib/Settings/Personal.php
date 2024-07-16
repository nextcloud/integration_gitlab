<?php

namespace OCA\Gitlab\Settings;

use OCA\Gitlab\AppInfo\Application;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IConfig;

use OCP\Settings\ISettings;

class Personal implements ISettings {

	public function __construct(private IConfig $config,
		private IInitialState $initialStateService,
		private ?string $userId) {
	}

	/**
	 * @return TemplateResponse
	 */
	public function getForm(): TemplateResponse {
		$token = $this->config->getUserValue($this->userId, Application::APP_ID, 'token');
		$searchEnabled = $this->config->getUserValue($this->userId, Application::APP_ID, 'search_enabled', '0') === '1';
		$searchIssuesEnabled = $this->config->getUserValue($this->userId, Application::APP_ID, 'search_issues_enabled', '0') === '1';
		$searchMRsEnabled = $this->config->getUserValue($this->userId, Application::APP_ID, 'search_mrs_enabled', '0') === '1';
		$linkPreviewEnabled = $this->config->getUserValue($this->userId, Application::APP_ID, 'link_preview_enabled', '1') === '1';

		$userName = $this->config->getUserValue($this->userId, Application::APP_ID, 'user_name');
		$userDisplayName = $this->config->getUserValue($this->userId, Application::APP_ID, 'user_displayname');

		// for OAuth
		$clientID = $this->config->getAppValue(Application::APP_ID, 'client_id');
		// don't expose the client secret to users
		$clientSecret = ($this->config->getAppValue(Application::APP_ID, 'client_secret') !== '');
		$adminOauthUrl = $this->config->getAppValue(Application::APP_ID, 'oauth_instance_url', Application::DEFAULT_GITLAB_URL) ?: Application::DEFAULT_GITLAB_URL;
		$usePopup = $this->config->getAppValue(Application::APP_ID, 'use_popup', '0');

		$url = $this->config->getUserValue($this->userId, Application::APP_ID, 'url', $adminOauthUrl) ?: $adminOauthUrl;

		$userConfig = [
			'token' => $token,
			'url' => $url,
			'client_id' => $clientID,
			'client_secret' => $clientSecret,
			'oauth_instance_url' => $adminOauthUrl,
			'use_popup' => ($usePopup === '1'),
			'user_name' => $userName,
			'user_displayname' => $userDisplayName,
			'search_enabled' => $searchEnabled,
			'search_issues_enabled' => $searchIssuesEnabled,
			'search_mrs_enabled' => $searchMRsEnabled,
			'link_preview_enabled' => $linkPreviewEnabled,
		];
		$this->initialStateService->provideInitialState('user-config', $userConfig);
		return new TemplateResponse(Application::APP_ID, 'personalSettings');
	}

	public function getSection(): string {
		return 'connected-accounts';
	}

	public function getPriority(): int {
		return 10;
	}
}
