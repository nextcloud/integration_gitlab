<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Gitlab\Migration;

use Closure;
use OCA\Gitlab\AppInfo\Application;
use OCA\Gitlab\Service\ConfigService;
use OCP\IAppConfig;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version040000Date20250911150115 extends SimpleMigrationStep {

	public function __construct(
		private IAppConfig $appConfig,
		private ConfigService $configService,
	) {
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 */
	#[ \Override]
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options) {
		// app config
		foreach (['client_id', 'client_secret'] as $key) {
			$value = $this->configService->getClearAppValue($key, false);
			if ($value !== '') {
				$this->configService->setEncryptedAppValue($key, $value, true);
			}
		}

		$value = $this->appConfig->getValueString(Application::APP_ID, 'oauth_instance_url');
		$this->appConfig->setValueString(Application::APP_ID, 'oauth_instance_url', $value, lazy: true);
	}
}
