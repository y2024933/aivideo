export const Watermark = ({ text }) => {
  return (
    <div
      style={{
        position: "absolute",
        bottom: 40,
        right: 40,
        color: "rgba(255, 255, 255, 0.6)",
        fontSize: 22,
        fontWeight: 400,
        textShadow: "1px 1px 3px rgba(0,0,0,0.5)",
        pointerEvents: "none",
      }}
    >
      {text}
    </div>
  );
};
