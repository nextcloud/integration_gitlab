<!--
  - @copyright Copyright (c) 2022 2022 Julien Veyssier <eneiluj@posteo.net>
  -
  - @author 2022 Julien Veyssier <eneiluj@posteo.net>
  -
  - @license GNU AGPL version 3 or any later version
  -
  - This program is free software: you can redistribute it and/or modify
  - it under the terms of the GNU Affero General Public License as
  - published by the Free Software Foundation, either version 3 of the
  - License, or (at your option) any later version.
  -
  - This program is distributed in the hope that it will be useful,
  - but WITHOUT ANY WARRANTY; without even the implied warranty of
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
  -->

<template>
	<div class="gitlab-reference">
		<div v-if="isError">
			<h3>
				<GitlabIcon :size="20" class="icon" />
				<span>{{ t('integration_gitlab', 'GitLab API error') }}</span>
			</h3>
			<p v-if="richObject.body?.message"
				class="widget-error">
				{{ richObject.body?.message }}
			</p>
			<p v-else
				class="widget-error">
				{{ t('integration_gitlab', 'Unknown error') }}
			</p>
			<a :href="settingsUrl" class="settings-link external" target="_blank">
				<OpenInNewIcon :size="20" class="icon" />
				{{ t('integration_gitlab', 'GitLab connected accounts settings') }}
			</a>
		</div>
		<div v-if="isIssue || isPr" class="issue-pr-wrapper">
			<div class="issue-pr-info">
				<div class="line">
					<div class="title">
						<a :href="richObject.web_url" class="issue-pr-link" target="_blank">
							<strong>
								{{ richObject.title }}
							</strong>
						</a>
					</div>
				</div>
				<div class="sub-text">
					<component :is="iconComponent"
						v-tooltip.top="{ content: stateTooltip }"
						:size="16"
						class="icon"
						:fill-color="iconColor" />
					<span>
						<a :href="repoUrl" class="slug-link" target="_blank">
							{{ slug }}
						</a>
						{{ gitlabId }}
					</span>
					<span
						v-tooltip.top="{ content: subTextTooltip }"
						v-html="subText" />
					<a v-if="richObject.milestone"
						v-tooltip.top="{ content: richObject.milestone.description }"
						:href="richObject.milestone.web_url"
						target="_blank"
						class="milestone">
						<MilestoneIcon :size="16" class="icon" />
						{{ richObject.milestone.title }}
					</a>
					<div v-for="label in labels"
						:key="label.id"
						v-tooltip.top="{ content: label.description }"
						class="label"
						:style="{
							background: label.color,
							color: label.text_color,
						}">
						{{ label.name }}
					</div>
				</div>
			</div>
			<div class="spacer" />
			<div class="right-content">
				<div>
					<span v-if="richObject.state === 'closed'" class="closed-prefix">
						{{ t('integration_gitlab', 'CLOSED') }}
					</span>
					<span v-if="richObject.state === 'merged'" class="closed-prefix">
						{{ t('integration_gitlab', 'MERGED') }}
					</span>
					<div v-if="richObject.assignees.length > 0"
						class="assignee-avatars">
						<NcAvatar v-for="assignee in richObject.assignees"
							:key="assignee.username"
							:tooltip-message="getAssigneeTooltip(assignee)"
							:is-no-user="true"
							:size="20"
							:url="getAssigneeAvatarUrl(assignee)" />
					</div>
					<div v-if="richObject.upvotes > 0"
						v-tooltip.top="{ content: t('integration_gitlab', 'Upvotes') }"
						class="comments-count">
						<UpVoteIcon :size="16" class="icon" />
						{{ richObject.upvotes }}
					</div>
					<div v-if="richObject.downvotes > 0"
						v-tooltip.top="{ content: t('integration_gitlab', 'Downvotes') }"
						class="comments-count">
						<DownVoteIcon :size="16" class="icon" />
						{{ richObject.downvotes }}
					</div>
					<div v-tooltip.top="{ content: t('integration_gitlab', 'Comments') }"
						class="comments-count">
						<CommentIcon :size="16" class="icon" />
						{{ richObject.user_notes_count }}
					</div>
				</div>
				<div v-if="richObject.closed_at" class="closed-at">
					{{ closedAtText }}
				</div>
				<div v-else-if="richObject.updated_at" class="updated-at">
					{{ updatedAtText }}
				</div>
			</div>
		</div>
		<div v-if="richObject.gitlab_comment" class="comment">
			<div class="comment--author">
				<NcAvatar
					class="comment--author--avatar"
					:tooltip-message="commentAuthorTooltip"
					:is-no-user="true"
					:url="commentAuthorAvatarUrl" />
				<span class="comment--author--bubble-tip" />
				<span class="comment--author--bubble">
					<div class="comment--author--bubble--header">
						<a :href="getUserUrl(richObject.gitlab_comment.author.username)" target="_blank" class="author-link">
							<strong class="comment-author-display-name">{{ richObject.gitlab_comment.author.name }}</strong>
							@{{ richObject.gitlab_comment.author.username }}
						</a>
						&nbsp;·&nbsp;
						<span v-tooltip.top="{ content: commentUpdatedAtTooltip }">
							{{ commentUpdatedAtText }}
						</span>
						<div class="spacer" />
						<div v-if="richObject.gitlab_comment.author.username === richObject.author.username" class="label">
							{{ t('integration_gitlab', 'Author') }}
						</div>
						<div v-if="richObject.gitlab_comment.author.username === richObject.gitlab_project_owner_username" class="label">
							{{ t('integration_gitlab', 'Owner') }}
						</div>
					</div>
					<div class="comment--author--bubble--content" :title="richObject.gitlab_comment.body">
						{{ richObject.gitlab_comment.body }}
					</div>
				</span>
			</div>
		</div>
	</div>
