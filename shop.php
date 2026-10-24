<?php
declare(strict_types=1);

require_once __DIR__ . '/store.php';

$pdo = storeDatabase();
$categoryInput = $_GET['category'] ?? '';
$category = is_string($categoryInput) ? trim($categoryInput) : '';
$searchInput = $_GET['q'] ?? '';
$search = is_string($searchInput) ? trim($searchInput) : '';
$sortInput = $_GET['sort'] ?? 'featured';
$sort = is_string($sortInput) ? $sortInput : 'featured';
$orderBy = match ($sort) {
    'price_asc' => 'price ASC, name ASC',
    'price_desc' => 'price DESC, name ASC',
    'name' => 'name ASC',
    default => 'id ASC',
};

$categories = $pdo->query(
    'SELECT DISTINCT category FROM products WHERE is_active = 1 ORDER BY category'
)->fetchAll(PDO::FETCH_COLUMN);
$conditions = ['is_active = 1'];
$parameters = [];
if ($category !== '' && in_array($category, $categories, true)) {
    $conditions[] = 'category = :category';
    $parameters['category'] = $category;
}
if ($search !== '') {
    $conditions[] = '(name LIKE :name_search OR category LIKE :category_search)';
    $parameters['name_search'] = '%' . $search . '%';
    $parameters['category_search'] = '%' . $search . '%';
}
$statement = $pdo->prepare(
    'SELECT id, name, category, price, stock_quantity FROM products WHERE '
    . implode(' AND ', $conditions) . ' ORDER BY ' . $orderBy
);
$statement->execute($parameters);
$products = $statement->fetchAll();
$cartError = $_SESSION['cart_error'] ?? '';
unset($_SESSION['cart_error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Shop All — Golden Glow</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css?v=3"><link rel="stylesheet" href="shop.css?v=2">
</head>
<body>
<?php renderStoreHeader('shop'); ?>
<div class="page-head"><div class="wrap"><div class="crumb"><a href="index.php">Home</a> / Shop</div><h1>Shop all products</h1></div></div>
<main class="wrap shop-layout">
  <aside>
    <h2 class="filter-title">Browse products</h2>
    <form method="get" action="shop.php" class="shop-filter-form">
      <label for="category">Category</label>
      <select id="category" name="category">
        <option value="">All categories</option>
        <?php foreach ($categories as $categoryOption): ?>
          <option value="<?= storeEscape((string) $categoryOption) ?>" <?= $category === $categoryOption ? 'selected' : '' ?>><?= storeEscape((string) $categoryOption) ?></option>
        <?php endforeach; ?>
      </select>
      <label for="search">Search</label>
      <input id="search" name="q" type="search" value="<?= storeEscape($search) ?>" placeholder="Product or category">
      <label for="sort">Sort by</label>
      <select id="sort" name="sort">
        <option value="featured" <?= $sort === 'featured' ? 'selected' : '' ?>>Featured</option>
        <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Price: low to high</option>
        <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price: high to low</option>
        <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>Name</option>
      </select>
      <button class="btn btn-navy btn-block apply-btn" type="submit">Apply</button>
    </form>
  </aside>
  <section>
    <?php if (is_string($cartError) && $cartError !== ''): ?>
      <p class="store-message" role="alert"><?= storeEscape($cartError) ?></p>
    <?php endif; ?>
    <div class="shop-toolbar"><span class="result-count">Showing <?= count($products) ?> products</span></div>
    <div class="grid-3">
      <?php foreach ($products as $product): ?>
        <?php renderProductCard($product); ?>
      <?php endforeach; ?>
    </div>
    <?php if (!$products): ?><p class="empty-state">No products match those filters. <a href="shop.php">View all products</a>.</p><?php endif; ?>
  </section>
</main>
<?php renderStoreFooter(); ?>
</body>
</html>
