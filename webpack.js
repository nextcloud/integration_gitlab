const path = require('path')
const webpackConfig = require('@nextcloud/webpack-vue-config')

webpackConfig.entry = {
	personalSettings: path.join(__dirname, 'src', 'personalSettings.js'),
	adminSettings: path.join(__dirname, 'src', 'adminSettings.js'),
	dashboard: path.join(__dirname, 'src', 'dashboard.js'),
}

module.exports = webpackConfig
