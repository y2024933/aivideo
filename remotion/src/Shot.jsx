import {
  AbsoluteFill,
  OffthreadVideo,
  useCurrentFrame,
  interpolate,
} from "remotion";
import { Subtitle } from "./Subtitle";

export const Shot = ({
  videoUrl,
  durationFrames,
  subtitle,
  isPublicFacility,
  publicFacilityLabel,
  crossfadeFrames,
  isFirst,
  isLast,
}) => {
  const frame = useCurrentFrame();

  // Crossfade: 淡入（非第一段）、淡出（非最後一段）
  const fadeIn = isFirst
    ? 1
    : interpolate(frame, [0, crossfadeFrames], [0, 1], {
        extrapolateRight: "clamp",
      });

  const fadeOut = isLast
    ? 1
    : interpolate(
        frame,
        [durationFrames - crossfadeFrames, durationFrames],
        [1, 0],
        { extrapolateLeft: "clamp" }
      );

  const opacity = fadeIn * fadeOut;

  return (
    <AbsoluteFill style={{ opacity }}>
      <OffthreadVideo
        src={videoUrl}
        style={{
          width: "100%",
          height: "100%",
          objectFit: "cover",
        }}
      />

      {/* 字幕 */}
      {subtitle && <Subtitle text={subtitle} />}

      {/* 公設示意圖標籤 */}
      {isPublicFacility && publicFacilityLabel && (
        <div
          style={{
            position: "absolute",
            bottom: 180,
            left: 40,
            background: "rgba(0, 0, 0, 0.6)",
            color: "#fff",
            padding: "8px 20px",
            borderRadius: 6,
            fontSize: 28,
            fontWeight: 500,
          }}
        >
          {publicFacilityLabel}
        </div>
      )}
    </AbsoluteFill>
  );
};
