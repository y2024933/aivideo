#!/bin/sh
# 修正 filament-tiptap-editor TextAlign renderHTML bug
# 問題1：已有 inline style text-align 的元素無法切換對齊
# 問題2：靠左對齊（start）等於預設值時 renderHTML 回傳 {} 導致無法覆蓋既有 style
# 修正：只要有 textAlign 值就輸出 style，不特殊處理預設值
FILE="vendor/awcodes/filament-tiptap-editor/resources/dist/filament-tiptap-editor.js"
if [ -f "$FILE" ]; then
    # 先修原始 bug（如果是全新安裝）
    sed -i 's#renderHTML:t=>t\.style&&t\.style\.includes("text-align")?{}:t\.textAlign===this\.options\.defaultAlignment?{}:{style:`text-align: ${t\.textAlign}`}#renderHTML:t=>!t.textAlign?{}:{style:`text-align: ${t.textAlign}`}#' "$FILE"
    echo "✓ TipTap TextAlign bug patched"
fi
