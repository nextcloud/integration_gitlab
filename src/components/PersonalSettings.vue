<template>
	<div id="gitlab_prefs" class="section">
		<h2>
			<GitlabIcon class="icon" />
			{{ t('integration_gitlab', 'GitLab integration') }}
		</h2>
		<p v-if="!showOAuth && !connected" class="settings-hint">
			{{ t('integration_gitlab', 'When you create an access token yourself, give it at least "read_user", "read_api" and "read_repository" permissions. Optionally "api" instead.') }}
		</p>
		<div id="gitlab-content">
			<div class="line">
				<label for="gitlab-url">
					<EarthIcon :size="20" class="icon" />
					{{ t('integration_gitlab', 'GitLab instance address') }}
				</label>
				<input id="gitlab-url"
					v-model="state.url"
					type="text"
					:disabled="connected === true"
					:placeholder="t('integration_gitlab', 'GitLab instance address')"
					@input="onInput">
			</div>
			<div v-show="!showOAuth" class="line">
				<label
					for="gitlab-token">
					<KeyIcon :size="20" class="icon" />
					{{ t('integration_gitlab', 'Personal access token') }}
				</label>
				<input
					id="gitlab-token"
					v-model="state.token"
					type="password"
					:disabled="connected === true"
					:placeholder="t('integration_gitlab', 'GitLab personal access token')"
					@keyup.enter="onConnectClick">
			</div>
			<NcButton v-if="!connected"
				id="gitlab-oauth"
				:disabled="loading === true || (!showOAuth && !state.token)"
				:class="{ loading }"
				@click="onConnectClick">
				<template #icon>
					<OpenInNewIcon :size="20" />
				</template>
				{{ t('integration_gitlab', 'Connect to GitLab') }}
			</NcButton>
			<div v-if="connected" class="line">
				<label class="gitlab-connected">
					<CheckIcon :size="20" class="icon" />
					{{ t('integration_gitlab', 'Connected as {user}', { user: connectedAs }) }}
				</label>
				<NcButton @click="onLogoutClick">
					<template #icon>
						<CloseIcon :size="20" />
					</template>
					{{ t('integration_gitlab', 'Disconnect from GitLab') }}
				</NcButton>
				<span />
			</div>
			<br>
			<div v-if="connected" id="gitlab-search-block">
				<NcCheckboxRadioSwitch
					:checked="state.search_enabled"
					@update:checked="onCheckboxChanged($event, 'search_enabled')">
					{{ t('integration_gitlab', 'Enable searching for repositories') }}
				</NcCheckboxRadioSwitch>
				<NcCheckboxRadioSwitch
					:checked="state.search_issues_enabled"
					@update:checked="onCheckboxChanged($event, 'search_issues_enabled')">
					{{ t('integration_gitlab', 'Enable searching for issues and merge requests') }}
					{{ t('integration_gitlab', '(This may be slow or even fail on some GitLab instances)') }}
				</NcCheckboxRadioSwitch>
				<br>
				<p v-if="state.search_enabled || state.search_issues_enabled" class="settings-hint">
					<InformationOutlineIcon :size="20" class="icon" />
					{{ t('integration_gitlab', 'Warning, everything you type in the search bar will be sent to GitLab.') }}
				</p>
			</div>
			<NcCheckboxRadioSwitch
				:checked="state.navigation_enabled"
				@update:checked="onCheckboxChanged($event, 'navigation_enabled')">
				{{ t('integration_gitlab', 'Enable navigation link') }}
			</NcCheckboxRadioSwitch>
			<NcCheckboxRadioSwitch
				:checked="state.link_preview_enabled"
				@update:checked="onCheckboxChanged($event, 'link_preview_enabled')">
				{{ t('integration_gitlab', 'Enable GitLab link previews') }}
			</NcCheckboxRadioSwitch>
		</div>
	</div>
</template>

<script>
import InformationOutlineIcon from 'vue-material-design-icons/InformationOutline.vue'
import KeyIcon from 'vue-material-design-icons/Key.vue'
import OpenInNewIcon from 'vue-material-design-icons/OpenInNew.vue'
import CheckIcon from 'vue-material-design-icons/Check.vue'
import CloseIcon from 'vue-material-design-icons/Close.vue'
import EarthIcon from 'vue-material-design-icons/Earth.vue'

import GitlabIcon from './icons/GitlabIcon.vue'

import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { delay, oauthConnect } from '../utils.js'
import { showSuccess, showError } from '@nextcloud/dialogs'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'

export default {
	name: 'PersonalSettings',

	components: {
		GitlabIcon,
		NcCheckboxRadioSwitch,
		NcButton,
		OpenInNewIcon,
		EarthIcon,
		CheckIcon,
		CloseIcon,
		KeyIcon,
		InformationOutlineIcon,
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
			return !!this.state.token
				&& !!this.state.url
				&& !!this.state.user_name
		},
		connectedAs() {
			return this.state.user_displayname
				? this.state.user_displayname + ' (@' + this.state.user_name + ')'
				: '@' + this.state.user_name
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
		onCheckboxChanged(newValue, key) {
			this.state[key] = newValue
			this.saveOptions({ [key]: this.state[key] ? '1' : '0' })
		},
		onInput() {
			this.loading = true
			delay(() => {
				this.saveOptions({ url: this.state.url })
			}, 2000)()
		},
		saveOptions(values) {
			const req = {
				values,
			}
			const url = generateUrl('/apps/integration_gitlab/config')
			axios.put(url, req).then((response) => {
				if (response.data.user_name !== undefined) {
					this.state.user_name = response.data.user_name
					this.state.user_displayname = response.data.user_displayname
					if (this.state.token && response.data.user_name === '') {
						showError(t('integration_gitlab', 'Incorrect access token'))
					} else if (response.data.user_name) {
						showSuccess(t('integration_gitlab', 'Successfully connected to GitLab!'))
					}
				} else {
					showSuccess(t('integration_gitlab', 'GitLab options saved'))
				}
			}).catch((error) => {
				showError(
					t('integration_gitlab', 'Failed to save GitLab options')
					+ ': ' + (error.response?.data?.error ?? '')
				)
				console.debug(error)
			}).then(() => {
				this.loading = false
			})
		},
		onConnectClick() {
			if (this.showOAuth) {
				this.connectWithOauth()
			} else {
				this.connectWithToken()
			}
		},
		connectWithToken() {
			this.loading = true
			this.saveOptions({
				token: this.state.token,
				url: this.state.url,
			})
		},
		connectWithOauth() {
			if (this.state.use_popup) {
				oauthConnect(this.state.url, this.state.client_id, null, true)
					.then((data) => {
						this.state.token = 'dummyToken'
						this.state.user_name = data.userName
						this.state.user_displayname = data.userDisplayName
					})
			} else {
				oauthConnect(this.state.url, this.state.client_id, 'settings')
			}
		},
	},
}
</script>

<style scoped lang="scss">
#gitlab_prefs {
	#gitlab-content {
		margin-left: 40px;
	}
	h2,
	.line,
	.settings-hint {
		display: flex;
		align-items: center;
		.icon {
			margin-right: 4px;
		}
	}

	h2 .icon {
		margin-right: 8px;
	}

	.line {
		> label {
			width: 300px;
			display: flex;
			align-items: center;
		}
		> input {
			width: 300px;
		}
	}
}
</style>
