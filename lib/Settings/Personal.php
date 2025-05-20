<?php

namespace OCA\Gitlab\Settings;

use OCA\Gitlab\AppInfo\Application;
use OCA\Gitlab\Db\GitlabAccount;
use OCA\Gitlab\Db\GitlabAccountMapper;
use OCA\Gitlab\Model\UserConfig;
use OCA\Gitlab\Service\ConfigService;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\Settings\ISettings;

class Personal implements ISettings {

	public function __construct(
		private ConfigService $config,
		private IInitialState $initialStateService,
		private string $userId,
		private GitlabAccountMapper $accountMapper,
	) {
	}

	/**
	 * @return TemplateResponse
	 */
	public function getForm(): TemplateResponse {
		$this->initialStateService->provideInitialState('accounts', array_map(static fn (GitlabAccount $account) => $account->jsonSerialize(), $this->accountMapper->find($this->userId)));
		$this->initialStateService->provideInitialState('user-config', UserConfig::loadConfig($this->userId, $this->config)->toArray());
		$this->initialStateService->provideInitialState('admin-config', [
			'oauth_is_possible' => $this->config->getAdminClientId() !== '' && $this->config->getAdminClientSecret() !== '',
			'oauth_instance_url' => $this->config->getAdminOauthUrl(),
			'force_gitlab_instance_url' => $this->config->getAdminForceGitlabInstanceUrl(),
		]);
		return new TemplateResponse(Application::APP_ID, 'personalSettings');
	}

	public function getSection(): string {
		return 'connected-accounts';
	}

	public function getPriority(): int {
		return 10;
	}
}
