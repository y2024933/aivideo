<?php

return [

    'builder' => [
        'actions' => [
            'clone' => ['label' => '複製'],
            'add' => ['label' => '新增至 :label', 'modal' => ['heading' => '新增至 :label', 'actions' => ['add' => ['label' => '新增']]]],
            'add_between' => ['label' => '在區塊之間插入', 'modal' => ['heading' => '新增至 :label', 'actions' => ['add' => ['label' => '新增']]]],
            'delete' => ['label' => '刪除'],
            'edit' => ['label' => '編輯', 'modal' => ['heading' => '編輯區塊', 'actions' => ['save' => ['label' => '儲存變更']]]],
            'reorder' => ['label' => '移動'],
            'move_down' => ['label' => '下移'],
            'move_up' => ['label' => '上移'],
            'collapse' => ['label' => '收合'],
            'expand' => ['label' => '展開'],
            'collapse_all' => ['label' => '全部收合'],
            'expand_all' => ['label' => '全部展開'],
        ],
    ],

    'checkbox_list' => [
        'actions' => [
            'deselect_all' => ['label' => '取消全選'],
            'select_all' => ['label' => '全選'],
        ],
    ],

    'file_upload' => [
        'editor' => [
            'actions' => [
                'cancel' => ['label' => '取消'],
                'drag_crop' => ['label' => '拖曳模式「裁切」'],
                'drag_move' => ['label' => '拖曳模式「移動」'],
                'flip_horizontal' => ['label' => '水平翻轉'],
                'flip_vertical' => ['label' => '垂直翻轉'],
                'move_down' => ['label' => '下移圖片'],
                'move_left' => ['label' => '左移圖片'],
                'move_right' => ['label' => '右移圖片'],
                'move_up' => ['label' => '上移圖片'],
                'reset' => ['label' => '重設'],
                'rotate_left' => ['label' => '向左旋轉'],
                'rotate_right' => ['label' => '向右旋轉'],
                'set_aspect_ratio' => ['label' => '設定比例為 :ratio'],
                'save' => ['label' => '儲存'],
                'zoom_100' => ['label' => '縮放至 100%'],
                'zoom_in' => ['label' => '放大'],
                'zoom_out' => ['label' => '縮小'],
            ],
            'fields' => [
                'height' => ['label' => '高度', 'unit' => 'px'],
                'rotation' => ['label' => '旋轉', 'unit' => 'deg'],
                'width' => ['label' => '寬度', 'unit' => 'px'],
                'x_position' => ['label' => 'X', 'unit' => 'px'],
                'y_position' => ['label' => 'Y', 'unit' => 'px'],
            ],
            'aspect_ratios' => [
                'label' => '長寬比',
                'no_fixed' => ['label' => '自由'],
            ],
            'svg' => [
                'messages' => [
                    'confirmation' => '不建議編輯 SVG 檔案，縮放時可能造成品質損失。\n 確定要繼續嗎？',
                    'disabled' => '已停用 SVG 檔案編輯，因為縮放時可能造成品質損失。',
                ],
            ],
        ],
    ],

    'key_value' => [
        'actions' => [
            'add' => ['label' => '新增列'],
            'delete' => ['label' => '刪除列'],
            'reorder' => ['label' => '排序列'],
        ],
        'fields' => [
            'key' => ['label' => '鍵'],
            'value' => ['label' => '值'],
        ],
    ],

    'markdown_editor' => [
        'toolbar_buttons' => [
            'attach_files' => '附加檔案',
            'blockquote' => '引用',
            'bold' => '粗體',
            'bullet_list' => '項目清單',
            'code_block' => '程式碼',
            'heading' => '標題',
            'italic' => '斜體',
            'link' => '連結',
            'ordered_list' => '編號清單',
            'redo' => '重做',
            'strike' => '刪除線',
            'table' => '表格',
            'undo' => '復原',
        ],
    ],

    'radio' => [
        'boolean' => ['true' => '是', 'false' => '否'],
    ],

    'repeater' => [
        'actions' => [
            'add' => ['label' => '新增至 :label'],
            'add_between' => ['label' => '在之間插入'],
            'delete' => ['label' => '刪除'],
            'clone' => ['label' => '複製'],
            'reorder' => ['label' => '移動'],
            'move_down' => ['label' => '下移'],
            'move_up' => ['label' => '上移'],
            'collapse' => ['label' => '收合'],
            'expand' => ['label' => '展開'],
            'collapse_all' => ['label' => '全部收合'],
            'expand_all' => ['label' => '全部展開'],
        ],
    ],

    'rich_editor' => [
        'dialogs' => [
            'link' => [
                'actions' => ['link' => '連結', 'unlink' => '取消連結'],
                'label' => '網址',
                'placeholder' => '輸入網址',
            ],
        ],
        'toolbar_buttons' => [
            'attach_files' => '附加檔案',
            'blockquote' => '引用',
            'bold' => '粗體',
            'bullet_list' => '項目清單',
            'code_block' => '程式碼',
            'h1' => '大標題',
            'h2' => '標題',
            'h3' => '小標題',
            'italic' => '斜體',
            'link' => '連結',
            'ordered_list' => '編號清單',
            'redo' => '重做',
            'strike' => '刪除線',
            'underline' => '底線',
            'undo' => '復原',
        ],
    ],

    'select' => [
        'actions' => [
            'create_option' => ['label' => '新增', 'modal' => ['heading' => '新增', 'actions' => ['create' => ['label' => '新增'], 'create_another' => ['label' => '建立並繼續新增']]]],
            'edit_option' => ['label' => '編輯', 'modal' => ['heading' => '編輯', 'actions' => ['save' => ['label' => '儲存']]]],
        ],
        'boolean' => ['true' => '是', 'false' => '否'],
        'loading_message' => '載入中...',
        'max_items_message' => '最多只能選擇 :count 項。',
        'no_search_results_message' => '沒有符合搜尋條件的選項。',
        'placeholder' => '請選擇',
        'searching_message' => '搜尋中...',
        'search_prompt' => '開始輸入以搜尋...',
    ],

    'tags_input' => [
        'placeholder' => '新增標籤',
    ],

    'text_input' => [
        'actions' => [
            'hide_password' => ['label' => '隱藏密碼'],
            'show_password' => ['label' => '顯示密碼'],
        ],
    ],

    'toggle_buttons' => [
        'boolean' => ['true' => '是', 'false' => '否'],
    ],

    'wizard' => [
        'actions' => [
            'previous_step' => ['label' => '上一步'],
            'next_step' => ['label' => '下一步'],
        ],
    ],

];
