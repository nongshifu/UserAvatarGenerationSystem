<?php $active='keys'; include __DIR__ . '/../_partials/header.php'; ?>
<section class="container">
    <div class="console-layout">
        <?php include __DIR__ . '/../_partials/console_side.php'; ?>
        <div>
            <h2 style="margin-bottom:20px">API KEY 管理</h2>
            <?php if ($newKey): ?>
            <div class="alert" style="background:rgba(123,92,255,.12);border-color:rgba(123,92,255,.4);color:#cdb8ff">
                新 KEY（仅展示一次，请妥善保存）：<br>
                <code style="word-break:break-all"><?= $e($newKey) ?></code>
            </div>
            <?php endif; ?>
            <?php if ($error): ?><div class="alert"><?= $e($error) ?></div><?php endif; ?>

            <details class="card" style="margin-bottom:16px">
                <summary style="cursor:pointer;font-weight:600">+ 创建新 KEY</summary>
                <form method="post" action="/console/keys" style="margin-top:14px">
                    <label>名称</label>
                    <input type="text" name="name" required placeholder="如：生产环境">
                    <div class="row" style="margin-top:8px">
                        <div style="flex:1"><label>QPS（0=不限）</label><input type="number" name="rate_limit" value="0" min="0"></div>
                        <div style="flex:1"><label>日限额（0=不限）</label><input type="number" name="daily_limit" value="0" min="0"></div>
                    </div>
                    <label style="margin-top:8px">回调 URL（可选）</label>
                    <input type="url" name="callback_url" placeholder="https://...">
                    <button type="submit" class="btn btn-sm" style="margin-top:12px">创建</button>
                </form>
            </details>

            <?php if (!empty($keys)): ?>
            <table class="table">
                <thead><tr><th>名称</th><th>KEY</th><th>QPS/日</th><th>今日</th><th>状态</th><th>操作</th></tr></thead>
                <tbody>
                <?php foreach ($keys as $k): $ok=(int)$k['status']===1; ?>
                <tr>
                    <td><?= $e((string)$k['name']) ?></td>
                    <td><code><?= $e((string)$k['api_key']) ?></code></td>
                    <td><?= (int)$k['rate_limit'].' / '.(int)$k['daily_limit'] ?></td>
                    <td><?= (int)$k['used_today'] ?></td>
                    <td><span class="badge <?= $ok?'badge-ok':'badge-off' ?>"><?= $ok?'启用':'禁用' ?></span></td>
                    <td>
                        <form method="post" action="/console/keys/<?= (int)$k['id'] ?>/reset" style="display:inline">
                            <button class="btn btn-sm btn-outline" type="submit" onclick="return confirm('重置后旧 KEY 立即失效，确认？')">重置</button>
                        </form>
                        <form method="post" action="/console/keys/<?= (int)$k['id'] ?>/toggle" style="display:inline">
                            <button class="btn btn-sm btn-outline" type="submit"><?= $ok?'禁用':'启用' ?></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p class="muted">还没有 KEY，<a href="#" onclick="document.querySelector('details').open=true;return false" style="color:#7b5cff">创建第一个</a></p>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php include __DIR__ . '/../_partials/footer.php'; ?>
