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

namespace OCA\Gitlab\AppInfo;

use OCP\AppFramework\App;

use OCP\Util;

$app = new Application();
$container = $app->getContainer();

// later we will add a navigation entry
//$container->query('OCP\INavigationManager')->add(function () use ($container) {
//    $urlGenerator = $container->query(\OCP\IURLGenerator::class);
//    $l10n = $container->query(\OCP\IL10N::class);
//    return [
//        'id' => 'gitlab',
//        'order' => 10,
//        'href' => $urlGenerator->linkToRoute('gitlab.page.index'),
//        'icon' => $urlGenerator->imagePath('gitlab', 'app.svg'),
//        'name' => $l10n->t('Gitlab'),
//    ];
//});