</template>

<script>
import OpenInNewIcon from 'vue-material-design-icons/OpenInNew.vue'

import GitlabIcon from '../components/icons/GitlabIcon.vue'
import IssueIcon from '../components/icons/IssueIcon.vue'
import CommentIcon from '../components/icons/CommentIcon.vue'
import MilestoneIcon from '../components/icons/MilestoneIcon.vue'
import MergeRequestIcon from '../components/icons/MergeRequestIcon.vue'
import UpVoteIcon from '../components/icons/UpVoteIcon.vue'
import DownVoteIcon from '../components/icons/DownVoteIcon.vue'

import { generateUrl } from '@nextcloud/router'
import moment from '@nextcloud/moment'
import escapeHtml from 'escape-html'

import NcAvatar from '@nextcloud/vue/dist/Components/NcAvatar.js'
import Tooltip from '@nextcloud/vue/dist/Directives/Tooltip.js'
import Vue from 'vue'
Vue.directive('tooltip', Tooltip)

export default {
	name: 'ReferenceGitlabWidget',

	components: {
		DownVoteIcon,
		UpVoteIcon,
		GitlabIcon,
		MilestoneIcon,
		MergeRequestIcon,
		// CommentReactions,
		NcAvatar,
		CommentIcon,
		OpenInNewIcon,
	},

	props: {
		richObjectType: {
			type: String,
			default: '',
		},
		richObject: {
			type: Object,
			default: null,
		},
		accessible: {
			type: Boolean,
			default: true,
		},
	},

	data() {
		return {
			settingsUrl: generateUrl('/settings/user/connected-accounts#gitlab_prefs'),
		}
	},

	computed: {
		isError() {
			return ['issue-error', 'pr-error'].includes(this.richObject.gitlab_type)
		},
		isIssue() {
			return this.richObject.gitlab_type === 'issue'
		},
		isPr() {
			return this.richObject.gitlab_type === 'pr'
		},
		slug() {
			return this.richObject.gitlab_repo_owner + '/' + this.richObject.gitlab_repo
		},
		repoUrl() {
			return 'https://gitlab.com/' + this.slug
		},
		gitlabId() {
			if (this.isIssue) {
				return '#' + this.richObject.gitlab_issue_id
			} else if (this.isPr) {
				return '!' + this.richObject.gitlab_pr_id
			}
			return ''
		},
		labels() {
			return this.richObject.labels?.map(l => {
				return this.richObject.gitlab_project_labels.find(sl => sl.name === l)
			})
		},
		iconComponent() {
			if (this.isIssue) {
				return IssueIcon
			} else if (this.isPr) {
				return MergeRequestIcon
			}
			return IssueIcon
		},
		iconColor() {
			if (this.richObject.state === 'opened') {
				return '#3fb950'
			} else if (this.richObject.state === 'closed') {
				return '#a371f7'
			}
			return '#8b949e'
		},
		stateTooltip() {
			if (this.isIssue) {
				if (this.richObject.state === 'opened') {
					return t('integration_gitlab', 'Open issue')
				} else if (this.richObject.state === 'closed') {
					return t('integration_gitlab', 'Closed issue')
				}
			} else if (this.isPr) {
				if (this.richObject.state === 'opened') {
					return t('integration_gitlab', 'Open merge request')
				} else if (this.richObject.state === 'closed') {
					return t('integration_gitlab', 'Closed merge request')
				} else if (this.richObject.state === 'merged') {
					return t('integration_gitlab', 'Merged merge request')
				}
			}
			return t('integration_gitlab', 'Unknown state')
		},
		subText() {
			if (this.isPr) {
				return this.createdAtText
					+ (this.richObject.requested_reviewers?.length > 0 ? ' • ' + t('integration_gitlab', 'Review requested') : '')
			}
			return this.createdAtText
		},
		subTextTooltip() {
			if (this.isIssue) {
				if (this.richObject.state === 'opened') {
					return this.createdAtFormatted
				} else if (this.richObject.state === 'closed') {
					return this.closedAtFormatted
				}
			} else if (this.isPr) {
				if (this.richObject.state === 'opened') {
					return this.createdAtFormatted
				} else if (this.richObject.state === 'closed') {
					if (this.richObject.merged) {
						return this.closedAtFormatted
					} else {
						return this.closedAtFormatted
					}
				}
			}
			return ''
		},
		createdAtFormatted() {
			return moment(this.richObject.created_at).format('LLL')
		},
		closedAtFormatted() {
			return moment(this.richObject.closed_at).format('LLL')
		},
		createdAtText() {
			return t('integration_gitlab', 'created {relativeDate} by {creator}', {
				relativeDate: moment(this.richObject.created_at).fromNow(),
				creator: this.getUserLink(this.richObject.author?.username),
			}, null, { escape: false })
		},
		closedAtText() {
			return t('integration_gitlab', 'closed {relativeDate}', { relativeDate: moment(this.richObject.closed_at).fromNow() })
		},
		updatedAtText() {
			return t('integration_gitlab', 'updated {relativeDate}', { relativeDate: moment(this.richObject.updated_at).fromNow() })
		},
		commentUpdatedAtText() {
			return moment(this.richObject.gitlab_comment.updated_at).fromNow()
		},
		commentUpdatedAtTooltip() {
			return moment(this.richObject.gitlab_comment.updated_at).format('LLL')
		},
		commentAuthorAvatarUrl() {
			const userId = this.richObject.gitlab_comment.author?.id ?? ''
			return generateUrl('/apps/integration_gitlab/avatar/user/{userId}', { userId })
		},
		commentAuthorTooltip() {
			return t('integration_gitlab', 'Comment from {username}', { username: this.richObject.gitlab_comment.author?.username ?? '' })
		},
	},

	methods: {
		getUserLink(userName) {
			if (userName) {
				const cleanName = escapeHtml(userName)
				return '<a href="' + this.getUserUrl(userName) + '" class="author-link" target="_blank">' + cleanName + '</a>'
			}
			return '??'
		},
		getUserUrl(userName) {
			const cleanName = escapeHtml(userName)
			return 'https://gitlab.com/' + cleanName
		},
		getAssigneeAvatarUrl(assignee) {
			const userId = assignee.id ?? ''
			return generateUrl('/apps/integration_gitlab/avatar/user/{userId}', { userId })
		},
		getAssigneeTooltip(assignee) {
			return t('integration_gitlab', 'Assigned to {username}', { username: assignee.username })
		},
	},
}
</script>

