import {
  AbsoluteFill,
  Audio,
  Sequence,
  useVideoConfig,
} from "remotion";
import { Shot } from "./Shot";
import { Watermark } from "./Watermark";

export const BuildingVideo = ({
  shots,
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

      {/* Per-shot 配音音軌 */}
      {shotTimings.map(({ shot, from, durationFrames }, i) =>
        shot.voiceoverUrl ? (
          <Sequence key={`vo-${i}`} from={from} durationInFrames={durationFrames}>
            <Audio src={shot.voiceoverUrl} volume={1} />
          </Sequence>
        ) : null
      )}

      {/* 浮水印（全程顯示） */}
      {watermark?.text && <Watermark text={watermark.text} />}

      {/* BGM 音軌 */}
      {bgm?.audioUrl && (
        <Audio src={bgm.audioUrl} volume={bgm.volume ?? 0.3} loop />
      )}
    </AbsoluteFill>
  );
};
