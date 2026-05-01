import { Composition } from "remotion";
import { BuildingVideo } from "./BuildingVideo";

const TRANSITION_DURATION_SEC = 0.5;

export const RemotionRoot = () => {
  return (
    <Composition
      id="BuildingVideo"
      component={BuildingVideo}
      durationInFrames={300}
      fps={30}
      width={1080}
      height={1920}
      defaultProps={{
        fps: 30,
        shots: [
          {
            videoUrl: "https://placehold.co/1080x1920.mp4",
            durationSec: 5,
            subtitle: "範例字幕",
            isPublicFacility: false,
          },
        ],
        voiceover: null,
        bgm: null,
        watermark: { text: "3D 示意圖｜實品以建造完成後為準" },
        publicFacilityLabel: "公設示意圖",
        brand: { name: "範例建案", slogan: "" },
        subtitleSettings: {
          fontSize: "medium",
          color: "#ffffff",
          position: "bottom",
          animation: "slideIn",
        },
        globalTransition: "crossfade",
      }}
      calculateMetadata={({ props }) => {
        const fps = props.fps || 30;
        const globalTransition = props.globalTransition || "crossfade";
        const overlapFrames = Math.round(TRANSITION_DURATION_SEC * fps);

        let totalFrames = 0;
        props.shots.forEach((shot, i) => {
          totalFrames += Math.round(shot.durationSec * fps);
          if (i < props.shots.length - 1) {
            const transitionType = shot.transition ?? globalTransition;
            // cut 不產生 overlap
            if (transitionType !== "cut") {
              totalFrames -= overlapFrames;
            }
          }
        });

        return { durationInFrames: Math.max(totalFrames, 1), fps };
      }}
    />
  );
};
