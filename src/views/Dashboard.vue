<template>
    <div>
        <ul v-if="state === 'ok'" class="notification-list">
            <li v-for="n in notifications"
                :key="getUniqueKey(n)"
                :title="getNotificationTitle(n)">
                <a :href="getNotificationTarget(n)" target="_blank" class="notification">
                    <Avatar
                        class="author-avatar"
                        :url="getNotificationImage(n)"
                        :tooltipMessage="getNotificationAuthorName(n)"
                        />
                    <div class="notification__details">
                        <h3>
                            <img class="notification-icon" :src="getNotificationTypeImage(n)"/>
                            {{ n.target_title }}
                        </h3>
                        <p class="message">
                            <span :class="'icon ' + getNotificationActionClass(n)"/>
                            {{ getNotificationContent(n) }}
                        </p>
                    </div>
                </a>
            </li>
        </ul>
        <div v-else-if="state === 'no-token'">
            <a :href="settingsUrl">
                {{ t('gitlab', 'Click here to configure the access to your Gitlab account.')}}
            </a>
        </div>
        <div v-else-if="state === 'error'">
            <a :href="settingsUrl">
                {{ t('gitlab', 'Incorrect access token.') }}
                {{ t('gitlab', 'Click here to configure the access to your Gitlab account.')}}
            </a>
        </div>
        <div v-else-if="state === 'loading'" class="icon-loading-small"></div>
    </div>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateUrl, imagePath } from '@nextcloud/router'
import { Avatar } from '@nextcloud/vue'
import { showSuccess, showError } from '@nextcloud/dialogs'
import moment from '@nextcloud/moment'

