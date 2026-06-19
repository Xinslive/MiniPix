# MiniPix

MiniPix 是一个轻量 PHP 图床程序，面向个人或小团队使用。它保留简洁的前台上传页和后台瀑布流管理页，重点放在图片压缩、存储适配、上传接口稳定性和低维护成本上。

当前版本只保留一个上传入口：`api.php`。输出 WebP 或 AVIF 由 `other/config.ini` 中的 `imageFormat` 配置决定。

## 功能特性

- 支持点击上传、拖拽上传、粘贴上传、URL 本地化上传。
- 支持 JPEG、PNG、GIF、WebP、SVG、AVIF 上传。
- 默认将图片压缩为 WebP，默认质量为 70。
- 可在配置文件中将压缩输出改为 AVIF。
- 保持省流量策略，图片压缩时会限制最大边界约 `2500x1600`。
- `quality=100` 时不压缩，保留原图。
- 透明 PNG/WebP 转 WebP 会保留透明背景。
- 若配置为 AVIF，透明图会自动回退为 WebP，避免透明区域被写成黑色。
- 支持本地存储、阿里云 OSS、S3 兼容存储、FTP 存储。
- 后台支持登录、分页、复制链接、删除图片。
- 删除接口需要后台会话或上传返回的删除凭据。
- 安装完成后会生成安装锁，禁止重复安装。

## 目录结构

```text
.
├── index.php              # 前台上传页
├── api.php                # 上传接口
├── admin/index.php        # 后台管理页
├── other/
│   ├── core.php           # 配置、数据库、上传主流程
│   ├── image.php          # 图片检测、压缩、格式转换
│   ├── storage.php        # local / OSS / S3 / FTP 存储适配器
│   ├── config-api.php     # 前端读取上传配置
│   ├── del.php            # 删除接口
│   └── install.php        # 安装向导
├── static/                # CSS、JS、图标和背景资源
├── uploads/               # 本地上传目录
└── vendor/                # 第三方 SDK
```

## 运行环境

- PHP 8.1 或更高版本，推荐 PHP 8.3。
- MySQL 5.7 或更高版本。
- Nginx 或 Apache，建议启用 HTTPS。
- PHP 扩展：`fileinfo`、`imagick`、`mysqli`。
- 如果使用 FTP 存储，需要启用 PHP FTP 扩展。
- 如果上传大图，建议 PHP 与 Web 服务器上传限制至少设置为 50 MB。

推荐 PHP 配置：

```ini
upload_max_filesize = 50M
post_max_size = 50M
memory_limit = 512M
max_execution_time = 300
```

推荐 Nginx 配置：

```nginx
client_max_body_size 50m;
```

## 安装

1. 将项目文件上传到网站根目录。
2. 确认网站运行目录对 `other/`、`uploads/` 有写入权限。
3. 浏览器访问你的域名，会自动进入 `other/install.php`。
4. 填写 MySQL、管理员账号、存储方式等信息。
5. 安装完成后会生成：
   - `other/config.ini`
   - `other/install.lock`

安装完成后，访问网站根目录即可使用上传页，访问 `admin/` 进入后台。

## 升级

覆盖程序文件即可升级，但不要覆盖或删除以下内容：

```text
other/config.ini
other/install.lock
uploads/
```

如果你使用本地存储，`uploads/` 中保存的是实际图片文件。

## 安全配置

`other/config.ini` 包含数据库密码、存储密钥和上传 token，必须禁止公网访问。

Nginx 建议添加：

```nginx
location ~* /other/config\.ini$ {
    deny all;
}

location ~* /other/install\.lock$ {
    deny all;
}
```

也建议删除或限制不需要公开访问的测试文件，例如 `other/test.php`。

## 配置说明

配置文件位于：

```text
other/config.ini
```

主要配置项：

```ini
[Other]
validToken = "自动生成的上传 token"
storage = "local"
; imageFormat 可选 webp 或 avif，默认 webp。AVIF 压缩更慢、服务器压力更高；透明图若无法安全保存为 AVIF，会自动回退为 WebP。
imageFormat = "webp"
```

`storage` 可选：

```text
local
oss
s3
ftp
```

`imageFormat` 可选：

```text
webp
avif
```

说明：

- 默认推荐 `webp`，速度快，兼容性好，透明图表现稳定。
- `avif` 通常体积更小，但压缩更慢，对服务器压力更高。
- 配置为 `avif` 时，GIF 仍会转为 WebP；透明图也会优先回退为 WebP，避免透明背景异常。

