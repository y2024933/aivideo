import { Extension } from '@tiptap/core'

const FontSize = Extension.create({
    name: 'fontSize',

    addOptions() {
        return { types: ['textStyle'] }
    },

    addGlobalAttributes() {
        return [{
            types: this.options.types,
            attributes: {
                fontSize: {
                    default: null,
                    parseHTML: el => el.style.fontSize?.replace(/['"]+/g, '') || null,
                    renderHTML: attrs => attrs.fontSize ? { style: `font-size: ${attrs.fontSize}` } : {},
                },
            },
        }]
    },

    addCommands() {
        return {
            setFontSize: fontSize => ({ chain }) => chain().setMark('textStyle', { fontSize }).run(),
            unsetFontSize: () => ({ chain }) => chain().setMark('textStyle', { fontSize: null }).removeEmptyTextStyle().run(),
        }
    },
})

// 註冊到 filament-tiptap-editor 的自訂 extension 注入點
window.TiptapEditorExtensions = window.TiptapEditorExtensions || {}
window.TiptapEditorExtensions['fontSize'] = [FontSize]
