<?php

declare(strict_types=1);

namespace OCA\Gitlab\Service;

use OCA\Gitlab\AppInfo\Application;
use OCP\IConfig;
use OCP\PreConditionNotMetException;
use OCP\Security\ICrypto;

class ConfigService {
	public function __construct(
		private IConfig $config,
		private ICrypto $crypto,
	) {
	}

	public function getClearAppValue(string $key): string {
		$value = $this->config->getAppValue(Application::APP_ID, $key);
		if ($value === '') {
			return $value;
		}
		return $this->crypto->decrypt($value);
	}

	public function setEncryptedAppValue(string $key, string $value): void {
		if ($value === '') {
			$this->config->setAppValue(Application::APP_ID, $key, $value);
		} else {
			$encryptedValue = $this->crypto->encrypt($value);
			$this->config->setAppValue(Application::APP_ID, $key, $encryptedValue);
		}
	}

	public function getAdminClientId(): string {
		return $this->getClearAppValue('client_id');
	}

	public function setAdminClientId(string $clientId): void {
		$this->setEncryptedAppValue('client_id', $clientId);
	}

	public function hasAdminClientSecret(): bool {
		return $this->getAdminClientSecret() !== '';
	}

	public function getAdminClientSecret(): string {
		return $this->getClearAppValue('client_secret');
	}

	public function setAdminClientSecret(string $clientSecret): void {
		$this->setEncryptedAppValue('client_secret', $clientSecret);
	}

	public function getAdminOauthUrl(): string {
		return $this->config->getAppValue(Application::APP_ID, 'oauth_instance_url') ?: Application::DEFAULT_GITLAB_URL;
	}

	public function setAdminOauthUrl(string $url): void {
		$this->config->setAppValue(Application::APP_ID, 'oauth_instance_url', $url);
	}

	public function getAdminLinkPreviewEnabled(): bool {
		return $this->config->getAppValue(Application::APP_ID, 'link_preview_enabled', '1') === '1';
	}

	public function setAdminLinkPreviewEnabled(bool $enabled): void {
		$this->config->setAppValue(Application::APP_ID, 'link_preview_enabled', $enabled ? '1' : '0');
	}

	public function getAdminForceGitlabInstanceUrl(): string {
		return $this->config->getAppValue(Application::APP_ID, 'force_gitlab_instance_url');
	}

	public function setAdminForceGitlabInstanceUrl(string $url): void {
		$this->config->setAppValue(Application::APP_ID, 'force_gitlab_instance_url', $url);
	}

	public function getUserUrl(string $userId): string {
		return $this->config->getUserValue($userId, Application::APP_ID, 'url') ?: $this->getAdminOauthUrl();
	}

	public function deleteUserUrl(string $userId): void {
		$this->config->deleteUserValue($userId, Application::APP_ID, 'url');
	}

	public function getUserTokenType(string $userId): string {
		return $this->config->getUserValue($userId, Application::APP_ID, 'token_type');
	}

	public function deleteUserTokenType(string $userId): void {
		$this->config->deleteUserValue($userId, Application::APP_ID, 'token_type');
	}

	public function hasUserRefreshToken(string $userId): bool {
		return $this->config->getUserValue($userId, Application::APP_ID, 'refresh_token') !== '';
	}

	public function getUserRefreshToken(string $userId): string {
		return $this->config->getUserValue($userId, Application::APP_ID, 'refresh_token');
	}

	public function deleteUserRefreshToken(string $userId): void {
		$this->config->deleteUserValue($userId, Application::APP_ID, 'refresh_token');
	}

	public function hasUserTokenExpiresAt(string $userId): bool {
		return $this->config->getUserValue($userId, Application::APP_ID, 'token_expires_at') !== '';
	}

	public function getUserTokenExpiresAt(string $userId): int {
		return (int)$this->config->getUserValue($userId, Application::APP_ID, 'token_expires_at');
	}

	public function deleteUserTokenExpiresAt(string $userId): void {
		$this->config->deleteUserValue($userId, Application::APP_ID, 'token_expires_at');
	}

	public function deleteUserId(string $userId): void {
		$this->config->deleteUserValue($userId, Application::APP_ID, 'user_id');
	}

	public function getUserName(string $userId): string {
		return $this->config->getUserValue($userId, Application::APP_ID, 'user_name');
	}

	public function deleteUserName(string $userId): void {
		$this->config->deleteUserValue($userId, Application::APP_ID, 'user_name');
	}

	public function getUserDisplayName(string $userId): string {
		return $this->config->getUserValue($userId, Application::APP_ID, 'user_displayname');
	}

