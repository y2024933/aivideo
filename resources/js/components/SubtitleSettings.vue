<script setup>
/**
 * @props {{ fontSize: string, color: string, customColor: string, position: string, animation: string }} modelValue
 * @emits update:modelValue
 */
import { computed } from 'vue'

const props = defineProps({
    modelValue: {
        type: Object,
        required: true,
    },
})

const emit = defineEmits(['update:modelValue'])

const settings = computed({
    get: () => props.modelValue,
    set: (v) => emit('update:modelValue', v),
})

function update(key, value) {
    emit('update:modelValue', { ...props.modelValue, [key]: value })
}

const fontSizes = [
    { value: 'small', label: '小' },
    { value: 'medium', label: '中' },
    { value: 'large', label: '大' },
]

const colorPresets = [
    { value: '#FFFFFF', label: '白色', class: 'bg-white border border-gray-300' },
    { value: '#FACC15', label: '黃色', class: 'bg-yellow-400' },
    { value: 'custom', label: '自訂', class: '' },
]

const positions = [
    { value: 'top', label: '上方' },
    { value: 'center', label: '中間' },
    { value: 'bottom', label: '下方' },
]

const animations = [
    { value: 'none', label: '無' },
    { value: 'fade', label: '淡入' },
    { value: 'slide', label: '滑入' },
    { value: 'typewriter', label: '打字機' },
]

const isCustomColor = computed(() => !['#FFFFFF', '#FACC15'].includes(settings.value.color))
</script>

<template>
    <div class="bg-white rounded-lg border p-6">
        <h3 class="font-semibold text-gray-800 mb-4">字幕設定</h3>

        <!-- 字體大小 -->
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-600 mb-2">字體大小</label>
            <div class="flex gap-2">
                <button
                    v-for="opt in fontSizes"
                    :key="opt.value"
                    class="px-4 py-1.5 text-sm rounded-md border transition-colors"
                    :class="settings.fontSize === opt.value
                        ? 'bg-indigo-600 text-white border-indigo-600'
                        : 'bg-white text-gray-700 border-gray-300 hover:border-indigo-400'"
                    @click="update('fontSize', opt.value)"
                >
                    {{ opt.label }}
                </button>
            </div>
        </div>

        <!-- 顏色 -->
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-600 mb-2">顏色</label>
            <div class="flex items-center gap-2">
                <button
                    v-for="opt in colorPresets"
                    :key="opt.value"
                    class="w-8 h-8 rounded-full ring-2 ring-offset-1 transition-all"
                    :class="[
                        opt.class,
                        (opt.value === 'custom' ? isCustomColor : settings.color === opt.value)
                            ? 'ring-indigo-500'
                            : 'ring-transparent hover:ring-gray-300'
                    ]"
                    :title="opt.label"
                    @click="opt.value === 'custom' ? null : update('color', opt.value)"
                >
                    <span v-if="opt.value === 'custom'" class="flex items-center justify-center text-xs text-gray-500">?</span>
                </button>
                <input
                    type="color"
                    :value="isCustomColor ? settings.color : '#FF6600'"
                    class="w-8 h-8 rounded cursor-pointer border-0 p-0"
                    @input="update('color', $event.target.value)"
                />
            </div>
        </div>

        <!-- 位置 -->
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-600 mb-2">位置</label>
            <div class="flex gap-2">
                <button
                    v-for="opt in positions"
                    :key="opt.value"
                    class="px-4 py-1.5 text-sm rounded-md border transition-colors"
                    :class="settings.position === opt.value
                        ? 'bg-indigo-600 text-white border-indigo-600'
                        : 'bg-white text-gray-700 border-gray-300 hover:border-indigo-400'"
                    @click="update('position', opt.value)"
                >
                    {{ opt.label }}
                </button>
            </div>
        </div>

        <!-- 動畫 -->
        <div>
            <label class="block text-sm font-medium text-gray-600 mb-2">動畫效果</label>
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="opt in animations"
                    :key="opt.value"
                    class="px-4 py-1.5 text-sm rounded-md border transition-colors"
                    :class="settings.animation === opt.value
                        ? 'bg-indigo-600 text-white border-indigo-600'
                        : 'bg-white text-gray-700 border-gray-300 hover:border-indigo-400'"
                    @click="update('animation', opt.value)"
                >
                    {{ opt.label }}
                </button>
            </div>
        </div>
    </div>
</template>
