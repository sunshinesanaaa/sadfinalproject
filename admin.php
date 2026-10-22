<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
requireAdmin();

function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function formatPeso(float $amount): string
{
    return '₱' . number_format($amount, 2);
}

function solveLinearSystem(array $matrix, array $values): ?array
{
    $size = count($values);

    for ($column = 0; $column < $size; $column++) {
        $pivotRow = $column;
        for ($row = $column + 1; $row < $size; $row++) {
            if (abs($matrix[$row][$column]) > abs($matrix[$pivotRow][$column])) {
                $pivotRow = $row;
            }
        }

        if (abs($matrix[$pivotRow][$column]) < 1.0e-10) {
            return null;
        }

        if ($pivotRow !== $column) {
            [$matrix[$column], $matrix[$pivotRow]] = [$matrix[$pivotRow], $matrix[$column]];
            [$values[$column], $values[$pivotRow]] = [$values[$pivotRow], $values[$column]];
        }

        $pivot = $matrix[$column][$column];
        for ($entry = $column; $entry < $size; $entry++) {
            $matrix[$column][$entry] /= $pivot;
        }
        $values[$column] /= $pivot;

        for ($row = 0; $row < $size; $row++) {
            if ($row === $column) {
                continue;
            }

            $factor = $matrix[$row][$column];
            for ($entry = $column; $entry < $size; $entry++) {
                $matrix[$row][$entry] -= $factor * $matrix[$column][$entry];
            }
            $values[$row] -= $factor * $values[$column];
        }
    }

    return $values;
}

function predictWithSeasonalLinearRegression(array $monthlyUnits, int $forecastCalendarMonth): ?float
{
    $observationCount = count($monthlyUnits);
    if ($observationCount < 24 || $forecastCalendarMonth < 1 || $forecastCalendarMonth > 12) {
        return null;
    }

    $monthCounts = array_fill(1, 12, 0);
    foreach ($monthlyUnits as $observation) {
        $monthCounts[$observation['calendar_month']]++;
    }
    if (in_array(0, $monthCounts, true)) {
        return null;
    }

    $featureCount = 13;
    $matrix = array_fill(0, $featureCount, array_fill(0, $featureCount, 0.0));
    $values = array_fill(0, $featureCount, 0.0);

    foreach ($monthlyUnits as $index => $observation) {
        $features = [1.0, (float) $index];
        for ($month = 2; $month <= 12; $month++) {
            $features[] = $observation['calendar_month'] === $month ? 1.0 : 0.0;
        }
        $units = (float) $observation['units'];

        for ($row = 0; $row < $featureCount; $row++) {
            $values[$row] += $features[$row] * $units;
            for ($column = 0; $column < $featureCount; $column++) {
                $matrix[$row][$column] += $features[$row] * $features[$column];
            }
        }
    }

    $coefficients = solveLinearSystem($matrix, $values);
    if ($coefficients === null) {
        return null;
    }

    $forecastFeatures = [1.0, (float) $observationCount];
    for ($month = 2; $month <= 12; $month++) {
        $forecastFeatures[] = $forecastCalendarMonth === $month ? 1.0 : 0.0;
    }

    $prediction = 0.0;
    foreach ($coefficients as $index => $coefficient) {
        $prediction += $coefficient * $forecastFeatures[$index];
    }

    return max(0.0, $prediction);
}

