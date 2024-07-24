import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { showError } from '@nextcloud/dialogs'

let mytimer = 0
export function delay(callback, ms) {
	return function() {
		const context = this
		const args = arguments
		clearTimeout(mytimer)
		mytimer = setTimeout(function() {
			callback.apply(context, args)
		}, ms || 0)
	}
}

export function oauthConnect(gitlabUrl, clientId, oauthOrigin) {
	const redirectUri = window.location.protocol + '//' + window.location.host + generateUrl('/apps/integration_gitlab/oauth-redirect')

	const oauthState = Math.random().toString(36).substring(3)
	const requestUrl = gitlabUrl + '/oauth/authorize'
		+ '?client_id=' + encodeURIComponent(clientId)
		+ '&redirect_uri=' + encodeURIComponent(redirectUri)
		+ '&response_type=code'
		+ '&state=' + encodeURIComponent(oauthState)
		+ '&scope=' + encodeURIComponent('read_user read_api read_repository')

	return new Promise((resolve, reject) => {
		axios.put(generateUrl('/apps/integration_gitlab/config'), {
			values: {
				oauth_state: oauthState,
				redirect_uri: redirectUri,
				oauth_origin: oauthOrigin,
			},
		}).then(() => {
			window.location.replace(requestUrl)
		}).catch((error) => {
			showError(
				t('integration_gitlab', 'Failed to save GitLab OAuth state')
				+ ': ' + (error.response?.request?.responseText ?? ''),
			)
			console.error(error)
		})
	})
}

export function hexToRgb(hex) {
	const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex)
	return result
		? {
			r: parseInt(result[1], 16),
			g: parseInt(result[2], 16),
			b: parseInt(result[3], 16),
		}
		: null
}
