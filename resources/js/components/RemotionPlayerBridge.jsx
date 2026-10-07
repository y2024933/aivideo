import React, { forwardRef, useImperativeHandle, useRef } from 'react';
import { Player } from '@remotion/player';
import { ProductVideo } from '../../../remotion/src/ProductVideo';

const RemotionPlayerBridge = forwardRef(({ inputProps, durationInFrames, fps = 30 }, ref) => {
  const playerRef = useRef(null);

  useImperativeHandle(ref, () => ({
    play: () => playerRef.current?.play(),
    pause: () => playerRef.current?.pause(),
    seekTo: (frame) => playerRef.current?.seekTo(frame),
  }));

  return (
    <Player
      ref={playerRef}
      component={ProductVideo}
      inputProps={inputProps}
      durationInFrames={durationInFrames}
      compositionWidth={1080}
      compositionHeight={1920}
      fps={fps}
      style={{ width: '100%', aspectRatio: '9/16', maxHeight: '600px' }}
      controls
      autoPlay={false}
    />
  );
});

export default RemotionPlayerBridge;