function renderLineChart(array $rows, string $valueKey, string $chartType): void
{
    $maximum = 0.0;
    foreach ($rows as $row) {
        $maximum = max($maximum, (float) $row[$valueKey]);
    }

    $plotLeft = 58.0;
    $plotRight = 720.0;
    $plotTop = 28.0;
    $plotBottom = 220.0;
    $plotHeight = $plotBottom - $plotTop;
    $tickStep = max(1, (int) ceil($maximum / 5));
    $axisMaximum = $tickStep * 5;
    $pointCount = count($rows);
    $pointSpacing = $pointCount > 1
        ? ($plotRight - $plotLeft) / ($pointCount - 1)
        : 0.0;
    $points = [];

    foreach ($rows as $index => $row) {
        $value = (float) $row[$valueKey];
        $x = $pointCount > 1 ? $plotLeft + $index * $pointSpacing : ($plotLeft + $plotRight) / 2;
        $y = $plotBottom - ($value / $axisMaximum) * $plotHeight;
        $points[] = [
            'x' => $x,
            'y' => $y,
            'value' => $value,
            'name' => (string) $row['name'],
            'label' => $chartType === 'forecast'
                ? number_format($value, 1)
                : number_format($value),
        ];
    }

    $color = $chartType === 'forecast' ? '#B8902E' : '#1B2A4D';
    $chartLabel = $chartType === 'forecast'
        ? 'Predicted units sold'
        : 'Historical units sold';
    $polylinePoints = implode(' ', array_map(
        static fn(array $point): string => number_format($point['x'], 2, '.', '')
            . ',' . number_format($point['y'], 2, '.', ''),
        $points
    ));

    echo '<div class="line-chart-scroll">';
    echo '<svg class="line-chart-svg" viewBox="0 0 760 335" role="img" aria-label="'
        . escape($chartLabel) . ' ranked by product">';
    for ($tick = 0; $tick <= 5; $tick++) {
        $value = $axisMaximum - ($tick * $tickStep);
        $y = $plotTop + ($tick / 5) * $plotHeight;
        echo '<line class="chart-grid-line" x1="' . $plotLeft . '" y1="' . number_format($y, 2, '.', '')
            . '" x2="' . $plotRight . '" y2="' . number_format($y, 2, '.', '') . '"></line>';
        echo '<text class="chart-axis-label" x="' . ($plotLeft - 10) . '" y="'
            . number_format($y + 4, 2, '.', '') . '" text-anchor="end">'
            . number_format($value) . '</text>';
    }
    echo '<line class="chart-axis-line" x1="' . $plotLeft . '" y1="' . $plotTop
        . '" x2="' . $plotLeft . '" y2="' . $plotBottom . '"></line>';
    echo '<line class="chart-axis-line" x1="' . $plotLeft . '" y1="' . $plotBottom
        . '" x2="' . $plotRight . '" y2="' . $plotBottom . '"></line>';

    if (count($points) > 1) {
        echo '<polyline class="chart-line" points="' . $polylinePoints . '" stroke="' . $color . '"></polyline>';
    }

    foreach ($rows as $index => $row) {
        $value = (float) $row[$valueKey];
        $point = $points[$index];
        echo '<circle class="chart-point" cx="' . number_format($point['x'], 2, '.', '')
            . '" cy="' . number_format($point['y'], 2, '.', '') . '" r="5" fill="' . $color . '"></circle>';
        echo '<text class="chart-point-value" x="' . number_format($point['x'], 2, '.', '')
            . '" y="' . number_format(max($plotTop - 5, $point['y'] - 11), 2, '.', '')
            . '" text-anchor="middle">' . escape($point['label']) . '</text>';
        $labelY = 246.0;
        echo '<text class="chart-product-label" transform="rotate(-35 '
            . number_format($point['x'], 2, '.', '') . ' ' . $labelY . ')" x="'
            . number_format($point['x'], 2, '.', '') . '" y="' . $labelY
            . '" text-anchor="end">' . escape($point['name']) . '</text>';
    }
    echo '<text class="chart-axis-title" transform="rotate(-90 15 124)" x="15" y="124" text-anchor="middle">Units sold</text>';
    echo '</svg>';
    echo '</div>';
}

$databaseError = false;
$rankings = [];
$forecastRankings = [];
$forecastReady = false;
$forecastMonthLabel = '';
$forecastPeriodLabel = '';
$summary = ['completed_orders' => 0, 'order_total' => 0.0];
$earliestMonth = null;
$latestMonth = null;
$selectedYear = (int) date('Y');
$selectedMonth = (int) date('n');
$yearOptions = [];

