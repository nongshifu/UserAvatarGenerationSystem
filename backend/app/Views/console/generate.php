<?php $active='generate'; include __DIR__ . '/../_partials/header.php'; ?>
<style>
.history-head{display:flex;align-items:baseline;gap:10px;margin-bottom:10px;flex-wrap:wrap}
.history-row{display:flex;gap:10px;overflow-x:auto;padding:2px 2px 8px;scroll-snap-type:x proximity;-webkit-overflow-scrolling:touch}
.history-row::-webkit-scrollbar{height:6px}
.history-row::-webkit-scrollbar-thumb{background:rgba(255,255,255,.16);border-radius:3px}
.history-thumb{position:relative;flex:0 0 auto;width:76px;height:76px;padding:0;border-radius:12px;overflow:hidden;border:2px solid rgba(255,255,255,.14);background:rgba(255,255,255,.06);cursor:pointer;scroll-snap-align:start;transition:border-color .15s,box-shadow .15s,transform .15s}
.history-thumb img{width:100%;height:100%;object-fit:cover;display:block;background:rgba(0,0,0,.3)}
.history-thumb:hover{border-color:rgba(139,115,255,.7);transform:translateY(-2px)}
.history-thumb.active{border-color:#8b73ff;box-shadow:0 0 0 3px rgba(123,92,255,.3)}
.history-thumb.active::after{content:'✓';position:absolute;top:4px;right:4px;width:20px;height:20px;line-height:20px;text-align:center;font-size:.72rem;border-radius:50%;background:linear-gradient(135deg,#7b5cff,#00d4ff);color:#fff;font-weight:700;box-shadow:0 2px 6px rgba(0,0,0,.35)}
</style>
<section class="container">
    <div class="console-layout">
        <?php include __DIR__ . '/../_partials/console_side.php'; ?>
        <div>
            <h2 style="margin-bottom:20px">生成头像</h2>
            <?php if (!empty($error)): ?><div class="alert"><?= $e($error) ?></div><?php endif; ?>
            <?php if (!empty($needPhoneVerify)): ?>
            <div class="alert" style="background:rgba(230,162,60,.12);border-color:rgba(230,162,60,.45);color:#ffd28a;margin-bottom:16px">
                请先在<a href="/console/profile" style="color:#ffd28a;text-decoration:underline">「个人资料」</a>绑定并验证手机号后，才能生成头像。
            </div>
            <?php endif; ?>
            <form method="post" action="/console/generate" enctype="multipart/form-data" id="genForm">
                <div class="card" style="margin-bottom:16px">
                    <label>上传照片</label>
                    <div class="uploader" id="uploader">
                        <input type="file" name="file" id="fileInput" accept="image/jpeg,image/png,image/webp" hidden>
                        <input type="hidden" name="image" id="imageInput">
                        <div class="uploader-empty" id="uploaderEmpty">
                            <svg viewBox="0 0 24 24" width="34" height="34" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" style="opacity:.7;margin-bottom:8px">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                <polyline points="17 8 12 3 7 8"/>
                                <line x1="12" y1="3" x2="12" y2="15"/>
                            </svg>
                            <p style="margin:0;font-weight:600">点击选择图片，或将图片拖到此处</p>
                            <p class="muted" style="font-size:.8rem;margin:6px 0 0">支持 jpg / png / webp，≤ 10MB，上传时自动压缩，无需准备高清图</p>
                        </div>
                        <div class="uploader-preview" id="uploaderPreview" hidden>
                            <img id="previewImg" alt="已选照片预览">
                            <div class="uploader-meta">
                                <div class="uploader-name" id="fileName"></div>
                                <div class="uploader-hint" id="uploaderHint">图片已就绪，可在下方选择风格后生成；如需更换请点击右侧按钮</div>
                                <button type="button" class="btn btn-sm" id="reselectBtn">重新选择</button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php if (!empty($historyImages)): ?>
                <div class="card" style="margin-bottom:16px">
                    <div class="history-head">
                        <label style="margin:0">历史上传</label>
                        <span class="muted" style="font-size:.8rem">点击可直接复用，无需重新上传</span>
                    </div>
                    <div class="history-row" id="historyRow">
                        <?php foreach ($historyImages as $hurl): ?>
                        <button type="button" class="history-thumb" data-url="<?= $e($hurl) ?>" title="使用这张照片">
                            <img src="<?= $e($hurl) ?>" alt="历史上传照片" loading="lazy"
                                 onerror="this.closest('.history-thumb').remove()">
                        </button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                <div class="card" style="margin-bottom:16px">
                    <label>风格</label>
                    <div class="radio-pills">
                        <label class="radio-pill"><input type="radio" name="style_id" value="0" checked> 默认</label>
                        <?php foreach ($styles as $s): ?>
                        <label class="radio-pill">
                            <input type="radio" name="style_id" value="<?= (int)$s['id'] ?>"> <?= $e($s['name']) ?>
                            <?php if ((int)$s['cost_points']>0): ?><span class="muted">(<?= (int)$s['cost_points'] ?>分)</span><?php endif; ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <label style="margin-top:12px">颜色</label>
                    <select name="color_id"><option value="0">默认</option>
                    <?php foreach ($colors as $c): ?><option value="<?= (int)$c['id'] ?>"><?= $e($c['name']) ?></option><?php endforeach; ?>
                    </select>
                    <label>形状</label>
                    <select name="shape_id"><option value="0">默认</option>
                    <?php foreach ($shapes as $s): ?><option value="<?= (int)$s['id'] ?>"><?= $e($s['name']) ?></option><?php endforeach; ?>
                    </select>
                    <label class="check-line" style="margin-top:16px"><input type="checkbox" name="is_public" value="1" checked> 加入公共头像池（可被其他用户浏览）</label>
                </div>
                <div class="card" style="display:flex;justify-content:space-between;align-items:center">
                    <span class="muted">当前余额 <b style="color:#7b5cff"><?= $e((string)$balance) ?></b> 积分</span>
                    <button type="submit" class="btn">确认生成</button>
                </div>
            </form>
        </div>
    </div>
</section>
<script>
(function () {
    var uploader = document.getElementById('uploader');
    var input = document.getElementById('fileInput');
    var emptyBox = document.getElementById('uploaderEmpty');
    var previewBox = document.getElementById('uploaderPreview');
    var previewImg = document.getElementById('previewImg');
    var fileName = document.getElementById('fileName');
    var reselectBtn = document.getElementById('reselectBtn');
    var form = document.getElementById('genForm');
    var imageInput = document.getElementById('imageInput');
    var historyRow = document.getElementById('historyRow');
    var uploaderHint = document.getElementById('uploaderHint');
    var currentFile = null;
    var previewUrl = null;
    var historyUrl = null;

    // 参考图压缩参数：最长边限制 + JPEG 质量（图生图无需原图分辨率）
    var MAX_DIM = 1536;
    var JPEG_QUALITY = 0.85;
    var SKIP_COMPRESS_BYTES = 500 * 1024; // 已经很小的 jpeg 直接用

    function pick() { input.click(); }

    var DEFAULT_HINT = '图片已就绪，可在下方选择风格后生成；如需更换请点击右侧按钮';

    function setPreview(url, name, sizeText) {
        if (previewUrl) URL.revokeObjectURL(previewUrl);
        previewUrl = url;
        previewImg.src = url;
        fileName.textContent = name + '（' + sizeText + '）';
        uploaderHint.textContent = DEFAULT_HINT;
        emptyBox.hidden = true;
        previewBox.hidden = false;
    }

    // ── 历史上传图片快捷选择 ──
    function markHistoryActive(activeBtn) {
        if (!historyRow) return;
        var btns = historyRow.querySelectorAll('.history-thumb');
        for (var i = 0; i < btns.length; i++) {
            btns[i].classList.toggle('active', btns[i] === activeBtn);
        }
    }

    function applySelectedImage(url, label) {
        historyUrl = url;
        imageInput.value = url;
        currentFile = null;
        input.value = '';
        if (previewUrl) { URL.revokeObjectURL(previewUrl); previewUrl = null; }
        previewImg.src = url;
        fileName.textContent = label;
        uploaderHint.textContent = '已选择「' + label + '」，可在下方选择风格后点击确认生成；如需更换请点击右侧按钮';
        emptyBox.hidden = true;
        previewBox.hidden = false;
    }

    function selectHistory(url, btn) {
        if (!url) return;
        applySelectedImage(url, '历史上传照片');
        markHistoryActive(btn);
    }

    if (historyRow) {
        historyRow.addEventListener('click', function (e) {
            var btn = e.target.closest('.history-thumb');
            if (btn) selectHistory(btn.getAttribute('data-url') || '', btn);
        });
    }

    // 支持 /console/generate?use_image=xxx：
    // 头像详情页「用原图重新生成」带原图跳转过来，自动选中，仍需手动点击「确认生成」
    (function () {
        var useImg = '';
        try { useImg = (new URLSearchParams(location.search).get('use_image') || '').trim(); } catch (err) { return; }
        if (!useImg || !/^(https?:\/\/|\/)/i.test(useImg)) return;
        var matchedBtn = null;
        if (historyRow) {
            var thumbs = historyRow.querySelectorAll('.history-thumb');
            for (var i = 0; i < thumbs.length; i++) {
                if (thumbs[i].getAttribute('data-url') === useImg) { matchedBtn = thumbs[i]; break; }
            }
        }
        applySelectedImage(useImg, matchedBtn ? '历史上传照片' : '原始照片');
        markHistoryActive(matchedBtn);
    })();

    function handleFile(file) {
        if (!file) return;
        if (!/^image\/(jpeg|png|webp)$/.test(file.type)) { alert('仅支持 jpg / png / webp 格式'); return; }
        if (file.size > 10 * 1024 * 1024) { alert('图片不能超过 10MB'); return; }

        fileName.textContent = '图片处理中…';
        emptyBox.hidden = true;
        previewBox.hidden = false;
        previewImg.removeAttribute('src');

        compressImage(file).then(function (result) {
            currentFile = result.blob;
            // 选择了本地文件，取消历史图选中状态
            historyUrl = null;
            imageInput.value = '';
            markHistoryActive(null);
            // 用压缩后的文件替换 input 内容，表单原生提交即上传压缩图
            try {
                var dt = new DataTransfer();
                dt.items.add(result.blob);
                input.files = dt.files;
            } catch (err) { /* 极旧浏览器不支持 DataTransfer，下面 submit 兜底 */ }

            var origMB = (file.size / 1024 / 1024).toFixed(2);
            var newMB = (result.blob.size / 1024 / 1024).toFixed(2);
            var sizeText = result.compressed
                ? newMB + 'MB，已自动压缩（原图 ' + origMB + 'MB）'
                : newMB + 'MB';
            setPreview(result.url, result.blob.name, sizeText);
        }).catch(function () {
            alert('图片读取失败，请换一张试试');
            currentFile = null;
            previewBox.hidden = true;
            emptyBox.hidden = false;
        });
    }

    /**
     * 浏览器端压缩：缩放到最长边 MAX_DIM，统一转 JPEG
     * 优先 createImageBitmap（自动纠正手机照片 EXIF 方向），回退 <img>
     */
    function compressImage(file) {
        return new Promise(function (resolve, reject) {
            var baseName = file.name.replace(/\.[^.]+$/, '') || 'photo';

            function finish(bitmapOrImg, width, height) {
                var scale = Math.min(1, MAX_DIM / Math.max(width, height));
                // 小尺寸 jpeg 且体积已很小：无需压缩
                if (scale >= 1 && file.type === 'image/jpeg' && file.size <= SKIP_COMPRESS_BYTES) {
                    resolve({ blob: file, url: URL.createObjectURL(file), compressed: false });
                    return;
                }
                var nw = Math.max(1, Math.round(width * scale));
                var nh = Math.max(1, Math.round(height * scale));
                var canvas = document.createElement('canvas');
                canvas.width = nw;
                canvas.height = nh;
                var ctx = canvas.getContext('2d');
                ctx.fillStyle = '#ffffff'; // PNG 透明区域填白
                ctx.fillRect(0, 0, nw, nh);
                ctx.drawImage(bitmapOrImg, 0, 0, nw, nh);
                canvas.toBlob(function (blob) {
                    if (!blob) { resolve({ blob: file, url: URL.createObjectURL(file), compressed: false }); return; }
                    var out = new File([blob], baseName + '.jpg', { type: 'image/jpeg' });
                    resolve({ blob: out, url: URL.createObjectURL(blob), compressed: true });
                }, 'image/jpeg', JPEG_QUALITY);
            }

            if (window.createImageBitmap) {
                createImageBitmap(file, { imageOrientation: 'from-image' })
                    .then(function (bmp) { finish(bmp, bmp.width, bmp.height); })
                    .catch(function () { loadViaImage(); });
            } else {
                loadViaImage();
            }

            function loadViaImage() {
                var url = URL.createObjectURL(file);
                var img = new Image();
                img.onload = function () { URL.revokeObjectURL(url); finish(img, img.naturalWidth, img.naturalHeight); };
                img.onerror = function () { URL.revokeObjectURL(url); reject(new Error('load failed')); };
                img.src = url;
            }
        });
    }

    uploader.addEventListener('click', function (e) {
        if (e.target.closest('#reselectBtn')) return;
        if (previewBox.hidden) pick();
    });
    reselectBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        input.value = '';
        currentFile = null;
        historyUrl = null;
        imageInput.value = '';
        markHistoryActive(null);
        previewImg.src = '';
        previewBox.hidden = true;
        emptyBox.hidden = false;
        pick();
    });
    input.addEventListener('change', function () { handleFile(input.files[0]); });

    ['dragenter', 'dragover'].forEach(function (ev) {
        uploader.addEventListener(ev, function (e) { e.preventDefault(); uploader.classList.add('drag-over'); });
    });
    ['dragleave', 'drop'].forEach(function (ev) {
        uploader.addEventListener(ev, function (e) { e.preventDefault(); uploader.classList.remove('drag-over'); });
    });
    uploader.addEventListener('drop', function (e) {
        var file = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0];
        if (file) { input.files = e.dataTransfer.files; handleFile(file); }
    });

    form.addEventListener('submit', function (e) {
        if (!currentFile && !historyUrl) {
            e.preventDefault();
            alert('请先上传一张照片，或从历史上传中选择');
            return;
        }
        // DataTransfer 不被支持时的兜底：改用 FormData 用压缩文件提交
        // （历史图选中时 currentFile 为空，走原生提交，由隐藏 image 字段传 URL）
        if (currentFile && (!input.files || input.files.length === 0) && window.FormData) {
            e.preventDefault();
            var fd = new FormData(form);
            fd.set('file', currentFile, currentFile.name);
            fetch(form.action, { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function (r) { window.location.href = r.redirected ? r.url : '/console'; })
                .catch(function () { alert('提交失败，请重试'); });
        }
    });
})();
</script>
<?php include __DIR__ . '/../_partials/footer.php'; ?>
