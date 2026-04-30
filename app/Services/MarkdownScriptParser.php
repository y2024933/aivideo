<?php

declare(strict_types=1);

namespace App\Services;

/**
 * 解析 Markdown 腳本，回傳結構化資料。
 * 支援兩種格式：
 *   A) 分區式（鏡頭時長表 / Flux Prompt / Kling / 配音稿 / 字幕 各自獨立區塊）
 *   B) 內嵌式（每個 ### S0X 區塊內含 Bing prompt + Kling 動畫）
 */
final class MarkdownScriptParser
{
    /**
     * @return array{name: string, builder_name: string, character_nickname: string, character_dna: string, shots: list<array>}
     */
    public function parse(string $mdText): array
    {
        $text = str_replace("\r\n", "\n", $mdText);

        // --- Metadata ---
        $name = $this->extractMeta($text, '建案');
        $builderName = $this->extractMeta($text, '建商');
        $characterNickname = $this->extractMeta($text, '主角');

        // --- 角色 DNA ---
        $characterDna = $this->extractCharacterDna($text);

        // --- 鏡頭時長表 ---
        $durationMap = $this->parseDurationTable($text);

        // --- 偵測格式：分區式 or 內嵌式 ---
        $hasFluxSection = (bool) preg_match('/##[^#]*(?:Flux|Bing\/Flux)\s*Prompt/i', $text);
        $hasKlingSection = (bool) preg_match('/##[^#]*Kling\s*動畫/i', $text);

        $fluxMap = [];
        $klingMap = [];
        $inlineShots = [];

        if ($hasFluxSection) {
            // 格式 A：分區式
            $fluxMap = $this->parseFluxSection($text);
            $klingMap = $hasKlingSection ? $this->parseKlingSection($text) : [];
        } else {
            // 格式 B：內嵌式
            $inlineShots = $this->parseInlineShots($text);
        }

        // --- 配音稿 ---
        $voiceoverMap = $this->parseVoiceover($text);

        // --- 字幕 SRT ---
        $subtitleList = $this->parseSubtitles($text);

        // --- 組合 shots ---
        $shotIds = $this->collectShotIds($durationMap, $fluxMap, $klingMap, $inlineShots, $voiceoverMap);

        $shots = [];
        foreach (array_values($shotIds) as $idx => $sid) {
            $inline = $inlineShots[$sid] ?? [];
            $shots[] = [
                'shot_id' => $sid,
                'shot_order' => $idx + 1,
                'duration_seconds' => $durationMap[$sid] ?? ($inline['duration_seconds'] ?? 5),
                'scene_description' => $durationMap[$sid . '_desc'] ?? ($inline['scene_description'] ?? ''),
                'flux_prompt' => $fluxMap[$sid] ?? ($inline['flux_prompt'] ?? ''),
                'kling_prompt' => $klingMap[$sid] ?? ($inline['kling_prompt'] ?? ''),
                'voiceover_text' => $voiceoverMap[$sid]['text'] ?? '',
                'subtitle' => $subtitleList[$idx] ?? '',
                'emotion' => $voiceoverMap[$sid]['emotion'] ?? ($inline['emotion'] ?? ''),
            ];
        }

        return [
            'name' => $name,
            'builder_name' => $builderName,
            'character_nickname' => $characterNickname,
            'character_dna' => $characterDna,
            'shots' => $shots,
        ];
    }

    // ========== 內部輔助方法 ==========

    /** 從 blockquote 或粗體行提取 metadata */
    private function extractMeta(string $text, string $key): string
    {
        // 匹配 **建案**：XXX 或 **建案**:XXX（全形/半形冒號都支援）
        if (! preg_match('/\*\*' . preg_quote($key, '/') . '\*\*[：:]\s*(.+?)(?:\s*[｜|]|\s*$)/mu', $text, $m)) {
            return '';
        }

        // 清除括號內的附註，例如「（虛構，練習用）」
        return trim(preg_replace('/（[^）]*）/u', '', $m[1]));
    }

    /** 提取角色 DNA：找到含「DNA」的區塊，取第一個 code block */
    private function extractCharacterDna(string $text): string
    {
        $section = $this->findSection($text, '/DNA/i');
        if ($section === null) {
            return '';
        }

        if (! preg_match('/```[\s\S]*?\n([\s\S]*?)```/', $section, $m)) {
            return '';
        }

        return trim($m[1]);
    }

