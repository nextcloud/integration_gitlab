/* jshint esversion: 6 */

/**
 * Nextcloud - gitlab
 *
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <julien-nc@posteo.net>
 * @copyright Julien Veyssier 2020
 */

import { createApp } from 'vue'
import { translate as t, translatePlural as n } from '@nextcloud/l10n'
import Dashboard from './views/Dashboard.vue'

document.addEventListener('DOMContentLoaded', function() {

	OCA.Dashboard.register('gitlab_todos', (el, { widget }) => {
		const app = createApp(Dashboard, { widget })
		app.mixin({ methods: { t, n } })
		app.mount(el)
	})

})
