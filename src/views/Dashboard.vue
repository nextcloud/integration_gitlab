<template>
	<DashboardWidget :items="items"
		:show-more-url="showMoreUrl"
		:show-more-text="title"
		:loading="state === 'loading'"
		:item-menu="itemMenu"
		@markDone="onMarkDone">
		<template #empty-content>
			<EmptyContent
				v-if="emptyContentMessage">
				<template #icon>
					<component :is="emptyContentIcon" />
				</template>
				<template #desc>
					{{ emptyContentMessage }}
					<div v-if="state === 'no-token' || state === 'error'" class="connect-button">
						<a v-if="!initialState.oauth_is_possible"
							:href="settingsUrl">
							<NcButton>
								<template #icon>
									<LoginVariantIcon />
								</template>
								{{ t('integration_gitlab', 'Connect to GitLab') }}
							</NcButton>
						</a>
						<NcButton v-else
							@click="onOauthClick">
							<template #icon>
								<LoginVariantIcon />
							</template>
							{{ t('integration_gitlab', 'Connect to {url}', { url: gitlabUrl }) }}
						</NcButton>
					</div>
				</template>
			</EmptyContent>
		</template>
	</DashboardWidget>
</template>

<script>
import LoginVariantIcon from 'vue-material-design-icons/LoginVariant.vue'
import CheckIcon from 'vue-material-design-icons/Check.vue'
import CloseIcon from 'vue-material-design-icons/Close.vue'

import GitlabIcon from '../components/icons/GitlabIcon.vue'

import axios from '@nextcloud/axios'
import { generateUrl, imagePath } from '@nextcloud/router'
import { DashboardWidget } from '@nextcloud/vue-dashboard'
import { showError } from '@nextcloud/dialogs'
import { loadState } from '@nextcloud/initial-state'
import moment from '@nextcloud/moment'

import EmptyContent from '@nextcloud/vue/dist/Components/EmptyContent.js'
import NcButton from '@nextcloud/vue/dist/Components/Button.js'

import { oauthConnect, oauthConnectConfirmDialog } from '../utils.js'

export default {
	name: 'Dashboard',

	components: {
		DashboardWidget,
		EmptyContent,
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
			themingColor: OCA.Theming ? OCA.Theming.color.replace('#', '') : '0082C9',
			itemMenu: {
				markDone: {
					text: t('integration_gitlab', 'Mark as done'),
					icon: 'icon-checkmark',
				},
			},
			initialState: loadState('integration_gitlab', 'user-config'),
			windowVisibility: true,
		}
	},

	computed: {
		gitlabUrl() {
			return this.initialState?.url?.replace(/\/+$/, '')
		},
		showMoreUrl() {
			return this.gitlabUrl + '/dashboard/todos'
		},
		items() {
			return this.notifications.map((n) => {
				return {
					id: this.getUniqueKey(n),
					targetUrl: this.getNotificationTarget(n),
					avatarUrl: this.getNotificationImage(n),
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
			if (this.state === 'no-token') {
				return t('integration_gitlab', 'No GitLab account connected')
			} else if (this.state === 'error') {
				return t('integration_gitlab', 'Error connecting to GitLab')
			} else if (this.state === 'ok') {
				return t('integration_gitlab', 'No GitLab notifications!')
			}
			return ''
		},
		emptyContentIcon() {
			if (this.state === 'no-token') {
				return GitlabIcon
			} else if (this.state === 'error') {
				return CloseIcon
			} else if (this.state === 'ok') {
				return CheckIcon
			}
			return CheckIcon
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

	beforeDestroy() {
		document.removeEventListener('visibilitychange', this.changeWindowVisibility)
	},

	beforeMount() {
		this.launchLoop()
		document.addEventListener('visibilitychange', this.changeWindowVisibility)
	},

	mounted() {
	},

	methods: {
		onOauthClick() {
			oauthConnectConfirmDialog(this.gitlabUrl).then((result) => {
				if (result) {
					if (this.initialState.use_popup) {
						this.state = 'loading'
						oauthConnect(this.gitlabUrl, this.initialState.client_id, null, true)
							.then((data) => {
								this.stopLoop()
								this.launchLoop()
							})
					} else {
						oauthConnect(this.gitlabUrl, this.initialState.client_id, 'dashboard')
					}
				}
			})
		},
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
			const req = {}
			if (this.lastDate) {
				req.params = {
					since: this.lastDate,
				}
			}
			axios.get(generateUrl('/apps/integration_gitlab/todos'), req).then((response) => {
				this.processNotifications(response.data)
				this.state = 'ok'
			}).catch((error) => {
				clearInterval(this.loop)
				if (error.response && error.response.status === 400) {
					this.state = 'no-token'
				} else if (error.response && error.response.status === 401) {
					showError(t('integration_gitlab', 'Failed to get GitLab notifications'))
					this.state = 'error'
				} else {
					// there was an error in notif processing
					console.debug(error)
				}
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
				? generateUrl('/apps/integration_gitlab/avatar/project?') + encodeURIComponent('projectId') + '=' + encodeURIComponent(n.project.id)
				: undefined
		},
		getAuthorFullName(n) {
			return n.author.name
				? (n.author.name + ' (@' + n.author.username + ')')
				: n.author.username
		},
		getAuthorAvatarUrl(n) {
			return (n.author && n.author.id)
				? generateUrl('/apps/integration_gitlab/avatar/user?') + encodeURIComponent('userId') + '=' + encodeURIComponent(n.author.id)
				: ''
		},
		getRepositoryName(n) {
			return n.project.path
				? n.project.path
				: ''
		},
		getNotificationProjectName(n) {
			return n.project.path_with_namespace
		},
		getNotificationContent(n) {
			if (n.action_name === 'mentioned') {
				return t('integration_gitlab', 'You were mentioned')
			} else if (n.action_name === 'approval_required') {
				return t('integration_gitlab', 'Your approval is required')
			} else if (n.action_name === 'assigned') {
				return t('integration_gitlab', 'You were assigned')
			} else if (n.action_name === 'build_failed') {
				return t('integration_gitlab', 'A build has failed')
			} else if (n.action_name === 'marked') {
				return t('integration_gitlab', 'Marked')
			} else if (n.action_name === 'directly_addressed') {
				return t('integration_gitlab', 'You were directly addressed')
			}
			return ''
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
		getTargetContent(n) {
			return n.body
		},
		getTargetTitle(n) {
			return n.target.title
		},
		getProjectPath(n) {
			return n.project.path_with_namespace
		},
		getTargetIdentifier(n) {
			if (n.target_type === 'MergeRequest') {
				return '!' + n.target.iid
			} else if (n.target_type === 'Issue') {
				return '#' + n.target.iid
			}
			return ''
		},
		getFormattedDate(n) {
			return moment(n.updated_at).format('LLL')
		},
		onMarkDone(item) {
			// TODO adapt vue-dashboard to give ID in item and use following line
			// const i = this.notifications.findIndex((n) => this.getUniqueKey(n) === item.id)
			const i = this.notifications.findIndex((n) => n.target_url === item.targetUrl)
			if (i !== -1) {
				const id = this.notifications[i].id
				this.notifications.splice(i, 1)
				this.editTodo(id, 'mark-done')
			}
		},
		editTodo(id, action) {
			axios.put(generateUrl('/apps/integration_gitlab/todos/' + id + '/' + action)).then((response) => {
			}).catch((error) => {
				showError(t('integration_gitlab', 'Failed to edit GitLab To-Do'))
				console.debug(error)
			})
		},
	},
}
</script>

<style scoped lang="scss">
::v-deep .connect-button {
	margin-top: 10px;
}
</style>
