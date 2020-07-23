<template>
    <div id="gitlab_prefs" class="section">
            <h2>
                <a class="icon icon-gitlab" :style="{'background-image': 'url(' + iconUrl + ')'}"></a>
                {{ t('gitlab', 'Gitlab') }}
            </h2>
            <div class="grid-form">
                <label for="gitlab-client-id">
                    <a class="icon icon-category-auth"></a>
                    {{ t('gitlab', 'Gitlab client ID') }}
                </label>
                <input id="gitlab-client-id" type="password" v-model="state.client_id" @input="onInput"
                    :readonly="readonly"
                    @focus="readonly = false"
                    :placeholder="t('gitlab', 'Client ID or your gitlab.com application')" />
                <label for="gitlab-client-secret">
                    <a class="icon icon-category-auth"></a>
                    {{ t('gitlab', 'Gitlab client secret') }}
                </label>
                <input id="gitlab-client-secret" type="password" v-model="state.client_secret" @input="onInput"
                    :readonly="readonly"
                    @focus="readonly = false"
                    :placeholder="t('gitlab', 'Client secret or your gitlab.com application')" />
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
            iconUrl: imagePath('gitlab', 'app.svg'),
            // to prevent some browsers to fill fields with remembered passwords
            readonly: true,
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
    width: 500px;
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
    mix-blend-mode: difference;
    background-size: 23px 23px;
    height: 23px;
    margin-bottom: -4px;
}
</style>