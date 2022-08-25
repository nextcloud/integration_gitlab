<template>
	<span :aria-hidden="!title"
		:aria-label="title"
		class="material-design-icon gitlab-icon"
		role="img"
		v-bind="$attrs"
		@click="$emit('click', $event)">
		<svg
			:fill="fillColor"
			:width="size"
			:height="size"
			enable-background="new 0 0 256 256"
			version="1.1"
			viewBox="0 0 256 256"
			xml:space="preserve"
			xmlns="http://www.w3.org/2000/svg">
			<g>
				<path d="m128.57 247.57 47.104-144.97h-94.208z" />
				<path d="m15.456 102.6-14.314 44.055c-1.3056 4.018 0.12434 8.42 3.5426 10.903l123.89 90.012z" />
				<path d="m15.456 102.6h66.015l-28.371-87.314c-1.4592-4.4934-7.8153-4.4922-9.2745 0z" />
				<path d="m241.69 102.6 14.314 44.055c1.3056 4.018-0.12434 8.42-3.5426 10.903l-123.89 90.012z" />
				<path d="m241.69 102.6h-66.015l28.371-87.314c1.4592-4.4934 7.8153-4.4922 9.2745 0z" />
			</g>
		</svg>
	</span>
</template>

<script>
export default {
	name: 'GitlabIcon',
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
