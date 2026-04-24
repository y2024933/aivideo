import { Extension } from '@tiptap/core'

/**
 * 修正 filament-tiptap-editor 內建 TextAlign extension 的 renderHTML bug。
 * 原始邏輯：當元素已有 inline style text-align 時，renderHTML 回傳 {} 導致對齊無法切換。
 * 此 extension 覆蓋 textAlign attribute 的 renderHTML，正確根據 attribute 值輸出。
 */
const TextAlignFix = Extension.create({
    name: 'textAlignFix',

    addGlobalAttributes() {
        return [{
            types: ['heading', 'paragraph'],
            attributes: {
                textAlign: {
                    default: 'start',
                    parseHTML: el => el.style.textAlign || 'start',
                    renderHTML: attrs => {
                        if (!attrs.textAlign || attrs.textAlign === 'start') return {}
                        return { style: `text-align: ${attrs.textAlign}` }
                    },
                },
            },
        }]
    },
})

window.TiptapEditorExtensions = window.TiptapEditorExtensions || {}
window.TiptapEditorExtensions['textAlignFix'] = [TextAlignFix]
