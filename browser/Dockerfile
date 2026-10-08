# ⚠️ tag 必須跟 package.json 的 rebrowser-playwright 版本一致（1.63.0）。
#    Playwright 的 driver 與 image 內的瀏覽器是版本綁定的，對不上會在啟動時
#    報「Executable doesn't exist」或行為詭異。
FROM mcr.microsoft.com/playwright:v1.63.0-noble

ENV NODE_ENV=production \
    TZ=Asia/Taipei \
    PORT=3031 \
    SHOPEE_PROFILE_DIR=/profiles \
    ARTIFACTS_DIR=/artifacts

WORKDIR /app

COPY package.json ./
# 沒有 lockfile 時 npm ci 會失敗，所以用 install --omit=dev；
# rebrowser-playwright 的版本已經在 package.json 寫死（不加 ^）。
RUN npm install --omit=dev

COPY . .

# profiles / artifacts 由 docker volume 掛進來，這裡只是先建好並交出擁有權。
# 不使用 root 跑 Chromium：沙箱在 root 下會被停用，等於拿掉一層防護。
RUN mkdir -p /profiles /artifacts && chown -R pwuser:pwuser /app /profiles /artifacts

USER pwuser

EXPOSE 3031

HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
  CMD node -e "fetch('http://127.0.0.1:'+(process.env.PORT||3031)+'/health').then(r=>process.exit(r.ok?0:1)).catch(()=>process.exit(1))"

CMD ["node", "src/server.js"]
