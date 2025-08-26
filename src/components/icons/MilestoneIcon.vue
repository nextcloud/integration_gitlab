<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<span :aria-hidden="!title"
		:aria-label="title"
		class="material-design-icon milestone-icon"
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
			<path fill-rule="evenodd" clip-rule="evenodd" d="M14.5 8a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0zM16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zM8.75 3.75a.75.75 0 0 0-1.5 0v4.56l.22.22 2.254 2.254a.75.75 0 1 0 1.06-1.06L8.75 7.689V3.75z" />
		</svg>
	</span>
</template>

<script>
export default {
	name: 'MilestoneIcon',
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
