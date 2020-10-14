<template>
	<div id="gitlab_prefs" class="section">
		<h2>
			<a class="icon icon-gitlab" />
			{{ t('integration_gitlab', 'GitLab integration') }}
		</h2>
		<p class="settings-hint">
			{{ t('integration_gitlab', 'If you want to allow your Nextcloud users to use OAuth to authenticate to a GitLab instance of your choice, create an application in your GitLab settings and set the ID and secret here.') }}
			<br><br>
			<span class="icon icon-details" />
			{{ t('integration_gitlab', 'Make sure you set the "Redirect URI" to') }}
			<b> {{ redirect_uri }} </b>
			<br><br>
			{{ t('integration_gitlab', 'and give "api", "read_user", "read_api" and "read_repository" permissions to the application.') }}
			<br>
			{{ t('integration_gitlab', 'Put the "Application ID" and "Application secret" below. Your Nextcloud users will then see a "Connect to GitLab" button in their personal settings if they select the GitLab instance defined here.') }}
		</p>
		<div class="grid-form">
			<label for="gitlab-oauth-instance">
				<a class="icon icon-link" />
				{{ t('integration_gitlab', 'OAuth app instance address') }}
			</label>
			<input id="gitlab-oauth-instance"
				v-model="state.oauth_instance_url"
				type="text"
				:placeholder="t('integration_gitlab', 'Instance address')"
				@input="onInput">
			<label for="gitlab-client-id">
				<a class="icon icon-category-auth" />
				{{ t('integration_gitlab', 'Application ID') }}
			</label>
			<input id="gitlab-client-id"
				v-model="state.client_id"
				type="password"
				:readonly="readonly"
				:placeholder="t('integration_gitlab', 'ID of your GitLab application')"
				@input="onInput"
				@focus="readonly = false">
			<label for="gitlab-client-secret">
				<a class="icon icon-category-auth" />
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
	</div>
</template>

<script>
import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { delay } from '../utils'
import { showSuccess, showError } from '@nextcloud/dialogs'

export default {
	name: 'AdminSettings',

	components: {
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
		onInput() {
			const that = this
			delay(() => {
				that.saveOptions()
			}, 2000)()
		},
		saveOptions() {
			const req = {
				values: {
					client_id: this.state.client_id,
					client_secret: this.state.client_secret,
					oauth_instance_url: this.state.oauth_instance_url,
				},
			}
			const url = generateUrl('/apps/integration_gitlab/admin-config')
			axios.put(url, req)
				.then((response) => {
					showSuccess(t('integration_gitlab', 'GitLab admin options saved'))
				})
				.catch((error) => {
					showError(
						t('integration_gitlab', 'Failed to save GitLab admin options')
						+ ': ' + (error.response?.request?.responseText ?? '')
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

body.theme--dark .icon-gitlab {
	background-image: url(./../../img/app.svg);
}

</style>
