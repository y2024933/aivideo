# Cowork 輸出模板

> 把以下指令貼到 Claude Cowork，讓它產出標準 YAML 格式的腳本，可以直接匯入 AI Video 系統。

## 使用方式

在 Cowork 對話結尾加上：

```
請用以下 YAML 格式輸出最終腳本，方便我匯入系統：

---
project_name: 建案名稱
builder_name: 建商名稱
location: 地點
video_length_seconds: 60
target_audience: first_buyer
tone: warm_family
character_nickname: 角色暱稱
character_dna: |
  完整的角色 DNA prompt（英文）

shots:
  - id: S01
    duration_sec: 4
    use_model: flux_kontext
    image_prompt: |
      完整的圖片生成 prompt（不要用 [DNA] 佔位符，直接寫完整）
    kling_prompt: |
      Kling 動畫指令
    voiceover: "旁白文字"
    subtitle: "字幕文字"
    emotion: "情緒描述"

  - id: S02
    ...（每個 shot 都用同樣格式）

voiceover_full: |
  完整配音稿

voice_id_preferred: zh-TW-HsiaoChenNeural

compliance_watermark: "3D／AI 示意圖｜實品以建造完成後為準"
compliance_footer:
  - "坪數及總價以實際買賣契約為準"
  - "公設規劃以實際完工及管委會規約為準"

bgm_keywords:
  - "heartwarming"
  - "gentle piano"
---

## 重要規則：
1. image_prompt 裡不要用 [松松DNA] 佔位符，直接寫完整的角色描述
2. use_model 可選值：flux_pro / flux_kontext / ideogram_v2_turbo
3. 不需要中文文字的鏡頭用 flux_kontext（帶角色參考圖）
4. 需要中文文字的鏡頭用 ideogram_v2_turbo
5. 多角色場景（如家庭畫面）用 flux_pro
6. S10/S11 品牌卡不需要 image_prompt（留空或不寫）
7. emotion 用中文描述
```

## use_model 選擇指南

| use_model | 用途 | 角色一致性 |
|---|---|---|
| `flux_kontext` | 帶角色參考圖的場景（預設首選） | 高 ~85-90% |
| `flux_pro` | 多角色場景（如家庭畫面）、角色特寫 | 中 ~70% |
| `ideogram_v2_turbo` | 需要正確中文文字的場景 | 低（純文字生圖） |

## 範例輸出

```yaml
---
project_name: 松竹敦富
builder_name: 松竹建設
location: 台中市北屯區
video_length_seconds: 30
target_audience: first_buyer
tone: warm_family
character_nickname: 松松
character_dna: |
  a chubby and round Golden Retriever puppy,
  bow tie, warm amber eyes (NOT blue),
  in 3D Pixar movie style, fully cartoon stylized animation,
  soft volumetric lighting, cinematic depth of field,
  consistent character design, same exact character

shots:
  - id: S01
    duration_sec: 3
    use_model: flux_pro
    image_prompt: |
      a chubby and round Golden Retriever puppy, bow tie,
      warm amber eyes (NOT blue), cute and curious expression,
      in 3D Pixar movie style, sitting on a Taiwan MRT train seat,
      looking forward with anticipation, ears perked up,
      blurred street view through window, soft afternoon sunlight,
      tall vertical 9:16 portrait composition, cinematic close-up
    kling_prompt: |
      puppy turns head with curious expression, ears perking up,
      bow tie stays neat, train rocks gently, camera pushes in
    voiceover: "咦？要去哪裡呀？"
    subtitle: "咦？要去哪裡呀？"
    emotion: "好奇期待"

  - id: S02
    duration_sec: 3
    use_model: flux_kontext
    image_prompt: |
      Same exact character from reference image, standing at
      the exit of a modern Taiwan MRT station, looking up with
      happy excited expression, bow tie visible, green leafy
      street trees, modern apartment building in background,
      warm golden hour lighting, tall vertical 9:16 portrait
    kling_prompt: |
      puppy slowly looks up with widening eyes, tail wagging,
      camera tilts up following gaze, slow dolly in
    voiceover: "啊！原來在這裡！"
    subtitle: "鄰近捷運松竹站"
    emotion: "恍然開心"

voiceover_full: |
  咦？要去哪裡呀？
  啊！原來在這裡！

voice_id_preferred: zh-TW-HsiaoChenNeural

compliance_watermark: "3D／AI 示意圖｜實品以建造完成後為準"
compliance_footer:
  - "坪數及總價以實際買賣契約為準"

bgm_keywords:
  - "heartwarming home"
  - "gentle piano"
---
```
