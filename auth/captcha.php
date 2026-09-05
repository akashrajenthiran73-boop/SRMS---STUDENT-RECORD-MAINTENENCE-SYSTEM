<?php
session_start();

// Generate 5-character random string
$captcha_code = substr(str_shuffle("0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 5);
$_SESSION['captcha'] = $captcha_code;

// Create image
$image = imagecreatetruecolor(120, 40);
$background = imagecolorallocate($image, 240, 240, 240);
$text_color = imagecolorallocate($image, 30, 30, 30);
$line_color = imagecolorallocate($image, 200, 200, 200);

imagefill($image, 0, 0, $background);

// Add noise lines
for ($i = 0; $i < 4; $i++) {
    imageline($image, rand(0, 120), rand(0, 40), rand(0, 120), rand(0, 40), $line_color);
}

// Render string without external fonts (built-in font 5)
imagestring($image, 5, 35, 12, $captcha_code, $text_color);

header("Content-Type: image/png");
imagepng($image);
imagedestroy($image);
?>