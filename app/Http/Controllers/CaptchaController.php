<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Str;

class CaptchaController extends Controller
{
    public function generate(): Response
    {
        $code = Str::upper(Str::random(5));
        session(['captcha_code' => $code]);

        $width = 150;
        $height = 45;
        $image = imagecreatetruecolor($width, $height);

        /* 背景 */
        $bg = imagecolorallocate($image, 245, 245, 245);
        imagefill($image, 0, 0, $bg);

        /* 干擾線 */
        for ($i = 0; $i < 5; $i++) {
            $lineColor = imagecolorallocate($image, rand(150, 200), rand(150, 200), rand(150, 200));
            imageline($image, rand(0, $width), rand(0, $height), rand(0, $width), rand(0, $height), $lineColor);
        }

        /* 干擾點 */
        for ($i = 0; $i < 50; $i++) {
            $dotColor = imagecolorallocate($image, rand(100, 200), rand(100, 200), rand(100, 200));
            imagesetpixel($image, rand(0, $width), rand(0, $height), $dotColor);
        }

        /* 文字 */
        $textColor = imagecolorallocate($image, rand(20, 80), rand(20, 80), rand(20, 80));
        $fontSize = 5; /* GD 內建字型大小 1-5 */
        $x = 20;
        for ($i = 0; $i < strlen($code); $i++) {
            $y = rand(8, 18);
            imagestring($image, $fontSize, $x, $y, $code[$i], $textColor);
            $x += rand(22, 28);
        }

        ob_start();
        imagepng($image);
        $content = ob_get_clean();
        imagedestroy($image);

        return response($content, 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    /**
     * 驗證使用者輸入的驗證碼是否正確，驗證後立即清除 session
     */
    public static function check(string $input): bool
    {
        $code = session('captcha_code');
        session()->forget('captcha_code');
        return $code && strtoupper(trim($input)) === strtoupper($code);
    }
}
