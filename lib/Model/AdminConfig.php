<?php

declare(strict_types=1);

namespace OCA\Gitlab\Model;

use OCA\Gitlab\Service\ConfigService;

class AdminConfig {
	private function __construct(
		public ?string $client_id,
		public ?string $client_secret,
		public ?string $oauth_instance_url,
		public ?bool $link_preview_enabled,
	) {
	}

	public static function loadConfig(ConfigService $config): AdminConfig {
		return new AdminConfig(
			client_id: $config->getAdminClientId(),
			client_secret: $config->getAdminClientSecret(),
			oauth_instance_url: $config->getAdminOauthUrl(),
			link_preview_enabled: $config->getAdminLinkPreviewEnabled(),
		);
	}

	public function saveConfig(ConfigService $config): void {
		if ($this->client_id !== null) {
			$config->setAdminClientId($this->client_id);
		}
		if ($this->client_secret !== null) {
			$config->setAdminClientSecret($this->client_secret);
		}
		if ($this->oauth_instance_url !== null) {
			$config->setAdminOauthUrl($this->oauth_instance_url);
		}
		if ($this->link_preview_enabled !== null) {
			$config->setAdminLinkPreviewEnabled($this->link_preview_enabled);
		}
	}

	public static function fromArray(array $config): AdminConfig {
		return new AdminConfig(
			client_id: $config['client_id'] ?? null,
			client_secret: $config['client_secret'] ?? null,
			oauth_instance_url: $config['oauth_instance_url'] ?? null,
			link_preview_enabled: $config['link_preview_enabled'] ?? null,
		);
	}

	public function toArray(): array {
		return [
			'client_id' => $this->client_id,
			'client_secret' => $this->client_secret !== null && $this->client_secret !== '' ? 'dummyToken' : $this->client_secret,
			'oauth_instance_url' => $this->oauth_instance_url,
			'link_preview_enabled' => $this->link_preview_enabled,
		];
	}
}