try {
    $pdo = require __DIR__ . '/db.php';

    $dateRange = $pdo->query(
        "SELECT MIN(DATE_FORMAT(order_date, '%Y-%m')) AS earliest_month,
                MAX(DATE_FORMAT(order_date, '%Y-%m')) AS latest_month
         FROM orders
         WHERE status = 'completed'"
    )->fetch();

    $earliestMonth = $dateRange['earliest_month'];
    $latestMonth = $dateRange['latest_month'];

    if ($latestMonth !== null) {
        $latestDate = DateTimeImmutable::createFromFormat('!Y-m', $latestMonth);
        if ($latestDate === false) {
            throw new RuntimeException('The latest completed order month is invalid.');
        }
        $selectedYear = (int) $latestDate->format('Y');
        $selectedMonth = (int) $latestDate->format('n');
    }

    $requestedYear = filter_var($_GET['year'] ?? null, FILTER_VALIDATE_INT);
    $requestedMonth = filter_var($_GET['month'] ?? null, FILTER_VALIDATE_INT);

    if ($requestedYear !== false && $requestedYear !== null) {
        $selectedYear = $requestedYear;
    }
    if ($requestedMonth !== false && $requestedMonth !== null) {
        $selectedMonth = $requestedMonth;
    }

    if ($selectedYear < 2000 || $selectedYear > 2100) {
        $selectedYear = (int) date('Y');
    }
    if ($selectedMonth < 1 || $selectedMonth > 12) {
        $selectedMonth = (int) date('n');
    }

    $firstYear = $earliestMonth !== null
        ? (int) substr($earliestMonth, 0, 4)
        : (int) date('Y');
    $lastYear = $latestMonth !== null
        ? (int) substr($latestMonth, 0, 4)
        : (int) date('Y');
    $firstYear = min($firstYear, $selectedYear);
    $lastYear = max($lastYear, $selectedYear);
    $yearOptions = range($firstYear, $lastYear);

    $periodStart = DateTimeImmutable::createFromFormat(
        '!Y-n-j',
        sprintf('%d-%d-1', $selectedYear, $selectedMonth)
    );
    if ($periodStart === false) {
        throw new RuntimeException('The selected reporting month is invalid.');
    }
    $periodEnd = $periodStart->modify('+1 month');

    $rankingQuery = $pdo->prepare(
        "SELECT p.id,
                p.name,
                p.category,
                p.price AS current_price,
                COALESCE(SUM(CASE WHEN o.id IS NOT NULL THEN oi.quantity ELSE 0 END), 0) AS units_sold,
                COALESCE(SUM(CASE WHEN o.id IS NOT NULL THEN oi.quantity * oi.unit_price ELSE 0 END), 0) AS sales_revenue
         FROM products AS p
         LEFT JOIN order_items AS oi ON oi.product_id = p.id
         LEFT JOIN orders AS o
           ON o.id = oi.order_id
          AND o.status = 'completed'
          AND o.order_date >= :period_start
          AND o.order_date < :period_end
         WHERE p.is_active = 1
         GROUP BY p.id, p.name, p.category"
    );
    $rankingQuery->execute([
        'period_start' => $periodStart->format('Y-m-d H:i:s'),
        'period_end' => $periodEnd->format('Y-m-d H:i:s'),
    ]);
    $rankings = $rankingQuery->fetchAll();

    $summaryQuery = $pdo->prepare(
        "SELECT COUNT(*) AS completed_orders,
                COALESCE(SUM(total_amount), 0) AS order_total
         FROM orders
         WHERE status = 'completed'
           AND order_date >= :period_start
           AND order_date < :period_end"
    );
    $summaryQuery->execute([
        'period_start' => $periodStart->format('Y-m-d H:i:s'),
        'period_end' => $periodEnd->format('Y-m-d H:i:s'),
    ]);
    $summary = $summaryQuery->fetch();

    if ($earliestMonth !== null && $latestMonth !== null) {
        $historyStart = DateTimeImmutable::createFromFormat('!Y-m', $earliestMonth);
        $latestAvailableMonth = DateTimeImmutable::createFromFormat('!Y-m', $latestMonth);
        if ($historyStart === false || $latestAvailableMonth === false) {
            throw new RuntimeException('The available sales history range is invalid.');
        }

        $trainingEnd = $periodStart < $latestAvailableMonth
            ? $periodStart
            : $latestAvailableMonth;
        $forecastMonth = $trainingEnd->modify('+1 month');
        $forecastMonthLabel = $forecastMonth->format('F Y');
        $forecastPeriodLabel = $historyStart->format('M Y') . ' – ' . $trainingEnd->format('M Y');

        $historyQuery = $pdo->prepare(
            "SELECT oi.product_id,
                    DATE_FORMAT(o.order_date, '%Y-%m') AS sales_month,
                    SUM(oi.quantity) AS units_sold
             FROM order_items AS oi
             JOIN orders AS o ON o.id = oi.order_id
             WHERE o.status = 'completed'
               AND o.order_date >= :history_start
               AND o.order_date < :training_end
             GROUP BY oi.product_id, DATE_FORMAT(o.order_date, '%Y-%m')"
        );
        $historyQuery->execute([
            'history_start' => $historyStart->format('Y-m-d H:i:s'),
            'training_end' => $forecastMonth->format('Y-m-d H:i:s'),
        ]);
        $monthlySales = [];
        foreach ($historyQuery->fetchAll() as $record) {
            $monthlySales[(int) $record['product_id']][(string) $record['sales_month']] =
                (int) $record['units_sold'];
        }

        foreach ($rankings as $product) {
            $productId = (int) $product['id'];
            $observations = [];
            for (
                $month = $historyStart;
                $month <= $trainingEnd;
                $month = $month->modify('+1 month')
            ) {
                $monthKey = $month->format('Y-m');
                $observations[] = [
                    'calendar_month' => (int) $month->format('n'),
                    'units' => $monthlySales[$productId][$monthKey] ?? 0,
                ];
            }

            $predictedUnits = predictWithSeasonalLinearRegression(
                $observations,
                (int) $forecastMonth->format('n')
            );
            if ($predictedUnits === null) {
                continue;
            }

            $forecastReady = true;
            $forecastRankings[] = [
                'id' => $productId,
                'name' => (string) $product['name'],
                'category' => (string) $product['category'],
                'predicted_units' => $predictedUnits,
                'predicted_revenue' => $predictedUnits * (float) $product['current_price'],
            ];
        }

        usort($forecastRankings, static function (array $left, array $right): int {
            return $right['predicted_units'] <=> $left['predicted_units'];
        });
    }
} catch (PDOException $exception) {
    error_log('Salevision admin dashboard database error: ' . $exception->getMessage());
    http_response_code(503);
    $databaseError = true;
} catch (RuntimeException $exception) {
    error_log('Salevision admin dashboard data error: ' . $exception->getMessage());
    http_response_code(500);
    $databaseError = true;
}

