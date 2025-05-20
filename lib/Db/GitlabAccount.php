<?php

declare(strict_types=1);

namespace OCA\Gitlab\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;
use OCP\Security\ICrypto;

/**
 * @method void setUserId(string $userId)
 * @method string getUrl()
 * @method void setUrl(string $url)
 * @method string getToken()
 * @method void setToken(string $token)
 * @method string getTokenType()
 * @method void setTokenType(string $tokenType)
 * @method int|null getTokenExpiresAt()
 * @method void setTokenExpiresAt(int|null $tokenExpiresAt)
 * @method string|null getRefreshToken()
 * @method void setRefreshToken(string|null $refreshToken)
 * @method string|null getUserInfoName()
 * @method void setUserInfoName(string $userInfoName)
 * @method string|null getUserInfoDisplayName()
 * @method void setUserInfoDisplayName(string $userInfoDisplayName)
 * @method array|null getWidgetProjects()
 * @method void setWidgetProjects(array $widgetProjects)
 * @method array|null getWidgetGroups()
 * @method void setWidgetGroups(array $widgetGroups)
 */
class GitlabAccount extends Entity implements JsonSerializable {
	protected $userId;
	protected $url;
	protected $token;
	protected $tokenType;
	protected $tokenExpiresAt;
	protected $refreshToken;
	protected $userInfoName;
	protected $userInfoDisplayName;
	protected $widgetProjects;
	protected $widgetGroups;

	private ICrypto $crypto;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('url', 'string');
		$this->addType('token', 'string');
		$this->addType('token_type', 'string');
		$this->addType('token_expires_at', 'integer');
		$this->addType('refresh_token', 'string');
		$this->addType('user_info_name', 'string');
		$this->addType('user_info_display_name', 'string');
		$this->addType('widget_projects', 'array');
		$this->addType('widget_groups', 'array');

		$this->crypto = \OC::$server->get(ICrypto::class);
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'url' => $this->url,
			'token' => $this->token !== '' ? 'dummyToken' : '',
			'tokenType' => $this->tokenType,
			'tokenExpiresAt' => $this->tokenExpiresAt,
			'refreshToken' => $this->refreshToken !== '' ? 'dummyToken' : '',
			'userInfoName' => $this->userInfoName,
			'userInfoDisplayName' => $this->userInfoDisplayName,
			'widgetProjects' => $this->widgetProjects && !is_array($this->widgetProjects) ? json_decode($this->widgetProjects) : [],
			'widgetGroups' => $this->widgetGroups && !is_array($this->widgetGroups) ? json_decode($this->widgetGroups) : [],
		];
	}

	public function getClearToken(): string {
		if (is_string($this->token) && $this->token !== '') {
			return $this->crypto->decrypt($this->token);
		}
		return $this->token;
	}

	public function setEncryptedToken(string $token): void {
		if ($token !== '') {
			$this->setter('token', [$this->crypto->encrypt($token)]);
		} else {
			$this->setter('token', [$token]);
		}
	}

	public function getClearRefreshToken(): ?string {
		if (is_string($this->refreshToken) && $this->refreshToken !== '') {
			return $this->crypto->decrypt($this->refreshToken);
		}
		return $this->refreshToken;
	}

	public function setEncryptedRefreshToken(?string $refreshToken): void {
		if (is_string($refreshToken) && $refreshToken !== '') {
			$this->setter('refreshToken', [$this->crypto->encrypt($refreshToken)]);
		} else {
			$this->setter('refreshToken', [$refreshToken]);
		}
	}
}
