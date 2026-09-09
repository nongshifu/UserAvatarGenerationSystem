<?php
$img = $avatar['result_url'] ?? '';
$thumb = $avatar['result_thumb_url'] ?: $img;
$baseUrl = $siteName;
?>
<?php include __DIR__ . '/../_partials/header.php'; ?>
<script type="application/ld+json">
{
  "@context":"https://schema.org",
  "@type":"ImageObject",
  "contentUrl":"<?= $e($img) ?>",
  "thumbnail":"<?= $e($thumb) ?>",
  "name":"AI 头像 #<?= (int)$avatar['id'] ?>",
  "description":"<?= $e($description) ?>"
}
</script>
<section class="container section" style="max-width:760px">
    <div class="card" style="text-align:center">
        <img src="<?= $e($img) ?>" alt="AI 头像 #<?= (int)$avatar['id'] ?>" style="max-width:100%;border-radius:12px">
        <h2 style="margin:16px 0 8px">头像 #<?= (int)$avatar['id'] ?></h2>
        <p class="muted">浏览量 <?= (int)$avatar['views'] ?> · 生成于 <?= $e((string)($avatar['created_at'] ?? '')) ?></p>
        <div style="margin-top:18px">
            <a href="<?= $e($img) ?>" class="btn btn-sm" download>下载</a>
            <a href="/console/generate" class="btn btn-sm btn-outline">我也生成</a>
            <?php if ($currentUser && (int)($avatar['user_id'] ?? 0) === (int)$currentUser->id): ?>
            <form method="post" action="/console/avatars/<?= (int)$avatar['id'] ?>/delete"
                  onsubmit="return confirm('确认删除这张头像吗？删除后原图和结果图都会被清理，且无法恢复。');"
                  style="display:inline;margin-left:8px">
                <button type="submit" class="btn btn-sm" style="border-color:rgba(255,80,80,.5);color:#ff8a8a">删除</button>
            </form>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php include __DIR__ . '/../_partials/footer.php'; ?>
