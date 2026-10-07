<?php

declare(strict_types=1);

/*
 * 一次性建置工具：產生 config/compliance/simplified_chars.php 與 glyph_variants.php。
 *
 * 不在 production code path 上，執行期不會被呼叫，產物已 commit 進 repo
 * （測試不聯網是本專案的硬規則）。
 *
 * 用法：php scripts/build-simplified-chars.php
 * 需要 scripts/stc.txt 與 scripts/tw_TWVariants.txt（來自 OpenCC，Apache-2.0）：
 *   curl -O https://raw.githubusercontent.com/BYVoid/OpenCC/master/data/dictionary/STCharacters.txt
 *   curl -O https://raw.githubusercontent.com/BYVoid/OpenCC/master/data/dictionary/TWVariants.txt
 */

$dir = __DIR__;
$out = dirname(__DIR__) . '/config/compliance';

$load = static function (string $file): array {
    $map = [];
    foreach (file($file, FILE_IGNORE_NEW_LINES) as $line) {
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        [$key, $value] = array_pad(explode("\t", $line, 2), 2, '');
        $candidates = preg_split('/\s+/u', trim($value), -1, PREG_SPLIT_NO_EMPTY);
        if ($candidates) {
            $map[$key] = $candidates;
        }
    }

    return $map;
};

/*
 * 字形變體表的人工排除清單。
 *
 * OpenCC TWVariants.txt 有兩類問題條目：
 *   1. 自我對應（梁 => 梁、麼 => 麼 么 的第一候選是自己）
 *   2. 方向有疑義：樑 => 梁，但教育部標準「橋樑、屋樑」用樑
 * 另有幾個在臺灣屬正常用字、Noto TC 也有 glyph 的，列入會造成高頻誤判。
 */
const GLYPH_VARIANT_EXCLUDE = [
    '梁' => '自我對應',
    '麼' => '自我對應（第一候選是自己）',
    '樑' => '教育部標準「橋樑、屋樑」用樑',
    '污' => '防污／污染在臺灣廣泛使用',
    '蔘' => '人蔘／高麗蔘是臺灣標準用法',
    '泄' => '排泄在臺灣用泄',
    '痹' => '麻痹／麻痺並用，電商場景無差異',
    '癡' => '教育部標準是癡（白癡），OpenCC 這條方向有疑義',
];

$stc = $load("$dir/stc.txt");
$tw = $load("$dir/tw_TWVariants.txt");
$twStandard = array_map(static fn (array $c): string => $c[0], $tw);

$simplifiedOnly = [];
foreach ($stc as $key => $candidates) {
    // key 本身也是合法繁體（如 台里表谷只后夫秋干云面系布、姓氏 范于杰沈）→ 排除，否則大量誤判
    if (in_array($key, $candidates, true)) {
        continue;
    }
    // 候選字套台灣標準字形，避免建議香港慣用字（啓→啟、着→著、衆→眾、裏→裡）
    $candidates = array_values(array_unique(array_map(static fn (string $c): string => $twStandard[$c] ?? $c, $candidates)));
    if (in_array($key, $candidates, true)) {
        continue;
    }
    $simplifiedOnly[$key] = $candidates;
}
ksort($simplifiedOnly);

printf("簡體專用字 %d（原始 %d，排除同時是繁體者 %d）\n", count($simplifiedOnly), count($stc), count($stc) - count($simplifiedOnly));
$glyph = [];
foreach ($tw as $key => $candidates) {
    if ($candidates[0] === $key || isset(GLYPH_VARIANT_EXCLUDE[$key])) {
        continue;
    }
    $glyph[$key] = $candidates[0];
}
printf("字形變體 %d（原始 %d，排除 %d）\n", count($glyph), count($tw), count($tw) - count($glyph));
printf("\n⚠️ 產物請人工 review 後再 commit：%s\n", $out);
