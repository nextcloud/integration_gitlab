/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { createAppConfig } from '@nextcloud/vite-config'
import eslint from 'vite-plugin-eslint'
import stylelint from 'vite-plugin-stylelint'

const isProduction = process.env.NODE_ENV === 'production'

export default createAppConfig({
    adminSettings: 'src/adminSettings.js',
    personalSettings: 'src/personalSettings.js',
    dashboard: 'src/dashboard.js',
    reference: 'src/reference.js',
}, {
    config: {
        css: {
            modules: {
                localsConvention: 'camelCase',
            },
            preprocessorOptions: {
                scss: {
                    api: 'modern-compiler',
                },
            },
        },
        plugins: [eslint(), stylelint()],
    },
    inlineCSS: { relativeCSSInjection: true },
    minify: isProduction,
})
