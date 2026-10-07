import { AbsoluteFill, Img, OffthreadVideo, interpolate, useCurrentFrame } from "remotion";
import { KEN_BURNS, resolveKenBurns } from "./kenBurns";
import { Subtitle } from "./Subtitle";

const FILL = { position: "absolute", inset: 0, width: "100%", height: "100%" };

/**
 * 單一鏡頭。圖片走 Ken Burns + blur 背景填充，B-roll 影片走 cover。
 *
 * ⚠️ durationInFrames 必須由父層傳進來。在 TransitionSeries.Sequence 內
 * useVideoConfig().durationInFrames 拿到的是「整支影片」長度而非本段長度，
 * 直接拿去當 interpolate 區間會讓 30 秒影片的每一鏡只走完運鏡的 1/8（看起來幾乎靜止）。
 * useCurrentFrame() 在 Sequence 內則是相對本段（從 0 起算），可以直接用。
 */
export const Shot = ({
  kind = "image",
  imageUrl,
  videoUrl,
  durationInFrames,
  kenBurns,
  fit = "contain",
  subtitle,
  subtitleSettings,
}) => {
  const frame = useCurrentFrame();
  const p = (typeof kenBurns === "string" ? resolveKenBurns(kenBurns) : kenBurns) ?? KEN_BURNS.none;
  const last = Math.max((durationInFrames ?? 1) - 1, 1);
  const at = (range) => interpolate(frame, [0, last], range, { extrapolateLeft: "clamp", extrapolateRight: "clamp" });
  const [scale, tx, ty] = [at(p.s), at(p.x), at(p.y)];

  return (
    <AbsoluteFill style={{ backgroundColor: "#000", overflow: "hidden" }}>
      {kind === "video" ? (
        <OffthreadVideo src={videoUrl} style={{ width: "100%", height: "100%", objectFit: "cover" }} />
      ) : (
        <>
          {/* 背景填充層：同一張圖放大模糊，補滿 contain 之後的上下留白。
              一律渲染不做條件分支 —— 已是 9:16 的圖 contain === cover，背景會被完全蓋掉。
              scale 1.3 是為了蓋掉 blur 在邊緣產生的透明暈。 */}
          <Img
            src={imageUrl}
            style={{
              ...FILL,
              objectFit: "cover",
              filter: "blur(48px) brightness(0.5) saturate(1.25)",
              transform: "scale(1.3)",
            }}
          />
          {/* 前景層：商品本體 + Ken Burns */}
          <Img
            src={imageUrl}
            style={{
              ...FILL,
              objectFit: fit,
              transform: `translate(${tx}%, ${ty}%) scale(${scale})`,
              transformOrigin: "center center",
              willChange: "transform",
            }}
          />
        </>
      )}

      {subtitle && <Subtitle text={subtitle} settings={subtitleSettings} />}
    </AbsoluteFill>
  );
};
