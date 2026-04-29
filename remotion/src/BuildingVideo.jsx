import {
  AbsoluteFill,
  Audio,
  Sequence,
  useCurrentFrame,
  useVideoConfig,
} from "remotion";
import { Shot } from "./Shot";
import { Watermark } from "./Watermark";

export const BuildingVideo = ({
  shots,
  voiceover,
  bgm,
  watermark,
  publicFacilityLabel,
}) => {
  const { fps } = useVideoConfig();
  const crossfadeFrames = Math.round(0.5 * fps);

  // 計算每段 shot 的起始 frame（含 crossfade 重疊）
  const shotTimings = [];
  let offset = 0;
  shots.forEach((shot, i) => {
    const durationFrames = Math.round(shot.durationSec * fps);
    shotTimings.push({ shot, from: offset, durationFrames });
    offset += durationFrames - (i < shots.length - 1 ? crossfadeFrames : 0);
  });

  return (
    <AbsoluteFill style={{ backgroundColor: "#000" }}>
      {shotTimings.map(({ shot, from, durationFrames }, i) => (
        <Sequence key={i} from={from} durationInFrames={durationFrames}>
          <Shot
            videoUrl={shot.videoUrl}
            durationFrames={durationFrames}
            subtitle={shot.subtitle}
            isPublicFacility={shot.isPublicFacility}
            publicFacilityLabel={publicFacilityLabel}
            crossfadeFrames={crossfadeFrames}
            isFirst={i === 0}
            isLast={i === shots.length - 1}
          />
        </Sequence>
      ))}

      {/* 浮水印（全程顯示） */}
      {watermark?.text && <Watermark text={watermark.text} />}

      {/* 配音音軌 */}
      {voiceover?.audioUrl && (
        <Audio src={voiceover.audioUrl} volume={1} />
      )}

      {/* BGM 音軌 */}
      {bgm?.audioUrl && (
        <Audio src={bgm.audioUrl} volume={bgm.volume ?? 0.3} loop />
      )}
    </AbsoluteFill>
  );
};
