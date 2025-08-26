/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createApp } from 'vue'
import Dashboard from './views/Dashboard.vue'

document.addEventListener('DOMContentLoaded', function() {

	OCA.Dashboard.register('gitlab_todos', (el, { widget }) => {
		const app = createApp(Dashboard, {
			title: widget.title,
		})
		app.mixin({ methods: { t, n } })
		app.mount(el)
	})

})
