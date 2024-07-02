<?php

declare(strict_types=1);

namespace OCA\Gitlab\Model;

use InvalidArgumentException;
use OCA\Gitlab\Service\ConfigService;
use OCP\PreConditionNotMetException;

class UserConfig {
	private function __construct(
		public ?string $token,
		public ?string $url,
		public ?string $client_id,
		// don't expose the client secret to users
		public ?bool $client_secret,
		public ?string $oauth_instance_url,
		public ?bool $use_popup,
		public ?string $user_name,
		public ?string $user_displayname,
		public ?bool $search_enabled,
		public ?bool $search_issues_enabled,
		public ?bool $search_mrs_enabled,
		public ?bool $navigation_enabled,
		public ?bool $link_preview_enabled,
		public ?string $oauth_state,
		public ?string $redirect_uri,
		public ?string $oauth_origin,
	) {
	}

	public static function loadConfig(string $userId, ConfigService $config): UserConfig {
		return new UserConfig(
			token: $config->getUserToken($userId),
			url: $config->getUserUrl($userId),
			client_id: $config->getAdminClientId(),
			client_secret: $config->hasAdminClientSecret(),
			oauth_instance_url: $config->getAdminOauthUrl(),
			use_popup: $config->getAdminUsePopup(),
			user_name: $config->getUserName($userId),
			user_displayname: $config->getUserDisplayName($userId),
			search_enabled: $config->getUserSearchEnabled($userId),
			search_issues_enabled: $config->getUserSearchIssuesEnabled($userId),
			search_mrs_enabled: $config->getUserSearchMergeRequestsEnabled($userId),
			navigation_enabled: $config->getUserNavigationEnabled($userId),
			link_preview_enabled: $config->getUserLinkPreviewEnabled($userId),
			oauth_state: $config->getUserOauthState($userId),
			redirect_uri: $config->getUserRedirectUri($userId),
			oauth_origin: $config->getUserOauthOrigin($userId),
		);
	}

	/**
	 * @throws PreConditionNotMetException|InvalidArgumentException
	 */
	public function saveConfig(string $userId, ConfigService $config): void {
		if ($this->client_id !== null || $this->client_secret !== null || $this->oauth_instance_url !== null || $this->use_popup !== null || $this->user_name !== null || $this->user_displayname !== null) {
			throw new InvalidArgumentException();
		}
		if ($this->token !== null) {
			$config->setUserToken($userId, $this->token);
		}
		if ($this->url !== null) {
			$config->setUserUrl($userId, $this->url);
		}
		if ($this->search_enabled !== null) {
			$config->setUserSearchEnabled($userId, $this->search_enabled);
		}
		if ($this->search_issues_enabled !== null) {
			$config->setUserSearchIssuesEnabled($userId, $this->search_issues_enabled);
		}
		if ($this->search_mrs_enabled !== null) {
			$config->setUserSearchMergeRequestsEnabled($userId, $this->search_mrs_enabled);
		}
		if ($this->navigation_enabled !== null) {
			$config->setUserNavigationEnabled($userId, $this->navigation_enabled);
		}
		if ($this->link_preview_enabled !== null) {
			$config->setUserLinkPreviewEnabled($userId, $this->link_preview_enabled);
		}
		if ($this->oauth_state !== null) {
			$config->setUserOauthState($userId, $this->oauth_state);
		}
		if ($this->redirect_uri !== null) {
			$config->setUserRedirectUri($userId, $this->redirect_uri);
		}
		if ($this->oauth_origin !== null) {
			$config->setUserOauthOrigin($userId, $this->oauth_origin);
		}
	}

	public static function fromArray(array $config): UserConfig {
		return new UserConfig(
			token: $config['token'] ?? null,
			url: $config['url'] ?? null,
			client_id: $config['client_id'] ?? null,
			client_secret: $config['client_secret'] ?? null,
			oauth_instance_url: $config['oauth_instance_url'] ?? null,
			use_popup: $config['use_popup'] ?? null,
			user_name: $config['user_name'] ?? null,
			user_displayname: $config['user_displayname'] ?? null,
			search_enabled: $config['search_enabled'] ?? null,
			search_issues_enabled: $config['search_issues_enabled'] ?? null,
			search_mrs_enabled: $config['search_mrs_enabled'] ?? null,
			navigation_enabled: $config['navigation_enabled'] ?? null,
			link_preview_enabled: $config['link_preview_enabled'] ?? null,
			oauth_state: $config['oauth_state'] ?? null,
			redirect_uri: $config['redirect_uri'] ?? null,
			oauth_origin: $config['oauth_origin'] ?? null,
		);
	}

	public function toArray(): array {
		return [
			'token' => $this->token !== null && $this->token !== '' ? 'dummyToken' : $this->token,
			'url' => $this->url,
			'client_id' => $this->client_id,
			'client_secret' => $this->client_secret,
			'oauth_instance_url' => $this->oauth_instance_url,
			'use_popup' => $this->use_popup,
			'user_name' => $this->user_name,
			'user_displayname' => $this->user_displayname,
			'search_enabled' => $this->search_enabled,
			'search_issues_enabled' => $this->search_issues_enabled,
			'search_mrs_enabled' => $this->search_mrs_enabled,
			'navigation_enabled' => $this->navigation_enabled,
			'link_preview_enabled' => $this->link_preview_enabled,
			'oauth_state' => $this->oauth_state,
			'redirect_uri' => $this->redirect_uri,
			'oauth_origin' => $this->oauth_origin,
		];
	}
}
