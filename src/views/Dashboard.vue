<template>
	<DashboardWidget :items="items"
		:showMoreUrl="showMoreUrl"
		:showMoreText="title"
		:loading="state === 'loading'"
		:itemMenu="itemMenu"
		@markDone="onMarkDone">
		<template v-slot:empty-content>
			<div v-if="state === 'no-token'">
				<a :href="settingsUrl">
					{{ t('gitlab', 'Click here to configure the access to your Gitlab account.') }}
				</a>
			</div>
			<div v-else-if="state === 'error'">
				<a :href="settingsUrl">
					{{ t('gitlab', 'Incorrect access token.') }}
					{{ t('gitlab', 'Click here to configure the access to your Gitlab account.') }}
				</a>
			</div>
			<div v-else-if="state === 'ok'">
				{{ t('gitlab', 'Nothing to show') }}
			</div>
		</template>
	</DashboardWidget>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { DashboardWidget } from '@nextcloud/vue-dashboard'
import { showError } from '@nextcloud/dialogs'
import moment from '@nextcloud/moment'

export default {
	name: 'Dashboard',

	components: {
		DashboardWidget,
	},

	props: {
		title: {
			type: String,
			required: true,
		}
	},

	data() {
		return {
			gitlabUrl: null,
			notifications: [],
			loop: null,
			state: 'loading',
			settingsUrl: generateUrl('/settings/user/linked-accounts'),
			themingColor: OCA.Theming ? OCA.Theming.color.replace('#', '') : '0082C9',
			itemMenu: {
				  markDone: {
					  text: t('gitlab', 'Mark as done'),
					  icon: 'icon-checkmark',
				  },
			  },
		}
	},

	computed: {
		showMoreUrl() {
			return this.gitlabUrl + '/dashboard/todos'
		},
		items() {
			return this.notifications.map((n) => {
				return {
					id: this.getUniqueKey(n),
					targetUrl: this.getNotificationTarget(n),
					avatarUrl: this.getNotificationImage(n),
					// avatarUsername: '',
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
	},

	beforeMount() {
		this.launchLoop()
	},

	mounted() {
	},

	methods: {
		async launchLoop() {
			// get gitlab URL first
			try {
				const response = await axios.get(generateUrl('/apps/gitlab/url'))
				this.gitlabUrl = response.data.replace(/\/+$/, '')
				if (this.gitlabUrl === '') {
					this.gitlabUrl = 'https://gitlab.com'
				}
			} catch (error) {
				console.debug(error)
			}
			// then launch the loop
			this.fetchNotifications()
			this.loop = setInterval(() => this.fetchNotifications(), 15000)
		},
		fetchNotifications() {
			const req = {}
			if (this.lastDate) {
				req.params = {
					since: this.lastDate,
				}
			}
			axios.get(generateUrl('/apps/gitlab/todos'), req).then((response) => {
				this.processNotifications(response.data)
				this.state = 'ok'
			}).catch((error) => {
				clearInterval(this.loop)
				if (error.response && error.response.status === 400) {
					this.state = 'no-token'
				} else if (error.response && error.response.status === 401) {
					showError(t('gitlab', 'Failed to get Gitlab notifications.'))
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
			return notifications
		},
		getNotificationTarget(n) {
			return n.target_url
		},
		getUniqueKey(n) {
			return n.id + ':' + n.updated_at
		},
		getNotificationImage(n) {
			return (n.project && n.project.avatar_url)
				? generateUrl('/apps/gitlab/avatar?') + encodeURIComponent('url') + '=' + encodeURIComponent(n.project.avatar_url)
				: ''
		},
		getAuthorFullName(n) {
			return n.author.name
				? (n.author.name + ' (@' + n.author.username + ')')
				: n.author.username
		},
		getAuthorAvatarUrl(n) {
			return (n.author && n.author.avatar_url)
				? generateUrl('/apps/gitlab/avatar?') + encodeURIComponent('url') + '=' + encodeURIComponent(n.author.avatar_url)
				: ''
		},
		getNotificationProjectName(n) {
			return n.project.path_with_namespace
		},
		getNotificationContent(n) {
			if (n.action_name === 'mentioned') {
				return t('gitlab', 'You were mentioned')
			} else if (n.action_name === 'approval_required') {
				return t('gitlab', 'Your approval is required')
			} else if (n.action_name === 'assigned') {
				return t('gitlab', 'You were assigned')
			} else if (n.action_name === 'build_failed') {
				return t('gitlab', 'A build has failed')
			} else if (n.action_name === 'marked') {
				return t('gitlab', 'Marked')
			} else if (n.action_name === 'directly_addressed') {
				return t('gitlab', 'You were directly addressed')
			}
			return ''
		},
		getNotificationTypeImage(n) {
			if (n.target_type === 'MergeRequest') {
				return generateUrl('/svg/gitlab/merge_request?color=ffffff')
			} else if (n.target_type === 'Issue') {
				return generateUrl('/svg/gitlab/issues?color=ffffff')
			}
			return generateUrl('/svg/core/actions/sound?color=' + this.themingColor)
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
			return this.getNotificationActionChar(n) + ' ' + n.project.path + this.getTargetIdentifier(n)
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
			const i = this.notifications.findIndex((n) => this.getUniqueKey(n) === item.id)
			if (i !== -1) {
				this.notifications.splice(i, 1)
			}
			// this.editNotification(item, 'mark-done')
		},
		editNotification(item, action) {
			axios.put(generateUrl('/apps/gitlab/todos/' + item.id + '/' + action)).then((response) => {
			}).catch((error) => {
				showError(t('gitlab', 'Failed to edit Gitlab todo.'))
				console.debug(error)
			})
		},
	},
}
</script>

<style scoped lang="scss">
</style>
