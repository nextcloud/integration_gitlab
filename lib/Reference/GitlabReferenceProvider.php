<?php

/**
 * @copyright Copyright (c) 2022 Julien Veyssier <julien-nc@posteo.net>
 *
 * @author Julien Veyssier <julien-nc@posteo.net>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\Gitlab\Reference;

use DateTime;
use Exception;
use OC\Collaboration\Reference\ReferenceManager;
use OCA\Gitlab\AppInfo\Application;
use OCA\Gitlab\Db\GitlabAccount;
use OCA\Gitlab\Db\GitlabAccountMapper;
use OCA\Gitlab\Service\ConfigService;
use OCA\Gitlab\Service\GitlabAPIService;
use OCP\Collaboration\Reference\ADiscoverableReferenceProvider;
use OCP\Collaboration\Reference\IReference;
use OCP\Collaboration\Reference\ISearchableReferenceProvider;
use OCP\Collaboration\Reference\Reference;
use OCP\IL10N;
use OCP\IURLGenerator;
use Psr\Log\LoggerInterface;
use Throwable;

class GitlabReferenceProvider extends ADiscoverableReferenceProvider implements ISearchableReferenceProvider {

	public function __construct(
		private GitlabAPIService $gitlabAPIService,
		private ConfigService $config,
		private ReferenceManager $referenceManager,
		private IURLGenerator $urlGenerator,
		private IL10N $l10n,
		private ?string $userId,
		private GitlabAccountMapper $accountMapper,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function getId(): string {
		return 'gitlab-issue-mr';
	}

	/**
	 * @inheritDoc
	 */
	public function getTitle(): string {
		return $this->l10n->t('GitLab repositories, issues and merge requests');
	}

	/**
	 * @inheritDoc
	 */
	public function getOrder(): int {
		return 10;
	}

	/**
	 * @inheritDoc
	 */
	public function getIconUrl(): string {
		return $this->urlGenerator->getAbsoluteURL(
			$this->urlGenerator->imagePath(Application::APP_ID, 'app-dark.svg')
		);
	}

	/**
	 * @inheritDoc
	 */
	public function getSupportedSearchProviderIds(): array {
		return ['gitlab-search-issues', 'gitlab-search-repos', 'gitlab-search-mrs'];
	}

	private function urlMatchesText(string $url, string $referenceText): bool {
		return preg_match('/^' . preg_quote($url, '/') . '\/[^\/\?]+\/[^\/\?]+\/-\/(issues|merge_requests)\/[0-9]+/', $referenceText) === 1;
	}

	/**
	 * @inheritDoc
	 */
	public function matchReference(string $referenceText): bool {
		if ($this->userId !== null) {
			$linkPreviewEnabled = $this->config->getUserLinkPreviewEnabled($this->userId);
			if (!$linkPreviewEnabled) {
				return false;
			}
		}
		$adminLinkPreviewEnabled = $this->config->getAdminLinkPreviewEnabled();
		if (!$adminLinkPreviewEnabled) {
			return false;
		}

		$urls = array_map(static fn (GitlabAccount $account) => $account->getUrl(), $this->accountMapper->find($this->userId));
		$adminOauthUrl = $this->config->getAdminOauthUrl();
		if ($adminOauthUrl !== '') {
			$urls[] = $adminOauthUrl;
		}

		foreach ($urls as $url) {
			if ($this->urlMatchesText($url, $referenceText)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @inheritDoc
	 */
	public function resolveReference(string $referenceText): ?IReference {
		$accounts = $this->accountMapper->find($this->userId);
		$adminOauthUrl = $this->config->getAdminOauthUrl();
		if ($adminOauthUrl !== '') {
			$accounts[] = null;
		}

		foreach ($accounts as $account) {
			$baseUrl = $account !== null ? $account->getUrl() : $adminOauthUrl;
			if (!$this->urlMatchesText($baseUrl, $referenceText)) {
				continue;
			}

			// The account might not have the permissions, so catch any errors and try the remaining accounts
			try {
				$issuePath = $this->getIssuePath($baseUrl, $referenceText);
				if ($issuePath !== null) {
					[$owner, $repo, $issueId, $end] = $issuePath;
					$projectInfo = $this->gitlabAPIService->getProjectInfo($account, $baseUrl, $owner, $repo);
					if (isset($projectInfo['error'])) {
						return null;
					}
					$projectLabels = $account !== null ? $this->gitlabAPIService->getProjectLabels($account, $baseUrl, $projectInfo['id']) : [];
					$commentInfo = $this->getIssueCommentInfo($account, $baseUrl, $projectInfo['id'], $issueId, $end);
					$issueInfo = $this->gitlabAPIService->getIssueInfo($account, $baseUrl, $projectInfo['id'], $issueId);
					$reference = new Reference($referenceText);
					$reference->setRichObject(
						Application::APP_ID,
						array_merge([
							'account_id' => $account?->getId(),
							'gitlab_type' => isset($issueInfo['error']) ? 'issue-error' : 'issue',
							'gitlab_url' => $baseUrl,
							'gitlab_issue_id' => $issueId,
							'gitlab_repo_owner' => $owner,
							'gitlab_repo' => $repo,
							'gitlab_project_owner_username' => $projectInfo['owner']['username'] ?? '',
							'gitlab_project_labels' => $projectLabels,
							'gitlab_comment' => $commentInfo,
							'vcs_comment' => $commentInfo ? $this->getGenericCommentInfo($commentInfo) : null,
							'vcs_issue' => $this->getGenericIssueInfo($issueInfo, $projectLabels),
						], $issueInfo)
					);
					return $reference;
				}

				$prPath = $this->getPrPath($baseUrl, $referenceText);
				if ($prPath !== null) {
					[$owner, $repo, $prId, $end] = $prPath;
					$projectInfo = $this->gitlabAPIService->getProjectInfo($account, $baseUrl, $owner, $repo);
					if (isset($projectInfo['error'])) {
						return null;
					}
					$projectLabels = $account !== null ? $this->gitlabAPIService->getProjectLabels($account, $baseUrl, $projectInfo['id']) : [];
					$commentInfo = $this->getPrCommentInfo($account, $baseUrl, $projectInfo['id'], $prId, $end);
					$prInfo = $this->gitlabAPIService->getPrInfo($account, $baseUrl, $projectInfo['id'], $prId);
					$reference = new Reference($referenceText);
					$reference->setRichObject(
						Application::APP_ID,
						array_merge([
							'account_id' => $account?->getId(),
							'gitlab_type' => isset($prInfo['error']) ? 'pr-error' : 'pr',
							'gitlab_url' => $baseUrl,
							'gitlab_pr_id' => $prId,
							'gitlab_repo_owner' => $owner,
							'gitlab_repo' => $repo,
							'gitlab_project_owner_username' => $projectInfo['owner']['username'] ?? '',
							'gitlab_project_labels' => $projectLabels,
							'gitlab_comment' => $commentInfo,
							'vcs_comment' => $commentInfo ? $this->getGenericCommentInfo($commentInfo) : null,
							'vcs_pull_request' => $this->getGenericPrInfo($prInfo, $projectLabels),
						], $prInfo),
					);
					return $reference;
				}
			} catch (Exception $e) {
				$this->logger->error('Failed to resolve reference for url ' . $baseUrl . ' with account id ' . $account?->getId(), ['exception' => $e]);
			}
		}

		return null;
	}

	/**
	 * @param array $commentInfo
	 * @return array
	 */
	private function getGenericCommentInfo(array $commentInfo): array {
		$info = [
			'body' => $commentInfo['body'] ?? '',
		];
		if (isset($commentInfo['created_at'])) {
			try {
				$ts = (new DateTime($commentInfo['created_at']))->getTimestamp();
				$info['created_at'] = $ts;
			} catch (Exception|Throwable $e) {
			}
		}
		if (isset($commentInfo['updated_at'])) {
			try {
				$ts = (new DateTime($commentInfo['updated_at']))->getTimestamp();
				$info['updated_at'] = $ts;
			} catch (Exception|Throwable $e) {
			}
		}
		if (isset($commentInfo['author'], $commentInfo['author']['username'])) {
			$info['author'] = $commentInfo['author']['username'];
		}

		return $info;
	}

	/**
	 * @param array $issueInfo
	 * @param array $projectLabels
	 * @return array
	 */
	private function getGenericIssueInfo(array $issueInfo, array $projectLabels): array {
		$info = [
			'id' => $issueInfo['iid'] ?? null,
			'url' => $issueInfo['web_url'] ?? null,
			'title' => $issueInfo['title'] ?? '',
			'comment_count' => $issueInfo['user_notes_count'] ?? 0,
			'state' => $issueInfo['state'],
		];

		if (isset($issueInfo['labels']) && is_array($issueInfo['labels'])) {
			$labelsByName = [];
			foreach ($projectLabels as $label) {
				$labelsByName[$label['name']] = $label;
			}
			$info['labels'] = array_map(static function (string $label) use ($labelsByName) {
				return [
					'name' => $label,
					'color' => $labelsByName[$label]['text_color'],
				];
			}, $issueInfo['labels']);
		}
		if (isset($issueInfo['created_at'])) {
			try {
				$ts = (new DateTime($issueInfo['created_at']))->getTimestamp();
				$info['created_at'] = $ts;
			} catch (Exception|Throwable $e) {
			}
		}
		if (isset($issueInfo['author'], $issueInfo['author']['username'])) {
			$info['author'] = $issueInfo['author']['username'];
		}

		return $info;
	}

	/**
	 * @param array $prInfo
	 * @param array $projectLabels
	 * @return array
	 */
	private function getGenericPrInfo(array $prInfo, array $projectLabels): array {
		$info = [
			'id' => $prInfo['iid'] ?? null,
			'url' => $prInfo['web_url'] ?? null,
			'title' => $prInfo['title'] ?? '',
			'comment_count' => $prInfo['user_notes_count'] ?? 0,
			'state' => $prInfo['state'],
		];

		if (isset($prInfo['labels']) && is_array($prInfo['labels'])) {
			$labelsByName = [];
			foreach ($projectLabels as $label) {
				$labelsByName[$label['name']] = $label;
			}
			$info['labels'] = array_map(static function (string $label) use ($labelsByName) {
				return [
					'name' => $label,
					'color' => $labelsByName[$label]['text_color'],
				];
			}, $prInfo['labels']);
		}
		if (isset($prInfo['created_at'])) {
			try {
				$ts = (new DateTime($prInfo['created_at']))->getTimestamp();
				$info['created_at'] = $ts;
			} catch (Exception|Throwable $e) {
			}
		}
		if (isset($prInfo['author'], $prInfo['author']['username'])) {
			$info['author'] = $prInfo['author']['username'];
		}

		return $info;
	}

	/**
	 * @param string $gitlabUrl
	 * @param string $url
	 * @return array|null
	 */
	private function getIssuePath(string $gitlabUrl, string $url): ?array {
		preg_match('/^' . preg_quote($gitlabUrl, '/') . '\/([^\/\?]+)\/([^\/\?]+)\/-\/issues\/([0-9]+)(.*$)/', $url, $matches);
		return count($matches) > 3 ? [$matches[1], $matches[2], $matches[3], $matches[4]] : null;
	}

	/**
	 * @param string $gitlabUrl
	 * @param string $url
	 * @return array|null
	 */
	private function getPrPath(string $gitlabUrl, string $url): ?array {
		preg_match('/^' . preg_quote($gitlabUrl, '/') . '\/([^\/\?]+)\/([^\/\?]+)\/-\/merge_requests\/([0-9]+)(.*$)/', $url, $matches);
		return count($matches) > 3 ? [$matches[1], $matches[2], $matches[3], $matches[4]] : null;
	}

	/**
	 * @param string $urlEnd
	 * @return int|null
	 */
	private function getCommentId(string $urlEnd): ?int {
		preg_match('/^#note_([0-9]+)$/', $urlEnd, $matches);
		return (count($matches) > 1) ? ((int)$matches[1]) : null;
	}

	private function getIssueCommentInfo(?GitlabAccount $account, string $baseUrl, int $projectId, int $issueId, string $end): ?array {
		$commentId = $this->getCommentId($end);
		return $commentId !== null ? $this->gitlabAPIService->getIssueCommentInfo($account, $baseUrl, $projectId, $issueId, $commentId) : null;
	}

	private function getPrCommentInfo(?GitlabAccount $account, string $baseUrl, int $projectId, int $prId, string $end): ?array {
		$commentId = $this->getCommentId($end);
		return $commentId !== null ? $this->gitlabAPIService->getPrCommentInfo($account, $baseUrl, $projectId, $prId, $commentId) : null;
	}

	/**
	 * We use the userId here because when connecting/disconnecting from the GitHub account,
	 * we want to invalidate all the user cache and this is only possible with the cache prefix
	 * @inheritDoc
	 */
	public function getCachePrefix(string $referenceId): string {
		return $this->userId ?? '';
	}

	/**
	 * We don't use the userId here but rather a reference unique id
	 * @inheritDoc
	 */
	public function getCacheKey(string $referenceId): ?string {
		return $referenceId;
	}

	/**
	 * @param string $userId
	 * @return void
	 */
	public function invalidateUserCache(string $userId): void {
		$this->referenceManager->invalidateCache($userId);
	}
}
