<template>
	<div id="gitlab_prefs" class="section">
		<h2>
			<a class="icon icon-gitlab" />
			{{ t('integration_gitlab', 'GitLab integration') }}
		</h2>
		<p v-if="!showOAuth && !connected" class="settings-hint">
			{{ t('integration_gitlab', 'When you create an access token yourself, give it at least "read_user", "read_api" and "read_repository" permissions.') }}
		</p>
		<div id="gitlab-content">
			<div class="gitlab-grid-form">
				<label for="gitlab-url">
					<a class="icon icon-link" />
					{{ t('integration_gitlab', 'GitLab instance address') }}
				</label>
				<input id="gitlab-url"
					v-model="state.url"
					type="text"
					:disabled="connected === true"
					:placeholder="t('integration_gitlab', 'GitLab instance address')"
					@input="onInput">
				<label v-show="!showOAuth"
					for="gitlab-token">
					<a class="icon icon-category-auth" />
					{{ t('integration_gitlab', 'Personal access token') }}
				</label>
				<input v-show="!showOAuth"
					id="gitlab-token"
					v-model="state.token"
					type="password"
					:disabled="connected === true"
					:placeholder="t('integration_gitlab', 'GitLab personal access token')"
					@input="onInput">
			</div>
			<button v-if="showOAuth && !connected" id="gitlab-oauth" @click="onOAuthClick">
				<span class="icon icon-external" />
				{{ t('integration_gitlab', 'Connect to GitLab') }}
			</button>
			<div v-if="connected" class="gitlab-grid-form">
				<label class="gitlab-connected">
					<a class="icon icon-checkmark-color" />
					{{ t('integration_gitlab', 'Connected as {user}', { user: state.user_name }) }}
				</label>
				<button id="gitlab-rm-cred" @click="onLogoutClick">
					<span class="icon icon-close" />
					{{ t('integration_gitlab', 'Disconnect from GitLab') }}
				</button>
				<span />
			</div>
		</div>
	</div>
</template>

<script>
import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { delay } from '../utils'
import { showSuccess, showError } from '@nextcloud/dialogs'

export default {
	name: 'PersonalSettings',

	components: {
	},

	props: [],

	data() {
		return {
			state: loadState('integration_gitlab', 'user-config'),
		}
	},

	computed: {
		showOAuth() {
			return (this.state.url === this.state.oauth_instance_url) && this.state.client_id && this.state.client_secret
		},
		connected() {
			return this.state.token && this.state.token !== ''
				&& this.state.url && this.state.url !== ''
				&& this.state.user_name && this.state.user_name !== ''
		},
	},

	watch: {
	},

	mounted() {
		const paramString = window.location.search.substr(1)
		// eslint-disable-next-line
		const urlParams = new URLSearchParams(paramString)
		const glToken = urlParams.get('gitlabToken')
		if (glToken === 'success') {
			showSuccess(t('integration_gitlab', 'Successfully connected to GitLab!'))
		} else if (glToken === 'error') {
			showError(t('integration_gitlab', 'Error connecting to GitLab:') + ' ' + urlParams.get('message'))
		}
	},

	methods: {
		onLogoutClick() {
			this.state.token = ''
			this.saveOptions()
		},
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
					url: this.state.url,
				},
			}
			const url = generateUrl('/apps/integration_gitlab/config')
			axios.put(url, req)
				.then((response) => {
					showSuccess(t('integration_gitlab', 'GitLab options saved.'))
					console.debug(response)
					if (response.data.user_name !== undefined) {
						this.state.user_name = response.data.user_name
						if (this.state.token && response.data.user_name === '') {
							showError(t('integration_gitlab', 'Incorrect access token'))
						}
					}
				})
				.catch((error) => {
					showError(
						t('integration_gitlab', 'Failed to save GitLab options')
						+ ': ' + error.response.request.responseText
					)
					console.debug(error)
				})
				.then(() => {
				})
		},
		onOAuthClick() {
			const redirectEndpoint = generateUrl('/apps/integration_gitlab/oauth-redirect')
			const redirectUri = window.location.protocol + '//' + window.location.host + redirectEndpoint
			const oauthState = Math.random().toString(36).substring(3)
			const requestUrl = this.state.url + '/oauth/authorize?client_id=' + encodeURIComponent(this.state.client_id)
				+ '&redirect_uri=' + encodeURIComponent(redirectUri)
				+ '&response_type=code'
				+ '&state=' + encodeURIComponent(oauthState)
				+ '&scope=' + encodeURIComponent('read_user read_api read_repository')

			const req = {
				values: {
					oauth_state: oauthState,
				},
			}
			const url = generateUrl('/apps/integration_gitlab/config')
			axios.put(url, req)
				.then((response) => {
					window.location.replace(requestUrl)
				})
				.catch((error) => {
					showError(
						t('integration_gitlab', 'Failed to save GitLab OAuth state')
						+ ': ' + error.response.request.responseText
					)
					console.debug(error)
				})
				.then(() => {
				})
		},
	},
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
	max-width: 600px;
	display: grid;
	grid-template: 1fr / 1fr 1fr;
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
body.theme--dark .icon-gitlab {
	background-image: url(./../../img/app.svg);
}
#gitlab-content {
	margin-left: 40px;
}
</style>
