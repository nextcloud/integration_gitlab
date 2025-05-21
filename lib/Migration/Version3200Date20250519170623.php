<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Gitlab\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version3200Date20250519170623 extends SimpleMigrationStep {

	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('gitlab_accounts')) {
			$table = $schema->getTable('gitlab_accounts');

			if (!$table->hasColumn('widget_projects')) {
				$table->addColumn('widget_projects', Types::JSON, ['notnull' => false]);
			}
			if (!$table->hasColumn('widget_groups')) {
				$table->addColumn('widget_groups', Types::JSON, ['notnull' => false]);
			}
		}

		return $schema;
	}
}
