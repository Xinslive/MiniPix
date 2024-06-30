## **前言**
前段时间有心思把我的博客从WordPress迁移到Hexo。Hexo使用Markdown语法撰写文章，没有WP上传图片那么方便了，我得找一个图床，但是又不想弄的太复杂，兰空图床的图片压缩率我个人不是很满意，也不敢用免费的图床。

目前我在用的一款WordPress图像转webp插件压缩率很高，并且还不怎么影响画质。我就在这个插件的压缩原理基础上延伸内容，写了一个简单的图床程序。

正好阿里云40GB的OSS资源包卖的便宜，就使用OSS来作为图床空间。
## **演示站点**
https://dev.yeuer.com/

后台：https://dev.yeuer.com/admin

账号：admin

密码：123456
## **项目详情**
本项目由几个简单的文件组成。采用简单高效的方式进行图片压缩，支持自定义压缩率和尺寸。
帮助大家减少图片储存、流量等方面的支出。
感谢🙏梦爱吃鱼 | blog.bsgun.cn对本项目的美化
## **安装教程**
将文件上传到网站根目录，访问  网址/install.php  ，填写相关信息，即可完成安装。
## **请注意**
本程序依赖PHP的 Fileinfo 、 Imagick 拓展，需要自行安装。依赖 pcntl 扩展（宝塔PHP默认已安装）

要求 pcntl_signal 和 pcntl_alarm 函数可用（需解除禁用）。
## **拓展功能**

本程序支持 UPGIT 对接，对接方法如下：

**UPGIT 配置信息**

在upgit.exe所在目录下新建`config.toml`文件。文件内容如下：
```
default_uploader = "easyimage"

[uploaders.easyimage]
request_url = "https://xxx.xxx.xxx/api.php"
token = "xxxxxxxxxxxxxxxxxxxxx"

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
