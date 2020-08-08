<template>
    <div id="gitlab_prefs" class="section">
            <h2>
                <a class="icon icon-gitlab"></a>
                {{ t('gitlab', 'Gitlab') }}
            </h2>
            <p class="settings-hint">
                {{ t('gitlab', 'If you want to allow your Nextcloud users to use OAuth to authenticate to a Gitlab instance of your choice, create an application in your Gitlab settings and set the ID and secret here.') }}
                <br/>
                {{ t('gitlab', 'Make sure you set the "redirect_uri" to') }}
                <br/><b> {{ redirect_uri }} </b><br/>
                {{ t('gitlab', ' and give at least read_* permissions to the application.') }}
            </p>
            <div class="grid-form">
                <label for="gitlab-oauth-instance">
                    <a class="icon icon-link"></a>
                    {{ t('gitlab', 'OAuth app instance address') }}
                </label>
                <input id="gitlab-oauth-instance" type="text" v-model="state.oauth_instance_url" @input="onInput"
                    :placeholder="t('gitlab', 'Instance address')" />
                <label for="gitlab-client-id">
                    <a class="icon icon-category-auth"></a>
                    {{ t('gitlab', 'Application ID') }}
                </label>
                <input id="gitlab-client-id" type="password" v-model="state.client_id" @input="onInput"
                    :readonly="readonly"
                    @focus="readonly = false"
                    :placeholder="t('gitlab', 'ID or your Gitlab application')" />
                <label for="gitlab-client-secret">
                    <a class="icon icon-category-auth"></a>
                    {{ t('gitlab', 'Application secret') }}
                </label>
                <input id="gitlab-client-secret" type="password" v-model="state.client_secret" @input="onInput"
                    :readonly="readonly"
                    @focus="readonly = false"
                    :placeholder="t('gitlab', 'Client secret or your Gitlab application')" />
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
    name: 'AdminSettings',

    props: [],
    components: {
    },

    mounted() {
    },

    data() {
        return {
            state: loadState('gitlab', 'admin-config'),
            // to prevent some browsers to fill fields with remembered passwords
            readonly: true,
            redirect_uri: OC.getProtocol() + '://' + OC.getHostName() + generateUrl('/apps/gitlab/oauth-redirect')
        }
    },

    watch: {
    },

    methods: {
        onInput() {
            const that = this
            delay(function() {
                that.saveOptions()
            }, 2000)()
        },
        saveOptions() {
            const req = {
                values: {
                    client_id: this.state.client_id,
                    client_secret: this.state.client_secret,
                    oauth_instance_url: this.state.oauth_instance_url,
                }
            }
            const url = generateUrl('/apps/gitlab/admin-config')
            axios.put(url, req)
                .then(function (response) {
                    showSuccess(t('gitlab', 'Gitlab admin options saved.'))
                })
                .catch(function (error) {
                    showError(t('gitlab', 'Failed to save Gitlab admin options') +
                        ': ' + error.response.request.responseText
                    )
                })
                .then(function () {
                })
        },
    }
}
</script>

<style scoped lang="scss">
.grid-form label {
    line-height: 38px;
}
.grid-form input {
    width: 100%;
}
.grid-form {
    max-width: 500px;
    display: grid;
    grid-template: 1fr / 1fr 1fr;
    margin-left: 30px;
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