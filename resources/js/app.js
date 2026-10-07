import './bootstrap';

/**
 * Remotion Player 預覽：由 Filament 頁面呼叫，動態掛載 Vue 元件。
 * @param {string|HTMLElement} target 容器選擇器或 DOM 節點
 * @param {{inputProps: object, durationInFrames: number, fps?: number}} props
 */
window.mountRemotionPreview = async (target, props) => {
    const el = typeof target === 'string' ? document.querySelector(target) : target;
    if (!el) return null;

    const { createApp, h } = await import('vue');
    const { default: RemotionPreview } = await import('./components/RemotionPreview.vue');
    const app = createApp({ render: () => h(RemotionPreview, props) });
    app.mount(el);

    return app;
};
