jQuery(document).ready(function($) {
    // 获取最大文件大小
    var maxFileSize = image_hosting_params.max_file_size; // 以字节为单位
    var maxFileSizeMb = image_hosting_params.max_file_size_mb; // 以MB为单位

    // 获取允许的文件格式
    var allowedFormats = image_hosting_params.allowed_formats;

    // 拖拽和选择图片上传
    $('.simple-image-hosting-upload-form #image-upload-input').on('change', function(e) {
        handleImageUpload(e.target.files);
    });

    $('.simple-image-hosting-drop-area').on('dragover', function(e) {
        e.preventDefault();
    }).on('drop', function(e) {
        e.preventDefault();
        handleImageUpload(e.originalEvent.dataTransfer.files);
    });

    // 图片上传处理函数
    function handleImageUpload(files) {
        // 遍历所有文件并进行检查
        for (var i = 0; i < files.length; i++) {
            var file = files[i];

            // 检查文件格式
            var fileExtension = file.name.split('.').pop().toLowerCase();
            if (!allowedFormats.includes(fileExtension)) {
                updateUploadMessage('文件 "' + file.name + '" 格式不支持。只允许上传：' + allowedFormats.join(', ') + '。', 'upload-message-error');
                return; // 阻止文件上传
            }

            // 检查文件大小
            if (file.size > maxFileSize) {
                updateUploadMessage('文件 "' + file.name + '" 大小超过限制。最大支持上传 ' + maxFileSizeMb + 'MB的文件。', 'upload-message-error');
                return; // 阻止文件上传
            }
        }

        // 文件格式和大小都没有问题，进行上传
        var formData = new FormData();
        for (var i = 0; i < files.length; i++) {
            formData.append('files[]', files[i]);
        }

        formData.append('action', 'handle_image_upload');
        formData.append('security', image_hosting_params.ajax_nonce);

        $.ajax({
            url: image_hosting_params.ajax_url,
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            dataType: 'json',
            beforeSend: function() {
                $('.simple-image-hosting-upload-form button').text('上传中...').prop('disabled', true);
                $('#upload-message').text('正在上传，请稍候...').removeClass().addClass('upload-message-info').show();
            },
            success: function(response) {
                $('.simple-image-hosting-upload-form button').text('上传').prop('disabled', false);
                if (response.success) {
                    response.data.files.forEach(function(file) {
                        displayImagePreview(file.url, file.file_name, file.markdown, file.bbcode);
                    });
                    updateUploadMessage('上传成功！', 'upload-message-success');
                } else {
                    updateUploadMessage('上传失败: ' + response.data.message, 'upload-message-error');
                }
            },
            error: function(xhr, status, error) {
                console.error("AJAX 请求失败:", status, error);
                updateUploadMessage('上传失败，请稍后重试', 'upload-message-error');
                $('.simple-image-hosting-upload-form button').text('上传').prop('disabled', false);
            }
        });
    }

    // 显示图片预览及URL链接
    function displayImagePreview(url, fileName, markdownCode, bbcodeCode) {
    // 生成Markdown和BBCode代码
    var markdownCode = `![${fileName}](${url})`;
    var bbcodeCode = `[img]${url}[/img]`;

    var previewHTML = `
        <div class="simple-image-hosting-image-item">
            <img src="${url}" class="simple-image-hosting-image-preview" />
            <input type="text" value="${url}" class="simple-image-hosting-image-url" readonly />
            <button class="simple-image-hosting-copy-btn" onclick="copyLink('${url}')">复制当前图片链接</button>
            <p class="simple-image-hosting-markdown-label">Markdown格式:</p>
            <input type="text" value="${markdownCode}" class="simple-image-markdown-url" readonly />
            <p class="simple-image-hosting-bbcode-label">BBCode格式:</p>
            <input type="text" value="${bbcodeCode}" class="simple-image-bbcode-url" readonly />
        </div>
    `;
    $('.simple-image-hosting-preview-container').append(previewHTML);
    $('.simple-image-hosting-copy-all-btn').show();
}

    // 复制当前图片链接
    window.copyLink = function(url) {
        var textarea = document.createElement('textarea');
        textarea.value = url;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);

        // 显示复制成功
        updateUploadMessage('当前图片链接已复制', 'upload-message-info');
    };

    // 一键复制所有图片链接
    $('.simple-image-hosting-copy-all-btn').on('click', function() {
        var allLinks = [];
        $('.simple-image-hosting-image-url').each(function() {
            allLinks.push($(this).val());
        });
        copyAllLinks(allLinks);
    });

    // 复制所有图片链接
    function copyAllLinks(links) {
        var textarea = document.createElement('textarea');
        textarea.value = links.join('\n');
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);

        // 显示复制成功
        updateUploadMessage('所有图片链接已复制', 'upload-message-info');
    }

    // 更新消息显示函数
    function updateUploadMessage(message, messageClass) {
        var $messageContainer = $('#upload-message');

        // 每次显示新的消息时，先清除旧的样式和内容
        $messageContainer.removeClass().text(message).addClass(messageClass).show();

        // 2秒后自动隐藏消息
        setTimeout(function() {
            $messageContainer.fadeOut();
        }, 2000);
    }
});
