## **演示站点**

首页：[MiniPix.Pro](https://MiniPix.Pro/)

后台：[MiniPix.Pro 管理后台](https://MiniPix.Pro/admin)

## **项目简介**
一款专为个人需求设计的高效图床解决方案，集成了强大的图片压缩功能与优雅的前台后台界面。

项目结构精简高效，提供自定义图片压缩率与最大尺寸设置，有效降低存储与带宽成本。


* 支持将JPEG / PNG / GIF 图片转换为 WEBP 格式。
* 支持S3储存、FTP远程储存、阿里OSS储存、本地储存，还可以通过把储存桶挂载到本地的方式解锁更多储存方式。
* 简洁美观的前端，支持点击上传、拖拽上传、粘贴上传、URL本地化。
* 瀑布流管理后台，便捷查找图片，支持图片灯箱、AJAX无加载刷新。


## **安装教程**
将安装包解压到网站根目录，直接访问你的域名，填写配置信息。
### **版本升级**
下载最新的安装包，解压覆盖旧版本文件即可升级。
## **运行环境**
推荐PHP 8.1 + MySQL 5.7，必须配置 SSL证书。
本程序依赖PHP的 Fileinfo 、 Imagick 拓展，需要自行安装。（如果出现问题，请先确认你安装的 Imagick 拓展是否支持 Webp ）（在这里我提供了一个简单的测试方法，访问 **域名/other/test.php** 就可以看到你现在用的 Imagick 拓展是否支持 Webp ）

默认提供的安装包不含SDK，安装时会进行在线下载所需要的SDK。（安装时依赖 **exec** 函数，但是宝塔是默认禁用的，自己在PHP禁用函数那里解除一下禁用，安装好之后就用不到 exec 了，建议恢复禁用状态。）

安装SDK过程中出现问题的（主要是墙的原因），可以解压根目录的SDK文件到网站根目录，再进行安装。（零碎的文件太多，网页一次只能上传一百个文件到github上，我索性就上传一个压缩包完事）

## **安全配置**
### **配置信息安全**
设置如下 nginx 规则（可以放到伪静态规则那里）
```
location ~* /config\.ini$ {
    deny all;
}
```
### **上传限制**
先删除api.php文件第二行的注释符，再编辑 other/validate.php 文件。
```
<?php
session_start();

function isUploadAllowed() {
    // 上传大小限制
    if ($_FILES['image']['size'] > 5000000) {
        return '文件大小超过5MB';
    }

    // 上传频率限制
    $timeLimit = 3; // 3秒
    if (isset($_SESSION['last_upload_time'])) {
        $lastUploadTime = $_SESSION['last_upload_time'];
        if (time() - $lastUploadTime < $timeLimit) {
            return '上传过于频繁，请稍后再试';
        }
    }

    $_SESSION['last_upload_time'] = time();

    return true;
}

$uploadCheck = isUploadAllowed();
if ($uploadCheck !== true) {
    echo json_encode(['error' => $uploadCheck]);
    exit();
}
?>
```
### **修改后台地址**
直接修改 admin 目录名即可

## **参数介绍**
```
validToken = 1c17b11693cb5ec63859b091c5b9c1b2 //对接API时需要验证的Token，不建议修改。
storage = local //可选参数有：local s3 oss ftp

[OSS]
ossAccessKeyId = xxxxxxxxxx
ossAccessKeySecret = xxxxxxxxx
ossEndpoint = xxxxxxxxx
ossBucket = xxxxxxxxx
ossdomain = xxx.xxx.xxx //不要携带 “http(s)://” 和 “ / ” 


[S3]
S3Region = cn-east-1
S3Bucket = xxxxxx
S3Endpoint = https://s3.bitiful.net  //链接后面不带“ / ”
S3AccessKeyId = xxxxxxx
S3AccessKeySecret = xxxxxxxxx
customUrlPrefix = https://xxx.xxx.xxx  //你的自定义域名，链接后面不带“ / ”

[FTP]
ftpHost = xxx.xxx.xxx  //这里填你的ftp主机地址
ftpPort = 21 //ftp默认端口一般是21，但是也有不用默认端口的
ftpUsername = xxxxx //ftp账号
ftpPassword = xxxxx //ftp密码
ftpdomain = xxx.xxx.xxx //你绑定ftp的域名，不要携带 “http(s)://” 和 “ / ” 
```

## **拓展功能**

本程序支持 UPGIT 对接，对接方法如下：

**UPGIT 配置信息**

在upgit.exe所在目录下新建`config.toml`文件。文件内容如下：
```
default_uploader = "easyimage"

[uploaders.easyimage]
request_url = "https://xxx.xxx.xxx/api.php"
token = "1c17b11693cb5ec63859b091c5b9c1b2"

```

创建一个 upgit.exe 的同级目录：**extensions**

然后到 **extensions** 目录下新建一个 **easyimage.jsonc** 文件，输入下面的内容并保存。
```
{
    "meta": {
        "id": "easyimage",
        "name": "EasyImage Uploader",
        "type": "simple-http-uploader",
        "version": "0.0.1",
        "repository": ""
    },
    "http": {
        "request": {
            // See https://www.kancloud.cn/easyimage/easyimage/2625228
            "url": "$(ext_config.request_url)",
            "method": "POST",
            "headers": {
                "Content-Type": "multipart/form-data",
                "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/98.0.4758.80 Safari/537.36"
            },
            "body": {
                "token": {
                    "type": "string",
                    "value": "$(ext_config.token)"
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
