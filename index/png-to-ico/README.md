# Favicon 转换工具集

这个项目提供了多种将PNG图像转换为ICO格式的工具，适用于Windows 11 IIS环境下的PHP应用。

## 项目内容

- **convert_to_ico.html** - 基于浏览器的图像转换工具，使用JavaScript和Canvas API在客户端完成转换
- **convert_to_ico.php** - PHP命令行脚本，使用GD库尝试转换图像
- **check_image_extensions.php** - 检查PHP环境中是否安装了必要的图像处理扩展
- **favicon_test.html** - 测试和验证favicon.ico是否正确工作的页面
- **README.md** - 项目说明文档

## 使用方法

### 方法1：使用浏览器工具（推荐）

1. 在浏览器中打开 `convert_to_ico.html`
2. 点击"选择文件"按钮，选择您的 `favicon.png` 文件
3. 系统会自动转换并显示下载链接
4. 点击"下载 ICO 文件"保存转换后的图标
5. 将下载的 `favicon.ico` 文件上传到您的网站根目录
6. 在您的HTML页面的 `<head>` 部分添加以下代码：
   ```html
   <link rel="icon" type="image/x-icon" href="/favicon.ico">
   ```

### 方法2：使用PHP脚本（需要正确配置PHP环境）

1. 确保您的PHP环境已安装GD库扩展
2. 在命令行中运行：
   ```bash
   php convert_to_ico.php
   ```
3. 检查是否生成了 `favicon.ico` 文件

## 测试Favicon

1. 将生成的 `favicon.ico` 文件放在网站根目录
2. 打开 `favicon_test.html` 页面
3. 检查浏览器标签页上是否正确显示了图标
4. 页面会自动检测并提示 `favicon.ico` 文件是否加载成功

## 故障排除

- 如果转换失败，请确保您的PNG图像格式正确
- 推荐使用16x16、32x32或48x48像素的图像尺寸
- 对于PHP脚本方式，请确保PHP的GD库已正确安装和配置
- 如果图标在浏览器中不显示，请尝试清除浏览器缓存

## 技术说明

- 浏览器工具使用Canvas API和JavaScript创建ICO文件
- PHP脚本使用GD库处理图像转换
- 为了获得最佳兼容性，建议同时提供ICO格式和PNG格式的图标

## 系统要求

- 浏览器工具：现代浏览器（Chrome、Firefox、Edge等）
- PHP脚本：PHP 5.6+ 并安装GD库扩展
- Web服务器：IIS 7.0+（Windows 11环境）

## 注意事项

- 本工具仅用于将PNG图像转换为ICO格式
- 对于复杂的图像转换需求，建议使用专业的图像编辑软件
- 请确保您拥有原始图像的版权或使用权