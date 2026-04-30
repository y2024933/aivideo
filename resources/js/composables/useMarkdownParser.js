/**
 * 解析 Markdown 腳本，回傳結構化資料
 * 支援兩種格式：
 *   A) 分區式（鏡頭時長表 / Flux Prompt / Kling / 配音稿 / 字幕 各自獨立區塊）
 *   B) 內嵌式（每個 ### S0X 區塊內含 Bing prompt + Kling 動畫）
 */

/**
 * @param {string} mdText - 完整的 Markdown 腳本內容
 * @returns {{ name: string, builder_name: string, character_nickname: string, character_dna: string, shots: Array }}
 */
export function parseScriptMarkdown(mdText) {
    const text = mdText.replace(/\r\n/g, '\n')

    // --- Metadata ---
    const name = extractMeta(text, '建案') || ''
    const builder_name = extractMeta(text, '建商') || ''
    const character_nickname = extractMeta(text, '主角') || ''

    // --- 角色 DNA ---
    const character_dna = extractCharacterDna(text)

    // --- 鏡頭時長表 ---
    const durationMap = parseDurationTable(text)

    // --- 偵測格式：分區式 or 內嵌式 ---
    const hasFluxSection = /##[^#]*(?:Flux|Bing\/Flux)\s*Prompt/i.test(text)
    const hasKlingSection = /##[^#]*Kling\s*動畫/i.test(text)

    let fluxMap, klingMap, inlineShots
    if (hasFluxSection) {
        // 格式 A：分區式
        fluxMap = parseFluxSection(text)
        klingMap = hasKlingSection ? parseKlingSection(text) : {}
        inlineShots = null
    } else {
        // 格式 B：內嵌式（每個 ### S0X 內含 prompt）
        inlineShots = parseInlineShots(text)
        fluxMap = {}
        klingMap = {}
    }

    // --- 配音稿 ---
    const voiceoverMap = parseVoiceover(text)

    // --- 字幕 SRT ---
    const subtitleList = parseSubtitles(text)

    // --- 組合 shots ---
    const shotIds = collectShotIds(durationMap, fluxMap, klingMap, inlineShots, voiceoverMap)
    const shots = shotIds.map((sid, idx) => {
        const inline = inlineShots?.[sid] || {}
        return {
            shot_id: sid,
            shot_order: idx + 1,
            duration_seconds: durationMap[sid] ?? inline.duration_seconds ?? 5,
            scene_description: durationMap[sid + '_desc'] ?? inline.scene_description ?? '',
            flux_prompt: fluxMap[sid] ?? inline.flux_prompt ?? '',
            kling_prompt: klingMap[sid] ?? inline.kling_prompt ?? '',
            voiceover_text: voiceoverMap[sid]?.text ?? '',
            subtitle: subtitleList[idx] ?? '',
            emotion: voiceoverMap[sid]?.emotion ?? inline.emotion ?? '',
        }
    })

    return { name, builder_name, character_nickname, character_dna, shots }
}

// ========== 內部輔助函數 ==========

/** 從 blockquote 或粗體行提取 metadata */
function extractMeta(text, key) {
    // 匹配 **建案**：XXX 或 **建案**:XXX（全形/半形冒號都支援）
    const re = new RegExp(`\\*\\*${key}\\*\\*[：:]\\s*(.+?)(?:\\s*[｜|]|\\s*$)`, 'm')
    const m = text.match(re)
    if (!m) return null
    // 清除括號內的附註，例如「（虛構，練習用）」
    return m[1].replace(/（[^）]*）/g, '').trim()
}

/** 提取角色 DNA：找到「角色 DNA」區塊內的第一個 code block */
function extractCharacterDna(text) {
    // 找含有「DNA」的 ## 區塊
    const dnaSection = findSection(text, /DNA/i)
    if (!dnaSection) return ''
    const codeBlock = dnaSection.match(/```[\s\S]*?\n([\s\S]*?)```/)
    return codeBlock ? codeBlock[1].trim() : ''
}

