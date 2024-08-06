<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Gitlab\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version3000Date20240718103726 extends SimpleMigrationStep {

	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('gitlab_accounts')) {
			$table = $schema->createTable('gitlab_accounts');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 11,
				'unsigned' => true,
			]);
			$table->addColumn('user_id', Types::TEXT, [
				'notnull' => true,
			]);
			$table->addColumn('url', Types::TEXT, [
				'notnull' => true,
			]);
			$table->addColumn('token', Types::TEXT, [
				'notnull' => true,
			]);
			$table->addColumn('token_type', Types::TEXT, [
				'notnull' => true,
			]);
			$table->addColumn('token_expires_at', Types::BIGINT, [
				'notnull' => false,
			]);
			$table->addColumn('refresh_token', Types::TEXT, [
				'notnull' => false,
			]);
			$table->addColumn('user_info_name', Types::TEXT, [
				'notnull' => false,
			]);
			$table->addColumn('user_info_display_name', Types::TEXT, [
				'notnull' => false,
			]);
			$table->setPrimaryKey(['id']);
		}

		return $schema;
	}
}
