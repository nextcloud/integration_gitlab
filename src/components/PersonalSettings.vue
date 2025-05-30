<template>
	<div id="gitlab_prefs" class="section">
		<h2>
			<GitlabIcon class="icon" />
			{{ t('integration_gitlab', 'GitLab integration') }}
		</h2>
		<div id="gitlab-content">
			<div v-if="adminConfig.oauth_is_possible">
				<NcButton
					id="gitlab-oauth"
					:disabled="state.loading === true"
					:class="{ loading: state.loading }"
					@click="connectWithOauth">
					<template #icon>
						<OpenInNewIcon :size="20" />
					</template>
					{{ t('integration_gitlab', 'Connect to GitLab using OAuth') }}
				</NcButton>
				<br>
				<p class="settings-hint">
					{{ t('integration_gitlab', 'OAuth instance URL') }}:&nbsp;<span>{{ adminConfig.oauth_instance_url }}</span>
				</p>
				<hr>
				<br>
			</div>

			<div class="line">
				<label for="gitlab-url">
					<EarthIcon :size="20" class="icon" />
					{{ t('integration_gitlab', 'GitLab instance address') }}
					<span v-if="adminConfig.force_gitlab_instance_url !== ''" style="font-style: italic;">
						&nbsp;({{ t('integration_gitlab', 'enforced by administrator') }})
					</span>
				</label>
				<input
					id="gitlab-url"
					v-model="state.url"
					type="text"
					:disabled="adminConfig.force_gitlab_instance_url !== ''"
					:placeholder="t('integration_gitlab', 'GitLab instance address')">
			</div>
			<div class="line">
				<label
					for="gitlab-token">
					<KeyIcon :size="20" class="icon" />
					{{ t('integration_gitlab', 'Personal access token') }}
				</label>
				<input
					id="gitlab-token"
					v-model="state.token"
					type="password"
					:placeholder="t('integration_gitlab', 'GitLab personal access token')">
			</div>
			<br>
			<p class="settings-hint">
				{{
					t('integration_gitlab', 'Give the token the "read_user", "read_api" and "read_repository" permissions.')
				}}
			</p>
			<NcButton
				id="gitlab-oauth"
				:disabled="state.loading === true || state.url === '' || state.token === ''"
				:class="{loading: state.loading }"
				@click="addAccount">
				<template #icon>
					<OpenInNewIcon :size="20" />
				</template>
				{{ t('integration_gitlab', 'Connect to GitLab using Personal Access Token') }}
			</NcButton>
			<br>

			<div v-if="accounts.length > 0">
				<hr>
				<br>

				<div v-for="account in accounts" :key="account.id">
					<div class="line">
						<label class="gitlab-connected">
							<CheckIcon :size="20" class="icon" />
							{{
								t('integration_gitlab', 'Connected to {url} as {displayname} ({name})', {
									url: account.url,
									displayname: account.userInfoDisplayName,
									name: account.userInfoName,
								})
							}}
						</label>
						<NcButton type="tertiary" @click="()=> deleteAccount(account.id)">
							<template #icon>
								<CloseIcon :size="20" />
							</template>
							{{ t('integration_gitlab', 'Remove account') }}
						</NcButton>
					</div>
					<br>
				</div>
			</div>

			<hr>
			<br>

			<p v-if="userConfig.search_enabled || userConfig.search_issues_enabled || userConfig.search_mrs_enabled"
				class="settings-hint">
				<InformationOutlineIcon :size="20" class="icon" />
				{{
					t('integration_gitlab', 'Warning, everything you type in the search bar will be sent to GitLab.')
				}}
			</p>
			<p>
				{{ t('integration_gitlab', 'Please use the External Sites app to add GitLab to your navigation bar:') }}
				<a href="https://apps.nextcloud.com/apps/external" target="_blank" rel="noopener">https://apps.nextcloud.com/apps/external</a>
			</p>
			<NcCheckboxRadioSwitch
				:checked="userConfig.search_enabled"
				@update:checked="onConfigChanged($event, 'search_enabled')">
				{{ t('integration_gitlab', 'Enable searching for repositories') }}
			</NcCheckboxRadioSwitch>
			<NcCheckboxRadioSwitch
				:checked="userConfig.search_issues_enabled"
				@update:checked="onConfigChanged($event, 'search_issues_enabled')">
				{{ t('integration_gitlab', 'Enable searching for issues') }}
			</NcCheckboxRadioSwitch>
			<NcCheckboxRadioSwitch
				:checked="userConfig.search_mrs_enabled"
				@update:checked="onConfigChanged($event, 'search_mrs_enabled')">
				{{ t('integration_gitlab', 'Enable searching for merge requests') }}
			</NcCheckboxRadioSwitch>
			<NcCheckboxRadioSwitch
				:checked="userConfig.link_preview_enabled"
				@update:checked="onConfigChanged($event, 'link_preview_enabled')">
				{{ t('integration_gitlab', 'Enable GitLab link previews') }}
			</NcCheckboxRadioSwitch>
			<br>
			<div v-if="accounts.length > 0">
				<NcSelect
					:value="selectedAccount"
					:input-label="t('integration_gitlab', 'Gitlab Account for Dashboard widget')"
					:options="selectableAccounts"
					@input="(value) => onConfigChanged(value?.id ?? 0, 'widget_account_id')" />

				<NcSelect
					:value="selectedProjectsFilter"
					:input-label="t('integration_gitlab', 'Projects filter for widget')"
					:placeholder="t('integration_gitlab', 'Projects filter for widget')"
					:multiple="true"
					:options="widgetProjectOptions"
					@input="(value) => onSelectedProjectChange(value)" />

				<NcSelect
					:value="selectedGroupsFilter"
					:input-label="t('integration_gitlab', 'Groups filter for widget')"
					:placeholder="t('integration_gitlab', 'Groups filter for widget')"
					:options="widgetGroupOptions"
					@input="(value) => onSelectedGroupChange(value)" />
			</div>
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
import { oauthConnect } from '../utils.js'
import { showError, showSuccess } from '@nextcloud/dialogs'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'
import NcSelect from '@nextcloud/vue/dist/Components/NcSelect.js'
import { confirmPassword } from '@nextcloud/password-confirmation'
import '@nextcloud/password-confirmation/dist/style.css'