/** 找到以 ## 開頭且標題匹配 pattern 的區塊內容（到下一個 ## 為止） */
function findSection(text, pattern) {
    const sections = text.split(/^(?=##\s)/m)
    return sections.find(s => pattern.test(s.split('\n')[0])) || null
}

/** 找到以 ## 開頭且標題匹配 pattern 的所有區塊 */
function findSections(text, pattern) {
    const sections = text.split(/^(?=##\s)/m)
    return sections.filter(s => pattern.test(s.split('\n')[0]))
}

/** 解析鏡頭時長表 */
function parseDurationTable(text) {
    const section = findSection(text, /鏡頭時長/i)
    if (!section) return {}
    const map = {}
    const re = /S(\d{2})\s*\((\d+)s\)\s*(.+)/g
    let m
    while ((m = re.exec(section)) !== null) {
        const sid = `S${m[1]}`
        map[sid] = parseInt(m[2], 10)
        map[sid + '_desc'] = m[3].trim()
    }
    return map
}

/** 格式 A：解析分區式 Flux Prompt 區塊 */
function parseFluxSection(text) {
    const section = findSection(text, /(?:Flux|Bing\/Flux)\s*Prompt/i)
    if (!section) return {}
    const map = {}
    // 按 ### S0X 切割
    const parts = section.split(/###\s+S(\d{2})/i)
    for (let i = 1; i < parts.length; i += 2) {
        const sid = `S${parts[i]}`
        const content = parts[i + 1] || ''
        const codeBlock = content.match(/```[\s\S]*?\n([\s\S]*?)```/)
        if (codeBlock) map[sid] = codeBlock[1].trim()
    }
    return map
}

/** 格式 A：解析分區式 Kling 動畫指令 */
function parseKlingSection(text) {
    const section = findSection(text, /Kling\s*動畫/i)
    if (!section) return {}
    const map = {}
    // 嘗試 code block 內的格式
    const codeBlock = section.match(/```[\s\S]*?\n([\s\S]*?)```/)
    if (codeBlock) {
        const lines = codeBlock[1].split('\n')
        let currentSid = null
        let currentLines = []

        const flush = () => {
            if (currentSid && currentLines.length) {
                // S07a/S07b 合併到 S07
                const baseSid = currentSid.replace(/[ab]$/, '')
                map[baseSid] = map[baseSid]
                    ? map[baseSid] + '\n' + currentLines.join('\n').trim()
                    : currentLines.join('\n').trim()
            }
            currentLines = []
        }

        for (const line of lines) {
            const sidMatch = line.match(/^S(\d{2}[ab]?)\s*(?:\[.*?\]\s*)?(?:[：:,]|(?:\s*\())/i)
            if (sidMatch) {
                flush()
                currentSid = `S${sidMatch[1].substring(0, 2)}`
                // 如果是 S07a: ... 格式，冒號後面的也是內容
                const afterPrefix = line.replace(/^S\d{2}[ab]?\s*(?:\[.*?\]\s*)?[：:]\s*/, '')
                if (afterPrefix !== line) {
                    currentLines.push(afterPrefix)
                } else {
                    currentLines.push(line.replace(/^S\d{2}[ab]?\s*(?:\[.*?\]\s*)?[,]\s*/, ''))
                }
            } else if (line.trim()) {
                currentLines.push(line)
            }
        }
        flush()
    } else {
        // 非 code block 格式：逐行 S0X: ...
        const re = /S(\d{2})[ab]?[：:]\s*(.+)/g
        let m
        while ((m = re.exec(section)) !== null) {
            const sid = `S${m[1]}`
            map[sid] = map[sid] ? map[sid] + '\n' + m[2].trim() : m[2].trim()
        }
    }
    return map
}

/** 格式 B：解析內嵌式的各鏡頭（每個 ### S0X 內含 Bing prompt + Kling 動畫） */
function parseInlineShots(text) {
    // 找到包含鏡頭詳細腳本的大區塊
    const section = findSection(text, /鏡頭.*腳本|腳本.*鏡頭/i)
    if (!section) return {}

    const map = {}
    // 按 ### S0X 切割
    const parts = section.split(/###\s+S(\d{2})\s/)
    for (let i = 1; i < parts.length; i += 2) {
        const sid = `S${parts[i]}`
        const content = parts[i + 1] || ''

        // 提取所有 code block
        const codeBlocks = [...content.matchAll(/```[\s\S]*?\n([\s\S]*?)```/g)].map(m => m[1].trim())

        // 第一個 code block 通常是 Bing/Flux prompt
        const flux_prompt = codeBlocks[0] || ''
        // 第二個 code block 通常是 Kling 動畫
        const kling_prompt = codeBlocks[1] || ''

        // 提取情緒
        const emotionMatch = content.match(/\*\*情緒\*\*[：:]\s*(.+)/m)
        const emotion = emotionMatch ? emotionMatch[1].trim() : ''

        // 提取場景描述
        const sceneMatch = content.match(/\*\*畫面\*\*[：:]\s*(.+)/m)
        const scene_description = sceneMatch ? sceneMatch[1].trim() : ''

        map[sid] = { flux_prompt, kling_prompt, emotion, scene_description }
    }
    return map
}

/** 解析配音稿區塊 */
function parseVoiceover(text) {
    const section = findSection(text, /配音稿/i)
    if (!section) return {}

    const map = {}
    // 按 S0X ─ 分割
    const parts = section.split(/S(\d{2})\s*─+/)
    for (let i = 1; i < parts.length; i += 2) {
        const sid = `S${parts[i]}`
        const block = parts[i + 1] || ''

        // 提取情緒：（XXX）
        const emotionMatch = block.match(/（([^）]+)）/)
        const emotion = emotionMatch ? emotionMatch[1].trim() : ''

        // 提取旁白文本：去掉情緒標記行和空行
        const lines = block.split('\n')
            .map(l => l.trim())
            .filter(l => l && !l.startsWith('（') && !l.startsWith('〔'))
        const voiceText = lines.join('\n').trim()

        map[sid] = { text: voiceText, emotion }
    }
    return map
}

/** 解析字幕 SRT 區塊 */
function parseSubtitles(text) {
    const section = findSection(text, /字幕/i)
    if (!section) return []

    const codeBlock = section.match(/```(?:srt)?\s*\n([\s\S]*?)```/)
    if (!codeBlock) return []

    const srtText = codeBlock[1].trim()
    // SRT 格式：序號 / 時間碼 / 內容 / 空行
    const blocks = srtText.split(/\n\n+/)
    return blocks.map(block => {
        const lines = block.split('\n').filter(l => l.trim())
        // 跳過序號行和時間碼行，取剩餘行
        const contentLines = lines.filter(l =>
            !/^\d+$/.test(l.trim()) && !/-->/.test(l)
        )
        return contentLines.join('\n').trim()
    }).filter(Boolean)
}

/** 收集所有 shot ID 並排序 */
function collectShotIds(...sources) {
    const ids = new Set()
    for (const src of sources) {
        if (!src) continue
        if (Array.isArray(src)) continue
        for (const key of Object.keys(src)) {
            // 只取 S0X 格式的 key，排除 _desc 後綴
            const m = key.match(/^(S\d{2})$/)
            if (m) ids.add(m[1])
        }
    }
    return [...ids].sort()
}
