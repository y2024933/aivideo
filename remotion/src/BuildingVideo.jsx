import { AbsoluteFill, Audio, Sequence, useVideoConfig } from "remotion";
import { TransitionSeries, linearTiming } from "@remotion/transitions";
import { slide } from "@remotion/transitions/slide";
import { fade } from "@remotion/transitions/fade";
import { Shot } from "./Shot";
import { Watermark } from "./Watermark";

const TRANSITION_DURATION_SEC = 0.5;

function getPresentation(type) {
  switch (type) {
    case "crossfade":
      return fade();
    case "slideLeft":
      return slide({ direction: "from-left" });
    case "slideRight":
      return slide({ direction: "from-right" });
    case "slideUp":
      return slide({ direction: "from-bottom" });
    default:
      return null; // 'cut' 不加轉場
  }
}

export const BuildingVideo = ({
  shots,
  bgm,
  watermark,
  publicFacilityLabel,
  subtitleSettings,
  globalTransition,
}) => {
  const { fps } = useVideoConfig();
  const overlapFrames = Math.round(TRANSITION_DURATION_SEC * fps);

  const resolvedGlobalTransition = globalTransition || "crossfade";

  return (
    <AbsoluteFill style={{ backgroundColor: "#000" }}>
      {/* 影片 TransitionSeries */}
      <TransitionSeries>
        {shots.map((shot, i) => {
          const durationFrames = Math.round(shot.durationSec * fps);
          const transitionType = shot.transition ?? resolvedGlobalTransition;
          const presentation = getPresentation(transitionType);

          return [
            <TransitionSeries.Sequence
              key={`shot-${i}`}
              durationInFrames={durationFrames}
            >
              <Shot
                videoUrl={shot.videoUrl}
                subtitle={shot.subtitle}
                isPublicFacility={shot.isPublicFacility}
                publicFacilityLabel={publicFacilityLabel}
                subtitleSettings={subtitleSettings}
              />
            </TransitionSeries.Sequence>,
            // 非最後一段且非 cut 時加轉場
            i < shots.length - 1 && presentation ? (
              <TransitionSeries.Transition
                key={`trans-${i}`}
                presentation={presentation}
                timing={linearTiming({ durationInFrames: overlapFrames })}
              />
            ) : null,
          ];
        })}
      </TransitionSeries>

      {/* Per-shot 配音音軌（獨立 timing，不受 transition overlap 影響） */}
      {(() => {
        let audioOffset = 0;
        return shots.map((shot, i) => {
          const durationFrames = Math.round(shot.durationSec * fps);
          const from = audioOffset;
          audioOffset += durationFrames;
          return shot.voiceoverUrl ? (
            <Sequence key={`vo-${i}`} from={from} durationInFrames={durationFrames}>
              <Audio src={shot.voiceoverUrl} volume={1} />
            </Sequence>
          ) : null;
        });
      })()}

      {/* 浮水印（全程顯示） */}
      {watermark?.text && <Watermark text={watermark.text} />}

      {/* BGM 音軌 */}
      {bgm?.audioUrl && (
        <Audio src={bgm.audioUrl} volume={bgm.volume ?? 0.3} loop />
      )}
    </AbsoluteFill>
  );
};