export default {
	name: 'PersonalSettings',

	components: {
		GitlabIcon,
		NcCheckboxRadioSwitch,
		NcButton,
		NcSelect,
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
			state: {
				url: '',
				token: '',
				loading: false,
			},
			userConfig: loadState('integration_gitlab', 'user-config'),
			adminConfig: loadState('integration_gitlab', 'admin-config'),
			accounts: loadState('integration_gitlab', 'accounts'),
			widget_projects_list: [],
			widget_groups_list: [],
		}
	},

	computed: {
		selectableAccounts() {
			return this.accounts.map(this.formatAccountForSelect)
		},
		selectedAccount() {
			if (this.userConfig.widget_account_id === 0) {
				return null
			}

			const account = this.accounts.find((account) => account.id === this.userConfig.widget_account_id)

			return this.formatAccountForSelect(account)
		},
		selectedProjectsFilter() {
			if (!this.selectedAccount) {
				return []
			}

			const account = this.accounts.find((account) => account.id === this.userConfig.widget_account_id)

			return account.widgetProjects ?? []
		},
		selectedGroupsFilter() {
			if (!this.selectedAccount) {
				return []
			}

			const account = this.accounts.find((account) => account.id === this.userConfig.widget_account_id)
			return account.widgetGroups ?? []
		},
		widgetProjectOptions() {
			return this.widget_projects_list.map((project) => ({
				id: project.id,
				label: project.name,
			}))
		},
		widgetGroupOptions() {
			return this.widget_groups_list.map((group) => ({
				id: group.id,
				label: group.name,
			}))
		},
	},

	watch: {
		selectedAccount(newValue) {
			if (newValue) {
				this.fetchAccountProjectsList()
				this.fetchAccountGroupsList()
			}
		},
	},

	mounted() {
		const paramString = window.location.search.slice(1)
		// eslint-disable-next-line
		const urlParams = new URLSearchParams(paramString)
		const glToken = urlParams.get('gitlabToken')
		if (glToken === 'success') {
			showSuccess(t('integration_gitlab', 'Successfully connected to GitLab!'))
		} else if (glToken === 'error') {
			showError(t('integration_gitlab', 'Error connecting to GitLab:') + ' ' + urlParams.get('message'))
		}
		if (this.adminConfig.force_gitlab_instance_url !== '') {
			this.state.url = this.adminConfig.force_gitlab_instance_url
		}
		this.fetchAccountProjectsList()
		this.fetchAccountGroupsList()
	},

	methods: {
		formatAccountForSelect(account) {
			return {
				id: account.id,
				label: t('integration_gitlab', '{url} as {displayname} ({name})', {
					url: account.url,
					displayname: account.userInfoDisplayName,
					name: account.userInfoName,
				}),
			}
		},
		async addAccount() {
			await confirmPassword()

			this.state.loading = true
			try {
				const response = await axios.post(generateUrl('/apps/integration_gitlab/account'), {
					url: this.state.url,
					token: this.state.token,
				})
				showSuccess(t('integration_gitlab', 'Account added'))
				if (this.adminConfig.force_gitlab_instance_url === '') {
					this.state.url = '' // Reset instance URL only if not enforced
				}
				this.state.token = ''
				this.accounts.push(response.data.account)
				this.userConfig = response.data.config
			} catch (error) {
				showError(t('integration_gitlab', 'Failed to add account') + ': ' + (error.response?.data?.error ?? ''))
				console.debug(error)
			}
			this.state.loading = false
		},
		async deleteAccount(id) {
			await confirmPassword()

			this.state.loading = true
			try {
				const response = await axios.delete(generateUrl(`/apps/integration_gitlab/account/${id}`))
				showSuccess(t('integration_gitlab', 'Account deleted'))
				this.accounts.splice(this.accounts.findIndex(e => e.id === id), 1)
				this.userConfig = response.data.config
			} catch (error) {
				showError(t('integration_gitlab', 'Failed to delete account') + ': ' + (error.response?.data?.error ?? ''))
				console.debug(error)
			}
			this.state.loading = false
		},
		async onConfigChanged(newValue, key) {
			this.state.loading = true

			try {
				await axios.put(generateUrl('/apps/integration_gitlab/config'), {
					values: { [key]: newValue },
				})
				showSuccess(t('integration_gitlab', 'GitLab options saved'))
				this.userConfig[key] = newValue
			} catch (error) {
				showError(t('integration_gitlab', 'Failed to save GitLab options') + ': ' + (error.response?.data?.error ?? ''))
				console.debug(error)
			}

			this.state.loading = false
		},
		connectWithOauth() {
			oauthConnect(this.userConfig.oauth_instance_url, this.userConfig.client_id, 'settings')
		},
		fetchAccountProjectsList() {
			const url = generateUrl(`/apps/integration_gitlab/gitlab/${this.userConfig.widget_account_id}/projects`)
			return axios.get(url).then((response) => {
				this.widget_projects_list = response.data
			}).catch((error) => {
				showError(t('integration_gitlab', 'Failed to get GitLab projects'))
				console.debug(error)
			})
		},
		onSelectedProjectChange(value) {
			const account = this.accounts.find((account) => account.id === this.userConfig.widget_account_id)
			const previousProjects = account.widgetProjects
			account.widgetProjects = value
			this.updateAccountFilters().catch(() => {
				account.widgetProjects = previousProjects
			})
		},
		fetchAccountGroupsList() {
			const url = generateUrl(`/apps/integration_gitlab/gitlab/${this.userConfig.widget_account_id}/groups`)
			return axios.get(url).then((response) => {
				this.widget_groups_list = response.data
			}).catch((error) => {
				showError(t('integration_gitlab', 'Failed to get GitLab groups'))
				console.debug(error)
			})
		},
		onSelectedGroupChange(value) {
			const account = this.accounts.find((account) => account.id === this.userConfig.widget_account_id)
			const previousGroups = account.widgetGroups
			account.widgetGroups = value ? [value] : []
			this.updateAccountFilters().catch(() => {
				account.widgetGroups = previousGroups
			})
		},
		updateAccountFilters() {
			const url = generateUrl(`/apps/integration_gitlab/account/${this.userConfig.widget_account_id}/filters`)
			const account = this.accounts.find((account) => account.id === this.userConfig.widget_account_id)
			return axios.put(url, {
				projects: account?.widgetProjects ?? [],
				groups: account?.widgetGroups ?? [],
			}).then(() => {
				showSuccess(t('integration_gitlab', 'Selected account filters updated'))
			}).catch((error) => {
				showError(t('integration_gitlab', 'Failed to update GitLab filters') + (`: ${error.response?.data?.error}` ?? ''))
				console.debug(error)
			})
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
			width: 500px;
			display: flex;
			align-items: center;
		}

		> input {
			width: 300px;
		}
	}
}
</style>
