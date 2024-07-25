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
		$clientID = $this->config->getAdminClientId();
		$clientSecret = $this->config->getAdminClientSecret();
		$adminOauthUrl = $this->config->getAdminOauthUrl();
		$url = $this->config->getUserUrl($this->userId);
		$usePopup = $this->config->getAdminUsePopup();

		$userConfig = [
			'oauth_is_possible' => $clientID !== '' && $clientSecret !== '' && $url === $adminOauthUrl,
			'use_popup' => $usePopup,
			'url' => $url,
			'client_id' => $clientID,
		];
		$this->initialStateService->provideInitialState('user-config', $userConfig);
		Util::addScript(Application::APP_ID, Application::APP_ID . '-dashboard');
		Util::addStyle(Application::APP_ID, 'dashboard');
	}
}
