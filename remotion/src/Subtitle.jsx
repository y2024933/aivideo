import { useCurrentFrame, useVideoConfig, spring } from "remotion";

export const Subtitle = ({ text }) => {
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();

  // spring 動畫：從下方滑入
  const progress = spring({
    frame,
    fps,
    config: { damping: 20, stiffness: 100, mass: 0.5 },
  });

  const translateY = (1 - progress) * 40;
  const opacity = progress;

  return (
    <div
      style={{
        position: "absolute",
        bottom: 100,
        left: 0,
        right: 0,
        display: "flex",
        justifyContent: "center",
        transform: `translateY(${translateY}px)`,
        opacity,
      }}
    >
      <div
        style={{
          background: "rgba(0, 0, 0, 0.55)",
          color: "#fff",
          padding: "12px 32px",
          borderRadius: 8,
          fontSize: 36,
          fontWeight: 600,
          lineHeight: 1.5,
          textAlign: "center",
          maxWidth: "85%",
        }}
      >
        {text}
      </div>
    </div>
  );
};
