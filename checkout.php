<?php
declare(strict_types=1);

require_once __DIR__ . '/store.php';
requireCustomer();

$pdo = storeDatabase();
$cart = $_SESSION['cart'] ?? [];
$cart = is_array($cart) ? $cart : [];
$message = '';
$orderError = '';

if (isset($_GET['confirmed'])) {
    $confirmation = $_SESSION['order_confirmation'] ?? null;
    unset($_SESSION['order_confirmation']);
    if (is_array($confirmation) && isset($confirmation['id'], $confirmation['number'])) {
        $checkOrder = $pdo->prepare(
            'SELECT id FROM orders WHERE id = :id AND customer_id = :customer_id AND order_number = :order_number'
        );
        $checkOrder->execute([
            'id' => $confirmation['id'],
            'customer_id' => $_SESSION['user']['id'],
            'order_number' => $confirmation['number'],
        ]);
        if ($checkOrder->fetchColumn() !== false) {
            $message = 'Your order is confirmed. Your order number is ' . (string) $confirmation['number'] . '.';
        }
    }
}

$items = [];
foreach ($cart as $id => $quantity) {
    $productId = filter_var((string) $id, FILTER_VALIDATE_INT);
    $itemQuantity = filter_var($quantity, FILTER_VALIDATE_INT);
    if ($productId !== false && $productId > 0 && $itemQuantity !== false && $itemQuantity > 0) {
        $items[$productId] = min(99, $itemQuantity);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
        $orderError = 'Your confirmation request expired. Please try again.';
    } elseif ($items === []) {
        $orderError = 'Your cart is empty. Add a product before confirming an order.';
    } else {
        $phoneInput = $_POST['phone'] ?? '';
        $addressInput = $_POST['shipping_address'] ?? '';
        $paymentInput = $_POST['payment_method'] ?? '';
        $phone = is_string($phoneInput) ? trim($phoneInput) : '';
        $address = is_string($addressInput) ? trim($addressInput) : '';
        $paymentMethod = is_string($paymentInput) ? $paymentInput : '';

        if (strlen($phone) < 7 || strlen($phone) > 30) {
            $orderError = 'Enter a valid contact phone number.';
        } elseif ($address === '' || strlen($address) > 500) {
            $orderError = 'Enter a delivery address (up to 500 characters).';
        } elseif (!in_array($paymentMethod, ['cash_on_delivery', 'gcash_manual'], true)) {
            $orderError = 'Choose a payment method.';
        } else {
            try {
                $pdo->beginTransaction();
                $lockedProducts = [];
                ksort($items);
                $productQuery = $pdo->prepare(
                    'SELECT id, name, price, stock_quantity FROM products WHERE id = :id AND is_active = 1 FOR UPDATE'
                );
                foreach ($items as $id => $quantity) {
                    $productQuery->execute(['id' => $id]);
                    $product = $productQuery->fetch();
                    if ($product === false) {
                        throw new RuntimeException('A product in the cart is no longer available.');
                    }
                    if ((int) $product['stock_quantity'] < $quantity) {
                        throw new RuntimeException('There is not enough stock for ' . (string) $product['name'] . '. Update your cart quantity.');
                    }
                    $lockedProducts[$id] = $product;
                }

                $subtotal = 0.0;
                foreach ($items as $id => $quantity) {
                    $subtotal += (float) $lockedProducts[$id]['price'] * $quantity;
                }
                $shippingFee = $subtotal >= 2500 ? 0.0 : 99.0;
                $orderNumber = 'GG-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
                $insertOrder = $pdo->prepare(
                    "INSERT INTO orders (
                       order_number, customer_id, shipping_address, contact_phone, order_date,
                       status, subtotal, shipping_fee, total_amount, payment_method
                     ) VALUES (
                       :order_number, :customer_id, :shipping_address, :contact_phone, NOW(),
                       'completed', :subtotal, :shipping_fee, :total_amount, :payment_method
                     )"
                );
                $insertOrder->execute([
                    'order_number' => $orderNumber,
                    'customer_id' => $_SESSION['user']['id'],
                    'shipping_address' => $address,
                    'contact_phone' => $phone,
                    'subtotal' => number_format($subtotal, 2, '.', ''),
                    'shipping_fee' => number_format($shippingFee, 2, '.', ''),
                    'total_amount' => number_format($subtotal + $shippingFee, 2, '.', ''),
                    'payment_method' => $paymentMethod,
                ]);
                $orderId = (int) $pdo->lastInsertId();
                $insertItem = $pdo->prepare(
                    'INSERT INTO order_items (order_id, product_id, quantity, unit_price) VALUES (:order_id, :product_id, :quantity, :unit_price)'
                );
                $updateStock = $pdo->prepare(
                    'UPDATE products SET stock_quantity = stock_quantity - :quantity WHERE id = :id AND stock_quantity >= :minimum'
                );
                foreach ($items as $id => $quantity) {
                    $unitPrice = (float) $lockedProducts[$id]['price'];
                    $insertItem->execute([
                        'order_id' => $orderId,
                        'product_id' => $id,
                        'quantity' => $quantity,
                        'unit_price' => number_format($unitPrice, 2, '.', ''),
                    ]);
                    $updateStock->execute(['quantity' => $quantity, 'id' => $id, 'minimum' => $quantity]);
                    if ($updateStock->rowCount() !== 1) {
                        throw new RuntimeException('Stock changed while confirming the order. Please review your cart.');
                    }
                }

                $pdo->commit();
                $_SESSION['cart'] = [];
                $_SESSION['order_confirmation'] = ['id' => $orderId, 'number' => $orderNumber];
                header('Location: checkout.php?confirmed=1');
                exit;
            } catch (RuntimeException $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $orderError = $exception->getMessage();
            } catch (PDOException $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Salevision order save error: ' . $exception->getMessage());
                $orderError = 'The order could not be saved. Please try again.';
            }
        }
    }
}

