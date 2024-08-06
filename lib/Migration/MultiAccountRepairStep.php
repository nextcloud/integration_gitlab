<?php

declare(strict_types=1);

namespace OCA\Gitlab\Migration;

use OCA\Gitlab\Db\GitlabAccount;
use OCA\Gitlab\Db\GitlabAccountMapper;
use OCA\Gitlab\Service\ConfigService;
use OCP\IUserManager;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

class MultiAccountRepairStep implements IRepairStep {
	public function __construct(
		private GitlabAccountMapper $accountMapper,
		private ConfigService $config,
		private IUserManager $userManager,
	) {
	}

	public function getName() {
		return 'Convert multi accounts';
	}

	public function run(IOutput $output) {
		foreach ($this->userManager->search('') as $user) {
			$userId = $user->getUID();
			if ($this->config->getUserToken($userId) !== '') {
				$account = new GitlabAccount();
				$account->setUserId($user->getUID());
				$account->setUrl($this->config->getUserUrl($userId));
				$account->setToken($this->config->getUserToken($userId));
				$account->setTokenType($this->config->getUserTokenType($userId));
				if ($this->config->hasUserTokenExpiresAt($userId)) {
					$account->setTokenExpiresAt($this->config->getUserTokenExpiresAt($userId));
				}
				if ($this->config->hasUserRefreshToken($userId)) {
					$account->setRefreshToken($this->config->getUserRefreshToken($userId));
				}
				$account->setUserInfoName($this->config->getUserName($userId));
				$account->setUserInfoDisplayName($this->config->getUserDisplayName($userId));

				$this->accountMapper->insert($account);

				$this->config->deleteUserUrl($userId);
				$this->config->deleteUserToken($userId);
				$this->config->deleteUserTokenType($userId);
				$this->config->deleteUserTokenExpiresAt($userId);
				$this->config->deleteUserRefreshToken($userId);
				$this->config->deleteUserName($userId);
				$this->config->deleteUserDisplayName($userId);
			}
		}
	}
}
