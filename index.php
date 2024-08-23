<?php
if (!file_exists('static/install.lock')) {
    header('Location: vendor/install.php');
    exit;
}
?>
<html lang="zh-CN"><head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>MiniPix 轻量图床</title>
    <meta name="keywords" content="图床程序,高效图片压缩,前端后台设计,图片上传,WEBP转换,阿里云OSS,本地存储,多格式支持,瀑布流管理,图片管理后台,自定义压缩率,尺寸限制">
    <meta name="description" content="一款专为个人需求设计的高效图床解决方案，集成了强大的图片压缩功能与优雅的前台后台界面。项目结构精简高效，提供自定义图片压缩率与尺寸设置，有效降低存储与带宽成本。支持JPEG, PNG, GIF转换为WEBP以及SVG、WEBP直接上传，搭载阿里云OSS存储（默认）及灵活的本地存储选项。特性包括点击、拖拽、粘贴及URL本地化上传方式，以及配备瀑布流布局的管理后台，实现图片轻松管理与预览。完全可自定制的体验，满足不同用户对图片管理和优化的高级需求。">
	<link rel="shortcut icon" href="static/favicon.ico">
	<link rel="stylesheet" type="text/css" href="static/css/styles.css">
</head>
	<body>
		<div class="uploadForm">
		 <div id="deleteButtonWrapper" style="position: absolute;"></div>
         <button id="deleteImageButton">×</button>
		<form id="uploadForm" action="api.php" method="POST" enctype="multipart/form-data">
			<div id="imageUploadBox" onclick="document.getElementById('imageInput').click();">
				<input type="file" id="imageInput" name="image" accept="image/*" required style="display: none;" onchange="updateImagePreview(event);">
				<img id="imagePreview" src="static/svg/up.svg" alt="预览图片">
			</div>
			<div id="pasteOrUrlInputBox">
				<input type="text" id="pasteOrUrlInput" placeholder="此处可粘贴图像URL或使用Ctrl+V粘贴图片">
			</div>
			<div id="parameters">
				<label for="qualityInput">压缩质量（60-99）：<output id="qualityOutput">70</output>
				</label>
				<input type="range" id="qualityInput" name="quality" min="60" max="99" value="70" step="1">
			</div>
			<div id="progressContainer">
				<div id="progressBar"></div>
			</div>
		</form>
		</div>
		<div id="urlOutput">
			<input type="text" class="copy-indicator" id="imageUrl" readonly placeholder="图片链接">
			<input type="text" class="copy-indicator" id="markdownUrl" readonly placeholder="Markdown代码">
			<input type="text" class="copy-indicator" id="markdownLinkUrl" readonly placeholder="Markdown链接代码">
			<input type="text" class="copy-indicator" id="htmlUrl" readonly placeholder="HTML代码">
			<input type="text" class="hidden-input" id="imagePath" placeholder="图片路径">
		</div>
		<div id="imageInfo" class="double-column-layout">
			<div>
				<h2>处理前</h2>
				<div style="text-align:center;">
					<p>大小：<span id="originalSize">0</span> KB</p>
				</div>
			</div>
			<div>
				<h2>处理后</h2>
				<div style="text-align:center;">
					<p>大小：<span id="compressedSize">0</span> KB</p>
				</div>
			</div>
		</div>
<script type="text/javascript" src="static/js/script.js"></script>
<script type="text/javascript" src="static/js/cursor.js"></script>
</body>
</html>
