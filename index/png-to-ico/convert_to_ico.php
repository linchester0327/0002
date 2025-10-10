<?php
// 源文件路径
$source_file = 'favicon.png';
// 目标文件路径
$target_file = 'favicon.ico';

// 检查源文件是否存在
if (!file_exists($source_file)) {
    die('源文件 favicon.png 不存在');
}

// 尝试使用GD库打开PNG文件
$image = imagecreatefrompng($source_file);
if (!$image) {
    die('无法打开PNG文件，请检查GD库是否已安装并支持PNG格式');
}

// 获取图像宽度和高度
$width = imagesx($image);
$height = imagesy($image);

// 创建一个新的图像，用于ICO格式（通常使用16x16, 32x32, 48x48等尺寸）
// 我们将创建几个常用尺寸的图标
$icon_sizes = array(16, 32, 48);

// 创建临时文件存储多个尺寸的图标
$temp_file = tempnam(sys_get_temp_dir(), 'icon');

// 保存为ICO格式
// 注意：PHP的imageico函数可能只支持创建单个尺寸的ICO
// 这里我们尝试保存为16x16尺寸，这是最常用的favicon尺寸
$success = imageico($image, $target_file, 16);

// 如果imageico函数不可用或失败，尝试其他方法
if (!$success) {
    // 尝试调整图像大小为16x16
    $resized_image = imagecreatetruecolor(16, 16);
    imagealphablending($resized_image, false);
    imagesavealpha($resized_image, true);
    imagecopyresampled($resized_image, $image, 0, 0, 0, 0, 16, 16, $width, $height);
    
    // 再次尝试保存
    $success = imageico($resized_image, $target_file);
    
    // 释放资源
    imagedestroy($resized_image);
}

// 释放资源
imagedestroy($image);

if ($success) {
    echo '转换成功！已生成 ' . $target_file;
} else {
    // 如果所有方法都失败，提供一个替代方案
    echo '转换失败。请尝试使用在线工具或图像编辑软件将PNG转换为ICO格式。';
    echo '<br>推荐尺寸：16x16, 32x32, 48x48像素';
}