export default {
    name: 'Dashboard',

    props: [],
    components: {
        Avatar
    },

    beforeMount() {
        this.launchLoop()
    },

    mounted() {
    },

    data() {
        return {
            gitlabUrl: null,
            notifications: [],
            loop: null,
            state: 'loading',
            settingsUrl: generateUrl('/settings/user/linked-accounts'),
            themingColor: OCA.Theming ? OCA.Theming.color.replace('#', '') : '0082C9'
        }
    },

    computed: {
        lastDate() {
            const nbNotif = this.notifications.length
            return (nbNotif > 0) ? this.notifications[0].created_at : null
        },
        lastMoment() {
            return moment(this.lastDate)
        }
    },

    methods: {
        async launchLoop() {
            // get gitlab URL first
            try {
                const response = await axios.get(generateUrl('/apps/gitlab/url'))
                this.gitlabUrl = response.data.replace(/\/+$/, '')
            } catch (error) {
                console.log(error)
            }
            // then launch the loop
            this.fetchNotifications()
            this.loop = setInterval(() => this.fetchNotifications(), 15000)
        },
        fetchNotifications() {
            const req = {}
            if (this.lastDate) {
                req.params = {
                    since: this.lastDate
                }
            }
            axios.get(generateUrl('/apps/gitlab/notifications'), req).then((response) => {
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
                    console.log(error)
                }
            })
        },
        processNotifications(newNotifications) {
            if (this.lastDate) {
                // just add those which are more recent than our most recent one
                let i = 0
                while (i < newNotifications.length && this.lastMoment.isBefore(newNotifications[i].created_at)) {
                    i++
                }
                if (i > 0) {
                    const toAdd = this.filter(newNotifications.slice(0, i+1))
                    this.notifications = toAdd.concat(this.notifications)
                }
            } else {
                // first time we don't check the date
                this.notifications = this.filter(newNotifications)
            }
        },
        filter(notifications) {
            return notifications;
            // only keep the unread ones with specific reasons
            return notifications.filter((n) => {
                return (['Issue'].includes(n.target_type))
            })
        },
        getNotificationTarget(n) {
            const path = n.project_path
            if (path === null) {
                // the API does not find all projects with /projects?membership=true
                console.error('bad path for project ' + n.project_id)
                return this.gitlabUrl
            } else if (n.target_type === 'MergeRequest') {
                return this.gitlabUrl + '/' + path + '/-/merge_requests/' + n.target_iid
            } else if (n.target_type === 'Issue') {
                return this.gitlabUrl + '/' + path + '/-/issues/' + n.target_iid
            } else if (n.target_type === 'Note') {
                if (n.note.noteable_type === 'Issue') {
                    return this.gitlabUrl + '/' + path + '/-/issues/' + n.note.noteable_iid
                } else if (n.note.noteable_type === 'MergeRequest') {
                    return this.gitlabUrl + '/' + path + '/-/merge_requests/' + n.note.noteable_iid
                } else {
                    console.error('note on unknown noteable type')
                    return this.gitlabUrl
                }
            } else {
                console.error('unknown target type')
                return this.gitlabUrl
            }
        },
        getUniqueKey(n) {
            return n.project_id + ':' + n.target_type + ':' + n.target_id + ':' + n.created_at
        },
        getNotificationImage(n) {
            return generateUrl('/apps/gitlab/avatar?') + encodeURIComponent('url') + '=' + encodeURIComponent(n.author.avatar_url)
        },
        getNotificationAuthorName(n) {
            return n.author.name
        },
        getNotificationContent(n) {
            if (n.target_type === 'Note') {
                return n.note.body
            } else if (n.target_type === 'Issue') {
                if (n.action_name === 'closed') {
                    return t('gitlab', 'Issue was closed')
                } else if (n.action_name === 'opened') {
                    return t('gitlab', 'Issue was created')
                }
            } else if (n.target_type === 'MergeRequest') {
                if (n.action_name === 'closed') {
                    return t('gitlab', 'Merge request was closed')
                } else if (n.action_name === 'opened') {
                    return t('gitlab', 'Merge request was created')
                } else if (n.action_name === 'accepted') {
                    return t('gitlab', 'Merge request was accepted')
                }
            }
            return ''
        },
        getNotificationTypeImage(n) {
            if (n.target_type === 'MergeRequest') {
                return generateUrl('/svg/gitlab/merge_request?color=' + this.themingColor)
            } else if (n.target_type === 'Issue') {
                return generateUrl('/svg/gitlab/issues?color=' + this.themingColor)
            } else if (n.target_type === 'Note') {
                if (n.note.noteable_type === 'Issue') {
                    return generateUrl('/svg/gitlab/issues?color=' + this.themingColor)
                } else if (n.note.noteable_type === 'MergeRequest') {
                    return generateUrl('/svg/gitlab/merge_request?color=' + this.themingColor)
                }
            }
            return generateUrl('/svg/core/actions/sound?color=' + this.themingColor)
        },
        getNotificationActionClass(n) {
            if (n.target_type === 'Note') {
                return 'icon-comment'
            } else if (['Issue', 'MergeRequest'].includes(n.target_type)) {
                if (n.action_name === 'closed') {
                    return 'icon-close'
                } else if (n.action_name === 'opened') {
                    return 'icon-add'
                } else if (n.action_name === 'accepted') {
                    return 'icon-checkmark-color'
                }
            }
            return ''
        },
        getNotificationTitle(n) {
            return '[' + n.target_title + ']\n\n' + this.getNotificationContent(n);
        },
    },
}
</script>

<style scoped lang="scss">
.notification-icon {
    width: 16px;
    height: 12px;
}
li .notification {
    display: flex;
    align-items: flex-start;
    padding: 8px;

    &:hover,
    &:focus {
        background-color: var(--color-background-hover);
        border-radius: var(--border-radius-large);
    }
    .author-avatar {
        position: relative;
        margin-top: auto;
        margin-bottom: auto;
    }
    .notification__details {
        padding-left: 8px;
        max-height: 44px;
        flex-grow: 1;
        overflow: hidden;
        h3,
        .message {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .message span {
            width: 10px;
            display: inline-block;
            margin-bottom: -3px;
        }
        h3 {
            font-size: 100%;
            margin: 0;
        }
        .message {
            width: 100%;
            color: var(--color-text-maxcontrast);
        }
    }
    button.primary {
        padding: 21px;
        margin: 0;
    }
}
</style>