$unitsRanking = $rankings;
$revenueRanking = $rankings;
usort($unitsRanking, static function (array $left, array $right): int {
    return (int) $right['units_sold'] <=> (int) $left['units_sold']
        ?: (float) $right['sales_revenue'] <=> (float) $left['sales_revenue'];
});
usort($revenueRanking, static function (array $left, array $right): int {
    return (float) $right['sales_revenue'] <=> (float) $left['sales_revenue']
        ?: (int) $right['units_sold'] <=> (int) $left['units_sold'];
});

$monthLabel = DateTimeImmutable::createFromFormat(
    '!Y-n-j',
    sprintf('%d-%d-1', $selectedYear, $selectedMonth)
);
$formattedMonth = $monthLabel === false ? '' : $monthLabel->format('F Y');
$totalUnits = array_sum(array_map(
    static fn(array $row): int => (int) $row['units_sold'],
    $rankings
));
$productRevenue = array_sum(array_map(
    static fn(array $row): float => (float) $row['sales_revenue'],
    $rankings
));

function renderRankingTable(array $products, string $metric): void
{
    foreach ($products as $index => $product) {
        echo '<tr>';
        echo '<td>' . ($index + 1) . '</td>';
        echo '<td><div class="row-name">' . escape((string) $product['name']) . '</div>';
        echo '<div class="row-cat">' . escape((string) $product['category']) . '</div></td>';
        if ($metric === 'units') {
            echo '<td>' . number_format((int) $product['units_sold']) . '</td>';
        } else {
            echo '<td>' . formatPeso((float) $product['sales_revenue']) . '</td>';
        }
        echo '</tr>';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sales Dashboard — Golden Glow</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css">
<link rel="stylesheet" href="admin.css">
</head>
<body>
<div class="admin-shell">
  <aside class="sidebar">
    <a href="index.html" class="brand">
      <img src="assets/gglogo.png" alt="Golden Glow">
      <span class="brand-word">Golden <b>Glow</b></span>
    </a>
    <nav class="side-nav">
      <a href="admin.php" class="active">Sales dashboard</a>
      <a href="shop.html">Storefront</a>
    </nav>
    <div class="side-foot">
      <a href="index.html">Back to storefront</a>
      <form method="post" action="logout.php" class="logout-form">
        <input type="hidden" name="csrf_token" value="<?= escape(csrfToken()) ?>">
        <button type="submit" class="logout-button">Log out</button>
      </form>
    </div>
  </aside>

  <main class="admin-main">
    <div class="admin-topbar">
      <div>
        <h1>Sales dashboard</h1>
        <p class="dashboard-subtitle">Historical product performance</p>
      </div>
      <div class="admin-topbar-actions">
        <div class="admin-who">
          <div>
            <div class="name"><?= escape((string) ($_SESSION['user']['full_name'] ?? 'Administrator')) ?></div>
            <div class="role">@<?= escape((string) ($_SESSION['user']['username'] ?? 'admin')) ?></div>
          </div>
          <div class="avatar" aria-hidden="true">GG</div>
        </div>
        <a class="dashboard-action dashboard-home" href="index.html">Back to homepage</a>
        <form method="post" action="logout.php" class="dashboard-logout-form">
          <input type="hidden" name="csrf_token" value="<?= escape(csrfToken()) ?>">
          <button type="submit" class="dashboard-action dashboard-logout">Log out</button>
        </form>
      </div>
    </div>

    <?php if ($databaseError): ?>
      <div class="dashboard-error" role="alert">
        The dashboard could not load its data. Check that MySQL is running on port 3307 and that the defaults in <code>db.php</code> (or any SALEVISION database environment overrides) match your XAMPP setup. See the PHP error log for technical details.
      </div>
    <?php else: ?>
      <section class="panel analytics-panel">
        <div class="panel-head">
          <div>
            <h2>Historical rankings</h2>
            <p class="panel-description">Completed orders only; cancelled orders are excluded.</p>
          </div>
          <form class="analytics-controls" method="get" action="admin.php">
            <label for="month">Month</label>
            <select id="month" name="month">
              <?php for ($month = 1; $month <= 12; $month++): ?>
                <option value="<?= $month ?>" <?= $selectedMonth === $month ? 'selected' : '' ?>>
                  <?= escape(date('F', mktime(0, 0, 0, $month, 1))) ?>
                </option>
              <?php endfor; ?>
            </select>
            <label for="year">Year</label>
            <select id="year" name="year">
              <?php foreach ($yearOptions as $year): ?>
                <option value="<?= $year ?>" <?= $selectedYear === $year ? 'selected' : '' ?>><?= $year ?></option>
              <?php endforeach; ?>
            </select>
            <button class="btn btn-gold" type="submit">View month</button>
          </form>
        </div>

        <div class="stat-grid analytics-stat-grid">
          <div class="stat-card">
            <div class="label">Product revenue · <?= escape($formattedMonth) ?></div>
            <div class="value"><?= escape(formatPeso((float) $productRevenue)) ?></div>
            <div class="delta">Items sold × purchase-time unit price</div>
          </div>
          <div class="stat-card">
            <div class="label">Units sold</div>
            <div class="value"><?= number_format($totalUnits) ?></div>
            <div class="delta">From completed orders</div>
          </div>
          <div class="stat-card">
            <div class="label">Completed orders</div>
            <div class="value"><?= number_format((int) $summary['completed_orders']) ?></div>
            <div class="delta">Selected month</div>
          </div>
          <div class="stat-card">
            <div class="label">Order total</div>
            <div class="value"><?= escape(formatPeso((float) $summary['order_total'])) ?></div>
            <div class="delta">Includes recorded shipping fees</div>
          </div>
        </div>

        <div class="chart-grid">
          <section class="chart-card" aria-labelledby="historical-chart-title">
            <div class="chart-card-heading">
              <div>
                <h3 id="historical-chart-title">Historical product ranking</h3>
                <p>Completed units sold · <?= escape($formattedMonth) ?></p>
              </div>
              <span class="chart-legend"><span class="legend-swatch historical-swatch"></span>Actual</span>
            </div>
            <?php if ($totalUnits > 0): ?>
              <?php renderLineChart($unitsRanking, 'units_sold', 'historical'); ?>
            <?php else: ?>
              <p class="chart-empty">No completed sales to chart for this month.</p>
            <?php endif; ?>
          </section>
          <section class="chart-card forecast-card" aria-labelledby="forecast-chart-title">
            <div class="chart-card-heading">
              <div>
                <h3 id="forecast-chart-title">Sales prediction</h3>
                <p>Forecast units · <?= escape($forecastMonthLabel !== '' ? $forecastMonthLabel : 'Unavailable') ?></p>
              </div>
              <span class="chart-legend"><span class="legend-swatch forecast-swatch"></span>Predicted</span>
            </div>
            <?php if ($forecastReady): ?>
              <?php renderLineChart($forecastRankings, 'predicted_units', 'forecast'); ?>
              <div class="forecast-values">
                <?php foreach ($forecastRankings as $index => $forecast): ?>
                  <p><span><?= $index + 1 ?>. <?= escape($forecast['name']) ?></span>
                    <strong><?= number_format($forecast['predicted_units'], 1) ?> units · <?= escape(formatPeso($forecast['predicted_revenue'])) ?></strong>
                  </p>
                <?php endforeach; ?>
              </div>
              <p class="chart-method-note">
                Trend-plus-seasonal linear regression. The table gives each product's predicted units and estimated revenue at current prices. Trained on completed sales from <?= escape($forecastPeriodLabel) ?>.
              </p>
            <?php else: ?>
              <p class="chart-empty">At least 24 consecutive months of completed sales, including every calendar month, are needed to generate this seasonal forecast.</p>
            <?php endif; ?>
          </section>
        </div>

        <div class="ranking-grid">
          <section class="ranking-card" aria-labelledby="units-heading">
            <h3 id="units-heading">Top products by units sold</h3>
            <div class="table-scroll">
              <table class="analytics-table">
                <thead><tr><th scope="col">Rank</th><th scope="col">Product</th><th scope="col">Units</th></tr></thead>
                <tbody><?php renderRankingTable($unitsRanking, 'units'); ?></tbody>
              </table>
            </div>
          </section>
          <section class="ranking-card" aria-labelledby="revenue-heading">
            <h3 id="revenue-heading">Top products by sales revenue</h3>
            <div class="table-scroll">
              <table class="analytics-table">
                <thead><tr><th scope="col">Rank</th><th scope="col">Product</th><th scope="col">Revenue</th></tr></thead>
                <tbody><?php renderRankingTable($revenueRanking, 'revenue'); ?></tbody>
              </table>
            </div>
          </section>
        </div>
        <?php if (!$rankings || $totalUnits === 0): ?>
          <p class="no-sales">No completed product sales were recorded for <?= escape($formattedMonth) ?>.</p>
        <?php endif; ?>
      </section>
    <?php endif; ?>

    <p class="dashboard-footnote">
      Historical revenue rankings use the saved price for each order item and do not include shipping. Linear regression includes a time trend and calendar-month seasonality. Predictions are estimates, not guaranteed results.
    </p>
  </main>
</div>
</body>
</html>
