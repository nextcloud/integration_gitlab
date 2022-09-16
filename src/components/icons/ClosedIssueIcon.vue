<template>
	<span :aria-hidden="!title"
		:aria-label="title"
		class="material-design-icon closed-issue-icon"
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
			<path fill-rule="evenodd" clip-rule="evenodd" d="M9 2.5H3a.5.5 0 0 0-.5.5v10a.5.5 0 0 0 .5.5h6a.5.5 0 0 0 .5-.5V3a.5.5 0 0 0-.5-.5zM3 1a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h6a2 2 0 0 0 1.651-.87.753.753 0 0 0 .098-.145l3.878-7.45a1.75 1.75 0 0 0-.744-2.361l-2.912-1.516A2 2 0 0 0 9 1H3zm10.297 4.841L11 10.254v-5.89l2.19 1.14a.25.25 0 0 1 .107.337zM8.28 7.281A.75.75 0 0 0 7.22 6.22L5.25 8.19l-.47-.47a.75.75 0 0 0-1.06 1.06l1 1a.75.75 0 0 0 1.06 0l2.5-2.5z" />
		</svg>
	</span>
</template>

<script>
export default {
	name: 'ClosedIssueIcon',
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
