<template>
    <div id="gitlab_prefs" class="section">
            <h2>
                <a class="icon icon-gitlab" :style="{'background-image': 'url(' + iconUrl + ')'}"></a>
                {{ t('gitlab', 'Gitlab') }}
            </h2>
            <div class="grid-form">
                <label for="gitlab-url">
                    <a class="icon icon-link"></a>
                    {{ t('gitlab', 'Gitlab instance address') }}
                </label>
                <input id="gitlab-url" type="text" v-model="state.url" @input="onInput"
                    :placeholder="t('gitlab', 'https://gitlab.com')"/>
                <label for="gitlab-token">
                    <a class="icon icon-category-auth"></a>
                    {{ t('gitlab', 'Gitlab access token') }}
                </label>
                <input id="gitlab-token" type="text" v-model="state.token" @input="onInput"
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
    name: 'Settings',

    props: [],
    components: {
    },

    mounted() {
    },

    data() {
        return {
            state: loadState('gitlab', 'user-config'),
            iconUrl: imagePath('gitlab', 'app.svg')
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
