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
        </div>
    </div>
</section>
<?php include __DIR__ . '/../_partials/footer.php'; ?>