	public function deleteUserDisplayName(string $userId): void {
		$this->config->deleteUserValue($userId, Application::APP_ID, 'user_displayname');
	}

	public function getUserToken(string $userId): string {
		return $this->config->getUserValue($userId, Application::APP_ID, 'token');
	}

	public function deleteUserToken(string $userId): void {
		$this->config->deleteUserValue($userId, Application::APP_ID, 'token');
	}

	public function getUserOauthState(string $userId): string {
		return $this->config->getUserValue($userId, Application::APP_ID, 'oauth_state');
	}

	/**
	 * @throws PreConditionNotMetException
	 */
	public function setUserOauthState(string $userId, string $state): void {
		$this->config->setUserValue($userId, Application::APP_ID, 'oauth_state', $state);
	}

	public function deleteUserOauthState(string $userId): void {
		$this->config->deleteUserValue($userId, Application::APP_ID, 'oauth_state');
	}

	public function getUserRedirectUri(string $userId): string {
		return $this->config->getUserValue($userId, Application::APP_ID, 'redirect_uri');
	}

	/**
	 * @throws PreConditionNotMetException
	 */
	public function setUserRedirectUri(string $userId, string $redirectUri): void {
		$this->config->setUserValue($userId, Application::APP_ID, 'redirect_uri', $redirectUri);
	}

	public function getUserOauthOrigin(string $userId): string {
		return $this->config->getUserValue($userId, Application::APP_ID, 'oauth_origin');
	}

	/**
	 * @throws PreConditionNotMetException
	 */
	public function setUserOauthOrigin(string $userId, string $origin): void {
		$this->config->setUserValue($userId, Application::APP_ID, 'oauth_origin', $origin);
	}

	public function deleteUserOauthOrigin(string $userId): void {
		$this->config->deleteUserValue($userId, Application::APP_ID, 'oauth_origin');
	}

	public function getUserSearchEnabled(string $userId): bool {
		return $this->config->getUserValue($userId, Application::APP_ID, 'search_enabled', '0') === '1';
	}

	/**
	 * @throws PreConditionNotMetException
	 */
	public function setUserSearchEnabled(string $userId, bool $enabled): void {
		$this->config->setUserValue($userId, Application::APP_ID, 'search_enabled', $enabled ? '1' : '0');
	}

	public function getUserSearchIssuesEnabled(string $userId): bool {
		return $this->config->getUserValue($userId, Application::APP_ID, 'search_issues_enabled', '0') === '1';
	}

	/**
	 * @throws PreConditionNotMetException
	 */
	public function setUserSearchIssuesEnabled(string $userId, bool $enabled): void {
		$this->config->setUserValue($userId, Application::APP_ID, 'search_issues_enabled', $enabled ? '1' : '0');
	}

	public function getUserSearchMergeRequestsEnabled(string $userId): bool {
		return $this->config->getUserValue($userId, Application::APP_ID, 'search_mrs_enabled', '0') === '1';
	}

	/**
	 * @throws PreConditionNotMetException
	 */
	public function setUserSearchMergeRequestsEnabled(string $userId, bool $enabled): void {
		$this->config->setUserValue($userId, Application::APP_ID, 'search_mrs_enabled', $enabled ? '1' : '0');
	}

	public function getUserNavigationEnabled(string $userId): bool {
		return $this->config->getUserValue($userId, Application::APP_ID, 'navigation_enabled', '0') === '1';
	}

	/**
	 * @throws PreConditionNotMetException
	 */
	public function setUserNavigationEnabled(string $userId, bool $enabled): void {
		$this->config->setUserValue($userId, Application::APP_ID, 'navigation_enabled', $enabled ? '1' : '0');
	}

	public function getUserLinkPreviewEnabled(string $userId): bool {
		return $this->config->getUserValue($userId, Application::APP_ID, 'link_preview_enabled', '1') === '1';
	}

	/**
	 * @throws PreConditionNotMetException
	 */
	public function setUserLinkPreviewEnabled(string $userId, bool $enabled): void {
		$this->config->setUserValue($userId, Application::APP_ID, 'link_preview_enabled', $enabled ? '1' : '0');
	}

	public function getUserWidgetAccountId(string $userId): int {
		return (int)$this->config->getUserValue($userId, Application::APP_ID, 'widget_account_id', '0');
	}

	/**
	 * @throws PreConditionNotMetException
	 */
	public function setUserWidgetAccountId(string $userId, int $id): void {
		$this->config->setUserValue($userId, Application::APP_ID, 'widget_account_id', (string)$id);
	}
}
