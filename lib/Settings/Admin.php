<?php

namespace OCA\Gitlab\Settings;

use OCA\Gitlab\AppInfo\Application;
use OCA\Gitlab\Model\AdminConfig;
use OCA\Gitlab\Service\ConfigService;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\Settings\ISettings;

class Admin implements ISettings {

	public function __construct(
		private ConfigService $config,
		private IInitialState $initialStateService,
	) {
	}

	/**
	 * @return TemplateResponse
	 */
	public function getForm(): TemplateResponse {
		$this->initialStateService->provideInitialState('admin-config', AdminConfig::loadConfig($this->config)->toArray());
		return new TemplateResponse(Application::APP_ID, 'adminSettings');
	}

	public function getSection(): string {
		return 'connected-accounts';
	}

	public function getPriority(): int {
		return 10;
	}
}
