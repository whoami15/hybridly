/* eslint-disable vue/order-in-components */
import type { RouterContext } from '@hybridly/core'
import { debug } from '@hybridly/utils'
import type { PropType } from 'vue'
import { defineComponent, h } from 'vue'
import { state } from '../stores/state'

export const wrapper = defineComponent({
	name: 'Hybridly',
	setup(props) {
		if (typeof window !== 'undefined') {
			state.setContext(props.context)

			if (!props.context) {
				throw new Error('Hybridly was not properly initialized. The context is missing.')
			}
		}

		function renderLayout(child: any) {
			debug.adapter('vue:render:layout', 'Rendering layout.')

			if (typeof state.view.value?.layout === 'function') {
				return state.view.value.layout(h, child)
			}

			if (Array.isArray(state.view.value?.layout)) {
				return state.view
					.value!.layout.concat(child)
					.reverse()
					.reduce((child, layout) => {
						layout.inheritAttrs = !!layout.inheritAttrs

						return h(layout, {
							...(state.view.value?.layoutProperties ?? {}),
							...state.context.value!.view.properties,
						}, () => child)
					})
			}

			return [
				h(state.view.value?.layout, {
					...(state.view.value?.layoutProperties ?? {}),
					...state.context.value!.view.properties,
				}, () => child),
				renderDialog(),
			]
		}

		function renderView() {
			debug.adapter('vue:render:view', 'Rendering view.')
			state.view.value!.inheritAttrs = !!state.view.value!.inheritAttrs

			return h(state.view.value!, {
				...state.context.value!.view.properties,
				key: state.viewKey.value,
			})
		}

		function renderDialog() {
			debug.adapter('vue:render:dialog', 'Rendering dialog.')

			if (state.dialog.value) {
				return h(state.dialog.value!, {
					...state.dialog.value.properties,
					key: state.dialogKey.value,
				})
			}
		}

		return () => {
			if (state.view.value) {
				const view = renderView()

				if (state.viewLayout.value) {
					state.view.value.layout = state.viewLayout.value
					state.viewLayout.value = undefined
				}

				if (state.viewLayoutProperties.value) {
					state.view.value.layoutProperties = state.viewLayoutProperties.value
					state.viewLayoutProperties.value = undefined
				}

				if (state.view.value.layout) {
					return renderLayout(view)
				}

				return [view, renderDialog()]
			}
		}
	},
	props: {
		context: {
			type: Object as PropType<RouterContext>,
			required: true,
		},
	},
})