<style scoped lang="scss">
.gitlab-reference {
	width: 100%;
	white-space: normal;
	padding: 12px;

	h3 {
		display: flex;
		align-items: center;
		font-weight: bold;
		.icon {
			margin-right: 8px;
		}
	}

	.issue-pr-wrapper {
		width: 100%;
		display: flex;
		align-items: start;

		.assignee-avatars {
			display: flex;
			align-items: center;
			margin-left: 8px;
		}

		.title {
			display: flex;
			align-items: center;
			flex-wrap: wrap;
			> * {
				margin-bottom: 2px;
			}
			.issue-pr-link {
				margin-right: 8px;
			}
		}

		.line {
			display: flex;
			align-items: center;

			> .icon {
				margin: 0 16px 0 8px;
			}
		}

		.sub-text {
			display: flex;
			flex-wrap: wrap;
			align-items: center;
			color: var(--color-text-maxcontrast);
			> * {
				margin-right: 4px;
			}

			.icon {
				margin-right: 4px;
			}
			.milestone {
				display: flex;
				align-items: center;
			}
		}

		.closed-at,
		.updated-at {
			color: var(--color-text-maxcontrast);
		}

		.right-content {
			display: flex;
			flex-direction: column;
			align-items: end;
			text-align: right;

			> * {
				display: flex;
				align-items: center;
			}

			.comments-count {
				display: flex;
				align-items: center;
				margin-left: 8px;
				color: var(--color-text-maxcontrast);
				.icon {
					margin-right: 4px;
				}
			}
		}
	}

	.comment {
		margin-top: 8px;
		display: flex;
		flex-direction: column;
		align-items: start;
		&--author {
			display: flex;
			align-items: center;
			width: 100%;

			&--bubble {
				// TODO improve this
				display: grid;
				//flex-direction: column;
				width: 100%;
				padding: 4px 8px;
				border: 1px solid var(--color-border-dark);
				border-radius: var(--border-radius);
				&--header {
					display: flex;
					color: var(--color-text-maxcontrast);
					.comment-author-display-name {
						color: var(--color-main-text);
					}
				}
				&--content {
					text-overflow: ellipsis;
					overflow: hidden;
					white-space: nowrap;
				}
			}
			&--bubble-tip {
				margin-left: 15px;
				position: relative;
				&:before {
					content: '';
					width: 0px;
					height: 0px;
					position: absolute;
					border-left: 8px solid transparent;
					border-right: 8px solid var(--color-border-dark);
					border-top: 8px solid transparent;
					border-bottom: 8px solid transparent;
					left: -15px;
					top: -8px;
				}

				&:after {
					content: '';
					width: 0px;
					height: 0px;
					position: absolute;
					border-left: 8px solid transparent;
					border-right: 8px solid var(--color-main-background);
					border-top: 8px solid transparent;
					border-bottom: 8px solid transparent;
					left: -14px;
					top: -8px;
				}
				.message-body:hover &:after {
					border-right: 8px solid var(--color-background-hover);
				}
			}
		}
	}

	.label {
		display: flex;
		align-items: center;
		height: 20px;
		margin-right: 4px;
		border: 1px solid var(--color-border-dark);
		padding: 0 7px;
		border-radius: var(--border-radius-pill);
		font-size: 12px;
	}

	.milestone,
	::v-deep .author-link,
	.slug-link {
		color: inherit;
	}

	.milestone,
	::v-deep .author-link,
	.author-link:hover .comment-author-display-name,
	.slug-link,
	.issue-pr-link {
		&:hover {
			color: #58a6ff;
		}
	}

	.item-reactions {
		margin: 8px 0 0 40px;
	}

	.settings-link {
		display: flex;
		align-items: center;
		.icon {
			margin-right: 4px;
		}
	}

	.widget-error {
		margin-bottom: 8px;
	}

	.spacer {
		flex-grow: 1;
	}
}
</style>
