<template>
    <div id="gitlab_prefs" class="section">
            <h2>
                <a class="icon icon-gitlab"></a>
                {{ t('gitlab', 'Gitlab') }}
            </h2>
            <div class="gitlab-grid-form">
                <label for="gitlab-url">
                    <a class="icon icon-link"></a>
                    {{ t('gitlab', 'Gitlab instance address') }}
                </label>
                <input id="gitlab-url" type="text" v-model="state.url" @input="onInput"
                    :placeholder="t('gitlab', 'Gitlab instance address')"/>
                <button id="gitlab-oauth" v-if="showOAuth" @click="onOAuthClick">
                    <span class="icon icon-external"/>
                    {{ t('gitlab', 'Get access with OAuth') }}
                </button>
                <span v-else></span>
                <label for="gitlab-token">
                    <a class="icon icon-category-auth"></a>
                    {{ t('gitlab', 'Gitlab access token') }}
                </label>
                <input id="gitlab-token" type="password" v-model="state.token" @input="onInput"
                    :placeholder="t('gitlab', 'Get a token in Gitlab settings')"/>
            </div>
    </div>
</template>

<script>
import { loadState } from '@nextcloud/initial-state'
import { generateUrl, imagePath } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { delay } from '../utils'
import { showSuccess, showError } from '@nextcloud/dialogs'

export default {
    name: 'PersonalSettings',

    props: [],
    components: {
    },

    mounted() {
        const paramString = window.location.search.substr(1)
        const urlParams = new URLSearchParams(paramString)
        const glToken = urlParams.get('gitlabToken')
        if (glToken === 'success') {
            showSuccess(t('gitlab', 'gitlab.com OAuth access token successfully retrieved!'))
        } else if (glToken === 'error') {
            showError(t('gitlab', 'gitlab.com OAuth access token could not be obtained:') + ' ' + urlParams.get('message'))
        }
    },

    data() {
        return {
            state: loadState('gitlab', 'user-config'),
        }
    },

    watch: {
    },

    computed: {
        showOAuth() {
            console.log(this.state.url +' '+ this.state.oauth_instance_url)
            return (this.state.url === this.state.oauth_instance_url) && this.state.client_id && this.state.client_secret
        },
    },

    methods: {
        onInput() {
            const that = this
            delay(function() {
                that.saveOptions()
            }, 2000)()
        },
        saveOptions() {
            if (this.state.url !== '' && !this.state.url.startsWith('https://')) {
                if (this.state.url.startsWith('http://')) {
                    this.state.url = this.state.url.replace('http://', 'https://')
                } else {
                    this.state.url = 'https://' + this.state.url
                }
            }
            const req = {
                values: {
                    token: this.state.token,
                    url: this.state.url
                }
            }
            const url = generateUrl('/apps/gitlab/config')
            axios.put(url, req)
                .then(function (response) {
                    showSuccess(t('gitlab', 'Gitlab options saved.'))
                })
                .catch(function (error) {
                    showError(t('gitlab', 'Failed to save Gitlab options') +
                        ': ' + error.response.request.responseText
                    )
                })
                .then(function () {
                })
        },
        onOAuthClick() {
            const redirect_endpoint = generateUrl('/apps/gitlab/oauth-redirect')
            const redirect_uri = OC.getProtocol() + '://' + OC.getHostName() + redirect_endpoint
            const oauth_state = Math.random().toString(36).substring(3)
            const request_url = this.state.url + '/oauth/authorize?client_id=' + encodeURIComponent(this.state.client_id) +
                '&redirect_uri=' + encodeURIComponent(redirect_uri) +
                '&response_type=code' +
                '&state=' + encodeURIComponent(oauth_state) +
                '&scope=' + encodeURIComponent('read_user read_api read_repository')

            const req = {
                values: {
                    oauth_state: oauth_state,
                }
            }
            const url = generateUrl('/apps/gitlab/config')
            axios.put(url, req)
                .then(function (response) {
                    window.location.replace(request_url)
                })
                .catch(function (error) {
                    showError(t('gitlab', 'Failed to save Gitlab OAuth state') +
                        ': ' + error.response.request.responseText
                    )
                })
                .then(function () {
                })
        }
    }
}
</script>

<style scoped lang="scss">
.gitlab-grid-form label {
    line-height: 38px;
}
.gitlab-grid-form input {
    width: 100%;
}
.gitlab-grid-form {
    width: 900px;
    display: grid;
    grid-template: 1fr / 233px 233px 300px;
    margin-left: 30px;
    button .icon {
        margin-bottom: -1px;
    }
}
#gitlab_prefs .icon {
    display: inline-block;
    width: 32px;
}
#gitlab_prefs .grid-form .icon {
    margin-bottom: -3px;
}
.icon-gitlab {
    background-image: url(./../../img/app-dark.svg);
    background-size: 23px 23px;
    height: 23px;
    margin-bottom: -4px;
}
body.dark .icon-gitlab {
    background-image: url(./../../img/app.svg);
}
</style>
