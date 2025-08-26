<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<span :aria-hidden="!title"
		:aria-label="title"
		class="material-design-icon issue-icon"
		role="img"
		v-bind="$attrs"
		@click="$emit('click', $event)">
		<svg
			:fill="fillColor"
			:width="size"
			:height="size"
			enable-background="new 0 0 16 16"
			version="1.1"
			viewBox="0 0 16 16"
			xml:space="preserve"
			xmlns="http://www.w3.org/2000/svg">
			<path fill-rule="evenodd" clip-rule="evenodd" d="M3 2.5h6a.5.5 0 0 1 .5.5v10a.5.5 0 0 1-.5.5H3a.5.5 0 0 1-.5-.5V3a.5.5 0 0 1 .5-.5zM1 3a2 2 0 0 1 2-2h6a2 2 0 0 1 1.97 1.658l2.913 1.516a1.75 1.75 0 0 1 .744 2.36l-3.878 7.45a.753.753 0 0 1-.098.145c-.36.526-.965.871-1.651.871H3a2 2 0 0 1-2-2V3zm10 7.254l2.297-4.413a.25.25 0 0 0-.106-.337L11 4.364v5.89z" />
		</svg>
	</span>
</template>

<script>
export default {
	name: 'IssueIcon',
	props: {
		title: {
			type: String,
			default: '',
		},
		fillColor: {
			type: String,
			default: 'currentColor',
		},
		size: {
			type: Number,
			default: 24,
		},
	},
}
</script>
