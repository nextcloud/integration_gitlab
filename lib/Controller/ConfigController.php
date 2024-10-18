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
use OCA\Gitlab\Db\GitlabAccount;
use OCA\Gitlab\Db\GitlabAccountMapper;
use OCA\Gitlab\Model\AdminConfig;
use OCA\Gitlab\Model\UserConfig;
use OCA\Gitlab\Reference\GitlabReferenceProvider;
use OCA\Gitlab\Service\ConfigService;
use OCA\Gitlab\Service\GitlabAPIService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\DB\Exception;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\PreConditionNotMetException;
use Psr\Log\LoggerInterface;

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
		private GitlabAccountMapper $accountMapper,
		private LoggerInterface $logger,
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
		$userConfig->saveConfig($this->userId, $this->config);

		return new DataResponse([]);
	}

	/**
	 * @PasswordConfirmationRequired
	 * @NoAdminRequired
	 */
	public function addAccount(string $url, string $token) {
		$account = new GitlabAccount();
		$account->setUserId($this->userId);
		$account->setUrl($url);
		$account->setEncryptedToken($token);
		$account->setTokenType('personal');

		try {
			$userInfo = $this->getUserInfo($account);
			$account->setUserInfoName($userInfo['username']);
			$account->setUserInfoDisplayName($userInfo['name']);
		} catch (Exception $e) {
			return new DataResponse(['error' => $e->getMessage()], $e->getCode());
		}

		try {
			$this->accountMapper->insert($account);
			$this->updateAccountsConfig();

			return new DataResponse([
				'account' => $account->jsonSerialize(),
				'config' => UserConfig::loadConfig($this->userId, $this->config)->toArray(),
			]);
		} catch (Exception $e) {
			$this->logger->error('Failed to save the Gitlab account: ' . $e->getMessage(), ['exception' => $e]);
			return new DataResponse(['error' => 'Server Error: Failed to save the Gitlab account'], 500);
		}
	}

	/**
	 * @PasswordConfirmationRequired
	 * @NoAdminRequired
	 */
	public function deleteAccount(int $id) {
		try {
			$account = $this->accountMapper->findById($this->userId, $id);
			$this->accountMapper->delete($account);

			$this->updateAccountsConfig();

			return new DataResponse([
				'config' => UserConfig::loadConfig($this->userId, $this->config)->toArray(),
			]);
		} catch (DoesNotExistException $e) {
			$this->logger->error('Requested Gitlab account with id ' . $id . 'not found');
			return new DataResponse([], 404);
		} catch (Exception $e) {
			$this->logger->error('Failed to query Gitlab account: ' . $e->getMessage(), ['exception' => $e]);
			return new DataResponse([], 500);
		}
	}

	private function updateAccountsConfig(): void {
		$widgetAccountId = $this->config->getUserWidgetAccountId($this->userId);

		$accounts = $this->accountMapper->find($this->userId);

		if (count($accounts) === 0) {
			$widgetAccountId = 0;
		} elseif (count($accounts) === 1) {
			$widgetAccountId = $accounts[0]->getId();
		} else {
			$account = array_filter($accounts, static fn (GitlabAccount $account) => $account->getId() === $widgetAccountId);
			if (count($account) !== 1) {
				$widgetAccountId = 0;
			}
		}

		$this->config->setUserWidgetAccountId($this->userId, $widgetAccountId);
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

				$account = new GitlabAccount();
				$account->setUserId($this->userId);
				$account->setUrl($adminOauthUrl);
				$account->setEncryptedToken($result['access_token']);
				$account->setTokenType('oauth');
				$account->setEncryptedRefreshToken($result['refresh_token'] ?? '');
				if (isset($result['expires_in'])) {
					$nowTs = (new Datetime())->getTimestamp();
					$expiresAt = $nowTs + (int)$result['expires_in'];
					$account->setTokenExpiresAt($expiresAt);
				}

				try {
					$userInfo = $this->getUserInfo($account);
					$account->setUserInfoName($userInfo['username']);
					$account->setUserInfoDisplayName($userInfo['name']);
				} catch (Exception $e) {
					return new RedirectResponse(
						$this->urlGenerator->linkToRoute('settings.PersonalSettings.index', ['section' => 'connected-accounts']) .
						'?gitlabToken=error&message=' . urlencode($e->getMessage())
					);
				}
				$this->accountMapper->insert($account);
				$this->updateAccountsConfig();

				$oauthOrigin = $this->config->getUserOauthOrigin($this->userId);
				$this->config->deleteUserOauthOrigin($this->userId);
				if ($oauthOrigin === 'settings') {
					return new RedirectResponse(
						$this->urlGenerator->linkToRoute('settings.PersonalSettings.index', ['section' => 'connected-accounts']) .
						'?gitlabToken=success'
					);
				}
				if ($oauthOrigin === 'dashboard') {
					return new RedirectResponse(
						$this->urlGenerator->linkToRoute('dashboard.dashboard.index')
					);
				}
				return new RedirectResponse(
					$this->urlGenerator->linkToRoute('settings.PersonalSettings.index', ['section' => 'connected-accounts']) .
					'?gitlabToken=success'
				);
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
	 * @param GitlabAccount $account
	 * @return array{username: string, name: string}
	 * @throws Exception
	 */
	private function getUserInfo(GitlabAccount $account): array {
		$info = $this->gitlabAPIService->request($account, $account->getUrl(), 'user');
		if (isset($info['error'])) {
			throw new Exception($info['error'], $info['code'] ?? 500);
		}
		if (!isset($info['username'])) {
			throw new Exception('Invalid response from Gitlab API, missing username', 500);
		}
		return [
			'username' => $info['username'],
			'name' => $info['name'] ?? $info['username'],
		];
	}
}
