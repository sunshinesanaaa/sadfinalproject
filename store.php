<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

function storeDatabase(): PDO
{
    static $pdo;
    if (!$pdo instanceof PDO) {
        $pdo = require __DIR__ . '/db.php';
    }
    return $pdo;
}

function storeEscape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function peso(float $amount): string
{
    return '₱' . number_format($amount, 2);
}

function cartQuantity(): int
{
    $cart = $_SESSION['cart'] ?? [];
    return array_sum(array_map('intval', is_array($cart) ? $cart : []));
}

function requireCustomer(): void
{
    if (($_SESSION['user']['role'] ?? null) === 'customer') {
        return;
    }

    if (($_SESSION['user']['role'] ?? null) === 'admin') {
        header('Location: admin.php');
        exit;
    }

    header('Location: login.php?next=checkout');
    exit;
}

function productArt(int $productId): string
{
    $art = [
        1 => '<rect class="pline" x="34" y="42" width="32" height="46" rx="6"/><rect class="pline" x="42" y="26" width="16" height="18" rx="2"/><rect class="pcap" x="46" y="14" width="8" height="14" rx="2"/><rect class="pcap" x="50" y="8" width="22" height="7" rx="3"/>',
        2 => '<rect class="pline" x="34" y="42" width="32" height="46" rx="6"/><rect class="pline" x="42" y="26" width="16" height="18" rx="2"/><rect class="pcap" x="46" y="14" width="8" height="14" rx="2"/><rect class="pcap" x="50" y="8" width="22" height="7" rx="3"/>',
        3 => '<rect class="pline" x="36" y="46" width="28" height="40" rx="6"/><rect class="pline" x="42" y="30" width="16" height="18"/><path class="pline" d="M50 10 L50 30"/><ellipse class="pcap" cx="50" cy="9" rx="6" ry="6"/>',
        4 => '<path class="pline" d="M38 20 h24 l4 10 v50 a14 14 0 0 1 -32 0 v-50 z"/><rect class="pcap" x="40" y="14" width="20" height="8" rx="2"/>',
        5 => '<rect class="pline" x="28" y="38" width="44" height="42" rx="6"/><rect class="pline" x="24" y="28" width="52" height="14" rx="4"/>',
        6 => '<rect class="pline" x="20" y="40" width="60" height="30" rx="12"/>',
    ];
    return $art[$productId] ?? '<rect class="pline" x="25" y="25" width="50" height="60" rx="8"/>';
}

function renderProductCard(array $product): void
{
    $id = (int) $product['id'];
    $name = storeEscape((string) $product['name']);
    $category = storeEscape((string) $product['category']);
    $price = storeEscape(peso((float) $product['price']));
    echo '<article class="product-card">';
    echo '<a href="product.php?id=' . $id . '" class="product-card-link">';
    echo '<div class="product-thumb">';
    if (is_file(__DIR__ . '/assets/products/product-' . $id . '.jpg')) {
        echo '<img src="assets/products/product-' . $id . '.jpg" alt="" loading="lazy" decoding="async">';
    } else {
        echo '<svg viewBox="0 0 100 100" aria-hidden="true">' . productArt($id) . '</svg>';
    }
    echo '</div>';
    echo '<div class="product-body"><span class="product-cat">' . $category . '</span><span class="product-name">' . $name . '</span></div>';
    echo '</a>';
    echo '<div class="product-card-buy"><span class="product-price">' . $price . '</span>';
    echo '<form method="post" action="cart_action.php">';
    echo '<input type="hidden" name="csrf_token" value="' . storeEscape(csrfToken()) . '">';
    echo '<input type="hidden" name="action" value="add"><input type="hidden" name="product_id" value="' . $id . '">';
    echo '<input type="hidden" name="quantity" value="1"><input type="hidden" name="return_to" value="shop.php">';
    echo '<button class="add-line" type="submit" aria-label="Add ' . $name . ' to cart">+</button></form></div>';
    echo '</article>';
}

function renderStoreHeader(string $current = ''): void
{
    $user = $_SESSION['user'] ?? null;
    $profileUrl = is_array($user) ? 'account.php' : 'register.php';
    $profileTitle = is_array($user) ? 'My account' : 'Create account or sign in';
    ?>
<div class="topbar">
  <div class="topbar-inner">
    <a href="index.php" class="brand">
      <img src="assets/gglogo.png" alt="Golden Glow">
      <span class="brand-word">Golden <b>Glow</b></span>
    </a>
    <nav class="nav-links">
      <a href="index.php"<?= $current === 'home' ? ' class="current"' : '' ?>>Home</a>
      <a href="shop.php"<?= $current === 'shop' ? ' class="current"' : '' ?>>Shop</a>
      <a href="index.php#story">Our Story</a>
      <a href="index.php#story">Contact</a>
    </nav>
    <div class="top-actions">
      <a href="<?= storeEscape($profileUrl) ?>" class="icon-link" title="<?= storeEscape($profileTitle) ?>" aria-label="<?= storeEscape($profileTitle) ?>">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/></svg>
      </a>
      <a href="cart.php" class="icon-link" title="Cart" aria-label="Cart, <?= cartQuantity() ?> items">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="9" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M1 1h4l2.7 13.4a2 2 0 0 0 2 1.6h9.7a2 2 0 0 0 2-1.6L23 6H6"/></svg>
        <span class="cart-count"><?= cartQuantity() ?></span>
      </a>
    </div>
  </div>
</div>
    <?php
}

function renderStoreFooter(): void
{
    ?>
<footer class="site-footer">
  <div class="footer-top">
    <div class="footer-brand">
      <a href="index.php" class="brand"><img src="assets/gglogo.png" alt="Golden Glow"><span class="brand-word">Golden <b>Glow</b></span></a>
      <p>Brightening skincare formulated for tropical climates. Made in the Philippines.</p>
    </div>
    <div><h5>Shop</h5><a href="shop.php">All products</a></div>
    <div><h5>Help</h5><a href="account.php">My account</a><a href="cart.php">Cart</a></div>
    <div><h5>Stay in the glow</h5><p class="newsletter-copy">Skin tips and early access to new drops, once or twice a month.</p></div>
  </div>
  <div class="footer-bottom"><span>© 2026 Golden Glow.</span><span>Facebook · Instagram · Email</span></div>
</footer>
    <?php
}
