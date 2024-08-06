<?php

declare(strict_types=1);

namespace OCA\Gitlab\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

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
 */
class GitlabAccount extends Entity implements JsonSerializable {
	protected string $userId = '';
	protected string $url = '';
	protected string $token = '';
	protected string $tokenType = '';
	protected ?int $tokenExpiresAt = null;
	protected ?string $refreshToken = null;
	protected ?string $userInfoName = null;
	protected ?string $userInfoDisplayName = null;

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
		];
	}
}
