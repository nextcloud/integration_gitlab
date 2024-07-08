<?php
/**
 * @copyright Copyright (c) 2022 Julien Veyssier <eneiluj@posteo.net>
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
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

use OCP\Collaboration\Reference\Reference;
use OC\Collaboration\Reference\ReferenceManager;
use OCA\Gitlab\AppInfo\Application;
use OCA\Gitlab\Service\GitlabAPIService;
use OCP\Collaboration\Reference\IReference;
use OCP\Collaboration\Reference\IReferenceProvider;
use OCP\IConfig;
use OCP\PreConditionNotMetException;

class GitlabReferenceProvider implements IReferenceProvider {
	private GitlabAPIService $gitlabAPIService;
	private IConfig $config;
	private ReferenceManager $referenceManager;
	private ?string $userId;

	public function __construct(GitlabAPIService $gitlabAPIService,
								IConfig $config,
								ReferenceManager $referenceManager,
								?string $userId) {
		$this->gitlabAPIService = $gitlabAPIService;
		$this->config = $config;
		$this->referenceManager = $referenceManager;
		$this->userId = $userId;
	}

	/**
	 * @return array
	 */
	private function getGitlabUrls(): array {
		//if ($this->userId === null) {
		//	return ['https://gitlab.com'];
		//}
		$urls = [];
		$adminOauthUrl = $this->config->getAppValue(Application::APP_ID, 'oauth_instance_url', Application::DEFAULT_GITLAB_URL) ?: Application::DEFAULT_GITLAB_URL;
		if ($this->userId !== null) {
			$urls[] = $this->config->getUserValue($this->userId, Application::APP_ID, 'url', $adminOauthUrl) ?: $adminOauthUrl;
		} else {
			$urls[] = $adminOauthUrl;
		}
		// unfortunately most of what we need for reference stuff requires authentication
		// let's not allow to handle multiple gitlab servers
		//$extraUrls = $this->config->getUserValue($this->userId, Application::APP_ID, 'link_urls');
		//$extraUrls = explode(',', $extraUrls);
		//foreach ($extraUrls as $url) {
		//	$urls[] = trim($url, " \t\n\r\0\x0B/");
		//}
		return $urls;
	}

	/**
	 * @param $referenceText
	 * @return string|null
	 */
	private function getMatchingGitlabUrl($referenceText): ?string {
		// example links
		// https://gitlab.com/owner/repo/-/issues/16
		// https://gitlab.com/owner/repo/-/issues/16#note_1049227787
		// https://gitlab.com/owner/repo/-/merge_requests/15
		// https://gitlab.com/owner/repo/-/merge_requests/15#note_411231913
		foreach ($this->getGitlabUrls() as $url) {
			if (preg_match('/^' . preg_quote($url, '/') . '\/[^\/\?]+\/[^\/\?]+\/-\/(issues|merge_requests)\/[0-9]+/', $referenceText) === 1) {
				return $url;
			}
		}

		return null;
	}

	/**
	 * @inheritDoc
	 */
	public function matchReference(string $referenceText): bool {
		if ($this->userId !== null) {
			$linkPreviewEnabled = $this->config->getUserValue($this->userId, Application::APP_ID, 'link_preview_enabled', '1') === '1';
			if (!$linkPreviewEnabled) {
				return false;
			}
		}
		$adminLinkPreviewEnabled = $this->config->getAppValue(Application::APP_ID, 'link_preview_enabled', '1') === '1';
		if (!$adminLinkPreviewEnabled) {
			return false;
		}

		return $this->getMatchingGitlabUrl($referenceText) !== null;
	}

	/**
	 * @inheritDoc
	 */
	public function resolveReference(string $referenceText): ?IReference {
		$gitlabUrl = $this->getMatchingGitlabUrl($referenceText);
		if ($gitlabUrl !== null) {
			$issuePath = $this->getIssuePath($gitlabUrl, $referenceText);
			if ($issuePath !== null) {
				[$owner, $repo, $issueId, $end] = $issuePath;
				$projectInfo = $this->gitlabAPIService->getProjectInfo($this->userId, $owner, $repo);
				if (isset($projectInfo['error'])) {
					return null;
				}
				$projectLabels = $this->gitlabAPIService->getProjectLabels($this->userId, $projectInfo['id']);
				$commentInfo = $this->getIssueCommentInfo($projectInfo['id'], $issueId, $end);
				$issueInfo = $this->gitlabAPIService->getIssueInfo($this->userId, $projectInfo['id'], $issueId);
				$reference = new Reference($referenceText);
				$reference->setRichObject(
					Application::APP_ID,
					array_merge([
						'gitlab_type' => isset($issueInfo['error']) ? 'issue-error' : 'issue',
						'gitlab_url' => $gitlabUrl,
						'gitlab_issue_id' => $issueId,
						'gitlab_repo_owner' => $owner,
						'gitlab_repo' => $repo,
						'gitlab_project_owner_username' => $projectInfo['owner']['username'] ?? '',
						'gitlab_project_labels' => $projectLabels,
						'gitlab_comment' => $commentInfo,
					], $issueInfo)
				);
				return $reference;
			} else {
				$prPath = $this->getPrPath($gitlabUrl, $referenceText);
				if ($prPath !== null) {
					[$owner, $repo, $prId, $end] = $prPath;
					$projectInfo = $this->gitlabAPIService->getProjectInfo($this->userId, $owner, $repo);
					if (isset($projectInfo['error'])) {
						return null;
					}
					$projectLabels = $this->gitlabAPIService->getProjectLabels($this->userId, $projectInfo['id']);
					$commentInfo = $this->getPrCommentInfo($projectInfo['id'], $prId, $end);
					$prInfo = $this->gitlabAPIService->getPrInfo($this->userId, $projectInfo['id'], $prId);
					$reference = new Reference($referenceText);
					$reference->setRichObject(
						Application::APP_ID,
						array_merge([
							'gitlab_type' => isset($prInfo['error']) ? 'pr-error' : 'pr',
							'gitlab_url' => $gitlabUrl,
							'gitlab_pr_id' => $prId,
							'gitlab_repo_owner' => $owner,
							'gitlab_repo' => $repo,
							'gitlab_project_owner_username' => $projectInfo['owner']['username'] ?? '',
							'gitlab_project_labels' => $projectLabels,
							'gitlab_comment' => $commentInfo,
						], $prInfo),
					);
					return $reference;
				}
			}
		}

		return null;
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
		preg_match('/^'. preg_quote($gitlabUrl, '/') . '\/([^\/\?]+)\/([^\/\?]+)\/-\/merge_requests\/([0-9]+)(.*$)/', $url, $matches);
		return count($matches) > 3 ? [$matches[1], $matches[2], $matches[3], $matches[4]] : null;
	}

	/**
	 * @param string $urlEnd
	 * @return int|null
	 */
	private function getCommentId(string $urlEnd): ?int {
		preg_match('/^#note_([0-9]+)$/', $urlEnd, $matches);
		return (is_array($matches) && count($matches) > 1) ? ((int) $matches[1]) : null;
	}

	/**
	 * @param int $projectId
	 * @param int $issueId
	 * @param string $end
	 * @return array|null
	 * @throws PreConditionNotMetException
	 */
	private function getIssueCommentInfo(int $projectId, int $issueId, string $end): ?array {
		$commentId = $this->getCommentId($end);
		return $commentId !== null ? $this->gitlabAPIService->getIssueCommentInfo($this->userId, $projectId, $issueId, $commentId) : null;
	}

	/**
	 * @param int $projectId
	 * @param int $prId
	 * @param string $end
	 * @return array|null
	 * @throws PreConditionNotMetException
	 */
	private function getPrCommentInfo(int $projectId, int $prId, string $end): ?array {
		$commentId = $this->getCommentId($end);
		return $commentId !== null ? $this->gitlabAPIService->getPrCommentInfo($this->userId, $projectId, $prId, $commentId) : null;
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