## 存储配置

本地存储：

```ini
storage = "local"
```

OSS：

```ini
[OSS]
ossAccessKeyId = "你的 AccessKeyId"
ossAccessKeySecret = "你的 AccessKeySecret"
ossEndpoint = "oss-cn-example.aliyuncs.com"
ossBucket = "bucket-name"
ossdomain = "img.example.com"
```

S3：

```ini
[S3]
S3Region = "auto"
S3Bucket = "bucket-name"
S3Endpoint = "https://s3.example.com"
S3AccessKeyId = "你的 AccessKeyId"
S3AccessKeySecret = "你的 AccessKeySecret"
customUrlPrefix = "https://img.example.com"
```

FTP：

```ini
[FTP]
ftpHost = "ftp.example.com"
ftpPort = "21"
ftpUsername = "username"
ftpPassword = "password"
ftpdomain = "img.example.com"
```

域名类配置一般不要带结尾 `/`。

## 上传接口

接口地址：

```text
POST /api.php
```

表单字段：

| 字段 | 必填 | 说明 |
| --- | --- | --- |
| `image` | 是 | 上传文件 |
| `quality` | 否 | 压缩质量，范围 60-100，默认 70 |
| `token` | 是 | `other/config.ini` 中的 `validToken` |

成功响应示例：

```json
{
  "result": "success",
  "code": 200,
  "url": "https://img.example.com/uploads/2026/06/19/example.webp",
  "srcName": "example",
  "deleteToken": "delete-token",
  "width": 1200,
  "height": 800,
  "ptime": 123,
  "size": 102400
}
```

删除接口：

```text
POST /other/del.php
```

删除字段：

| 字段 | 必填 | 说明 |
| --- | --- | --- |
| `srcName` | 是 | 上传响应中的 `srcName` |
| `deleteToken` | 前台删除时必填 | 上传响应中的 `deleteToken` |

后台登录状态下也可以删除图片。

## UPGIT 配置示例

`config.toml`：

```toml
default_uploader = "minipix"

[uploaders.minipix]
request_url = "https://img.example.com/api.php"
token = "other/config.ini 中的 validToken"
```

`extensions/minipix.jsonc`：

```jsonc
{
    "meta": {
        "id": "minipix",
        "name": "MiniPix Uploader",
        "type": "simple-http-uploader",
        "version": "1.0.0",
        "repository": ""
    },
    "http": {
        "request": {
            "url": "$(ext_config.request_url)",
            "method": "POST",
            "headers": {
                "Content-Type": "multipart/form-data",
                "User-Agent": "Mozilla/5.0"
            },
            "body": {
                "token": {
                    "type": "string",
                    "value": "$(ext_config.token)"
                },
                "quality": {
                    "type": "string",
                    "value": "70"
                },
                "image": {
                    "type": "file",
                    "value": "$(task.local_path)"
                }
            }
        }
    },
    "upload": {
        "rawUrl": {
            "from": "json_response",
            "path": "url"
        }
    }
}
```

## 常见问题

### 手机上传失败，但电脑可以上传

先确认手机是否请求到了 `other/config-api.php`。如果旧版 `static/js/script.js` 被 CDN 或浏览器强缓存，前端可能会携带旧 token，接口会返回 403。清理缓存或使用带版本号的 JS 引用即可解决。

### AVIF 透明背景变黑

这是部分 Imagick / AVIF 编码组合对 alpha 通道支持不稳定导致的。当前版本会检测透明图，配置为 AVIF 时自动回退输出 WebP。

### 上传大图失败

检查 PHP 与 Web 服务器上传限制：

```ini
upload_max_filesize
post_max_size
memory_limit
max_execution_time
```

以及 Nginx：

```nginx
client_max_body_size
```

### 安装页重复出现

检查 `other/install.lock` 是否存在，以及 PHP 运行用户是否有权限读取该文件。

## 维护说明

- 上传主流程在 `other/core.php`。
- 图片处理逻辑在 `other/image.php`。
- 存储渠道逻辑在 `other/storage.php`。
- 前台上传逻辑在 `static/js/script.js`。
- 后台分页和管理逻辑在 `admin/index.php` 与 `static/js/ajax.js`。

项目不依赖大型框架，适合直接部署在普通 PHP 虚拟主机或宝塔环境中。
