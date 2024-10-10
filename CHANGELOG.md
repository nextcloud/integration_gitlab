# Change Log
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/)
and this project adheres to [Semantic Versioning](http://semver.org/).

## 3.1.2 – 2024-10-10

### Fixed

- store a/c info only when creds are valid (#102) @kyteinsky


## 3.1.1 – 2024-08-30

### Fixed

- userId can be null in the service (the reference provider can now be used in public contexts) (#97) @julien-nc
- infer token_type in repair step (#98) @kyteinsky


## 3.1.0 – 2024-08-21

### Changed

- Centralize config handling and use strong typing @provokateurin
- Bump max NC version to 31

### Added

- Support multiple accounts @provokateurin
- Add PSR-4 autoloading to make tests work @provokateurin

## 3.0.1 – 2024-07-23

### Changed

- Remove navigation in favor of external sites app @provokateurin

### Fixed
- add vendor-bin to .nextcloudignore @kyteinsky
- Fix incorrect config handling (#91) @provokateurin

## 3.0.0 – 2024-07-11

### Changed

- Major version bump

## 1.1.0 – 2024-07-11

### Changed

- Remove unused endpoint @provokateurin

### Added

- Add Nextcloud 30 support @nickvergessen
- Add simple test setup for development @provokateurin

### Fixed

- Do not suggest wrong scope @provokateurin
- Only send Oauth credentials to the right URLs @provokateurin

## 1.0.19 – 2024-03-06

### Changed

- Updated node packages (mainly nc/vue8) @kyteinsky
- bump supported version to NC 29 @kyteinsky

### Added

- Php unit tests for all API controller methods @MB-Finski
- Psalm and cs:fix configs and related workflows @MB-Finksi

### Fixed

- All psalm errors @MB-Finski

## 1.0.18 – 2023-07-20

### Changed

- Make the picker work even if the search provider is disabled @julien-nc
- Add information in the link preview rich object for future clients implementation of the smart picker @julien-nc
- do not use OC functions/vars anymore @julien-nc

## 1.0.17 – 2023-04-26

### Changed

- get rid of the `@nextcloud/vue-richtext` dependency as the build bug has been fixed in the nextcloud webpack config @julien-nc
- update all npm pkgs @julien-nc

## 1.0.15 – 2023-04-02
### Changed
- update npm pkgs

### Fixed
- screenshot URL

## 1.0.13 – 2023-03-06
### Added
- smart picker provider

### Changed
- lazy load reference widget

## 1.0.12 – 2022-12-21
### Changed
- split issue/MR search provider in 2
- update npm pkgs

### Fixed
- avatar url generation in search providers
- merge request state check in search provider

## 1.0.10 – 2022-11-07
### Changed
- update npm pkgs
- improve reference widget wrapping (when screen gets narrow)
- improve reference widget style

### Fixed
- remove 'action' filter when getting todos (does not work with GitLab >= 15.5)

## 1.0.9 – 2022-09-27
### Changed
- use new reference class

## 1.0.8 – 2022-09-23
### Fixed
- reference widget style issues

## 1.0.7 – 2022-09-21
### Added
- reference widget for issues, MRs and comments

## 1.0.6 – 2022-08-30
### Added
- new option to connect with OAuth in a popup
- token refresh (based on expiration date)
- allow connection directly from the dashboard (with or without a popup)

### Changed
- use material icons
- use node 16, bump js libs, adjust to new eslint config
- get ready for NC 25

### Fixed
- fallback avatar

## 1.0.2 – 2021-10-14
### Changed
- don't ask for 'api' scope when using OAuth
- adjust advice to create personal token

## 1.0.1 – 2021-06-28
### Changed
- stop polling widget content when document is hidden
- bump js libs
- get rid of all deprecated stuff
- bump min NC version to 22
- cleanup backend code

## 1.0.0 – 2021-03-19
### Changed
- bump js libs (fix widget item menu placement)

## 0.0.15 – 2021-02-16
### Changed
- app certificate

## 0.0.13 – 2021-02-08
### Changed
- bump max NC version

### Fixed
- dialogs style import

## 0.0.10 – 2021-01-18
### Fixed
- Popover scroll issue

## 0.0.9 – 2020-12-15
### Changed
- bump js libs
- show namespace in dashboard widget items

## 0.0.8 – 2020-11-08
### Added
- optional link to GitLab in NC navigation

### Changed
- bump js libs

### Fixed
- make app icon dimensions square

## 0.0.7 – 2020-10-22
### Added
- automate releases

### Changed
- use Webpack 5 and style lint

### Fixed
- always use redirect URI generated on browser side

## 0.0.5 – 2020-10-12
### Changed
- better avatar management, url generated on server side
- safer search

### Fixed
- various small design problems

## 0.0.4 – 2020-10-02
### Added
- lots of translations

### Changed
- filter out 'marked' todo events

## 0.0.3 – 2020-09-21
### Added
* unified search provider

### Changed
* improve authentication design
* bump nc-vue and nc-vue-dashboard
* improve widget empty content

### Fixed
* small mistakes in icons and design

## 0.0.2 – 2020-09-02
### Fixed
* image loading with new webpack config

## 0.0.1 – 2020-09-02
### Added
* the app
