<script setup>
</script>

<template>
    <div class="max-w-3xl mx-auto">
        <h2 class="text-xl font-bold text-gray-900 mb-6">操作流程</h2>

        <div class="space-y-4">
            <div class="bg-white rounded-lg border p-5">
                <div class="flex items-start gap-4">
                    <span class="bg-indigo-100 text-indigo-700 rounded-full w-8 h-8 flex items-center justify-center font-bold text-sm shrink-0">1</span>
                    <div>
                        <h3 class="font-semibold text-gray-800">在 Claude Cowork 產腳本</h3>
                        <p class="text-sm text-gray-500 mt-1">貼入建案資料，請 AI 產出角色 DNA + 9 個鏡頭的 prompt + 配音稿。跑 4 位 Reviewer 審查後修正成 v2。</p>
                        <p class="text-xs text-gray-400 mt-2">預計 30-60 分鐘</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg border p-5">
                <div class="flex items-start gap-4">
                    <span class="bg-indigo-100 text-indigo-700 rounded-full w-8 h-8 flex items-center justify-center font-bold text-sm shrink-0">2</span>
                    <div>
                        <h3 class="font-semibold text-gray-800">建立建案 + 貼入腳本</h3>
                        <p class="text-sm text-gray-500 mt-1">點「新建案」，填入基本資料，逐一貼入 9 個 shot 的場景、旁白、Flux prompt、Kling prompt。</p>
                        <p class="text-xs text-gray-400 mt-2">預計 10-15 分鐘</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg border p-5">
                <div class="flex items-start gap-4">
                    <span class="bg-green-100 text-green-700 rounded-full w-8 h-8 flex items-center justify-center font-bold text-sm shrink-0">3</span>
                    <div>
                        <h3 class="font-semibold text-gray-800">核准角色</h3>
                        <p class="text-sm text-gray-500 mt-1">系統自動用 Flux Kontext 產 4 張角色預覽圖。你挑一張最好的按「選這個」。</p>
                        <p class="text-xs text-gray-400 mt-2">系統生成 ~30 秒，你操作 ~1 分鐘</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg border p-5">
                <div class="flex items-start gap-4">
                    <span class="bg-green-100 text-green-700 rounded-full w-8 h-8 flex items-center justify-center font-bold text-sm shrink-0">4</span>
                    <div>
                        <h3 class="font-semibold text-gray-800">核准場景圖</h3>
                        <p class="text-sm text-gray-500 mt-1">系統用核准的角色圖做參考，自動產 9 張場景圖。你核准全部（或重跑不滿意的）。</p>
                        <p class="text-xs text-gray-400 mt-2">系統生成 ~1-2 分鐘，你操作 ~2 分鐘</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg border p-5">
                <div class="flex items-start gap-4">
                    <span class="bg-yellow-100 text-yellow-700 rounded-full w-8 h-8 flex items-center justify-center font-bold text-sm shrink-0">5</span>
                    <div>
                        <h3 class="font-semibold text-gray-800">等待動畫生成</h3>
                        <p class="text-sm text-gray-500 mt-1">系統自動把 9 張場景圖送到 Kling 轉成 5 秒動畫。全自動，等就好。</p>
                        <p class="text-xs text-gray-400 mt-2">系統生成 ~10-20 分鐘（全自動）</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg border p-5">
                <div class="flex items-start gap-4">
                    <span class="bg-yellow-100 text-yellow-700 rounded-full w-8 h-8 flex items-center justify-center font-bold text-sm shrink-0">6</span>
                    <div>
                        <h3 class="font-semibold text-gray-800">生成配音 + 渲染成品</h3>
                        <p class="text-sm text-gray-500 mt-1">動畫全部完成後，按「生成配音」（Azure TTS 台灣腔），再按「渲染最終影片」（Remotion 自動剪接）。</p>
                        <p class="text-xs text-gray-400 mt-2">配音 ~5 秒，渲染 ~1-2 分鐘</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg border p-5">
                <div class="flex items-start gap-4">
                    <span class="bg-purple-100 text-purple-700 rounded-full w-8 h-8 flex items-center justify-center font-bold text-sm shrink-0">7</span>
                    <div>
                        <h3 class="font-semibold text-gray-800">下載成品</h3>
                        <p class="text-sm text-gray-500 mt-1">渲染完成後直接預覽 + 下載 MP4。如果需要更精細的效果，下載素材去 CapCut 精修。</p>
                        <p class="text-xs text-gray-400 mt-2">完成！</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-8 bg-gray-100 rounded-lg p-5">
            <h3 class="font-semibold text-gray-800 mb-2">費用估算</h3>
            <p class="text-sm text-gray-600">每支 60 秒影片約 <strong>$4.8 USD</strong>，無月費。</p>
            <ul class="text-sm text-gray-500 mt-2 space-y-1">
                <li>角色預覽 4 張 → $0.16</li>
                <li>場景圖 9 張 → $0.36</li>
                <li>動畫 9 段 → $1.89</li>
                <li>動畫重跑（預估）→ $1.05</li>
                <li>配音 → $0（免費額度）</li>
                <li>剪接 → $0.05</li>
            </ul>
        </div>

        <div class="mt-8 bg-blue-50 rounded-lg p-5">
            <h3 class="font-semibold text-gray-800 mb-2">預設帳號</h3>
            <p class="text-sm text-gray-600">Email: <code class="bg-white px-2 py-0.5 rounded">admin@aivideo.local</code></p>
            <p class="text-sm text-gray-600">密碼: <code class="bg-white px-2 py-0.5 rounded">password</code></p>
        </div>
    </div>
</template>
