<?php
/**
 * Nextcloud - gitlab
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <julien-nc@posteo.net>
 * @copyright Julien Veyssier 2020
 */

namespace OCA\Gitlab\Controller;

use DateTime;
use OCA\Gitlab\AppInfo\Application;
use OCA\Gitlab\Model\AdminConfig;
use OCA\Gitlab\Model\UserConfig;
use OCA\Gitlab\Reference\GitlabReferenceProvider;
use OCA\Gitlab\Service\ConfigService;
use OCA\Gitlab\Service\GitlabAPIService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\PreConditionNotMetException;

class ConfigController extends Controller {

	public function __construct(
		string $appName,
		IRequest $request,
		private ConfigService $config,
		private IURLGenerator $urlGenerator,
		private IL10N $l,
		private IInitialState $initialStateService,
		private GitlabAPIService $gitlabAPIService,
		private GitlabReferenceProvider $gitlabReferenceProvider,
		private string $userId,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * set config values
	 * @NoAdminRequired
	 *
	 * @throws PreConditionNotMetException
	 */
	public function setConfig(array $values): DataResponse {
		$userConfig = UserConfig::fromArray($values);
		if ($userConfig->url !== null || $userConfig->token !== null) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		$userConfig->saveConfig($this->userId, $this->config);

		return new DataResponse([]);
	}

	/**
	 * @PasswordConfirmationRequired
	 * @NoAdminRequired
	 *
	 * @throws PreConditionNotMetException
	 */
	public function setSensitiveConfig(array $values): DataResponse {
		$userConfig = UserConfig::fromArray($values);
		$userConfig->saveConfig($this->userId, $this->config);

		// revoke the oauth token if needed
		if ($userConfig->token === '') {
			$tokenType = $this->config->getUserTokenType($this->userId);
			if ($tokenType === 'oauth') {
				$this->gitlabAPIService->revokeOauthToken($this->userId);
			}
		}

		$result = [];

		if ($userConfig->token !== null) {
			// if the token is set, cleanup refresh token and expiration date
			$this->config->deleteUserTokenType($this->userId);
			$this->config->deleteUserRefreshToken($this->userId);
			$this->config->deleteUserTokenExpiresAt($this->userId);
			$this->gitlabReferenceProvider->invalidateUserCache($this->userId);

			if ($userConfig->token !== '') {
				$info = $this->storeUserInfo();
				if (isset($info['error'])) {
					return new DataResponse(['error' => $info['error']], Http::STATUS_BAD_REQUEST);
				}
				$result['user_name'] = $info['username'] ?? '';
				$result['user_displayname'] = $info['userdisplayname'] ?? '';
				// store token type if it's valid (so we have a user name)
				if ($result['user_name'] !== '') {
					$this->config->setUserTokenType($this->userId, 'personal');
				}
			} else {
				$this->config->deleteUserId($this->userId);
				$this->config->deleteUserName($this->userId);
				$this->config->deleteUserDisplayName($this->userId);
				$this->config->deleteUserToken($this->userId);
				$result['user_name'] = '';
			}
		}
		return new DataResponse($result);
	}

	/**
	 * set admin config values
	 *
	 * @param array $values
	 * @return DataResponse
	 */
	public function setAdminConfig(array $values): DataResponse {
		$adminConfig = AdminConfig::fromArray($values);
		if ($adminConfig->client_id !== null || $adminConfig->client_secret !== null || $adminConfig->oauth_instance_url !== null) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		$adminConfig->saveConfig($this->config);
		return new DataResponse(1);
	}

	/**
	 * @PasswordConfirmationRequired
	 */
	public function setSensitiveAdminConfig(array $values): DataResponse {
		$adminConfig = AdminConfig::fromArray($values);
		$adminConfig->saveConfig($this->config);

		return new DataResponse(1);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @param string $user_name
	 * @param string $user_displayname
	 * @return TemplateResponse
	 */
	public function popupSuccessPage(string $user_name, string $user_displayname): TemplateResponse {
		$this->initialStateService->provideInitialState('popup-data', ['user_name' => $user_name, 'user_displayname' => $user_displayname]);
		return new TemplateResponse(Application::APP_ID, 'popupSuccess', [], TemplateResponse::RENDER_AS_GUEST);
	}

	/**
	 * receive oauth code and get oauth access token
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @param string $code
	 * @param string $state
	 * @return RedirectResponse
	 * @throws PreConditionNotMetException
	 */
	public function oauthRedirect(string $code = '', string $state = ''): RedirectResponse {
		$configState = $this->config->getUserOauthState($this->userId);
		$clientID = $this->config->getAdminClientId();
		$clientSecret = $this->config->getAdminClientSecret();

		// anyway, reset state
		$this->config->deleteUserOauthState($this->userId);

		if ($clientID and $clientSecret and $configState !== '' and $configState === $state) {
			$adminOauthUrl = $this->config->getAdminOauthUrl();
			$result = $this->gitlabAPIService->requestOAuthAccessToken($adminOauthUrl, [
				'client_id' => $clientID,
				'client_secret' => $clientSecret,
				'code' => $code,
				'redirect_uri' => $this->config->getUserRedirectUri($this->userId),
				'grant_type' => 'authorization_code'
			], 'POST');
			if (isset($result['access_token'])) {
				$this->gitlabReferenceProvider->invalidateUserCache($this->userId);
				$accessToken = $result['access_token'];
				$refreshToken = $result['refresh_token'] ?? '';
				if (isset($result['expires_in'])) {
					$nowTs = (new Datetime())->getTimestamp();
					$expiresAt = $nowTs + (int)$result['expires_in'];
					$this->config->setUserTokenExpiresAt($this->userId, $expiresAt);
				}
				$this->config->setUserUrl($this->userId, $adminOauthUrl);
				$this->config->setUserToken($this->userId, $accessToken);
				$this->config->setUserRefreshToken($this->userId, $refreshToken);
				$this->config->setUserTokenType($this->userId, 'oauth');
				$userInfo = $this->storeUserInfo();

				if ($this->config->getAdminUsePopup()) {
					return new RedirectResponse(
						$this->urlGenerator->linkToRoute('integration_gitlab.config.popupSuccessPage', [
							'user_name' => $userInfo['username'] ?? '',
							'user_displayname' => $userInfo['userdisplayname'] ?? '',
						])
					);
				} else {
					$oauthOrigin = $this->config->getUserOauthOrigin($this->userId);
					$this->config->deleteUserOauthOrigin($this->userId);
					if ($oauthOrigin === 'settings') {
						return new RedirectResponse(
							$this->urlGenerator->linkToRoute('settings.PersonalSettings.index', ['section' => 'connected-accounts']) .
							'?gitlabToken=success'
						);
					} elseif ($oauthOrigin === 'dashboard') {
						return new RedirectResponse(
							$this->urlGenerator->linkToRoute('dashboard.dashboard.index')
						);
					}
					return new RedirectResponse(
						$this->urlGenerator->linkToRoute('settings.PersonalSettings.index', ['section' => 'connected-accounts']) .
						'?gitlabToken=success'
					);
				}
			}
			$result = $this->l->t('Error getting OAuth access token. ' . $result['error']);
		} else {
			$result = $this->l->t('Error during OAuth exchanges');
		}
		return new RedirectResponse(
			$this->urlGenerator->linkToRoute('settings.PersonalSettings.index', ['section' => 'connected-accounts']) .
			'?gitlabToken=error&message=' . urlencode($result)
		);
	}

	/**
	 * @return array
	 * @throws PreConditionNotMetException
	 */
	private function storeUserInfo(): array {
		$info = $this->gitlabAPIService->request($this->userId, 'user');
		if (isset($info['username']) && isset($info['id'])) {
			$this->config->setUserId($this->userId, $info['id']);
			$this->config->setUserName($this->userId, $info['username']);
			$this->config->setUserDisplayName($this->userId, $info['name']);
			return [
				'username' => $info['username'],
				'userid' => $info['id'],
				'userdisplayname' => $info['name'],
			];
		} else {
			$this->config->deleteUserId($this->userId);
			$this->config->deleteUserName($this->userId);
			return $info;
		}
	}
}
