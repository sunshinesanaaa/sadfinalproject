<?php
declare(strict_types=1);

require_once __DIR__ . '/store.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isValidCsrfToken($_POST['csrf_token'] ?? null)) {
    http_response_code(400);
    exit('Invalid cart request.');
}

$action = $_POST['action'] ?? '';
$returnTo = $_POST['return_to'] ?? 'cart.php';
$allowedReturnPaths = ['shop.php', 'cart.php', 'index.php'];
if (!is_string($returnTo) || !in_array($returnTo, $allowedReturnPaths, true)) {
    $returnTo = 'cart.php';
}

$cart = $_SESSION['cart'] ?? [];
if (!is_array($cart)) {
    $cart = [];
}
$cartError = '';

if ($action === 'add' || $action === 'update') {
    $productId = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT);
    $quantity = filter_var($_POST['quantity'] ?? null, FILTER_VALIDATE_INT);

    if ($productId === false || $productId < 1 || $quantity === false || $quantity < 1 || $quantity > 99) {
        $cartError = 'Choose a valid product and quantity.';
    } else {
        $statement = storeDatabase()->prepare(
            'SELECT id, stock_quantity FROM products WHERE id = :id AND is_active = 1'
        );
        $statement->execute(['id' => $productId]);
        $product = $statement->fetch();

        if ($product === false) {
            $cartError = 'That product is unavailable.';
        } else {
            $newQuantity = $action === 'add'
                ? (int) ($cart[$productId] ?? 0) + $quantity
                : $quantity;
            if ($newQuantity > 99 || $newQuantity > (int) $product['stock_quantity']) {
                $cartError = 'The requested quantity is greater than the available stock.';
            } else {
                $cart[$productId] = $newQuantity;
            }
        }
    }
} elseif ($action === 'remove') {
    $productId = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT);
    if ($productId !== false && $productId > 0) {
        unset($cart[$productId]);
    }
} elseif ($action === 'clear') {
    $cart = [];
} else {
    http_response_code(400);
    exit('Unknown cart action.');
}

$_SESSION['cart'] = $cart;
if ($cartError !== '') {
    $_SESSION['cart_error'] = $cartError;
}

header('Location: ' . $returnTo);
exit;
