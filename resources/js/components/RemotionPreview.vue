<script setup>
import { ref, onMounted, onUnmounted, watch } from 'vue'

const props = defineProps({
  inputProps: { type: Object, required: true },
  durationInFrames: { type: Number, required: true },
  fps: { type: Number, default: 30 },
})

const containerRef = ref(null)
let root = null

async function renderPlayer() {
  if (!root) return
  const React = await import('react')
  const { default: RemotionPlayerBridge } = await import('./RemotionPlayerBridge.jsx')

  root.render(React.createElement(RemotionPlayerBridge, {
    inputProps: props.inputProps,
    durationInFrames: props.durationInFrames,
    fps: props.fps,
  }))
}

onMounted(async () => {
  const { createRoot } = await import('react-dom/client')
  root = createRoot(containerRef.value)
  await renderPlayer()
})

watch(() => [props.inputProps, props.durationInFrames], () => renderPlayer(), { deep: true })

onUnmounted(() => {
  root?.unmount()
  root = null
})
</script>

<template>
  <div ref="containerRef" class="w-full flex justify-center" />
</template>
