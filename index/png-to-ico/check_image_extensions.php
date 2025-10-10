<?php
// 检查PHP是否加载了GD或Imagick扩展
if (extension_loaded('gd')) {
    echo 'GD扩展已加载<br>';
    $gd_info = gd_info();
    echo 'GD版本: ' . $gd_info['GD Version'] . '<br>';
    echo '支持的格式:<br>';
    foreach (array('PNG Support', 'GIF Create', 'JPEG Support', 'WebP Support') as $format) {
        if (isset($gd_info[$format]) && $gd_info[$format]) {
            echo '✓ ' . $format . '<br>';
        }
    }
} else {
    echo 'GD扩展未加载<br>';
}

if (extension_loaded('imagick')) {
    echo 'Imagick扩展已加载<br>';
    $imagick = new Imagick();
    $formats = $imagick->queryFormats();
    echo '支持的格式数量: ' . count($formats) . '<br>';
    echo '支持ICO: ' . (in_array('ICO', $formats) ? '✓' : '✗') . '<br>';
    echo '支持PNG: ' . (in_array('PNG', $formats) ? '✓' : '✗') . '<br>';
} else {
    echo 'Imagick扩展未加载<br>';
}