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
			<path fill-rule="evenodd" clip-rule="evenodd" d="M4 0a4 4 0 0 0-4 4v8a4 4 0 0 0 4 4h8a4 4 0 0 0 4-4V4a4 4 0 0 0-4-4H4zm1 4a1 1 0 0 0-1 1v6a1 1 0 0 0 1 1h4a1 1 0 0 0 1-1V5a1 1 0 0 0-1-1H5zm7.67 3.048a1 1 0 0 0-.386-1.234L11 5v6l1.67-3.952z" />
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
