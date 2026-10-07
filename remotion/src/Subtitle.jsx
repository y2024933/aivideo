import { useCurrentFrame, useVideoConfig, spring } from "remotion";

const FONT_SIZE_MAP = { small: 28, medium: 36, large: 48, xlarge: 56 };

const POSITION_STYLE = {
  top: { top: 80, bottom: "auto" },
  center: { top: "50%", bottom: "auto", transform: "translateY(-50%)" },
  bottom: { bottom: 100, top: "auto" },
};

const BG_STYLE_MAP = {
  dark: "rgba(0, 0, 0, 0.55)",
  darker: "rgba(0, 0, 0, 0.75)",
  light: "rgba(255, 255, 255, 0.7)",
  gradient: "linear-gradient(135deg, rgba(0,0,0,0.6), rgba(0,0,0,0.2))",
  blur: "rgba(0, 0, 0, 0.3)",
  none: "transparent",
};

export const Subtitle = ({ text, settings }) => {
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();

  const {
    fontSize = "medium",
    color = "#ffffff",
    position = "bottom",
    animation = "slideIn",
    fontFamily = "default",
    textStroke = "none",
    textShadow = "none",
    bgStyle = "dark",
    borderRadius = 8,
  } = settings || {};

  // 動畫計算
  let opacity = 1;
  let translateY = 0;
  let scale = 1;
  let displayText = text;

  if (animation === "fadeIn") {
    const progress = spring({ frame, fps, config: { damping: 20, stiffness: 100, mass: 0.5 } });
    opacity = progress;
  } else if (animation === "slideIn") {
    const progress = spring({ frame, fps, config: { damping: 20, stiffness: 100, mass: 0.5 } });
    translateY = (1 - progress) * 40;
    opacity = progress;
  } else if (animation === "slideDown") {
    const progress = spring({ frame, fps, config: { damping: 20, stiffness: 100, mass: 0.5 } });
    translateY = (progress - 1) * 40;
    opacity = progress;
  } else if (animation === "typewriter") {
    const totalChars = text.length;
    const charsPerFrame = totalChars / Math.min(fps * 1.5, totalChars * 3);
    const visibleChars = Math.min(totalChars, Math.ceil(frame * charsPerFrame));
    displayText = text.slice(0, visibleChars);
  } else if (animation === "bounce") {
    const progress = spring({ frame, fps, config: { damping: 5, stiffness: 200, mass: 0.5 } });
    scale = progress;
    opacity = Math.min(1, progress * 2);
  } else if (animation === "zoomIn") {
    const progress = spring({ frame, fps, config: { damping: 15, stiffness: 100, mass: 0.5 } });
    scale = 0.5 + progress * 0.5;
    opacity = progress;
  }
  // animation === 'none': 不做任何動畫

  // 描邊
  const strokeStyle = {
    none: {},
    thin: { WebkitTextStroke: "1px rgba(0,0,0,0.8)" },
    thick: { WebkitTextStroke: "2px rgba(0,0,0,0.9)" },
    white: { WebkitTextStroke: "2px rgba(255,255,255,0.9)" },
  }[textStroke] || {};

  // 陰影
  const shadowStyle = {
    none: {},
    soft: { textShadow: "2px 2px 4px rgba(0,0,0,0.6)" },
    hard: { textShadow: "-1px -1px 0 #000, 1px -1px 0 #000, -1px 1px 0 #000, 1px 1px 0 #000" },
    glow: { textShadow: `0 0 10px ${color}, 0 0 20px ${color}` },
  }[textShadow] || {};

  // 字體
  const fontFamilyValue = {
    default: "system-ui, -apple-system, sans-serif",
    serif: "'Noto Serif TC', 'Source Han Serif TC', serif",
    rounded: "'M PLUS Rounded 1c', 'Noto Sans TC', sans-serif",
    mono: "'JetBrains Mono', 'Source Code Pro', monospace",
  }[fontFamily] || "system-ui, -apple-system, sans-serif";

  const posStyle = POSITION_STYLE[position] || POSITION_STYLE.bottom;
  const baseTransform = position === "center" ? "translateY(-50%)" : "";
  const animTransform = `translateY(${translateY}px) scale(${scale})`;
  const combinedTransform = baseTransform
    ? `${baseTransform} ${animTransform}`
    : animTransform;

  const bgValue = BG_STYLE_MAP[bgStyle] || BG_STYLE_MAP.dark;
  const isGradient = bgStyle === "gradient";
  const useBackdropBlur = bgStyle === "blur";

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
          background: isGradient ? undefined : bgValue,
          backgroundImage: isGradient ? bgValue : undefined,
          backdropFilter: useBackdropBlur ? "blur(10px)" : undefined,
          color,
          padding: "12px 32px",
          borderRadius,
          fontSize: FONT_SIZE_MAP[fontSize] || 36,
          fontWeight: 600,
          fontFamily: fontFamilyValue,
          lineHeight: 1.5,
          textAlign: "center",
          maxWidth: "85%",
          ...strokeStyle,
          ...shadowStyle,
        }}
      >
        {displayText}
      </div>
    </div>
  );
};
