<?php
/**
 * Nextcloud - Gitlab
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 * @copyright Julien Veyssier 2020
 */

return [
    'routes' => [
        ['name' => 'config#oauthRedirect', 'url' => '/oauth-redirect', 'verb' => 'GET'],
        ['name' => 'config#setConfig', 'url' => '/config', 'verb' => 'PUT'],
        ['name' => 'config#setAdminConfig', 'url' => '/admin-config', 'verb' => 'PUT'],
        ['name' => 'gitlabAPI#getEvents', 'url' => '/events', 'verb' => 'GET'],
        ['name' => 'gitlabAPI#getTodos', 'url' => '/todos', 'verb' => 'GET'],
        ['name' => 'gitlabAPI#getGitlabUrl', 'url' => '/url', 'verb' => 'GET'],
        ['name' => 'gitlabAPI#getGitlabAvatar', 'url' => '/avatar', 'verb' => 'GET'],
    ]
];
