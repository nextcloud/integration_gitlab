<?php
/**
 * @copyright Copyright (c) 2020 Julien Veyssier <julien-nc@posteo.net>
 *
 * @author Julien Veyssier <julien-nc@posteo.net>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Gitlab\Dashboard;

use OCA\Gitlab\AppInfo\Application;
use OCA\Gitlab\Db\GitlabAccountMapper;
use OCA\Gitlab\Model\UserConfig;
use OCA\Gitlab\Service\ConfigService;
use OCP\AppFramework\Services\IInitialState;
use OCP\Dashboard\IWidget;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Util;

class GitlabWidget implements IWidget {

	public function __construct(
		private IL10N $l10n,
		private ConfigService $config,
		private IURLGenerator $url,
		private IInitialState $initialStateService,
		private GitlabAccountMapper $accountMapper,
		private string $userId,
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function getId(): string {
		return 'gitlab_todos';
	}

	/**
	 * @inheritDoc
	 */
	public function getTitle(): string {
		return $this->l10n->t('GitLab To-Dos');
	}

	/**
	 * @inheritDoc
	 */
	public function getOrder(): int {
		return 10;
	}

	/**
	 * @inheritDoc
	 */
	public function getIconClass(): string {
		return 'icon-gitlab';
	}

	/**
	 * @inheritDoc
	 */
	public function getUrl(): ?string {
		return $this->url->linkToRoute('settings.PersonalSettings.index', ['section' => 'connected-accounts']);
	}

	/**
	 * @inheritDoc
	 */
	public function load(): void {
		$userConfig = UserConfig::loadConfig($this->userId, $this->config)->toArray();

		try {
			$account = $this->accountMapper->findById($this->userId, $userConfig['widget_account_id'])->jsonSerialize();
			$userConfig['widget_projects'] = $account['widgetProjects'];
			$userConfig['widget_groups'] = $account['widgetGroups'];
		} catch (\Exception) {
			$userConfig['widget_projects'] = [];
			$userConfig['widget_groups'] = [];
		}

		$this->initialStateService->provideInitialState('user-config', $userConfig);
		Util::addScript(Application::APP_ID, Application::APP_ID . '-dashboard');
		Util::addStyle(Application::APP_ID, 'dashboard');
	}
}
