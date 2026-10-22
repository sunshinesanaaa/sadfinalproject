<?php
declare(strict_types=1);

require_once __DIR__ . '/store.php';

$user = $_SESSION['user'] ?? null;
if (!is_array($user) || ($user['role'] ?? null) !== 'customer') {
    if (is_array($user) && ($user['role'] ?? null) === 'admin') {
        header('Location: admin.php');
    } else {
        header('Location: register.php');
    }
    exit;
}

$ordersStatement = storeDatabase()->prepare(
    'SELECT id, order_number, order_date, status, subtotal, shipping_fee, total_amount, payment_method
     FROM orders
     WHERE customer_id = :customer_id
     ORDER BY order_date DESC, id DESC'
);
$ordersStatement->execute(['customer_id' => $user['id']]);
$orders = $ordersStatement->fetchAll();
$itemsStatement = storeDatabase()->prepare(
    'SELECT oi.quantity, oi.unit_price, p.name
     FROM order_items oi
     INNER JOIN products p ON p.id = oi.product_id
     WHERE oi.order_id = :order_id
     ORDER BY p.name'
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My account — Golden Glow</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css"><link rel="stylesheet" href="account.css">
</head>
<body>
<?php renderStoreHeader(); ?>
<main class="account-page">
  <section class="account-heading">
    <div><span class="account-eyebrow">MY ACCOUNT</span><h1>Hello, <?= storeEscape((string) $user['full_name']) ?></h1><p><?= storeEscape((string) $user['username']) ?></p></div>
    <form method="post" action="logout.php">
      <input type="hidden" name="csrf_token" value="<?= storeEscape(csrfToken()) ?>">
      <button class="btn btn-ghost" type="submit">Log out</button>
    </form>
  </section>
  <section class="account-orders">
    <div class="account-section-heading"><h2>Your orders</h2><a href="shop.php" class="btn btn-gold">Shop products</a></div>
    <?php if ($orders === []): ?>
      <div class="account-empty"><p>You have not placed an order yet.</p><a href="shop.php">Browse the collection</a></div>
    <?php else: ?>
      <?php foreach ($orders as $order): ?>
        <?php $itemsStatement->execute(['order_id' => $order['id']]); $orderItems = $itemsStatement->fetchAll(); ?>
        <article class="account-order">
          <div class="account-order-heading">
            <div><span class="order-number"><?= storeEscape((string) $order['order_number']) ?></span><span class="order-date"><?= storeEscape(date('F j, Y', strtotime((string) $order['order_date']))) ?></span></div>
            <span class="order-status"><?= storeEscape(ucfirst((string) $order['status'])) ?></span>
          </div>
          <ul class="account-order-items">
            <?php foreach ($orderItems as $item): ?>
              <li><span><?= storeEscape((string) $item['name']) ?> × <?= (int) $item['quantity'] ?></span><span><?= storeEscape(peso((float) $item['unit_price'] * (int) $item['quantity'])) ?></span></li>
            <?php endforeach; ?>
          </ul>
          <div class="account-order-total"><span><?= storeEscape(ucwords(str_replace('_', ' ', (string) $order['payment_method']))) ?></span><strong>Total <?= storeEscape(peso((float) $order['total_amount'])) ?></strong></div>
        </article>
      <?php endforeach; ?>
    <?php endif; ?>
  </section>
</main>
<?php renderStoreFooter(); ?>
</body>
</html>
