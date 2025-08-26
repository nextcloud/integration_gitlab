<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<span :aria-hidden="!title"
		:aria-label="title"
		class="material-design-icon downvote-icon"
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
			<path fill-rule="evenodd" clip-rule="evenodd" d="M10.72 13.402l-.27-.902L10 11h3.989a2 2 0 0 0 1.932-2.517l-1.19-4.448A4 4 0 0 0 9.852 1.2L3 3H0v8h3l4.39 4.39c1.47 1.47 3.928.002 3.33-1.988zM3 9.5H1.5v-5H3v5zm7.232-6.85L4.5 4.157v6.222l3.951 3.951c.12.119.22.149.296.155a.533.533 0 0 0 .314-.08.533.533 0 0 0 .22-.238.466.466 0 0 0 .003-.334l-.72-2.402-.58-1.931h6.005a.5.5 0 0 0 .483-.63l-1.19-4.447a2.5 2.5 0 0 0-3.05-1.772z" />
		</svg>
	</span>
</template>

<script>
export default {
	name: 'DownVoteIcon',
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
