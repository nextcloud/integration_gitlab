<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Gitlab\Migration;

use Closure;
use OCA\Gitlab\AppInfo\Application;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
use OCP\Security\ICrypto;

class Version030103Date20241017150114 extends SimpleMigrationStep {

	public function __construct(
		private IDBConnection $connection,
		private ICrypto $crypto,
		private IConfig $config,
	) {
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options) {
		// app config
		foreach (['client_id', 'client_secret'] as $key) {
			$value = $this->config->getAppValue(Application::APP_ID, $key);
			if ($value !== '') {
				$encryptedValue = $this->crypto->encrypt($value);
				$this->config->setAppValue(Application::APP_ID, $key, $encryptedValue);
			}
		}

		// ----------- user tokens
		$qbUpdate = $this->connection->getQueryBuilder();
		$qbUpdate->update('gitlab_accounts')
			->set('token', $qbUpdate->createParameter('updateToken'))
			->where(
				$qbUpdate->expr()->eq('id', $qbUpdate->createParameter('updateAccountId'))
			);

		$qbSelect = $this->connection->getQueryBuilder();
		$qbSelect->select('id', 'token')
			->from('gitlab_accounts')
			->where(
				$qbSelect->expr()->nonEmptyString('token')
			);
		$req = $qbSelect->executeQuery();
		while ($row = $req->fetch()) {
			$accountId = (int)$row['id'];
			$storedClearRefreshToken = $row['token'];
			$encryptedToken = $this->crypto->encrypt($storedClearRefreshToken);
			$qbUpdate->setParameter('updateAccountId', $accountId, IQueryBuilder::PARAM_INT);
			$qbUpdate->setParameter('updateToken', $encryptedToken, IQueryBuilder::PARAM_STR);
			$qbUpdate->executeStatement();
		}
		$req->closeCursor();

		// ----------- user refresh tokens
		$qbUpdate = $this->connection->getQueryBuilder();
		$qbUpdate->update('gitlab_accounts')
			->set('refresh_token', $qbUpdate->createParameter('updateRefreshToken'))
			->where(
				$qbUpdate->expr()->eq('id', $qbUpdate->createParameter('updateAccountId'))
			);

		$qbSelect = $this->connection->getQueryBuilder();
		$qbSelect->select('id', 'refresh_token')
			->from('gitlab_accounts')
			->where(
				$qbSelect->expr()->nonEmptyString('refresh_token')
			)
			->andWhere(
				$qbSelect->expr()->isNotNull('refresh_token')
			);
		$req = $qbSelect->executeQuery();
		while ($row = $req->fetch()) {
			$accountId = (int)$row['id'];
			$storedClearRefreshToken = $row['refresh_token'];
			$encryptedToken = $this->crypto->encrypt($storedClearRefreshToken);
			$qbUpdate->setParameter('updateAccountId', $accountId, IQueryBuilder::PARAM_INT);
			$qbUpdate->setParameter('updateRefreshToken', $encryptedToken, IQueryBuilder::PARAM_STR);
			$qbUpdate->executeStatement();
		}
		$req->closeCursor();
	}
}
