<?php
/**
 * Nextcloud - GitLab
 *
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 * @copyright Julien Veyssier 2020
 */

namespace OCA\Gitlab\AppInfo;

use OCP\IContainer;

use OCP\AppFramework\App;
use OCP\AppFramework\IAppContainer;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;

use OCA\Gitlab\Controller\PageController;
use OCA\Gitlab\Dashboard\GitlabWidget;
use OCA\Gitlab\Search\GitlabSearchIssuesProvider;
use OCA\Gitlab\Search\GitlabSearchReposProvider;

/**
 * Class Application
 *
 * @package OCA\Gitlab\AppInfo
 */
class Application extends App implements IBootstrap {
	public const APP_ID = 'integration_gitlab';

	/**
	 * Constructor
	 *
	 * @param array $urlParams
	 */
	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);

		$container = $this->getContainer();
	}

	public function register(IRegistrationContext $context): void {
		$context->registerDashboardWidget(GitlabWidget::class);
		//$context->registerSearchProvider(GitlabSearchIssuesProvider::class);
		$context->registerSearchProvider(GitlabSearchReposProvider::class);
	}

	public function boot(IBootContext $context): void {
	}
}

