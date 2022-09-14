<template>
	<span :aria-hidden="!title"
		:aria-label="title"
		class="material-design-icon upvote-icon"
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
			<path fill-rule="evenodd" clip-rule="evenodd" d="M10.72 2.598l-.27.902L10 5h3.989a2 2 0 0 1 1.932 2.517l-1.19 4.448a4 4 0 0 1-4.88 2.835L3 13H0V5h3L7.39.61c1.47-1.47 3.928-.002 3.33 1.988zM3 6.5H1.5v5H3v-5zm7.232 6.85L4.5 11.843V5.621L8.451 1.67a.466.466 0 0 1 .296-.155.533.533 0 0 1 .314.08c.11.065.183.154.22.238.03.07.051.173.003.334l-.72 2.402-.58 1.931h6.005a.5.5 0 0 1 .483.63l-1.19 4.447a2.5 2.5 0 0 1-3.05 1.773z" />
		</svg>
	</span>
</template>

<script>
export default {
	name: 'UpVoteIcon',
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
