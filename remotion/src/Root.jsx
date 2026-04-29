import { Composition } from "remotion";
import { BuildingVideo } from "./BuildingVideo";

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
      }}
      calculateMetadata={({ props }) => {
        const fps = props.fps || 30;
        const crossfadeFrames = Math.round(0.5 * fps);
        let totalFrames = 0;
        props.shots.forEach((shot, i) => {
          totalFrames += Math.round(shot.durationSec * fps);
          if (i < props.shots.length - 1) {
            totalFrames -= crossfadeFrames;
          }
        });
        return { durationInFrames: Math.max(totalFrames, 1), fps };
      }}
    />
  );
};
