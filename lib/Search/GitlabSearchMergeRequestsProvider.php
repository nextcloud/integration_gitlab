<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2020, Julien Veyssier
 *
 * @author Julien Veyssier <julien-nc@posteo.net>
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

use OCA\Gitlab\AppInfo\Application;
use OCA\Gitlab\Service\ConfigService;
use OCA\Gitlab\Service\GitlabAPIService;
use OCP\App\IAppManager;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\Search\IProvider;
use OCP\Search\ISearchQuery;
use OCP\Search\SearchResult;
use OCP\Search\SearchResultEntry;

class GitlabSearchMergeRequestsProvider implements IProvider {

	public function __construct(
		private IAppManager $appManager,
		private IL10N $l10n,
		private ConfigService $config,
		private IURLGenerator $urlGenerator,
		private GitlabAPIService $service,
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function getId(): string {
		return 'gitlab-search-mrs';
	}

	/**
	 * @inheritDoc
	 */
	public function getName(): string {
		return $this->l10n->t('GitLab merge requests');
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

		$routeFrom = $query->getRoute();
		$requestedFromSmartPicker = $routeFrom === '' || $routeFrom === 'smart-picker';

		$searchMRsEnabled = $this->config->getUserSearchMergeRequestsEnabled($user->getUID());
		if (!$requestedFromSmartPicker && !$searchMRsEnabled) {
			return SearchResult::paginated($this->getName(), [], 0);
		}

		$accessToken = $this->config->getUserToken($user->getUID());
		if ($accessToken === '') {
			return SearchResult::paginated($this->getName(), [], 0);
		}

		$url = $this->config->getUserUrl($user->getUID());

		$mergeRequests = $this->service->searchMergeRequests($user->getUID(), $term, $offset, $limit);
		if (isset($mergeRequests['error'])) {
			return SearchResult::paginated($this->getName(), [], 0);
		}

		$formattedResults = array_map(function (array $entry) use ($url): SearchResultEntry {
			$finalThumbnailUrl = $this->getThumbnailUrl($entry);
			return new SearchResultEntry(
				$finalThumbnailUrl,
				$this->getMainText($entry),
				$this->getSubline($entry, $url),
				$this->getLinkToGitlab($entry),
				$finalThumbnailUrl === '' ? 'icon-gitlab-search-fallback' : '',
				true
			);
		}, $mergeRequests);

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
		$stateChar = $entry['state'] === 'merged'
			? '✅'
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
		$repoFullName = preg_replace('/\/?-?\/merge_requests\/.*/', '', $repoFullName);
		$repoFullName = preg_replace('/^\//', '', $repoFullName);
		//		$spl = explode('/', $repoFullName);
		//		$owner = $spl[0];
		//		$repo = $spl[1];
		$number = $entry['iid'];
		$typeChar = '⑃';
		$idChar = '!';
		return $typeChar . ' ' . $idChar . $number . ' ' . $repoFullName;
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
