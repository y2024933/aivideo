import { Composition } from "remotion";
import { ProductVideo } from "./ProductVideo";
import { totalFrames } from "./timing";

export const RemotionRoot = () => {
  return (
    <Composition
      id="ProductVideo"
      component={ProductVideo}
      durationInFrames={300}
      fps={30}
      width={1080}
      height={1920}
      defaultProps={{
        fps: 30,
        shots: [
          {
            kind: "image",
            // 刻意用真實可讀的圖片 URL：.mp4 會讓 remotion studio 預覽炸掉
            imageUrl: "https://placehold.co/1000x1000/jpeg",
            videoUrl: null,
            durationSec: 4,
            kenBurns: "zoomIn",
            fit: "contain",
            subtitle: "範例字幕",
            voiceoverUrl: null,
            transition: "crossfade",
          },
          {
            kind: "image",
            imageUrl: "https://placehold.co/1080x1920/jpeg",
            videoUrl: null,
            durationSec: 4,
            kenBurns: "auto",
            fit: "cover",
            subtitle: "第二鏡字幕",
            voiceoverUrl: null,
            transition: null,
          },
        ],
        bgm: null,
        watermark: { text: "廣告｜含聯盟行銷連結" },
        subtitleSettings: {
          fontSize: "medium",
          color: "#ffffff",
          position: "bottom",
          animation: "slideIn",
          fontFamily: "default",
          textStroke: "none",
          textShadow: "none",
          bgStyle: "dark",
        },
        globalTransition: "crossfade",
      }}
      calculateMetadata={({ props }) => {
        const fps = props.fps ?? 30;

        return { durationInFrames: totalFrames(props.shots, fps, props.globalTransition), fps };
      }}
    />
  );
};
