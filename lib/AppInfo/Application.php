<?php
/**
 * Nextcloud - GitLab
 *
 *
 * @author Julien Veyssier <julien-nc@posteo.net>
 * @copyright Julien Veyssier 2020
 */

namespace OCA\Gitlab\AppInfo;

use OCA\Gitlab\Dashboard\GitlabWidget;
use OCA\Gitlab\Listener\GitlabReferenceListener;
use OCA\Gitlab\Reference\GitlabReferenceProvider;
use OCA\Gitlab\Search\GitlabSearchIssuesProvider;
use OCA\Gitlab\Search\GitlabSearchMergeRequestsProvider;
use OCA\Gitlab\Search\GitlabSearchReposProvider;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;

use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Collaboration\Reference\RenderReferenceEvent;
use OCP\IConfig;

use OCP\Util;

class Application extends App implements IBootstrap {
	public const APP_ID = 'integration_gitlab';
	public const DEFAULT_GITLAB_URL = 'https://gitlab.com';

	private IConfig $config;

	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);

		$container = $this->getContainer();
		$this->config = $container->get(IConfig::class);
	}

	public function register(IRegistrationContext $context): void {
		$context->registerDashboardWidget(GitlabWidget::class);
		$context->registerSearchProvider(GitlabSearchIssuesProvider::class);
		$context->registerSearchProvider(GitlabSearchMergeRequestsProvider::class);
		$context->registerSearchProvider(GitlabSearchReposProvider::class);

		$context->registerReferenceProvider(GitlabReferenceProvider::class);
		$context->registerEventListener(RenderReferenceEvent::class, GitlabReferenceListener::class);
	}

	public function boot(IBootContext $context): void {
		Util::addStyle(self::APP_ID, 'gitlab-search');
	}
}
