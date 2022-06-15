<?php
/**
 * @copyright Copyright (c) 2020 Julien Veyssier <eneiluj@posteo.net>
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
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

use OCP\AppFramework\Services\IInitialState;
use OCP\Dashboard\IWidget;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Util;

use OCA\Gitlab\AppInfo\Application;

class GitlabWidget implements IWidget {

	/** @var IL10N */
	private $l10n;
	/**
	 * @var IURLGenerator
	 */
	private $url;
	/**
	 * @var IInitialState
	 */
	private $initialStateService;
	/**
	 * @var string|null
	 */
	private $userId;
	private IConfig $config;

	public function __construct(IL10N $l10n,
								IConfig $config,
								IURLGenerator $url,
								IInitialState $initialStateService,
								?string $userId) {
		$this->l10n = $l10n;
		$this->url = $url;
		$this->initialStateService = $initialStateService;
		$this->userId = $userId;
		$this->config = $config;
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
		return $this->l10n->t('GitLab todos');
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
		$clientID = $this->config->getAppValue(Application::APP_ID, 'client_id');
		$clientSecret = $this->config->getAppValue(Application::APP_ID, 'client_secret');
		$adminOauthUrl = $this->config->getAppValue(Application::APP_ID, 'oauth_instance_url', 'https://gitlab.com') ?: 'https://gitlab.com';
		$url = $this->config->getUserValue($this->userId, Application::APP_ID, 'url', $adminOauthUrl) ?: $adminOauthUrl;
		$oauthPossible = $clientID !== '' && $clientSecret !== '' && $url === $adminOauthUrl;

		$userConfig = [
			'oauth_is_possible' => $oauthPossible,
			'url' => $url,
			'client_id' => $clientID,
		];
		$this->initialStateService->provideInitialState('user-config', $userConfig);
		Util::addScript(Application::APP_ID, Application::APP_ID . '-dashboard');
		Util::addStyle(Application::APP_ID, 'dashboard');
	}
}
