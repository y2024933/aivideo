<script setup>
/**
 * @props {{ global: string, perShot: Object }} modelValue
 * @props {Array} shots - 鏡頭列表
 * @emits update:modelValue
 */
import { computed } from 'vue'

const props = defineProps({
    modelValue: {
        type: Object,
        required: true,
    },
    shots: {
        type: Array,
        default: () => [],
    },
})

const emit = defineEmits(['update:modelValue'])

const settings = computed(() => props.modelValue)

function update(key, value) {
    emit('update:modelValue', { ...props.modelValue, [key]: value })
}

function updatePerShot(shotId, value) {
    const perShot = { ...(props.modelValue.perShot || {}), [shotId]: value }
    emit('update:modelValue', { ...props.modelValue, perShot })
}

const transitions = [
    { value: 'cut', label: '硬切' },
    { value: 'crossfade', label: '淡入淡出' },
    { value: 'slideLeft', label: '左滑' },
    { value: 'slideRight', label: '右滑' },
    { value: 'slideUp', label: '上滑' },
]
</script>

<template>
    <div class="bg-white rounded-lg border p-6">
        <h3 class="font-semibold text-gray-800 mb-4">轉場設定</h3>

        <!-- 全域轉場 -->
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-600 mb-2">全域轉場效果</label>
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="opt in transitions"
                    :key="opt.value"
                    class="px-4 py-1.5 text-sm rounded-md border transition-colors"
                    :class="settings.global === opt.value
                        ? 'bg-indigo-600 text-white border-indigo-600'
                        : 'bg-white text-gray-700 border-gray-300 hover:border-indigo-400'"
                    @click="update('global', opt.value)"
                >
                    {{ opt.label }}
                </button>
            </div>
        </div>

        <!-- 逐鏡覆寫 -->
        <div v-if="shots.length">
            <label class="block text-sm font-medium text-gray-600 mb-2">逐鏡覆寫（可選）</label>
            <div class="space-y-2 max-h-48 overflow-y-auto">
                <div v-for="shot in shots" :key="shot.id" class="flex items-center gap-3">
                    <span class="text-sm text-gray-700 w-16 shrink-0">{{ shot.shot_id }}</span>
                    <select
                        :value="settings.perShot?.[shot.id] || ''"
                        class="text-sm border-gray-300 rounded-md flex-1"
                        @change="updatePerShot(shot.id, $event.target.value)"
                    >
                        <option value="">跟全域</option>
                        <option v-for="opt in transitions" :key="opt.value" :value="opt.value">
                            {{ opt.label }}
                        </option>
                    </select>
                </div>
            </div>
        </div>
    </div>
</template>
