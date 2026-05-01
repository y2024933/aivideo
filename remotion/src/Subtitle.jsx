import { useCurrentFrame, useVideoConfig, spring, interpolate } from "remotion";

const FONT_SIZE_MAP = { small: 28, medium: 36, large: 48 };

const POSITION_STYLE = {
  top: { top: 80, bottom: "auto" },
  center: { top: "50%", bottom: "auto", transform: "translateY(-50%)" },
  bottom: { bottom: 100, top: "auto" },
};

export const Subtitle = ({ text, settings }) => {
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();

  const {
    fontSize = "medium",
    color = "#ffffff",
    position = "bottom",
    animation = "slideIn",
  } = settings || {};

  // 動畫計算
  let opacity = 1;
  let translateY = 0;
  let displayText = text;

  if (animation === "fadeIn") {
    const progress = spring({ frame, fps, config: { damping: 20, stiffness: 100, mass: 0.5 } });
    opacity = progress;
  } else if (animation === "slideIn") {
    const progress = spring({ frame, fps, config: { damping: 20, stiffness: 100, mass: 0.5 } });
    translateY = (1 - progress) * 40;
    opacity = progress;
  } else if (animation === "typewriter") {
    const totalChars = text.length;
    const charsPerFrame = totalChars / Math.min(fps * 1.5, totalChars * 3);
    const visibleChars = Math.min(totalChars, Math.ceil(frame * charsPerFrame));
    displayText = text.slice(0, visibleChars);
  }
  // animation === 'none': 不做任何動畫

  const posStyle = POSITION_STYLE[position] || POSITION_STYLE.bottom;
  const baseTransform = position === "center" ? "translateY(-50%)" : "";
  const animTransform = `translateY(${translateY}px)`;
  const combinedTransform = baseTransform
    ? `${baseTransform} ${animTransform}`
    : animTransform;

  return (
    <div
      style={{
        position: "absolute",
        left: 0,
        right: 0,
        display: "flex",
        justifyContent: "center",
        transform: combinedTransform,
        opacity,
        ...posStyle,
      }}
    >
      <div
        style={{
          background: "rgba(0, 0, 0, 0.55)",
          color,
          padding: "12px 32px",
          borderRadius: 8,
          fontSize: FONT_SIZE_MAP[fontSize] || 36,
          fontWeight: 600,
          lineHeight: 1.5,
          textAlign: "center",
          maxWidth: "85%",
        }}
      >
        {displayText}
      </div>
    </div>
  );
};
