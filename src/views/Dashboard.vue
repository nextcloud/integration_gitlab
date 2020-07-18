<template>
    <div>
        <ul v-if="state === 'ok'" class="notification-list">
            <li v-for="n in notifications"
                :key="n.project_id + ':' + n.target_type + ':' + n.target_id"
                :title="n.target_type">
                <a :href="getNotificationTarget(n)" target="_blank">{{ n.target_type }} {{ n.author.name }} {{ n.target_title }}</a>
                - {{ n.created_at }}
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
import { generateUrl } from '@nextcloud/router'
import { showSuccess, showError } from '@nextcloud/dialogs'
import moment from '@nextcloud/moment'

export default {
    name: 'Dashboard',

    props: [],
    components: {
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
            settingsUrl: generateUrl('/settings/user/linked-accounts')
        }
    },

    computed: {
        lastDate() {
            const nbNotif = this.notifications.length
            return (nbNotif > 0) ? this.getDay(this.notifications[0].created_at) : null
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
                this.gitlabUrl = response.data
            } catch (error) {
                console.log(error)
            }
            // then launch the loop
            this.fetchNotifications()
            this.loop = setInterval(() => this.fetchNotifications(), 5000)
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
            // for merge requests : "target_type": "MergeRequest" | "target_iid": 15,
            // for issue comments : target_type	"Note"  | "noteable_iid": 213
            return this.gitlabUrl + '/' + n.target_type
        },
        getDay(dateString) {
            return dateString.slice(0, 10)
        }
    },
}
</script>

<style scoped lang="scss">
.notification-list {
    list-style-type: disc;
}
</style>