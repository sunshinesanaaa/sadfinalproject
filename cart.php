<?php
declare(strict_types=1);

require_once __DIR__ . '/store.php';

$cart = $_SESSION['cart'] ?? [];
$cart = is_array($cart) ? $cart : [];
$products = [];
if ($cart !== []) {
    $ids = array_values(array_filter(array_map('intval', array_keys($cart)), static fn(int $id): bool => $id > 0));
    if ($ids !== []) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $statement = storeDatabase()->prepare(
            "SELECT id, name, category, price, stock_quantity FROM products WHERE is_active = 1 AND id IN ($placeholders)"
        );
        $statement->execute($ids);
        foreach ($statement->fetchAll() as $product) {
            $products[(int) $product['id']] = $product;
        }
    }
}

$cartError = $_SESSION['cart_error'] ?? '';
unset($_SESSION['cart_error']);
$subtotal = 0.0;
$totalItems = 0;
foreach ($products as $id => $product) {
    $quantity = (int) ($cart[$id] ?? 0);
    $subtotal += (float) $product['price'] * $quantity;
    $totalItems += $quantity;
}
$shippingFee = $products === [] || $subtotal >= 2500 ? 0.0 : 99.0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Your Cart — Golden Glow</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css"><link rel="stylesheet" href="cart.css">
</head>
<body>
<?php renderStoreHeader(); ?>
<main class="wrap">
  <div class="page-title"><h1>Your cart</h1><p><?= number_format($totalItems) ?> <?= $totalItems === 1 ? 'item' : 'items' ?></p></div>
  <?php if (is_string($cartError) && $cartError !== ''): ?><p class="store-message" role="alert"><?= storeEscape($cartError) ?></p><?php endif; ?>
  <div class="cart-layout">
    <section aria-label="Cart items">
      <?php foreach ($products as $id => $product): ?>
        <?php $quantity = (int) ($cart[$id] ?? 0); ?>
        <article class="cart-line">
          <div class="cart-thumb"><svg viewBox="0 0 100 100" aria-hidden="true"><?= productArt($id) ?></svg></div>
          <div class="cart-info">
            <a class="name" href="product.php?id=<?= $id ?>"><?= storeEscape((string) $product['name']) ?></a>
            <div class="cat"><?= storeEscape((string) $product['category']) ?> · <?= storeEscape(peso((float) $product['price'])) ?> each</div>
            <form method="post" action="cart_action.php" class="cart-remove-form">
              <input type="hidden" name="csrf_token" value="<?= storeEscape(csrfToken()) ?>"><input type="hidden" name="action" value="remove">
              <input type="hidden" name="product_id" value="<?= $id ?>"><input type="hidden" name="return_to" value="cart.php">
              <button class="remove" type="submit">Remove</button>
            </form>
          </div>
          <form method="post" action="cart_action.php" class="cart-quantity-form">
            <input type="hidden" name="csrf_token" value="<?= storeEscape(csrfToken()) ?>"><input type="hidden" name="action" value="update">
            <input type="hidden" name="product_id" value="<?= $id ?>"><input type="hidden" name="return_to" value="cart.php">
            <label class="visually-hidden" for="quantity-<?= $id ?>">Quantity of <?= storeEscape((string) $product['name']) ?></label>
            <input id="quantity-<?= $id ?>" type="number" name="quantity" min="1" max="<?= min(99, (int) $product['stock_quantity']) ?>" value="<?= $quantity ?>">
            <button class="btn btn-ghost btn-sm" type="submit">Update</button>
          </form>
          <div class="cart-line-price"><?= storeEscape(peso((float) $product['price'] * $quantity)) ?></div>
        </article>
      <?php endforeach; ?>
      <?php if ($cart !== [] && count($products) !== count($cart)): ?>
        <?php foreach ($cart as $cartId => $quantity): ?>
          <?php if (!isset($products[(int) $cartId])): ?>
            <div class="cart-unavailable">
              <p>A product in your cart is no longer available.</p>
              <form method="post" action="cart_action.php">
                <input type="hidden" name="csrf_token" value="<?= storeEscape(csrfToken()) ?>"><input type="hidden" name="action" value="remove">
                <input type="hidden" name="product_id" value="<?= (int) $cartId ?>"><input type="hidden" name="return_to" value="cart.php">
                <button class="remove" type="submit">Remove unavailable item</button>
              </form>
            </div>
          <?php endif; ?>
        <?php endforeach; ?>
      <?php endif; ?>
      <?php if ($products === []): ?>
        <div class="empty-state"><p>Your cart is empty.</p><a class="btn btn-gold" href="shop.php">Browse products</a></div>
      <?php else: ?>
        <a class="continue-shopping" href="shop.php">Continue shopping</a>
      <?php endif; ?>
    </section>
    <aside class="summary-card">
      <h2>Order summary</h2>
      <div class="summary-row"><span>Subtotal</span><span><?= storeEscape(peso($subtotal)) ?></span></div>
      <div class="summary-row"><span>Shipping</span><span><?= $shippingFee === 0.0 ? 'Free' : storeEscape(peso($shippingFee)) ?></span></div>
      <div class="summary-row total"><span>Total</span><span><?= storeEscape(peso($subtotal + $shippingFee)) ?></span></div>
      <?php if ($products !== [] && count($products) === count($cart)): ?>
        <a href="checkout.php" class="btn btn-gold btn-block checkout-link">Continue to confirmation</a>
      <?php endif; ?>
      <p class="summary-note">No payment is processed online. Confirm your order and payment method on the next page.</p>
    </aside>
  </div>
</main>
<?php renderStoreFooter(); ?>
</body>
</html>
