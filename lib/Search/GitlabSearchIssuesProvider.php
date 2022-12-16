<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2020, Julien Veyssier
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 *
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 *
 */
namespace OCA\Gitlab\Search;

use OCA\Gitlab\Service\GitlabAPIService;
use OCA\Gitlab\AppInfo\Application;
use OCP\App\IAppManager;
use OCP\IL10N;
use OCP\IConfig;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\Search\IProvider;
use OCP\Search\ISearchQuery;
use OCP\Search\SearchResult;

class GitlabSearchIssuesProvider implements IProvider {

	/** @var IAppManager */
	private $appManager;

	/** @var IL10N */
	private $l10n;

	/** @var IURLGenerator */
	private $urlGenerator;
	/**
	 * @var IConfig
	 */
	private $config;
	/**
	 * @var GitlabAPIService
	 */
	private $service;

	/**
	 * CospendSearchProvider constructor.
	 *
	 * @param IAppManager $appManager
	 * @param IL10N $l10n
	 * @param IConfig $config
	 * @param IURLGenerator $urlGenerator
	 * @param GitlabAPIService $service
	 */
	public function __construct(IAppManager $appManager,
								IL10N $l10n,
								IConfig $config,
								IURLGenerator $urlGenerator,
								GitlabAPIService $service) {
		$this->appManager = $appManager;
		$this->l10n = $l10n;
		$this->config = $config;
		$this->urlGenerator = $urlGenerator;
		$this->service = $service;
	}

	/**
	 * @inheritDoc
	 */
	public function getId(): string {
		return 'gitlab-search-issues';
	}

	/**
	 * @inheritDoc
	 */
	public function getName(): string {
		return $this->l10n->t('GitLab issues and merge requests');
	}

	/**
	 * @inheritDoc
	 */
	public function getOrder(string $route, array $routeParameters): int {
		if (strpos($route, Application::APP_ID . '.') === 0) {
			// Active app, prefer Gitlab results
			return -1;
		}

		return 20;
	}

	/**
	 * @inheritDoc
	 */
	public function search(IUser $user, ISearchQuery $query): SearchResult {
		if (!$this->appManager->isEnabledForUser(Application::APP_ID, $user)) {
			return SearchResult::complete($this->getName(), []);
		}

		$limit = $query->getLimit();
		$term = $query->getTerm();
		$offset = $query->getCursor();
		$offset = $offset ? intval($offset) : 0;

		$accessToken = $this->config->getUserValue($user->getUID(), Application::APP_ID, 'token');
		$adminOauthUrl = $this->config->getAppValue(Application::APP_ID, 'oauth_instance_url', 'https://gitlab.com') ?: 'https://gitlab.com';
		$url = $this->config->getUserValue($user->getUID(), Application::APP_ID, 'url', $adminOauthUrl) ?: $adminOauthUrl;
		$searchIssuesEnabled = $this->config->getUserValue($user->getUID(), Application::APP_ID, 'search_issues_enabled', '0') === '1';
		if ($accessToken === '' || !$searchIssuesEnabled) {
			return SearchResult::paginated($this->getName(), [], 0);
		}

		$issues = $this->service->searchIssues($user->getUID(), $term, $offset, $limit);
		if (isset($searchResult['error'])) {
			return SearchResult::paginated($this->getName(), [], 0);
		}

		$formattedResults = array_map(function (array $entry) use ($url): GitlabSearchResultEntry {
			$finalThumbnailUrl = $this->getThumbnailUrl($entry);
			return new GitlabSearchResultEntry(
				$finalThumbnailUrl,
				$this->getMainText($entry),
				$this->getSubline($entry, $url),
				$this->getLinkToGitlab($entry),
				$finalThumbnailUrl === '' ? 'icon-gitlab-search-fallback' : '',
				true
			);
		}, $issues);

		return SearchResult::paginated(
			$this->getName(),
			$formattedResults,
			$offset + $limit
		);
	}

	/**
	 * @param array $entry
	 * @return string
	 */
	protected function getMainText(array $entry): string {
		$stateChar = $entry['type'] !== 'issue'
			? ($entry['merged']
				? '✅'
				: ($entry['state'] === 'closed'
					? '❌'
					: '⋯'))
			: ($entry['state'] === 'closed'
				? '❌'
				: '⋯');
		return $stateChar . ' ' . $entry['title'];
	}

	/**
	 * @param array $entry
	 * @param string $url
	 * @return string
	 */
	protected function getSubline(array $entry, string $url): string {
		$repoFullName = str_replace($url, '', $entry['web_url']);
		$repoFullName = preg_replace('/\/issues\/.*/', '', $repoFullName);
		$repoFullName = preg_replace('/^\//', '', $repoFullName);
		$spl = explode('/', $repoFullName);
//		$owner = $spl[0];
		$repo = $spl[1];
		$number = $entry['iid'];
		$typeChar = $entry['type'] !== 'issue' ? '⑃' : '🂠';
		$idChar = $entry['type'] !== 'issue' ? '!' : '#';
		return $typeChar . ' ' . $repo . $idChar . $number;
	}

	/**
	 * @param array $entry
	 * @return string
	 */
	protected function getLinkToGitlab(array $entry): string {
		return $entry['web_url'] ?? '';
	}

	/**
	 * @param array $entry
	 * @param string $thumbnailUrl
	 * @return string
	 */
	protected function getThumbnailUrl(array $entry): string {
		$userId = $entry['author']['id'] ?? '';
		return $userId
			? $this->urlGenerator->linkToRoute('integration_gitlab.gitlabAPI.getUserAvatar', ['userId' => $userId])
			: '';
	}
}
