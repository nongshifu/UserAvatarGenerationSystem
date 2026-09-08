<?php include __DIR__ . '/../_partials/header.php'; ?>
<section class="container section">
    <h2><?= $styleName ? $e($styleName) . ' 风格' : '全部头像' ?></h2>

    <div class="card" style="margin-bottom:20px">
        <form method="get" action="/category" style="display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(140px,1fr))">
            <div>
                <label>风格</label>
                <select name="style_id" onchange="this.form.submit()">
                    <option value="0">全部</option>
                    <?php foreach ($styles as $s): ?>
                    <option value="<?= (int)$s['id'] ?>" <?= $filter['style_id']===(int)$s['id']?'selected':'' ?>><?= $e($s['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>颜色</label>
                <select name="color_id" onchange="this.form.submit()">
                    <option value="0">全部</option>
                    <?php foreach ($colors as $c): ?>
                    <option value="<?= (int)$c['id'] ?>" <?= $filter['color_id']===(int)$c['id']?'selected':'' ?>><?= $e($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>形状</label>
                <select name="shape_id" onchange="this.form.submit()">
                    <option value="0">全部</option>
                    <?php foreach ($shapes as $s): ?>
                    <option value="<?= (int)$s['id'] ?>" <?= $filter['shape_id']===(int)$s['id']?'selected':'' ?>><?= $e($s['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>

    <?php if (!empty($list)): ?>
    <div class="grid avatar-grid">
        <?php foreach ($list as $av): ?>
        <a class="avatar-card" href="/avatar/<?= (int)$av['id'] ?>">
            <img loading="lazy" src="<?= $e($av['result_thumb_url'] ?: $av['result_url']) ?>" alt="头像 #<?= (int)$av['id'] ?>">
        </a>
        <?php endforeach; ?>
    </div>

    <div class="pagination">
        <?php for ($i=1; $i<=$pagination['last_page']; $i++): ?>
        <a class="<?= $i===$pagination['page']?'active':'' ?>" href="?<?= http_build_query(array_filter(['style_id'=>$filter['style_id'],'color_id'=>$filter['color_id'],'shape_id'=>$filter['shape_id'],'page'=>$i])) ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
    <?php else: ?>
    <p class="muted" style="text-align:center">暂无符合条件的头像</p>
    <?php endif; ?>
</section>
<?php include __DIR__ . '/../_partials/footer.php'; ?>
