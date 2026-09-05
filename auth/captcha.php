<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$captcha_code = substr(str_shuffle("0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 5);

// login_process.php ethirpaarkura exact session variable name!
$_SESSION['captcha_code'] = $captcha_code;

$image = imagecreatetruecolor(120, 40);
$background = imagecolorallocate($image, 240, 240, 240);
$text_color = imagecolorallocate($image, 30, 30, 30);
$line_color = imagecolorallocate($image, 200, 200, 200);

imagefill($image, 0, 0, $background);

for ($i = 0; $i < 4; $i++) {
    imageline($image, rand(0, 120), rand(0, 40), rand(0, 120), rand(0, 40), $line_color);
}

imagestring($image, 5, 35, 12, $captcha_code, $text_color);

header("Content-Type: image/png");
imagepng($image);
imagedestroy($image);
?>