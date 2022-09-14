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
use OCA\Gitlab\Listener\GitlabReferenceListener;
use OCA\Gitlab\Reference\GitlabReferenceProvider;
use OCP\Collaboration\Reference\RenderReferenceEvent;
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

	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);

		$container = $this->getContainer();
		$this->config = $container->get(IConfig::class);
	}

	public function register(IRegistrationContext $context): void {
		$context->registerDashboardWidget(GitlabWidget::class);
		$context->registerSearchProvider(GitlabSearchIssuesProvider::class);
		$context->registerSearchProvider(GitlabSearchReposProvider::class);

		$context->registerReferenceProvider(GitlabReferenceProvider::class);
		$context->registerEventListener(RenderReferenceEvent::class, GitlabReferenceListener::class);
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
				$adminOauthUrl = $this->config->getAppValue(Application::APP_ID, 'oauth_instance_url', 'https://gitlab.com') ?: 'https://gitlab.com';
				$gitlabUrl = $this->config->getUserValue($userId, self::APP_ID, 'url', $adminOauthUrl) ?: $adminOauthUrl;
				$container->get(INavigationManager::class)->add(function () use ($container, $gitlabUrl) {
					$urlGenerator = $container->get(IURLGenerator::class);
					$l10n = $container->get(IL10N::class);
					return [
						'id' => self::APP_ID,
						'order' => 10,
						'href' => $gitlabUrl,
						'target' => '_blank',
						'icon' => $urlGenerator->imagePath(self::APP_ID, 'app.svg'),
						'name' => $l10n->t('GitLab'),
					];
				});
			}
		}
	}
}

