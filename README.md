## **前言**
点一个 Star 再走吧~

前段时间有心思把我的博客从WordPress迁移到Hexo。Hexo使用Markdown语法撰写文章，没有WP上传图片那么方便了，我得找一个图床程序，兰空图床的图片压缩率不够高，我的阿里云小水管顶不住，既不想花太多钱在CDN上，也不敢用免费的图床。

想要图片清晰，又想要流量费用低、加载速度快？怎么样才能有一个双全法呢？幸好让我遇见了它——背字根开发的Webp插件。

这是我用过效果最好的一款WordPress图像转webp插件，图片压缩率很高，并且还不怎么影响画质，可惜它**多年没更新了**。我就在这个插件的压缩原理基础上延伸内容，写了一个简单的图床程序。

正好阿里云40GB的OSS资源包卖的便宜，就使用OSS来作为图床空间。

注：本程序只适合个人自用。
## **演示站点**
https://dev.yeuer.com/

后台：https://dev.yeuer.com/admin

账号：admin

密码：123456
## **项目简介**
本项目由几个简单的文件组成。采用简单高效的方式进行图片压缩，支持自定义压缩率和尺寸。
帮助大家减少图片储存、流量等方面的支出。


* 支持上传JPEG、PNG、GIF格式图片并转换为WEBP格式
* 支持上传SVG、WEBP图片
* 支持本地储存、阿里云OSS储存(默认)
* 简洁美观的前端
* 瀑布流管理后台
* 支持自定义压缩率
* 支持自定义压缩图片尺寸限制

感谢🙏梦爱吃鱼（blog.bsgun.cn）对本项目的美化！

如果你需要本地储存图片，请安装后修改config.ini文件
```
storage = local
```
## **安装教程**
首先下载源码ZIP，将文件上传到网站根目录，访问  网址/install.php  ，填写相关信息，即可完成安装。
## **运行环境**
推荐PHP 8.1 + MySQL 5.7

本程序依赖PHP的 Fileinfo 、 Imagick 拓展，需要自行安装。依赖 pcntl 扩展（宝塔PHP默认已安装）

要求 pcntl_signal 和 pcntl_alarm 函数可用（需主动解除禁用）。

