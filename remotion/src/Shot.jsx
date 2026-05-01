import { AbsoluteFill, OffthreadVideo } from "remotion";
import { Subtitle } from "./Subtitle";

export const Shot = ({
  videoUrl,
  subtitle,
  isPublicFacility,
  publicFacilityLabel,
  subtitleSettings,
}) => {
  return (
    <AbsoluteFill>
      <OffthreadVideo
        src={videoUrl}
        style={{
          width: "100%",
          height: "100%",
          objectFit: "cover",
        }}
      />

      {/* 字幕 */}
      {subtitle && <Subtitle text={subtitle} settings={subtitleSettings} />}

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
