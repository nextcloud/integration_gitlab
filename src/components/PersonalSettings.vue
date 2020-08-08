<template>
	<div id="gitlab_prefs" class="section">
		<h2>
			<a class="icon icon-gitlab" />
			{{ t('gitlab', 'Gitlab') }}
		</h2>
		<p class="settings-hint">
			{{ t('gitlab', 'When you create an access token yourself, give it at least "read_user", "read_api" and "read_repository" permissions.') }}
		</p>
		<div class="gitlab-grid-form">
			<label for="gitlab-url">
				<a class="icon icon-link" />
				{{ t('gitlab', 'Gitlab instance address') }}
			</label>
			<input id="gitlab-url"
				v-model="state.url"
				type="text"
				:placeholder="t('gitlab', 'Gitlab instance address')"
				@input="onInput">
			<button v-if="showOAuth" id="gitlab-oauth" @click="onOAuthClick">
				<span class="icon icon-external" />
				{{ t('gitlab', 'Get access with OAuth') }}
			</button>
			<span v-else />
			<label for="gitlab-token">
				<a class="icon icon-category-auth" />
				{{ t('gitlab', 'Gitlab access token') }}
			</label>
			<input id="gitlab-token"
				v-model="state.token"
				type="password"
				:placeholder="t('gitlab', 'Get a token in Gitlab settings')"
				@input="onInput">
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
			state: loadState('gitlab', 'user-config'),
		}
	},

	computed: {
		showOAuth() {
			return (this.state.url === this.state.oauth_instance_url) && this.state.client_id && this.state.client_secret
		},
	},

	watch: {
	},

	mounted() {
		const paramString = window.location.search.substr(1)
		const urlParams = new URLSearchParams(paramString)
		const glToken = urlParams.get('gitlabToken')
		if (glToken === 'success') {
			showSuccess(t('gitlab', 'Gitlab OAuth access token successfully retrieved!'))
		} else if (glToken === 'error') {
			showError(t('gitlab', 'Gitlab OAuth access token could not be obtained:') + ' ' + urlParams.get('message'))
		}
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
					url: this.state.url,
				},
			}
			const url = generateUrl('/apps/gitlab/config')
			axios.put(url, req)
				.then((response) => {
					showSuccess(t('gitlab', 'Gitlab options saved.'))
					console.debug(response)
				})
				.catch((error) => {
					showError(
						t('gitlab', 'Failed to save Gitlab options')
						+ ': ' + error.response.request.responseText
					)
					console.debug(error)
				})
				.then(() => {
				})
		},
		onOAuthClick() {
			const redirectEndpoint = generateUrl('/apps/gitlab/oauth-redirect')
			const redirectUri = OC.getProtocol() + '://' + OC.getHostName() + redirectEndpoint
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
			const url = generateUrl('/apps/gitlab/config')
			axios.put(url, req)
				.then((response) => {
					window.location.replace(requestUrl)
				})
				.catch((error) => {
					showError(
						t('gitlab', 'Failed to save Gitlab OAuth state')
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
	max-width: 900px;
	display: grid;
	grid-template: 1fr / 1fr 1fr 1fr;
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