$items = [];
$itemStatement = $pdo->prepare(
    'SELECT id, name, category, price, stock_quantity FROM products WHERE is_active = 1 AND id = :id'
);
$subtotal = 0.0;
foreach ($cart as $id => $quantity) {
    $productId = filter_var((string) $id, FILTER_VALIDATE_INT);
    $itemQuantity = filter_var($quantity, FILTER_VALIDATE_INT);
    if ($productId === false || $productId < 1 || $itemQuantity === false || $itemQuantity < 1) {
        continue;
    }
    $itemStatement->execute(['id' => $productId]);
    $product = $itemStatement->fetch();
    if ($product === false) {
        continue;
    }
    $items[$productId] = ['product' => $product, 'quantity' => $itemQuantity];
    $subtotal += (float) $product['price'] * $itemQuantity;
}
$shippingFee = $subtotal >= 2500 ? 0.0 : ($items === [] ? 0.0 : 99.0);
$customer = $_SESSION['user'];
$phoneValue = $_POST['phone'] ?? '';
$addressValue = $_POST['shipping_address'] ?? '';
$phoneValue = is_string($phoneValue) ? $phoneValue : '';
$addressValue = is_string($addressValue) ? $addressValue : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Order confirmation — Golden Glow</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css"><link rel="stylesheet" href="checkout.css">
</head>
<body>
<?php renderStoreHeader(); ?>
<main class="checkout-page">
  <div class="checkout-title"><h1>Confirm your order</h1><p>Review your delivery information and confirm your selected payment method.</p></div>
  <?php if ($message !== ''): ?>
    <section class="checkout-card confirmation-card" role="status"><h2>Order placed</h2><p><?= storeEscape($message) ?></p><p>Your order has been recorded for sales reporting. No online payment was processed.</p><a class="btn btn-gold" href="shop.php">Continue shopping</a></section>
  <?php elseif ($items === []): ?>
    <section class="checkout-card"><p>Your cart is empty.</p><a class="btn btn-gold" href="shop.php">Browse products</a></section>
  <?php else: ?>
    <form method="post" action="checkout.php" class="checkout-grid">
      <div>
        <section class="checkout-card">
          <h2>Delivery information</h2>
          <p class="checkout-customer"><?= storeEscape((string) $customer['full_name']) ?> · <?= storeEscape((string) $customer['username']) ?></p>
          <div class="form-group"><label for="phone">Contact phone</label><input id="phone" name="phone" type="tel" maxlength="30" autocomplete="tel" required value="<?= storeEscape($phoneValue) ?>"></div>
          <div class="form-group"><label for="shipping_address">Delivery address</label><textarea id="shipping_address" name="shipping_address" maxlength="500" autocomplete="street-address" required><?= storeEscape($addressValue) ?></textarea></div>
        </section>
        <section class="checkout-card">
          <h2>Payment method</h2>
          <label class="payment-option"><input type="radio" name="payment_method" value="cash_on_delivery" checked> Cash on delivery</label>
          <label class="payment-option"><input type="radio" name="payment_method" value="gcash_manual"> GCash (confirm separately with the seller)</label>
          <p class="payment-note">This page records your order and selected payment method. It does not collect payment details or process a payment.</p>
        </section>
      </div>
      <aside class="checkout-card">
        <h2>Order summary</h2>
        <?php foreach ($items as $item): ?>
          <div class="order-item"><div class="order-item-name"><?= storeEscape((string) $item['product']['name']) ?> × <?= (int) $item['quantity'] ?></div><div class="order-item-price"><?= storeEscape(peso((float) $item['product']['price'] * $item['quantity'])) ?></div></div>
        <?php endforeach; ?>
        <div class="summary-row"><span>Subtotal</span><span><?= storeEscape(peso($subtotal)) ?></span></div>
        <div class="summary-row"><span>Shipping</span><span><?= $shippingFee === 0.0 ? 'Free' : storeEscape(peso($shippingFee)) ?></span></div>
        <div class="summary-row summary-total"><span>Total</span><span><?= storeEscape(peso($subtotal + $shippingFee)) ?></span></div>
        <?php if ($orderError !== ''): ?><p class="store-message" role="alert"><?= storeEscape($orderError) ?></p><?php endif; ?>
        <input type="hidden" name="csrf_token" value="<?= storeEscape(csrfToken()) ?>">
        <button class="btn btn-gold btn-block place-order-button" type="submit">Confirm order</button>
        <a class="continue-shopping" href="cart.php">Return to cart</a>
      </aside>
    </form>
  <?php endif; ?>
</main>
<?php renderStoreFooter(); ?>
</body>
</html>
