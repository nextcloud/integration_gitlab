<?php

namespace OCA\Gitlab\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<GitlabAccount>
 */
class GitlabAccountMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'gitlab_accounts');
	}

	/**
	 * @throws Exception
	 * @return array<GitlabAccount>
	 */
	public function find(string $userId): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from('gitlab_accounts')
			->where(
				$qb->expr()->eq('user_id', $qb->createNamedParameter($userId))
			);

		return $this->findEntities($qb);
	}

	/**
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws Exception
	 */
	public function findById(string $userId, int $id): GitlabAccount {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from('gitlab_accounts')
			->where(
				$qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			)->andWhere(
				$qb->expr()->eq('user_id', $qb->createNamedParameter($userId))
			);

		return $this->findEntity($qb);
	}

	/**
	 * @throws Exception
	 */
	public function updateFilters(string $accountId, array $widget_projects, array $widget_groups): int {
		$qb = $this->db->getQueryBuilder();

		$qb->update('gitlab_accounts')
			->set('widget_projects', $qb->createNamedParameter($widget_projects, IQueryBuilder::PARAM_JSON))
			->set('widget_groups', $qb->createNamedParameter($widget_groups, IQueryBuilder::PARAM_JSON))
			->where(
				$qb->expr()->eq('id', $qb->createNamedParameter($accountId, IQueryBuilder::PARAM_INT))
			);

		return $qb->executeStatement();
	}
}
