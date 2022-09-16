<template>
	<span :aria-hidden="!title"
		:aria-label="title"
		class="material-design-icon merged-merge-request-icon"
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
			<path fill-rule="evenodd" clip-rule="evenodd" d="M5.5 3.5a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm-.044 2.31a2.5 2.5 0 1 0-1.706.076v4.228a2.501 2.501 0 1 0 1.5 0V8.373a5.735 5.735 0 0 0 3.86 1.864 2.501 2.501 0 1 0 .01-1.504 4.254 4.254 0 0 1-3.664-2.922zM11.5 10.5a1 1 0 1 0 0-2 1 1 0 0 0 0 2zm-6 2a1 1 0 1 1-2 0 1 1 0 0 1 2 0z" />
		</svg>
	</span>
</template>

<script>
export default {
	name: 'MergedMergeRequestIcon',
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