    /** 找到以 ## 開頭且標題匹配 pattern 的區塊（到下一個 ## 為止） */
    private function findSection(string $text, string $pattern): ?string
    {
        $sections = preg_split('/^(?=##\s)/m', $text);

        foreach ($sections as $section) {
            $firstLine = strtok($section, "\n");
            if (preg_match($pattern, $firstLine)) {
                return $section;
            }
        }

        return null;
    }

    /** 解析鏡頭時長表 */
    private function parseDurationTable(string $text): array
    {
        $section = $this->findSection($text, '/鏡頭時長/i');
        if ($section === null) {
            return [];
        }

        $map = [];
        if (preg_match_all('/S(\d{2})\s*\((\d+)s\)\s*(.+)/m', $section, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $sid = 'S' . $m[1];
                $map[$sid] = (int) $m[2];
                $map[$sid . '_desc'] = trim($m[3]);
            }
        }

        return $map;
    }

    /** 格式 A：解析分區式 Flux Prompt 區塊 */
    private function parseFluxSection(string $text): array
    {
        $section = $this->findSection($text, '/(?:Flux|Bing\/Flux)\s*Prompt/i');
        if ($section === null) {
            return [];
        }

        $map = [];
        // 按 ### S0X 切割
        $parts = preg_split('/###\s+S(\d{2})/i', $section, -1, PREG_SPLIT_DELIM_CAPTURE);

        for ($i = 1; $i < count($parts); $i += 2) {
            $sid = 'S' . $parts[$i];
            $content = $parts[$i + 1] ?? '';
            if (preg_match('/```[\s\S]*?\n([\s\S]*?)```/', $content, $m)) {
                $map[$sid] = trim($m[1]);
            }
        }

        return $map;
    }

    /** 格式 A：解析分區式 Kling 動畫指令 */
    private function parseKlingSection(string $text): array
    {
        $section = $this->findSection($text, '/Kling\s*動畫/i');
        if ($section === null) {
            return [];
        }

        $map = [];

        // 嘗試 code block 內的格式
        if (preg_match('/```[\s\S]*?\n([\s\S]*?)```/', $section, $codeBlock)) {
            $lines = explode("\n", $codeBlock[1]);
            $currentSid = null;
            $currentLines = [];

            $flush = function () use (&$currentSid, &$currentLines, &$map): void {
                if ($currentSid && count($currentLines) > 0) {
                    // S07a/S07b 合併到 S07
                    $baseSid = preg_replace('/[ab]$/', '', $currentSid);
                    $content = trim(implode("\n", $currentLines));
                    $map[$baseSid] = isset($map[$baseSid])
                        ? $map[$baseSid] . "\n" . $content
                        : $content;
                }
                $currentLines = [];
            };

            foreach ($lines as $line) {
                if (preg_match('/^S(\d{2}[ab]?)\s*(?:\[.*?\]\s*)?(?:[：:,]|(?:\s*\())/i', $line, $sidMatch)) {
                    $flush();
                    $currentSid = 'S' . substr($sidMatch[1], 0, 2);

                    // 取冒號後的內容
                    $afterPrefix = preg_replace('/^S\d{2}[ab]?\s*(?:\[.*?\]\s*)?[：:]\s*/', '', $line);
                    if ($afterPrefix !== $line) {
                        $currentLines[] = $afterPrefix;
                    } else {
                        $currentLines[] = preg_replace('/^S\d{2}[ab]?\s*(?:\[.*?\]\s*)?[,]\s*/', '', $line);
                    }
                } elseif (trim($line) !== '') {
                    $currentLines[] = $line;
                }
            }
            $flush();
        } else {
            // 非 code block 格式：逐行 S0X: ...
            if (preg_match_all('/S(\d{2})[ab]?[：:]\s*(.+)/m', $section, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $m) {
                    $sid = 'S' . $m[1];
                    $map[$sid] = isset($map[$sid])
                        ? $map[$sid] . "\n" . trim($m[2])
                        : trim($m[2]);
                }
            }
        }

        return $map;
    }

