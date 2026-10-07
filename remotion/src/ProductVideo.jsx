import { AbsoluteFill, Audio, Sequence, useVideoConfig } from "remotion";
import { TransitionSeries, linearTiming } from "@remotion/transitions";
import { slide } from "@remotion/transitions/slide";
import { fade } from "@remotion/transitions/fade";
import { wipe } from "@remotion/transitions/wipe";
import { flip } from "@remotion/transitions/flip";
import { clockWipe } from "@remotion/transitions/clock-wipe";
import { Shot } from "./Shot";
import { Watermark } from "./Watermark";
import { resolveKenBurns } from "./kenBurns";
import { overlapFrames, resolveTransition, shotFrames, shotOffsets } from "./timing";

/**
 * bundle 版本標記。改動本目錄下任何 props 契約時必須遞增，
 * 並與 app/Services/RemotionVideoEditor::EXPECTED_BUILD_TAG 同步 + 重新 deploy。
 */
export const BUILD_TAG = "v2-kenburns";

/** 與 App\Filament\Resources\ProductResource::TRANSITIONS 一對一 */
function getPresentation(type, width = 1080, height = 1920) {
  switch (type) {
    case "crossfade":
      return fade();
    case "slideLeft":
      return slide({ direction: "from-left" });
    case "slideRight":
      return slide({ direction: "from-right" });
    case "slideUp":
      return slide({ direction: "from-bottom" });
    case "slideDown":
      return slide({ direction: "from-top" });
    case "wipeLeft":
      return wipe({ direction: "from-left" });
    case "wipeRight":
      return wipe({ direction: "from-right" });
    case "wipeUp":
      return wipe({ direction: "from-bottom" });
    case "wipeDown":
      return wipe({ direction: "from-top" });
    case "flipHorizontal":
      return flip({ direction: "from-left" });
    case "flipVertical":
      return flip({ direction: "from-top" });
    case "clockWipe":
      return clockWipe({ width, height });
    default:
      return null; // 'cut' 不加轉場
  }
}

export const ProductVideo = ({
  shots,
  bgm,
  watermark,
  subtitleSettings,
  globalTransition,
  expectBuildTag,
}) => {
  // 版本守衛：PHP 要求的契約版本與 Lambda 上實際的 bundle 不符時直接炸，
  // 比渲染出一支「字幕沒套用 / 運鏡沒生效」的影片好 debug。
  // && 是刻意的：舊 inputProps 或 Remotion Studio 的 defaultProps 不帶這欄時不應炸。
  if (expectBuildTag && expectBuildTag !== BUILD_TAG) {
    throw new Error(
      `Remotion bundle 版本不符：Lambda 上是 "${BUILD_TAG}"，PHP 要求 "${expectBuildTag}"。請執行 cd remotion && npm run deploy`,
    );
  }

  const { fps } = useVideoConfig();
  const overlap = overlapFrames(fps);
  const resolvedGlobalTransition = globalTransition || "crossfade";

  return (
    <AbsoluteFill style={{ backgroundColor: "#000" }}>
      {/* 畫面 TransitionSeries */}
      <TransitionSeries>
        {shots.map((shot, i) => {
          const durationFrames = shotFrames(shot, fps);
          const presentation = getPresentation(resolveTransition(shot, resolvedGlobalTransition));

          return [
            <TransitionSeries.Sequence key={`shot-${i}`} durationInFrames={durationFrames}>
              <Shot
                kind={shot.kind}
                imageUrl={shot.imageUrl}
                videoUrl={shot.videoUrl}
                durationInFrames={durationFrames}
                kenBurns={resolveKenBurns(shot.kenBurns, i)}
                fit={shot.fit ?? "contain"}
                subtitle={shot.subtitle}
                subtitleSettings={subtitleSettings}
              />
            </TransitionSeries.Sequence>,
            // 非最後一段且非 cut 時加轉場
            i < shots.length - 1 && presentation ? (
              <TransitionSeries.Transition
                key={`trans-${i}`}
                presentation={presentation}
                timing={linearTiming({ durationInFrames: overlap })}
              />
            ) : null,
          ];
        })}
      </TransitionSeries>

      {/* Per-shot 配音音軌。
          ⚠️ 必須用 shotOffsets() 這組「與畫面同源」的 offset，不可自行從 0 累加 durationFrames：
          畫面的時間軸每遇一次非 cut 轉場就被壓縮 overlap，只累加的話第 i 鏡配音會晚 i × overlap，
          8 鏡時尾端差 3.5 秒，最後一鏡甚至可能排在 calculateMetadata 算出的總長之外而完全不播（M1）。

          這不是在回退 commit eaa5145。eaa5145 修的是「音軌彼此重疊 → 兩個人聲疊在一起」；
          M1 修的是「音軌與自己的畫面不對齊」。兩者在數學上會衝突（畫面時間軸短了 (n-1)×0.5s），
          所以還需要 ShotDurationEstimator::fromTts() 的 padding（0.6s > overlap 0.5s）配合，
          才能同時滿足「對齊畫面」與「音軌不互相重疊」。 */}
      {(() => {
        const offs = shotOffsets(shots, fps, resolvedGlobalTransition);

        return shots.map((shot, i) =>
          shot.voiceoverUrl ? (
            <Sequence key={`vo-${i}`} from={offs[i]} durationInFrames={shotFrames(shot, fps)}>
              <Audio src={shot.voiceoverUrl} volume={1} />
            </Sequence>
          ) : null,
        );
      })()}

      {/* 浮水印（全程顯示） */}
      {watermark?.text && <Watermark text={watermark.text} />}

      {/* BGM 音軌 */}
      {bgm?.audioUrl && <Audio src={bgm.audioUrl} volume={bgm.volume ?? 0.3} loop />}
    </AbsoluteFill>
  );
};
