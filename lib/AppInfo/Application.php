<?php
/**
 * Nextcloud - GitLab
 *
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 * @copyright Julien Veyssier 2020
 */

namespace OCA\Gitlab\AppInfo;

use Closure;
use OCP\IConfig;
use OCP\IL10N;
use OCP\INavigationManager;
use OCP\IURLGenerator;
use OCP\IUserSession;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;

use OCA\Gitlab\Dashboard\GitlabWidget;
use OCA\Gitlab\Search\GitlabSearchIssuesProvider;
use OCA\Gitlab\Search\GitlabSearchReposProvider;
use OCP\Util;

/**
 * Class Application
 *
 * @package OCA\Gitlab\AppInfo
 */
class Application extends App implements IBootstrap {
	public const APP_ID = 'integration_gitlab';
	/**
	 * @var mixed
	 */
	private $config;

	/**
	 * Constructor
	 *
	 * @param array $urlParams
	 */
	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);

		$container = $this->getContainer();
		$this->config = $container->get(IConfig::class);
	}

	public function register(IRegistrationContext $context): void {
		$context->registerDashboardWidget(GitlabWidget::class);
		$context->registerSearchProvider(GitlabSearchIssuesProvider::class);
		$context->registerSearchProvider(GitlabSearchReposProvider::class);
	}

	public function boot(IBootContext $context): void {
		$context->injectFn(Closure::fromCallable([$this, 'registerNavigation']));
		Util::addStyle(self::APP_ID, 'gitlab-search');
	}

	public function registerNavigation(IUserSession $userSession): void {
		$user = $userSession->getUser();
		if ($user !== null) {
			$userId = $user->getUID();
			$container = $this->getContainer();

			if ($this->config->getUserValue($userId, self::APP_ID, 'navigation_enabled', '0') === '1') {
				$gitlabUrl = $this->config->getUserValue($userId, self::APP_ID, 'url', '') ?: 'https://gitlab.com';
				$container->get(INavigationManager::class)->add(function () use ($container, $gitlabUrl) {
					$urlGenerator = $container->get(IURLGenerator::class);
					$l10n = $container->get(IL10N::class);
					return [
						'id' => self::APP_ID,

						'order' => 10,

						// the route that will be shown on startup
						'href' => $gitlabUrl,

						// the icon that will be shown in the navigation
						// this file needs to exist in img/
						'icon' => $urlGenerator->imagePath(self::APP_ID, 'app.svg'),

						// the title of your application. This will be used in the
						// navigation or on the settings page of your app
						'name' => $l10n->t('GitLab'),
					];
				});
			}
		}
	}
}

