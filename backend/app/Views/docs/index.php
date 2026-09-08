<?php include __DIR__ . '/../_partials/header.php'; ?>
<section class="container section" style="max-width:900px">
    <h2 style="margin-bottom:20px">API 文档</h2>
    <div class="card" style="margin-bottom:16px">
        <h3 style="margin-bottom:8px">鉴权</h3>
        <p class="muted" style="margin-bottom:8px">所有需鉴权的接口在 Header 携带 <code>X-API-Key</code>。在控制台「API KEY」页面创建 KEY。</p>
        <pre style="background:rgba(0,0,0,.3);padding:12px;border-radius:8px;overflow:auto;font-size:.85rem">X-API-Key: ak_xxxxxxxxxxxxxxxx</pre>
    </div>

    <div class="card" style="margin-bottom:16px">
        <h3 style="margin-bottom:8px">上传图片</h3>
        <code>POST /api/v1/upload</code>
        <p class="muted" style="margin:.4rem 0">multipart/form-data，字段 file，≤10MB，jpg/png/webp</p>
        <pre style="background:rgba(0,0,0,.3);padding:12px;border-radius:8px;overflow:auto;font-size:.85rem">{
  "code": 0,
  "data": { "url": "https://.../up_xxx.jpg" }
}</pre>
    </div>

    <div class="card" style="margin-bottom:16px">
        <h3 style="margin-bottom:8px">生成头像（异步）</h3>
        <code>POST /api/v1/avatar/generate</code>
        <pre style="background:rgba(0,0,0,.3);padding:12px;border-radius:8px;overflow:auto;font-size:.85rem">{
  "image": "https://.../your-photo.jpg",
  "sub_user_id": "user_123",
  "style_id": 1,
  "color_id": 2,
  "shape_id": 1,
  "is_public": true
}</pre>
        <p class="muted" style="margin:.4rem 0">返回 record_id，配合查询接口轮询结果</p>
    </div>

    <div class="card" style="margin-bottom:16px;border-color:rgba(123,92,255,.5)">
        <h3 style="margin-bottom:8px">上传即生成（同步，推荐）</h3>
        <code>POST /api/v1/avatar/generate-sync</code>
        <p class="muted" style="margin:.4rem 0">multipart/form-data：<b>file</b> 为图片文件（≤10MB，jpg/png/webp），其余参数以表单字段提交（style_id/color_id/shape_id/is_public/sub_user_id）。无需先上传再轮询，一次请求直接返回头像地址。生成约需数秒~数十秒，客户端超时请设 ≥120s。</p>
        <pre style="background:rgba(0,0,0,.3);padding:12px;border-radius:8px;overflow:auto;font-size:.85rem">curl -X POST https://你的域名/api/v1/avatar/generate-sync \
  -H "X-API-Key: ak_xxxx" \
  -F "file=@/path/to/photo.jpg" \
  -F "style_id=1" \
  -F "is_public=1"</pre>
        <pre style="background:rgba(0,0,0,.3);padding:12px;border-radius:8px;overflow:auto;font-size:.85rem">{
  "code": 0,
  "data": {
    "record_id": 100,
    "status": "success",
    "avatar_id": 1001,
    "url": "https://.../result.png",
    "thumb_url": "https://.../thumb.png",
    "audit_status": "approved",
    "cost_points": 10,
    "remaining_points": 90
  }
}</pre>
        <p class="muted" style="margin:.4rem 0">生成失败会自动退还积分并返回 502；审核驳回时 audit_status=rejected 且积分退还。</p>
    </div>

    <div class="card" style="margin-bottom:16px">
        <h3 style="margin-bottom:8px">查询生成结果</h3>
        <code>GET /api/v1/avatar/generate/{record_id}</code>
        <pre style="background:rgba(0,0,0,.3);padding:12px;border-radius:8px;overflow:auto;font-size:.85rem">{
  "code": 0,
  "data": {
    "record_id": 100,
    "status": "success",
    "avatar_id": 1001,
    "url": "https://.../result.png",
    "thumb_url": "https://.../thumb.png",
    "cost_points": 10
  }
}</pre>
    </div>

    <div class="card" style="margin-bottom:16px">
        <h3 style="margin-bottom:8px">其他接口</h3>
        <table class="table">
            <tr><td><code>GET /api/v1/avatar/random</code></td><td>公共池随机头像，支持 style_id/color_id/shape_id/count</td></tr>
            <tr><td><code>GET /api/v1/key/info</code></td><td>当前 KEY 信息（名称、额度）</td></tr>
            <tr><td><code>GET /api/v1/key/avatars</code></td><td>KEY 下头像列表，可按 sub_user_id 筛选</td></tr>
            <tr><td><code>GET /api/v1/key/sub-users/{sid}/avatars</code></td><td>子用户头像历史</td></tr>
            <tr><td><code>GET /api/v1/user/points</code></td><td>用户积分余额</td></tr>
            <tr><td><code>GET /api/v1/configs</code></td><td>风格/颜色/形状配置（无需鉴权）</td></tr>
        </table>
    </div>

    <div class="card">
        <h3 style="margin-bottom:8px">错误码</h3>
        <table class="table">
            <tr><td>0</td><td>成功</td></tr>
            <tr><td>4001</td><td>参数错误</td></tr>
            <tr><td>4002</td><td>鉴权失败</td></tr>
            <tr><td>4003</td><td>积分不足/禁止访问</td></tr>
            <tr><td>4006</td><td>图片未通过审核（违规或非人物头像图，HTTP 422）</td></tr>
            <tr><td>4029</td><td>限流（QPS/日限额超限）</td></tr>
            <tr><td>4004</td><td>资源不存在</td></tr>
            <tr><td>5000</td><td>服务端错误</td></tr>
            <tr><td>5002</td><td>同步生成失败（积分已自动退还）</td></tr>
        </table>
    </div>
</section>
<?php include __DIR__ . '/../_partials/footer.php'; ?>
