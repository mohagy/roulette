<?php
// Script to generate PWA icons from the existing roulette.png image

// Source image
$sourceImage = 'images/roulette-wheel.png';

// Check if source image exists
if (!file_exists($sourceImage)) {
    die("Source image not found: $sourceImage");
}

// Create icons directory if it doesn't exist
if (!is_dir('images/icons')) {
    mkdir('images/icons', 0755, true);
}

// Icon sizes to generate
$sizes = [
    72, 96, 128, 144, 152, 192, 384, 512
];

// Load the source image
$source = imagecreatefrompng($sourceImage);
if (!$source) {
    die("Failed to load source image");
}

// Get original dimensions
$width = imagesx($source);
$height = imagesy($source);

// Generate icons for each size
foreach ($sizes as $size) {
    // Create a new image with the target size
    $icon = imagecreatetruecolor($size, $size);
    
    // Preserve transparency
    imagealphablending($icon, false);
    imagesavealpha($icon, true);
    $transparent = imagecolorallocatealpha($icon, 0, 0, 0, 127);
    imagefilledrectangle($icon, 0, 0, $size, $size, $transparent);
    
    // Copy and resize the source image
    imagecopyresampled($icon, $source, 0, 0, 0, 0, $size, $size, $width, $height);
    
    // Save the icon
    $filename = "images/icons/icon-{$size}x{$size}.png";
    imagepng($icon, $filename);
    imagedestroy($icon);
    
    echo "Generated: $filename\n";
}

// Generate favicon.ico (16x16)
$favicon = imagecreatetruecolor(16, 16);
imagealphablending($favicon, false);
imagesavealpha($favicon, true);
$transparent = imagecolorallocatealpha($favicon, 0, 0, 0, 127);
imagefilledrectangle($favicon, 0, 0, 16, 16, $transparent);
imagecopyresampled($favicon, $source, 0, 0, 0, 0, 16, 16, $width, $height);
imagepng($favicon, 'favicon.png');
echo "Generated: favicon.png\n";

// Clean up
imagedestroy($source);
echo "Icon generation complete!\n";
