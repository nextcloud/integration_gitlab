/* jshint esversion: 6 */

/**
 * Nextcloud - gitlab
 *
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 * @copyright Julien Veyssier 2020
 */

import Vue from 'vue'
import './bootstrap'
import PersonalSettings from './components/PersonalSettings'

'use strict'

new Vue({
	el: '#gitlab_prefs',
	render: h => h(PersonalSettings),
})
