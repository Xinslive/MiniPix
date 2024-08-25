## **前言**
点一个 Star 再走吧~

前段时间有心思把我的博客从WordPress迁移到Hexo。Hexo使用Markdown语法撰写文章，没有WP上传图片那么方便了，我需要一个图床程序，兰空图床不够轻量，压缩效果好像也不是太好，我的阿里云小水管顶不住大图片，既不想花太多钱在CDN上，也不敢用免费的图床网站。

于是就有了它 —— **MniPix**

注：本程序只适合个人自用。目前的功能供个人使用绰绰有余，如果你需要更复杂的更商业化的功能建议选择其他图床程序。
## **演示站点**

首页：[MiniPix.Pro](https://MiniPix.Pro/)

后台：[MiniPix.Pro 管理后台](https://MiniPix.Pro/admin)

## **项目简介**
一款专为个人需求设计的高效图床解决方案，集成了强大的图片压缩功能与优雅的前台后台界面。

项目结构精简高效，提供自定义图片压缩率与尺寸设置，有效降低存储与带宽成本。


* 支持将JPEG / PNG / GIF 图片转换为 WEBP 格式
* 支持阿里OSS储存(默认)、本地储存，可通过把储存桶挂载到本地的方式解锁更多储存方式。
* 简洁美观的前端，支持点击上传、拖拽上传、粘贴上传、URL本地化。
* 瀑布流管理后台，便捷管理图片，支持图片灯箱、AJAX无加载刷新。


感谢🙏梦爱吃鱼（blog.bsgun.cn）对本项目的美化！

如果你需要**本地储存图片**，可在安装后修改 static/config.ini 文件中的 storage 参数为 local
```
storage = local
```
## **安装教程**
首先下载源码ZIP，将文件上传到网站根目录，直接访问你的网址，填写相关信息，即可完成安装。
### **版本升级**
仅保留 uploads 目录 ，其他的全部删除，下载最新版的安装包，完成安装操作，
## **运行环境**
推荐PHP 8.1 + MySQL 5.7，注意要给网站配置SSL证书。
本程序依赖PHP的 Fileinfo 、 Imagick 拓展，需要自行安装。（如果出现问题，请先确认你安装的 Imagick 拓展是否支持 Webp ）
默认提供的安装包不含SDK，安装时会进行在线下载所需要的sdk。（依赖exec函数，自己在php那里解除一下禁用，安装好之后就用不到exec了）
如果你的服务器由于网络原因无法正常在线下载SDK文件，我这里也提供包含SDK的安装包（在版本介绍里面提供链接）
## **安全配置**
### **配置信息安全**
设置如下 nginx 规则
```
location ~* /config\.ini$ {
    deny all;
}
```
### **上传限制**
先删除api.php文件第二行的注释符。

再编辑 vendor/validate.php 文件。
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
