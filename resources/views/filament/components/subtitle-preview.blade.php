<div
    x-data="{
        fs: 'medium',
        col: '#ffffff',
        pos: 'bottom',
        anim: 'slideIn',
        ff: 'default',
        ts: 'none',
        tsh: 'none',
        bg: 'dark',
        init() {
            const self = this;
            this.$watch('$wire.mountedActionsData', (val) => {
                const d = val?.[0] ?? {};
                if (d.subtitle_fontSize) self.fs = d.subtitle_fontSize;
                if (d.subtitle_color) self.col = d.subtitle_color;
                if (d.subtitle_position) self.pos = d.subtitle_position;
                if (d.subtitle_animation) self.anim = d.subtitle_animation;
                if (d.subtitle_fontFamily) self.ff = d.subtitle_fontFamily;
                if (d.subtitle_textStroke) self.ts = d.subtitle_textStroke;
                if (d.subtitle_textShadow) self.tsh = d.subtitle_textShadow;
                if (d.subtitle_bgStyle) self.bg = d.subtitle_bgStyle;
            });
            const d = $wire.mountedActionsData?.[0] ?? {};
            if (d.subtitle_fontSize) this.fs = d.subtitle_fontSize;
            if (d.subtitle_color) this.col = d.subtitle_color;
            if (d.subtitle_position) this.pos = d.subtitle_position;
            if (d.subtitle_animation) this.anim = d.subtitle_animation;
            if (d.subtitle_fontFamily) this.ff = d.subtitle_fontFamily;
            if (d.subtitle_textStroke) this.ts = d.subtitle_textStroke;
            if (d.subtitle_textShadow) this.tsh = d.subtitle_textShadow;
            if (d.subtitle_bgStyle) this.bg = d.subtitle_bgStyle;
        },
    }"
    class="relative rounded-lg overflow-hidden"
    style="height: 240px; background: linear-gradient(135deg, #1a1a2e, #16213e, #0f3460);"
>
    {{-- 模擬影片背景 --}}
    <div class="absolute inset-0 flex items-center justify-center opacity-15">
        <svg class="w-20 h-20 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
        </svg>
    </div>

    {{-- 字幕 --}}
    <div
        class="absolute left-0 right-0 flex justify-center px-4 transition-all duration-300"
        :style="{
            top: pos === 'top' ? '24px' : (pos === 'center' ? '50%' : 'auto'),
            bottom: pos === 'bottom' ? '24px' : 'auto',
            transform: pos === 'center' ? 'translateY(-50%)' : 'none',
        }"
    >
        <span
            class="inline-block px-5 py-2.5 rounded-lg text-center transition-all duration-300"
            :class="{
                'animate-bounce': anim === 'bounce',
                'animate-pulse': anim === 'zoomIn',
            }"
            :style="{
                fontSize: {small: '14px', medium: '18px', large: '24px', xlarge: '28px'}[fs] || '18px',
                color: col,
                background: bg === 'gradient' ? undefined : {dark:'rgba(0,0,0,0.55)', darker:'rgba(0,0,0,0.75)', light:'rgba(255,255,255,0.7)', blur:'rgba(0,0,0,0.3)', none:'transparent'}[bg] || 'rgba(0,0,0,0.55)',
                backgroundImage: bg === 'gradient' ? 'linear-gradient(135deg, rgba(0,0,0,0.6), rgba(0,0,0,0.2))' : undefined,
                backdropFilter: bg === 'blur' ? 'blur(10px)' : undefined,
                fontFamily: {default:'system-ui,sans-serif', serif:'Georgia,serif', rounded:'system-ui,sans-serif', mono:'Courier New,monospace'}[ff] || 'system-ui,sans-serif',
                fontWeight: 600,
                WebkitTextStroke: {none:'unset', thin:'1px rgba(0,0,0,0.8)', thick:'2px rgba(0,0,0,0.9)', white:'2px rgba(255,255,255,0.9)'}[ts] || 'unset',
                textShadow: {none:'none', soft:'2px 2px 4px rgba(0,0,0,0.6)', hard:'-1px -1px 0 #000,1px -1px 0 #000,-1px 1px 0 #000,1px 1px 0 #000', glow:'0 0 10px '+col+',0 0 20px '+col}[tsh] || 'none',
                maxWidth: '85%',
            }"
        >
            降噪開啟後 世界瞬間安靜
        </span>
    </div>

    {{-- 資訊標籤 --}}
    <div class="absolute top-3 left-3 text-xs text-white/40">字幕預覽</div>
    <div class="absolute top-3 right-3 text-xs text-white/60 bg-white/10 px-2 py-0.5 rounded"
         x-text="'動畫：' + ({none:'無', fadeIn:'淡入', slideIn:'由下滑入', slideDown:'由上滑入', typewriter:'打字機', bounce:'彈跳', zoomIn:'縮放'}[anim] || '無')"
    ></div>
    <div class="absolute bottom-3 right-3 text-xs text-white/40"
         x-text="'位置：' + ({top:'上方', center:'中間', bottom:'下方'}[pos] || '下方')"
    ></div>
</div>
