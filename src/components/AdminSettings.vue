<template>
	<div id="gitlab_prefs" class="section">
		<h2>
			<GitlabIcon class="icon" />
			{{ t('integration_gitlab', 'GitLab integration') }}
		</h2>
		<p class="settings-hint">
			{{ t('integration_gitlab', 'If you want to allow your Nextcloud users to use OAuth to authenticate to a GitLab instance of your choice, create an application in your GitLab settings and set the ID and secret here.') }}
		</p>
		<p class="settings-hint">
			<InformationOutlineIcon :size="20" class="icon" />
			{{ t('integration_gitlab', 'Make sure you set the "Redirect URI" to:') }}
		</p>
		<p class="settings-hint">
			<strong>{{ redirect_uri }}</strong>
		</p>
		<p class="settings-hint">
			{{ t('integration_gitlab', 'Give "read_user", "read_api" and "read_repository" permissions to the application. Optionally "api" instead.') }}
		</p>
		<p class="settings-hint">
			{{ t('integration_gitlab', 'Put the "Application ID" and "Application secret" below. Your Nextcloud users will then see a "Connect to GitLab" button in their personal settings if they select the GitLab instance defined here.') }}
		</p>
		<div id="gitlab-content">
			<div class="line">
				<label for="gitlab-oauth-instance">
					<EarthIcon :size="20" class="icon" />
					{{ t('integration_gitlab', 'OAuth app instance address') }}
				</label>
				<input id="gitlab-oauth-instance"
					v-model="state.oauth_instance_url"
					type="text"
					:placeholder="t('integration_gitlab', 'Instance address')"
					@input="onInput">
			</div>
			<div class="line">
				<label for="gitlab-client-id">
					<KeyIcon :size="20" class="icon" />
					{{ t('integration_gitlab', 'Application ID') }}
				</label>
				<input id="gitlab-client-id"
					v-model="state.client_id"
					type="password"
					:readonly="readonly"
					:placeholder="t('integration_gitlab', 'ID of your GitLab application')"
					@input="onInput"
					@focus="readonly = false">
			</div>
			<div class="line">
				<label for="gitlab-client-secret">
					<KeyIcon :size="20" class="icon" />
					{{ t('integration_gitlab', 'Application secret') }}
				</label>
				<input id="gitlab-client-secret"
					v-model="state.client_secret"
					type="password"
					:readonly="readonly"
					:placeholder="t('integration_gitlab', 'Client secret of your GitLab application')"
					@focus="readonly = false"
					@input="onInput">
			</div>
			<NcCheckboxRadioSwitch
				:checked="state.use_popup"
				@update:checked="onCheckboxChanged($event, 'use_popup')">
				{{ t('integration_gitlab', 'Use a popup to authenticate') }}
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
import EarthIcon from 'vue-material-design-icons/Earth.vue'

import GitlabIcon from './icons/GitlabIcon.vue'

import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { delay } from '../utils.js'
import { showSuccess, showError } from '@nextcloud/dialogs'

import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'

export default {
	name: 'AdminSettings',

	components: {
		NcCheckboxRadioSwitch,
		GitlabIcon,
		KeyIcon,
		EarthIcon,
		InformationOutlineIcon,
	},

	props: [],

	data() {
		return {
			state: loadState('integration_gitlab', 'admin-config'),
			// to prevent some browsers to fill fields with remembered passwords
			readonly: true,
			redirect_uri: window.location.protocol + '//' + window.location.host + generateUrl('/apps/integration_gitlab/oauth-redirect'),
		}
	},

	watch: {
	},

	mounted() {
	},

	methods: {
		onCheckboxChanged(newValue, key) {
			this.state[key] = newValue
			this.saveOptions({ [key]: this.state[key] ? '1' : '0' })
		},
		onInput() {
			delay(() => {
				this.saveOptions({
					client_id: this.state.client_id,
					client_secret: this.state.client_secret,
					oauth_instance_url: this.state.oauth_instance_url,
				})
			}, 2000)()
		},
		saveOptions(values) {
			const req = {
				values,
			}
			const url = generateUrl('/apps/integration_gitlab/admin-config')
			axios.put(url, req).then((response) => {
				showSuccess(t('integration_gitlab', 'GitLab admin options saved'))
			}).catch((error) => {
				showError(
					t('integration_gitlab', 'Failed to save GitLab admin options')
					+ ': ' + (error.response?.request?.responseText ?? '')
				)
				console.debug(error)
			})
		},
	},
}
</script>

<style scoped lang="scss">
#gitlab_prefs {
	#gitlab-content{
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
