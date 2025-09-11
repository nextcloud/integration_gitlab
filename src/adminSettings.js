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
import AdminSettings from './components/AdminSettings.vue'

// eslint-disable-next-line
'use strict'

const app = createApp(AdminSettings)
app.mixin({ methods: { t, n } })
app.mount('#gitlab_prefs')