## **安全配置**
### **配置信息安全**
设置如下 nginx 规则
```
location ~* /config\.ini$ {
    deny all;
}
```
### **图片合规检测**
#### **使用ModerateContent**
ModerateContent提供免费的NSFW检测API。你可以在validate.php中添加对ModerateContent的请求。
```
<?php
session_start();

function isUploadAllowed() {
    // 上传大小限制
    if ($_FILES['image']['size'] > 10000000) {
        return '文件大小超过10MB';
    }

    // 上传频率限制
    $timeLimit = 5; // 10秒
    if (isset($_SESSION['last_upload_time'])) {
        $lastUploadTime = $_SESSION['last_upload_time'];
        if (time() - $lastUploadTime < $timeLimit) {
            return '上传过于频繁，请稍后再试';
        }
    }

    // 更新最后上传时间
    $_SESSION['last_upload_time'] = time();

    return true;
}

function moderateContent($imagePath) {
    $url = 'https://api.moderatecontent.com/moderate/?key=YOUR_API_KEY&url=' . urlencode($imagePath);
    $response = file_get_contents($url);
    $result = json_decode($response, true);

    return $result['rating_label'] !== 'adult';
}

$uploadCheck = isUploadAllowed();
if ($uploadCheck !== true) {
    echo json_encode(['error' => $uploadCheck]);
    exit();
}

// 临时存储文件以供审查
$imagePath = '/tmp/' . basename($_FILES['image']['name']);
move_uploaded_file($_FILES['image']['tmp_name'], $imagePath);

if (!moderateContent($imagePath)) {
    echo json_encode(['error' => '上传的图片包含不适当内容']);
    exit();
}

// 其他代码...
?>
```
#### **使用百度图像审核**
百度AI提供的图像审核服务可以更精确地检测不适当内容，你需要在百度AI平台注册并获取API密钥。
注：这只是一个示例，把KEY保存在这里是不安全的。可以存到config.ini文件中，具体方法不赘述。
```
<?php
session_start();

function isUploadAllowed() {
    // 上传大小限制
    if ($_FILES['image']['size'] > 10000000) {
        return '文件大小超过10MB';
    }

    // 上传频率限制
    $timeLimit = 5; // 5秒
    if (isset($_SESSION['last_upload_time'])) {
        $lastUploadTime = $_SESSION['last_upload_time'];
        if (time() - $lastUploadTime < $timeLimit) {
            return '上传过于频繁，请稍后再试';
        }
    }

    // 更新最后上传时间
    $_SESSION['last_upload_time'] = time();

    return true;
}

function baiduImageAudit($imagePath) {
    $apiKey = 'YOUR_API_KEY';
    $secretKey = 'YOUR_SECRET_KEY';

    // 获取access_token
    $authUrl = 'https://aip.baidubce.com/oauth/2.0/token?grant_type=client_credentials&client_id=' . $apiKey . '&client_secret=' . $secretKey;
    $authResponse = file_get_contents($authUrl);
    $authResult = json_decode($authResponse, true);
    $accessToken = $authResult['access_token'];

    // 读取图片并进行Base64编码
    $imageData = base64_encode(file_get_contents($imagePath));

    // 审核请求
    $url = 'https://aip.baidubce.com/rest/2.0/solution/v1/img_censor/v2/user_defined?access_token=' . $accessToken;
    $postData = [
        'image' => $imageData,
    ];
    $options = [
        'http' => [
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($postData),
        ],
    ];
    $context  = stream_context_create($options);
    $response = file_get_contents($url, false, $context);
    $result = json_decode($response, true);

    return $result['conclusion'] !== '不合规';
}

$uploadCheck = isUploadAllowed();
if ($uploadCheck !== true) {
    echo json_encode(['error' => $uploadCheck]);
    exit();
}

// 临时存储文件以供审查
$imagePath = '/tmp/' . basename($_FILES['image']['name']);
move_uploaded_file($_FILES['image']['tmp_name'], $imagePath);

if (!baiduImageAudit($imagePath)) {
    echo json_encode(['error' => '上传的图片包含不适当内容']);
    exit();
}

// 其他代码...
?>
```
#### **自建NSFW服务**
你也可以使用开源的NSFW模型，如nsfwjs，并在本地或服务器上部署。
```
<?php
session_start();

function isUploadAllowed() {
    // 上传大小限制
    if ($_FILES['image']['size'] > 10000000) {
        return '文件大小超过10MB';
    }

    // 上传频率限制
    $timeLimit = 5; // 5秒
    if (isset($_SESSION['last_upload_time'])) {
        $lastUploadTime = $_SESSION['last_upload_time'];
        if (time() - $lastUploadTime < $timeLimit) {
            return '上传过于频繁，请稍后再试';
        }
    }

    // 更新最后上传时间
    $_SESSION['last_upload_time'] = time();

    return true;
}

function nsfwCheck($imagePath) {
    $url = 'http://your_nsfw_service_endpoint';
    $imageData = base64_encode(file_get_contents($imagePath));

    $postData = json_encode(['image' => $imageData]);
    $options = [
        'http' => [
            'header'  => "Content-Type: application/json\r\n",
            'method'  => 'POST',
            'content' => $postData,
        ],
    ];
    $context  = stream_context_create($options);
    $response = file_get_contents($url, false, $context);
    $result = json_decode($response, true);

    return $result['safe'];
}

$uploadCheck = isUploadAllowed();
if ($uploadCheck !== true) {
    echo json_encode(['error' => $uploadCheck]);
    exit();
}

// 临时存储文件以供审查
$imagePath = '/tmp/' . basename($_FILES['image']['name']);
move_uploaded_file($_FILES['image']['tmp_name'], $imagePath);

if (!nsfwCheck($imagePath)) {
    echo json_encode(['error' => '上传的图片包含不适当内容']);
    exit();
}

// 其他代码...
?>
```

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
