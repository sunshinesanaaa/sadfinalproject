<?php
declare(strict_types=1);

require_once __DIR__ . '/store.php';

$productId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if ($productId === false || $productId === null || $productId < 1) {
    header('Location: shop.php');
    exit;
}

$statement = storeDatabase()->prepare(
    'SELECT id, name, category, price, stock_quantity FROM products WHERE id = :id AND is_active = 1'
);
$statement->execute(['id' => $productId]);
$product = $statement->fetch();
if ($product === false) {
    http_response_code(404);
}

$productText = [
    1 => ['120ml', 'A refreshing daily cleanser made to remove impurities while leaving skin comfortable.'],
    2 => ['150ml', 'A lightweight brightening toner to refresh skin and prepare it for the rest of your routine.'],
    3 => ['30ml', 'A lightweight, fast-absorbing serum with niacinamide and licorice root extract, formulated to even out visible dark spots and calm redness.'],
    4 => ['100ml', 'A daily lotion that moisturizes skin and helps support an even-looking complexion.'],
    5 => ['50g', 'An overnight cream designed to leave skin feeling soft, nourished, and refreshed by morning.'],
    6 => ['90g', 'A gentle exfoliating bar for a clean, refreshed feel.'],
];
$details = $productText[(int) ($product['id'] ?? 0)] ?? ['Product', 'A Golden Glow skincare essential.'];
$similarProducts = storeDatabase()->prepare(
    'SELECT id, name, category, price, stock_quantity FROM products WHERE is_active = 1 AND id <> :id ORDER BY id LIMIT 4'
);
$similarProducts->execute(['id' => (int) ($product['id'] ?? 0)]);
$related = $similarProducts->fetchAll();
$cartError = $_SESSION['cart_error'] ?? '';
unset($_SESSION['cart_error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $product ? storeEscape((string) $product['name']) . ' — Golden Glow' : 'Product not found — Golden Glow' ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css?v=3"><link rel="stylesheet" href="product.css?v=2">
</head>
<body>
<?php renderStoreHeader('shop'); ?>
<?php if (!$product): ?>
  <main class="wrap product-not-found"><h1>Product not found</h1><a class="btn btn-gold" href="shop.php">Return to shop</a></main>
<?php else: ?>
  <main class="wrap">
    <div class="crumb"><a href="index.php">Home</a> / <a href="shop.php">Shop</a> / <?= storeEscape((string) $product['name']) ?></div>
    <?php if (is_string($cartError) && $cartError !== ''): ?><p class="store-message" role="alert"><?= storeEscape($cartError) ?></p><?php endif; ?>
    <div class="product-layout">
      <div><div class="gallery-main"><svg viewBox="0 0 100 100" aria-hidden="true"><?= productArt((int) $product['id']) ?></svg></div></div>
      <div>
        <span class="p-cat"><?= storeEscape((string) $product['category']) ?></span>
        <h1 class="p-title"><?= storeEscape((string) $product['name']) ?></h1>
        <div class="p-price-row"><span class="p-price"><?= storeEscape(peso((float) $product['price'])) ?></span></div>
        <p class="p-desc"><?= storeEscape($details[1]) ?></p>
        <p class="stock-note"><?= (int) $product['stock_quantity'] > 0 ? 'In stock' : 'Currently out of stock' ?></p>
        <?php if ((int) $product['stock_quantity'] > 0): ?>
          <form class="product-add-form" method="post" action="cart_action.php">
            <input type="hidden" name="csrf_token" value="<?= storeEscape(csrfToken()) ?>">
            <input type="hidden" name="action" value="add"><input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
            <input type="hidden" name="return_to" value="cart.php">
            <label for="quantity">Quantity</label>
            <input id="quantity" type="number" name="quantity" min="1" max="<?= min(99, (int) $product['stock_quantity']) ?>" value="1" required>
            <button class="btn btn-gold" type="submit">Add to cart</button>
          </form>
        <?php endif; ?>
        <div class="accordion">
          <details class="accordion-item" open><summary>Product details</summary><p><?= storeEscape($details[1]) ?></p></details>
          <details class="accordion-item"><summary>How to use</summary><p>Use as directed as part of your daily skincare routine.</p></details>
          <details class="accordion-item"><summary>Shipping &amp; returns</summary><p>Delivery and return arrangements are confirmed with the customer after the order is placed.</p></details>
        </div>
      </div>
    </div>
  </main>
  <section class="wrap related"><div class="section-head"><h2>Explore more</h2><a href="shop.php" class="see-all">All products</a></div><div class="grid-4"><?php foreach ($related as $relatedProduct): ?><?php renderProductCard($relatedProduct); ?><?php endforeach; ?></div></section>
<?php endif; ?>
<?php renderStoreFooter(); ?>
</body>
</html>
