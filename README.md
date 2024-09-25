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
* 在保证图像清晰地条件下给你最大的压缩率，同时也支持仅保存原图。


## **安装教程**
将安装包解压到网站根目录，直接访问你的域名，填写配置信息。
### **版本升级**
下载最新的安装包，解压覆盖旧版本文件即可升级。
## **运行环境**
推荐PHP 8.1 + MySQL 5.7，必须配置 SSL证书。为什么推荐这两个？因为演示站运行环境就是这，已知PHP5.4不支持。

一、本程序依赖PHP的 Fileinfo 、 Imagick 拓展，需要自行安装。

二、安装时会进行在线下载所需要的SDK，依赖 **exec** 函数，但是宝塔是默认禁用的，自己在PHP禁用函数那里解除一下禁用，安装好之后就用不到 exec 了，建议恢复禁用状态。

三、安装SDK出现问题的（主要是墙的原因，导致国内服务器下载SDK失败），可以解压根目录的SDK压缩包文件到网站根目录，再进行安装。（SDK零碎的文件太多，网页一次只能上传一百个文件到Github上，我索性就上传一个压缩包完事）

二和三选一个就行，选二就不用再进行三了，二出了问题可以再进行三，也可以跳过二直接进行三。三是保底措施。

我目前用的 ImageMagick 6.9.11-60 Q16 x86_64 2021-01-25 这个版本是支持 avif 和 webp 格式的。如果你的 *ImageMagick* 不支持 WEBP ，那么你就要想办法换个支持的，怎么换我也没弄明白，宝塔可以参考这个文章升级一下ImageMagick版本，但是存在升级后还是不支持的可能性：https://dev.euyyue.com/note/379.html

## **安全配置**
### **配置信息安全**
设置如下 nginx 规则（可以放到伪静态规则那里）
```
location ~* /config\.ini$ {
    deny all;
}
```
### **上传限制**
先删除api.php文件第二行的注释符，启用上传限制，再根据需要，编辑 other/validate.php 文件。
### **修改后台地址**
直接修改 admin 目录名即可

## **参数介绍**
```
validToken = 1c17b11693cb5ec63859b091c5b9c1b2 //我在首页js里写死这个token了，如果你要修改，记得同步修改首页js内的token
storage = local //可选参数有：local s3 oss ftp

[OSS]
ossAccessKeyId = xxxxxxxxxx
ossAccessKeySecret = xxxxxxxxx
ossEndpoint = xxxxxxxxx  //地域节点
ossBucket = xxxxxxxxx  //储存桶名
ossdomain = xxx.xxx.xxx //不要携带 “http(s)://” 和 “ / ” 


[S3]
S3Region = cn-east-1
S3Bucket = xxxxxx
S3Endpoint = https://s3.bitiful.net  //链接后面不带“ / ”，但是前面要带“ https:// ”
S3AccessKeyId = xxxxxxx
S3AccessKeySecret = xxxxxxxxx
customUrlPrefix = https://xxx.xxx.xxx  //你的自定义域名，链接后面不带“ / ”，但是前面要带“ https:// ”

[FTP]
ftpHost = xxx.xxx.xxx  //这里填你的ftp主机地址
ftpPort = 21 //ftp默认端口一般是21，但是也有不用默认端口的，根据实际来填写。
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