    /** 格式 B：解析內嵌式的各鏡頭 */
    private function parseInlineShots(string $text): array
    {
        $section = $this->findSection($text, '/鏡頭.*腳本|腳本.*鏡頭/iu');
        if ($section === null) {
            return [];
        }

        $map = [];
        $parts = preg_split('/###\s+S(\d{2})\s/', $section, -1, PREG_SPLIT_DELIM_CAPTURE);

        for ($i = 1; $i < count($parts); $i += 2) {
            $sid = 'S' . $parts[$i];
            $content = $parts[$i + 1] ?? '';

            // 提取所有 code block
            preg_match_all('/```[\s\S]*?\n([\s\S]*?)```/', $content, $codeBlocks);
            $blocks = array_map('trim', $codeBlocks[1] ?? []);

            // 提取情緒
            $emotion = '';
            if (preg_match('/\*\*情緒\*\*[：:]\s*(.+)/m', $content, $em)) {
                $emotion = trim($em[1]);
            }

            // 提取場景描述
            $sceneDesc = '';
            if (preg_match('/\*\*畫面\*\*[：:]\s*(.+)/m', $content, $sm)) {
                $sceneDesc = trim($sm[1]);
            }

            $map[$sid] = [
                'flux_prompt' => $blocks[0] ?? '',
                'kling_prompt' => $blocks[1] ?? '',
                'emotion' => $emotion,
                'scene_description' => $sceneDesc,
            ];
        }

        return $map;
    }

    /** 解析配音稿區塊 */
    private function parseVoiceover(string $text): array
    {
        $section = $this->findSection($text, '/配音稿/i');
        if ($section === null) {
            return [];
        }

        // 配音稿可能包在 code block 裡，先提取 code block 內容
        if (preg_match('/```[\s\S]*?\n([\s\S]*?)```/', $section, $cb)) {
            $section = $cb[1];
        }

        $map = [];
        $parts = preg_split('/S(\d{2})\s*─+/u', $section, -1, PREG_SPLIT_DELIM_CAPTURE);

        for ($i = 1; $i < count($parts); $i += 2) {
            $sid = 'S' . $parts[$i];
            $block = $parts[$i + 1] ?? '';

            // 提取情緒：（XXX）
            $emotion = '';
            if (preg_match('/（([^）]+)）/u', $block, $em)) {
                $emotion = trim($em[1]);
            }

            // 提取旁白文本：去掉情緒標記行、短停標記行、殘留分隔線
            $lines = array_filter(
                array_map('trim', explode("\n", $block)),
                fn (string $l) => $l !== ''
                    && ! str_starts_with($l, '（')
                    && ! str_starts_with($l, '〔')
                    && ! preg_match('/^─+$/u', $l)
            );
            $voiceText = trim(implode("\n", $lines));

            $map[$sid] = ['text' => $voiceText, 'emotion' => $emotion];
        }

        return $map;
    }

    /** 解析字幕 SRT 區塊 */
    private function parseSubtitles(string $text): array
    {
        $section = $this->findSection($text, '/字幕/i');
        if ($section === null) {
            return [];
        }

        if (! preg_match('/```(?:srt)?\s*\n([\s\S]*?)```/', $section, $codeBlock)) {
            return [];
        }

        $srtText = trim($codeBlock[1]);
        $blocks = preg_split('/\n\n+/', $srtText);

        $subtitles = [];
        foreach ($blocks as $block) {
            $lines = array_filter(array_map('trim', explode("\n", $block)), fn ($l) => $l !== '');
            // 跳過序號行和時間碼行，取剩餘行
            $contentLines = array_filter($lines, fn ($l) => ! preg_match('/^\d+$/', $l) && ! str_contains($l, '-->'));
            $content = trim(implode("\n", $contentLines));
            if ($content !== '') {
                $subtitles[] = $content;
            }
        }

        return $subtitles;
    }

    /** 收集所有 shot ID 並排序 */
    private function collectShotIds(array ...$sources): array
    {
        $ids = [];
        foreach ($sources as $src) {
            foreach (array_keys($src) as $key) {
                if (preg_match('/^(S\d{2})$/', (string) $key, $m)) {
                    $ids[$m[1]] = true;
                }
            }
        }

        $keys = array_keys($ids);
        sort($keys);

        return $keys;
    }
}
