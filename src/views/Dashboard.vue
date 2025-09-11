<template>
	<NcDashboardWidget :items="items"
		:show-more-url="config?.url?.replace(/\/+$/, '') + '/dashboard/todos'"
		:show-more-label="title"
		:loading="state === 'loading'">
		<template #default="{ item }">
			<NcDashboardWidgetItem v-bind="item" />
		</template>
		<template #empty-content>
			<NcEmptyContent v-if="emptyContentMessage"
				:title="emptyContentMessage">
				<template #icon>
					<component :is="emptyContentIcon" />
				</template>
			</NcEmptyContent>
		</template>
	</NcDashboardWidget>
</template>

<script>
import LoginVariantIcon from 'vue-material-design-icons/LoginVariant.vue'
import CheckIcon from 'vue-material-design-icons/Check.vue'
import CloseIcon from 'vue-material-design-icons/Close.vue'

import GitlabIcon from '../components/icons/GitlabIcon.vue'

import axios from '@nextcloud/axios'
import { generateUrl, imagePath } from '@nextcloud/router'
import NcDashboardWidget from '@nextcloud/vue/components/NcDashboardWidget'
import NcDashboardWidgetItem from '@nextcloud/vue/components/NcDashboardWidgetItem'
import { showError } from '@nextcloud/dialogs'
import { loadState } from '@nextcloud/initial-state'
import moment from '@nextcloud/moment'

import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcButton from '@nextcloud/vue/components/NcButton'

