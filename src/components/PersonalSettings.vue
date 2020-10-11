<template>
	<div id="gitlab_prefs" class="section">
		<h2>
			<a class="icon icon-gitlab" />
			{{ t('integration_gitlab', 'GitLab integration') }}
		</h2>
		<p v-if="!showOAuth && !connected" class="settings-hint">
			{{ t('integration_gitlab', 'When you create an access token yourself, give it at least "api", "read_user", "read_api" and "read_repository" permissions.') }}
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
			<button v-if="showOAuth && !connected"
				id="gitlab-oauth"
				:disabled="loading === true"
				:class="{ loading }"
				@click="onOAuthClick">
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
			<br>
			<div v-if="connected" id="gitlab-search-block">
				<input
					id="search-gitlab"
					type="checkbox"
					class="checkbox"
					:checked="state.search_enabled"
					@input="onSearchChange">
				<label for="search-gitlab">{{ t('integration_gitlab', 'Enable searching for repositories') }}</label>
				<br><br>
				<input
					id="search-issues-gitlab"
					type="checkbox"
					class="checkbox"
					:checked="state.search_issues_enabled"
					@input="onSearchIssuesChange">
				<label for="search-issues-gitlab">{{ t('integration_gitlab', 'Enable searching for issues and merge requests') }}</label>
				<br><br>
				<p v-if="state.search_enabled || state.search_issues_enabled" class="settings-hint">
					<span class="icon icon-details" />
					{{ t('integration_gitlab', 'Warning, everything you type in the search bar will be sent to GitLab.') }}
				</p>
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
			loading: false,
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
			this.saveOptions({ token: '' })
		},
		onSearchChange(e) {
			this.state.search_enabled = e.target.checked
			this.saveOptions({ search_enabled: this.state.search_enabled ? '1' : '0' })
		},
		onSearchIssuesChange(e) {
			this.state.search_issues_enabled = e.target.checked
			this.saveOptions({ search_issues_enabled: this.state.search_issues_enabled ? '1' : '0' })
		},
		onInput() {
			this.loading = true
			if (this.state.url !== '' && !this.state.url.startsWith('https://')) {
				if (this.state.url.startsWith('http://')) {
					this.state.url = this.state.url.replace('http://', 'https://')
				} else {
					this.state.url = 'https://' + this.state.url
				}
			}
			delay(() => {
				this.saveOptions({ token: this.state.token, url: this.state.url })
			}, 2000)()
		},
		saveOptions(values) {
			const req = {
				values,
			}
			const url = generateUrl('/apps/integration_gitlab/config')
			axios.put(url, req)
				.then((response) => {
					if (response.data.user_name !== undefined) {
						this.state.user_name = response.data.user_name
						if (this.state.token && response.data.user_name === '') {
							showError(t('integration_gitlab', 'Incorrect access token'))
						} else if (response.data.user_name) {
							showSuccess(t('integration_gitlab', 'Successfully connected to GitLab!'))
						}
					} else {
						showSuccess(t('integration_gitlab', 'GitLab options saved'))
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
					this.loading = false
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
				+ '&scope=' + encodeURIComponent('api read_user read_api read_repository')

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
#gitlab-search-block .icon {
	width: 22px;
}
</style>
