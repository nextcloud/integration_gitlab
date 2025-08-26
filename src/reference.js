/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { registerWidget } from '@nextcloud/vue/components/NcRichText'

registerWidget('integration_gitlab', async (el, { richObjectType, richObject, accessible }) => {
	const { createApp } = await import('vue')
	const { default: ReferenceGitlabWidget } = await import('./views/ReferenceGitlabWidget.vue')

	const app = createApp(
		ReferenceGitlabWidget,
		{
			richObjectType,
			richObject,
			accessible,
		},
	)
	app.mixin({ methods: { t, n } })
	app.mount(el)
}, () => {}, { hasInteractiveView: false })
