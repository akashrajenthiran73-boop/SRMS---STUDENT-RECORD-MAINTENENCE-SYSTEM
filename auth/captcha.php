<?php
session_start();

// Generate random 4 character alphanumeric code
$random_alpha = md5(rand());
$captcha_code = substr($random_alpha, 0, 4);

// Set session
$_SESSION["captcha_code"] = $captcha_code;

// Create Image
$target_layer = imagecreatetruecolor(80, 40);
$captcha_background = imagecolorallocate($target_layer, 255, 255, 255); // White background
imagefill($target_layer, 0, 0, $captcha_background);

$captcha_text_color = imagecolorallocate($target_layer, 0, 0, 0); // Black text

// Add text to image (Font size 5, x=20, y=12)
imagestring($target_layer, 5, 20, 12, $captcha_code, $captcha_text_color);

// Output image
header("Content-type: image/jpeg");
imagejpeg($target_layer);
imagedestroy($target_layer);
?>