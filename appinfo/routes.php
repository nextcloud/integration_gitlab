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
		['name' => 'config#setAdminConfig', 'url' => '/admin-config', 'verb' => 'PUT'],
		['name' => 'config#popupSuccessPage', 'url' => '/popup-success', 'verb' => 'GET'],

		['name' => 'gitlabAPI#getEvents', 'url' => '/events', 'verb' => 'GET'],
		['name' => 'gitlabAPI#getTodos', 'url' => '/todos', 'verb' => 'GET'],
		['name' => 'gitlabAPI#markTodoAsDone', 'url' => '/todos/{id}/mark-done', 'verb' => 'PUT'],
		['name' => 'gitlabAPI#getProjectAvatar', 'url' => '/avatar/project', 'verb' => 'GET'],
		['name' => 'gitlabAPI#getUserAvatar', 'url' => '/avatar/user/{userId}', 'verb' => 'GET'],
	]
];
