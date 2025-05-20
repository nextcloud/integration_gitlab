<?php

/**
 * Nextcloud - GitLab
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <julien-nc@posteo.net>
 * @copyright Julien Veyssier 2020
 */

return [
	'routes' => [
		['name' => 'config#oauthRedirect', 'url' => '/oauth-redirect', 'verb' => 'GET'],
		['name' => 'config#setConfig', 'url' => '/config', 'verb' => 'PUT'],
		['name' => 'config#addAccount', 'url' => '/account', 'verb' => 'POST'],
		['name' => 'config#updateAccountFilters', 'url' => '/account/{id}/filters', 'verb' => 'PUT'],
		['name' => 'config#deleteAccount', 'url' => '/account/{id}', 'verb' => 'DELETE'],
		['name' => 'config#setAdminConfig', 'url' => '/admin-config', 'verb' => 'PUT'],
		['name' => 'config#setSensitiveAdminConfig', 'url' => '/sensitive-admin-config', 'verb' => 'PUT'],

		['name' => 'gitlabAPI#getTodos', 'url' => '/gitlab/{accountId}/todos', 'verb' => 'GET'],
		['name' => 'gitlabAPI#getProjectsList', 'url' => '/gitlab/{accountId}/projects', 'verb' => 'GET'],
		['name' => 'gitlabAPI#getGroupsList', 'url' => '/gitlab/{accountId}/groups', 'verb' => 'GET'],
		['name' => 'gitlabAPI#getProjectAvatar', 'url' => '/gitlab/{accountId}/avatar/project', 'verb' => 'GET'],
		['name' => 'gitlabAPI#getUserAvatar', 'url' => '/gitlab/{accountId}/avatar/user/{userId}', 'verb' => 'GET'],
	]
];
