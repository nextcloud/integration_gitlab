<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

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
			{{ t('integration_gitlab', 'Give "read_user", "read_api" and "read_repository" permissions to the application.') }}
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
				<label for="gitlab-force-instance-url">
					<EarthIcon :size="20" class="icon" />
					{{ t('integration_gitlab', 'Restrict GitLab instance URL') }}
				</label>
				<input id="gitlab-force-instance-url"
					v-model="state.force_gitlab_instance_url"
					type="text"
					:placeholder="t('integration_gitlab', 'Restrict GitLab instance URL')"
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
				v-model="state.link_preview_enabled"
				@update:model-value="onCheckboxChanged($event, 'link_preview_enabled')">
				{{ t('integration_gitlab', 'Enable GitLab link previews') }}
			</NcCheckboxRadioSwitch>
		</div>
	</div>
</template>

<script>
import InformationOutlineIcon from 'vue-material-design-icons/InformationOutline.vue'
import KeyIcon from 'vue-material-design-icons/KeyOutline.vue'
import EarthIcon from 'vue-material-design-icons/Earth.vue'

import GitlabIcon from './icons/GitlabIcon.vue'

import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { delay } from '../utils.js'
import { showSuccess, showError } from '@nextcloud/dialogs'

import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import { confirmPassword } from '@nextcloud/password-confirmation'

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
			axios.put(generateUrl('/apps/integration_gitlab/admin-config'), {
				values: { [key]: this.state[key] },
			}).then((response) => {
				showSuccess(t('integration_gitlab', 'GitLab admin options saved'))
			}).catch((error) => {
				showError(
					t('integration_gitlab', 'Failed to save GitLab admin options')
					+ ': ' + (error.response?.request?.responseText ?? ''),
				)
				console.debug(error)
			})
		},
		onInput() {
			delay(async () => {
				await confirmPassword()

				const values = {
					client_id: this.state.client_id,
					oauth_instance_url: this.state.oauth_instance_url,
					force_gitlab_instance_url: this.state.force_gitlab_instance_url,
				}
				if (this.state.client_secret !== 'dummyToken') {
					values.client_secret = this.state.client_secret
				}
				axios.put(generateUrl('/apps/integration_gitlab/sensitive-admin-config'), {
					values,
				}).then((response) => {
					showSuccess(t('integration_gitlab', 'GitLab admin options saved'))
				}).catch((error) => {
					showError(
						t('integration_gitlab', 'Failed to save GitLab admin options')
						+ ': ' + (error.response?.request?.responseText ?? ''),
					)
					console.debug(error)
				})
			}, 2000)()
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