export default {
	name: 'Dashboard',

	components: {
		NcDashboardWidgetItem,
		NcDashboardWidget,
		NcEmptyContent,
		NcButton,
		LoginVariantIcon,
	},

	props: {
		title: {
			type: String,
			required: true,
		},
	},

	data() {
		return {
			notifications: [],
			loop: null,
			state: 'loading',
			settingsUrl: generateUrl('/settings/user/connected-accounts'),
			config: loadState('integration_gitlab', 'user-config'),
			windowVisibility: true,
		}
	},

	computed: {
		items() {
			return this.notifications.map((n) => {
				return {
					id: this.getUniqueKey(n),
					targetUrl: this.getNotificationTarget(n),
					avatarUrl: this.getNotificationImage(n),
					// avatarUrl: this.getAuthorAvatarUrl(n),
					avatarUsername: this.getRepositoryName(n),
					avatarIsNoUser: true,
					overlayIconUrl: this.getNotificationTypeImage(n),
					mainText: this.getTargetTitle(n),
					subText: this.getSubline(n),
				}
			})
		},
		lastDate() {
			const nbNotif = this.notifications.length
			return (nbNotif > 0) ? this.notifications[0].updated_at : null
		},
		lastMoment() {
			return moment(this.lastDate)
		},
		emptyContentMessage() {
			if (this.state === 'no-account') {
				return t('integration_gitlab', 'No GitLab account connected')
			} else if (this.state === 'error') {
				return t('integration_gitlab', 'Error connecting to GitLab')
			} else if (this.state === 'ok') {
				return t('integration_gitlab', 'No GitLab notifications!')
			}
			return ''
		},
		emptyContentIcon() {
			if (this.state === 'no-account') {
				return GitlabIcon
			} else if (this.state === 'error') {
				return CloseIcon
			} else if (this.state === 'ok') {
				return CheckIcon
			}
			return CheckIcon
		},
		widgetProjectsFilter() {
			if (this.config.widget_projects) {
				return this.config.widget_projects.map((p) => p.id)
			}
			return []
		},
	},

	watch: {
		windowVisibility(newValue) {
			if (newValue) {
				this.launchLoop()
			} else {
				this.stopLoop()
			}
		},
	},

	beforeUnmount() {
		document.removeEventListener('visibilitychange', this.changeWindowVisibility)
	},

	beforeMount() {
		this.launchLoop()
		document.addEventListener('visibilitychange', this.changeWindowVisibility)
	},

	methods: {
		changeWindowVisibility() {
			this.windowVisibility = !document.hidden
		},
		stopLoop() {
			clearInterval(this.loop)
		},
		async launchLoop() {
			this.fetchNotifications()
			this.loop = setInterval(() => this.fetchNotifications(), 60000)
		},
		fetchNotifications() {
			if (this.config.widget_account_id === 0) {
				this.state = 'no-account'
				return
			}

			const req = {}
			if (this.lastDate) {
				req.params = {
					since: this.lastDate,
				}
			}

			if (this.config.widget_groups && this.config.widget_groups.length === 1) {
				req.params = req.params || {}
				req.params.groupId = this.config.widget_groups[0].id
			}

			axios.get(generateUrl(`/apps/integration_gitlab/gitlab/${this.config.widget_account_id}/todos`), req).then((response) => {
				this.processNotifications(response.data)
				this.state = 'ok'
			}).catch((error) => {
				clearInterval(this.loop)
				showError(t('integration_gitlab', 'Failed to get GitLab notifications'))
				this.state = 'error'
				console.debug(error)
			})
		},
		processNotifications(newNotifications) {
			if (this.lastDate) {
				// just add those which are more recent than our most recent one
				let i = 0
				while (i < newNotifications.length && this.lastMoment.isBefore(newNotifications[i].updated_at)) {
					i++
				}
				if (i > 0) {
					const toAdd = this.filter(newNotifications.slice(0, i))
					this.notifications = toAdd.concat(this.notifications)
				}
			} else {
				// first time we don't check the date
				this.notifications = this.filter(newNotifications)
			}
		},
		filter(notifications) {
			if (this.widgetProjectsFilter.length > 0) {
				notifications = notifications.filter((n) => {
					return this.widgetProjectsFilter.includes(n.project.id)
				})
			}
			return notifications.filter((n) => {
				return n.action_name !== 'marked'
			})
		},
		getNotificationTarget(n) {
			return n.target_url
		},
		getUniqueKey(n) {
			return n.id + ':' + n.updated_at
		},
		getNotificationImage(n) {
			return (n.project && n.project.id && n.project.visibility !== 'private')
				? generateUrl(`/apps/integration_gitlab/gitlab/${this.config.widget_account_id}/avatar/project?`) + encodeURIComponent('projectId') + '=' + encodeURIComponent(n.project.id)
				: undefined
		},
		getRepositoryName(n) {
			return n.project.path
				? n.project.path
				: ''
		},
		getNotificationTypeImage(n) {
			if (n.target_type === 'MergeRequest') {
				return imagePath('integration_gitlab', 'merge_request.svg')
			} else if (n.target_type === 'Issue') {
				return imagePath('integration_gitlab', 'issues.svg')
			}
			return imagePath('integration_gitlab', 'sound-border.svg')
		},
		getNotificationActionChar(n) {
			if (['Issue', 'MergeRequest'].includes(n.target_type)) {
				if (['approval_required', 'assigned'].includes(n.action_name)) {
					return '👁'
				} else if (['directly_addressed', 'mentioned'].includes(n.action_name)) {
					return '🗨'
				} else if (n.action_name === 'marked') {
					return '✅'
				} else if (['build_failed', 'unmergeable'].includes(n.action_name)) {
					return '❎'
				}
			}
			return ''
		},
		getSubline(n) {
			return this.getNotificationActionChar(n) + ' ' + n.project.path_with_namespace + this.getTargetIdentifier(n)
		},
		getTargetTitle(n) {
			return n.target.title
		},
		getTargetIdentifier(n) {
			if (n.target_type === 'MergeRequest') {
				return '!' + n.target.iid
			} else if (n.target_type === 'Issue') {
				return '#' + n.target.iid
			}
			return ''
		},
	},
}
</script>

<style scoped lang="scss">
::v-deep .connect-button {
	margin-top: 10px;
}
</style>
