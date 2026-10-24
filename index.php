<?php
declare(strict_types=1);

require_once __DIR__ . '/store.php';

$products = storeDatabase()->query(
    'SELECT id, name, category, price, stock_quantity FROM products WHERE is_active = 1 ORDER BY id LIMIT 4'
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Golden Glow — Skin that remembers its own light</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css?v=3">
<link rel="stylesheet" href="home.css">
</head>
<body>
<?php renderStoreHeader('home'); ?>
<section class="hero">
  <div class="hero-grid">
    <div class="hero-copy">
      <div class="eyebrow-line">GOLDEN GLOW SKINCARE</div>
      <h1>Skin that remembers its own light.</h1>
      <p>Brightening care formulated for tropical climates, gentle enough for daily use. No harsh bleaching agents, no guesswork, just a routine built around the glow you already have.</p>
      <div class="hero-actions"><a href="shop.php" class="btn btn-gold">Shop the collection</a><a href="#story" class="btn btn-ghost-light">Our story</a></div>
    </div>
    <div class="hero-photo-wrap"><div class="hero-photo">
      <img src="assets/ggprod.png" alt="Golden Glow product lineup">
      <div class="hero-photo-cap"><span>The full lineup</span><span><?= count($products) ?> featured products</span></div>
    </div></div>
  </div>
</section>
<div class="wrap concerns">
  <div class="eyebrow-line">SHOP BY CONCERN</div>
  <div class="concerns-row">
    <a href="shop.php" class="chip active">Brightening</a><a href="shop.php" class="chip">Hydration</a>
    <a href="shop.php" class="chip">Oil control</a><a href="shop.php" class="chip">Sensitive skin</a>
    <a href="shop.php" class="chip">Anti-aging</a><a href="shop.php" class="chip">Sun care</a>
  </div>
</div>
<section class="wrap featured">
  <div class="section-head"><h2>Reader favorites</h2><a href="shop.php" class="see-all">See full collection</a></div>
  <div class="grid-4">
    <?php foreach ($products as $product): ?>
      <?php renderProductCard($product); ?>
    <?php endforeach; ?>
  </div>
</section>
<section class="promise" id="story">
  <div class="promise-inner">
    <blockquote>"We formulate for humidity, sun, and the kind of skin that has to survive a whole day in it. Every Golden Glow product is built around that reality first."</blockquote>
    <cite>— Golden Glow Product Team</cite>
    <div class="pillars">
      <div class="pillar"><b>Dermatologist-tested</b>Every formula is patch-tested before release.</div>
      <div class="pillar"><b>No harsh bleach</b>Brightening actives only, nothing that strips skin.</div>
      <div class="pillar"><b>Made in the Philippines</b>Formulated and produced locally.</div>
    </div>
  </div>
</section>
<?php renderStoreFooter(); ?>
</body>
</html>
