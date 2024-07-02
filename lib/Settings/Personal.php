<?php

namespace OCA\Gitlab\Settings;

use OCA\Gitlab\AppInfo\Application;
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
	) {
	}

	/**
	 * @return TemplateResponse
	 */
	public function getForm(): TemplateResponse {
		$this->initialStateService->provideInitialState('user-config', UserConfig::loadConfig($this->userId, $this->config)->toArray());
		return new TemplateResponse(Application::APP_ID, 'personalSettings');
	}

	public function getSection(): string {
		return 'connected-accounts';
	}

	public function getPriority(): int {
		return 10;
	}
}
