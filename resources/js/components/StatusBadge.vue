<script setup>
const props = defineProps({
    status: String,
})

const config = {
    draft: { label: '草稿', color: 'bg-gray-100 text-gray-700' },
    character_generating: { label: '角色生成中', color: 'bg-yellow-100 text-yellow-700' },
    character_pending_review: { label: '角色待審核', color: 'bg-blue-100 text-blue-700' },
    character_failed: { label: '角色失敗', color: 'bg-red-100 text-red-700' },
    character_approved: { label: '角色已核准', color: 'bg-green-100 text-green-700' },
    script_pending_review: { label: '腳本待審核', color: 'bg-blue-100 text-blue-700' },
    images_generating: { label: '場景圖生成中', color: 'bg-yellow-100 text-yellow-700' },
    images_partial: { label: '部分場景圖失敗', color: 'bg-orange-100 text-orange-700' },
    images_pending_review: { label: '場景圖待審核', color: 'bg-blue-100 text-blue-700' },
    images_approved: { label: '場景圖已核准', color: 'bg-green-100 text-green-700' },
    producing_final: { label: '產出中', color: 'bg-yellow-100 text-yellow-700' },
    final_pending_review: { label: '成品待審核', color: 'bg-blue-100 text-blue-700' },
    completed: { label: '完成', color: 'bg-green-100 text-green-700' },
}

const badge = config[props.status] ?? { label: props.status, color: 'bg-gray-100 text-gray-700' }
</script>

<template>
    <span :class="['inline-block px-2 py-0.5 rounded-full text-xs font-medium', badge.color]">
        {{ badge.label }}
    </span>
</template>
