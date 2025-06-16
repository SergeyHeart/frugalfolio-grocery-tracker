<?php
/**
 * dashboard.php - Presentation Layer for the FrugalFolio Dashboard.
 *
 * This script displays key financial summaries and insights to the user
 * based on data fetched by dashboard_data_logic.php.
 */

define('FRUGALFOLIO_ACCESS', true);
require_once 'auth_bootstrap.php'; // Handles session, DB connection, and user authentication.
require_login(); // Ensures only logged-in users can access this page.

// --- Data Fetching ---
// $dashboardData contains all necessary data computed by the logic layer.
// $conn (from auth_bootstrap) is passed implicitly to dashboard_data_logic.php if it's not closed before require.
// dashboard_data_logic.php is responsible for closing its own connection after fetching data.
$dashboardData = require_once 'dashboard_data_logic.php';

// --- Page Meta and Assets ---
$pageTitle = "Dashboard - FrugalFolio";
$pageStylesheets = [
    '/css/dashboard_style.css', // Main styles for this page
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css' // For icons
];
$pageScripts = [ // For the category comparison chart
    '/js/dashboard_chart.js',
    '/js/tooltips.js'
]; 

// --- Load Header (includes <head>, top bar, navigation) ---
require_once 'header.php';

// --- Data Extraction for View ---
// Provides easier access to parts of the $dashboardData array within the HTML.
$lastTripDate   = $dashboardData['lastTripDate'] ?? null;
$errorMessage   = $dashboardData['errorMessage'] ?? null;
$r1             = $dashboardData['row1'] ?? []; // Data for Row 1 cards
$r2             = $dashboardData['row2'] ?? []; // Data for Row 3 cards (previously Row 2)
$notifications  = $dashboardData['notifications'] ?? ['price_increase_count' => 0, 'price_increase_items' => []];

// --- View Helper Functions ---

/**
 * Truncates text to a specified length, adding an ellipsis if truncated.
 * Uses mbstring functions if available for multi-byte character safety.
 *
 * @param string|null $text The text to truncate.
 * @param int $length The maximum length of the text.
 * @return string The truncated (or original) text.
 */
function truncate_text(?string $text, int $length): string {
    if ($text === null) return '';
    if (function_exists('mb_strlen') && mb_strlen($text) > $length) {
        return mb_substr($text, 0, $length) . '...';
    } elseif (!function_exists('mb_strlen') && strlen($text) > $length) {
        return substr($text, 0, $length) . '...';
    }
    return $text;
}

/**
 * Determines a CSS class based on a percentage value.
 * Used for styling positive (increase), negative (decrease), or neutral changes.
 *
 * @param float|null $percent The percentage value.
 * @return string CSS class ('increase', 'decrease', 'neutral').
 */
function getComparisonClass(?float $percent): string {
    if ($percent === null || !is_numeric($percent)) {
        return 'neutral';
    }
    // Using a small threshold to consider values around zero as neutral.
    if ($percent > 0.1) return 'increase';
    if ($percent < -0.1) return 'decrease';
    return 'neutral';
}

/**
 * Formats a comparison percentage with an appropriate icon and suffix.
 *
 * @param float|null $percent The percentage change.
 * @param string $class The CSS class ('increase', 'decrease', 'neutral') determining the icon.
 * @param string $prefix_neutral Optional prefix for neutral values.
 * @param string $suffix Text to append after the percentage (e.g., " vs. previous week").
 * @return string HTML string with formatted percentage and icon.
 */
function format_comparison_value_with_icon(?float $percent, string $class, string $prefix_neutral = '', string $suffix = ' vs. previous week'): string {
    if ($percent === null || !is_numeric($percent)) {
        return '<span class="no-data">N/A</span>';
    }
    $icon = '';
    if ($class === 'increase') {
        $icon = '<i class="fas fa-caret-up"></i> ';
    } elseif ($class === 'decrease') {
        $icon = '<i class="fas fa-caret-down"></i> ';
    } else {
        $icon = $prefix_neutral;
    }
    $formatted_percent = number_format(abs($percent), 1) . '%';
    return $icon . $formatted_percent . $suffix;
}

// --- Configuration for Display ---
$maxItemNameLength_Row3 = 26;    // Max length for item/group names in Row 3 cards
$maxItemNameLength_tracker = 25; // Max length for item names in Price Tracker
?>

<main class="main-content-wrapper">

    <?php if ($errorMessage): ?>
        <div class="alert alert-danger" role="alert"><?= htmlspecialchars($errorMessage) ?></div>
    <?php endif; ?>

    <h2 class="mobile-page-title-content">
        <?= htmlspecialchars(isset($pageTitle) ? str_replace(" - FrugalFolio", "", $pageTitle) : 'Dashboard') ?>
    </h2>

    <?php if ($lastTripDate): ?>
        <div class="dashboard-grid">

            <!-- ======================= ROW 1: Key Weekly/Monthly Metrics ======================= -->

            <!-- Card 1.1: Recent Week's Total Expenses -->
            <?php
            $latestWeekTotal          = $r1['latestWeekTotal'] ?? 0.00;
            $latestWeekLabel          = $r1['latestWeekLabel'] ?? 'N/A';
            $previousWeekLabelForComp = ($r1['previousWeekLabel'] ?? 'prev. week');
            if ($previousWeekLabelForComp === "N/A") {
                $previousWeekLabelForComp = 'prev. week';
            }
            $weeklySpendChangePercent = $r1['weeklySpendChangePercent'] ?? null;
            $card1_1_footerClass      = getComparisonClass($weeklySpendChangePercent);
            $card1_1_metricClass      = 'metric-' . $card1_1_footerClass;
            ?>
            <div class="dashboard-card card-recent-week">
                <div class="card-header">
                    <span class="card-icon"><i data-feather="trending-up"></i></span>
                    <span class="card-metric <?= $card1_1_metricClass ?>">₱<?= number_format($latestWeekTotal, 2) ?></span>
                </div>
                <div class="card-body">
                    <h3 class="card-title">
                        Recent Week's Total<br>Expenses
                        <?php if ($latestWeekLabel !== "N/A"): ?>
                        <span class="info-tooltip" data-tooltip="Period: <?= htmlspecialchars($latestWeekLabel) ?>">
                            <svg class="info-svg-icon" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="10" cy="10" r="9" stroke="#888" stroke-width="2" fill="none"/><rect x="9" y="8" width="2" height="6" rx="1" fill="#888"/><rect x="9" y="5" width="2" height="2" rx="1" fill="#888"/></svg>
                        </span>
                        <?php endif; ?>
                    </h3>
                </div>
                <div class="card-footer">
                    <span class="comparison <?= $card1_1_footerClass ?>">
                        <?= format_comparison_value_with_icon($weeklySpendChangePercent, $card1_1_footerClass, '', ' vs latest week') ?>
                    </span>
                </div>
            </div>

            <!-- Card 1.2: Average Weekly Spending -->
            <?php
            $avgWeeklySpend            = $r1['avgWeeklySpend'] ?? 0.00;
            $avgWeeklySpendPeriodLabel = $r1['avgWeeklySpendPeriodLabel'] ?? 'Details N/A';
            $percentDiffAvgVsLatest_1_2= null;
            if ($latestWeekTotal > 0) {
                $percentDiffAvgVsLatest_1_2 = (($avgWeeklySpend - $latestWeekTotal) / $latestWeekTotal) * 100;
            } elseif ($avgWeeklySpend > 0 && $latestWeekTotal == 0) {
                $percentDiffAvgVsLatest_1_2 = 100;
            }
            $card1_2_footerClass = getComparisonClass($percentDiffAvgVsLatest_1_2);
            $card1_2_metricClass = 'metric-' . $card1_2_footerClass;
            $latestWeekSpendHtml_1_2 = '₱' . number_format($latestWeekTotal, 2);
            if ($latestWeekTotal < $avgWeeklySpend && $latestWeekTotal > 0) {
                $latestWeekSpendHtml_1_2 = '<span class="decrease">' . $latestWeekSpendHtml_1_2 . '</span>';
            }
            ?>
            <div class="dashboard-card card-avg-weekly">
                <div class="card-header">
                    <span class="card-icon"><i data-feather="calendar"></i></span>
                    <span class="card-metric <?= $card1_2_metricClass ?>">₱<?= number_format($avgWeeklySpend, 2) ?></span>
                </div>
                <div class="card-body">
                    <h3 class="card-title">
                        Average Weekly<br>Spending
                        <?php if ($avgWeeklySpendPeriodLabel !== "N/A" && $avgWeeklySpendPeriodLabel !== "No data for period"): ?>
                        <span class="info-tooltip" data-tooltip="Average calculated for: <?= htmlspecialchars($avgWeeklySpendPeriodLabel) ?>">
                            <svg class="info-svg-icon" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="10" cy="10" r="9" stroke="#888" stroke-width="2" fill="none"/><rect x="9" y="8" width="2" height="6" rx="1" fill="#888"/><rect x="9" y="5" width="2" height="2" rx="1" fill="#888"/></svg>
                        </span>
                        <?php endif; ?>
                    </h3>
                </div>
                <div class="card-footer">
                    <span class="comparison-text <?= $card1_2_footerClass ?>">
                        <?php
                        $compText1_2 = '<span class="no-data">N/A vs. latest or no change</span>';
                        if ($percentDiffAvgVsLatest_1_2 !== null) {
                            $prefixIcon = ($card1_2_footerClass === 'increase') ? '<i class="fas fa-caret-up"></i> ' : (($card1_2_footerClass === 'decrease') ? '<i class="fas fa-caret-down"></i> ' : '');
                            $prefixPercent = ($card1_2_footerClass !== 'neutral') ? number_format(abs($percentDiffAvgVsLatest_1_2), 0) . '% ' : '';
                            $compText1_2 = $prefixIcon . $prefixPercent . 'vs. latest week: ' . $latestWeekSpendHtml_1_2;
                        }
                        echo $compText1_2;
                        ?>
                    </span>
                </div>
            </div>
			
			

            <!-- Card 1.3: Average Monthly Spending -->
            <?php
            $avgMonthlySpend_Recent3Mo         = $r1['avgMonthlySpend_Recent3Mo'] ?? 0.00;
            $avgMonthlySpend_CombinedTooltip   = $r1['avgMonthlySpend_CombinedTooltipLabel'] ?? 'Details not available';
            
            $avgMonthlySpend_Previous3Mo       = $r1['avgMonthlySpend_Previous3Mo'] ?? 0.00;
            $monthlyAvgChangePercent           = $r1['monthlyAvgChangePercent'] ?? null;

            $card1_3_footerClass = getComparisonClass($monthlyAvgChangePercent);
            // --- MODIFICATION HERE ---
            // Color the main metric based on its comparison to the prior 3-month average
            $card1_3_metricClass = 'metric-' . $card1_3_footerClass; 
            // --- END MODIFICATION ---
            
            // error_log("Card 1.3 Debug: monthlyAvgChangePercent = " . var_export($monthlyAvgChangePercent, true) . ", footerClass = " . $card1_3_footerClass . ", metricClass = " . $card1_3_metricClass);
            ?>
            <div class="dashboard-card card-avg-monthly">
                <div class="card-header">
                    <span class="card-icon"><i data-feather="bar-chart-2"></i></span>
                    <span class="card-metric <?= $card1_3_metricClass ?>">₱<?= number_format($avgMonthlySpend_Recent3Mo, 2) ?></span>
                </div>
                <div class="card-body">
                    <h3 class="card-title">
                        Average Monthly<br>Spending
                        <?php if ($avgMonthlySpend_CombinedTooltip !== "Details N/A" && $avgMonthlySpend_CombinedTooltip !== "Details not available"): ?>
                        <span class="info-tooltip" data-tooltip="<?= htmlspecialchars($avgMonthlySpend_CombinedTooltip) ?>">
                            <svg class="info-svg-icon" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="10" cy="10" r="9" stroke="#888" stroke-width="2" fill="none"/><rect x="9" y="8" width="2" height="6" rx="1" fill="#888"/><rect x="9" y="5" width="2" height="2" rx="1" fill="#888"/></svg>
                        </span>
                        <?php endif; ?>
                    </h3>
                </div>
                <div class="card-footer">
                     <span class="comparison-text <?= $card1_3_footerClass ?>">
                        <?php
                        // Footer logic remains the same as your last working version for the text content
                        $compText1_3 = '<span class="no-data">Comparison data N/A</span>';
                        if ($monthlyAvgChangePercent !== null && $avgMonthlySpend_Previous3Mo > 0) {
                            $prefixIcon = ($card1_3_footerClass === 'increase') ? '<i class="fas fa-caret-up"></i> ' : (($card1_3_footerClass === 'decrease') ? '<i class="fas fa-caret-down"></i> ' : '');
                            $prefixPercent = number_format(abs($monthlyAvgChangePercent), 0) . '% ';
                            $compText1_3 = $prefixIcon . $prefixPercent . 'vs. prior 3-month average: ₱' . number_format($avgMonthlySpend_Previous3Mo, 2);
                        } elseif ($avgMonthlySpend_Recent3Mo > 0 && $avgMonthlySpend_Previous3Mo == 0 && $monthlyAvgChangePercent !== null) {
                            $prefixIcon = '<i class="fas fa-caret-up"></i> ';
                             $compText1_3 = $prefixIcon . '100%+ vs. prior 3-month average: ₱0.00';
                        } elseif (($r1['avgMonthlySpend_Previous3Mo_Label'] ?? '') === "No data for prior 3mo period") {
                            $compText1_3 = '<span class="no-data">No prior 3-month data to compare.</span>';
                        }
                        echo $compText1_3;
                        ?>
                    </span>
                </div>
            </div>

            <!-- Card 1.4: Top Affected Category Average Price Increase -->
            <?php
            $avgIncreaseCat   = $r1['top_increase_group']['avg_increase'] ?? 0;
            $itemCountCat     = $r1['top_increase_group']['item_count'] ?? 0;
            $groupNameCat     = $r1['top_increase_group']['group_name'] ?? null;
            $topIncreasePeriodLabel = $r1['top_increase_group']['period_label'] ?? 'Details N/A';
            $metricClass1_4   = ($avgIncreaseCat > 0 && $groupNameCat !== null) ? 'metric-increase' : 'metric-neutral';
            $footerClass1_4   = 'neutral';
            if ($avgIncreaseCat > 0 && $groupNameCat !== null && $itemCountCat > 0) {
                $footerClass1_4 = 'increase';
            } elseif ($groupNameCat !== null && ($avgIncreaseCat <= 0 || $itemCountCat == 0)) {
                $footerClass1_4 = 'decrease';
            }
            ?>
            <div class="dashboard-card card-inflation-check">
                <div class="card-header">
                    <span class="card-icon"><i data-feather="tag"></i></span>
                    <span class="card-metric <?= $metricClass1_4 ?>">
                        <?php if ($avgIncreaseCat > 0 && $groupNameCat !== null && $itemCountCat > 0): ?>
                            +₱<?= number_format($avgIncreaseCat, 2) ?> <span class="metric-unit">/item</span>
                        <?php else: ?>
                            <span class="no-data">N/A</span>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="card-body">
                    <h3 class="card-title">
                        Average Price Increase of<br>Most Affected Category
                        <?php if ($topIncreasePeriodLabel !== "N/A" && $topIncreasePeriodLabel !== "Details N/A" && $topIncreasePeriodLabel !== "Recent period"): ?>
                        <span class="info-tooltip" data-tooltip="Analysis period: <?= htmlspecialchars($topIncreasePeriodLabel) ?>">
                            <svg class="info-svg-icon" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="10" cy="10" r="9" stroke="#888" stroke-width="2" fill="none"/><rect x="9" y="8" width="2" height="6" rx="1" fill="#888"/><rect x="9" y="5" width="2" height="2" rx="1" fill="#888"/></svg>
                        </span>
                        <?php endif; ?>
                    </h3>
                </div>
                <div class="card-footer">
                    <span class="comparison-text <?= $footerClass1_4 ?>">
                        <?php
                        if ($groupNameCat !== null && $itemCountCat > 0 && $avgIncreaseCat > 0):
                            echo htmlspecialchars($itemCountCat) . " item" . ($itemCountCat == 1 ? '' : 's') . " in \"" . htmlspecialchars($groupNameCat) . "\" increased.";
                        elseif ($groupNameCat !== null):
                            echo "<span class=\"no-data\">No avg. price increases in \"" . htmlspecialchars($groupNameCat) . "\".</span>";
                        else:
                            echo "<span class=\"no-data\">No significant category group price increases found.</span>";
                        endif;
                        ?>
                    </span>
                </div>
            </div>

            <?php // --- REST OF THE DASHBOARD (ROW 2 & 3 CARDS, etc.) --- ?>
            <?php // Ensure this part is complete as per your working version. For brevity, it's omitted here. ?>
            <!-- ======================= ROW 2: Charts and Trackers ======================= -->
            <div class="dashboard-card card-chart">
                <div class="card-header-large" style="position:relative;">
                    <div class="card-title-large-area">
                        <h3 class="card-title-large">Spending by Category Group</h3>
                        <span class="card-subtitle-large">(Latest Month vs Previous Month)</span>
                    </div>
                </div>
                <div class="card-body chart-body-area">
                    <canvas id="categoryBarChart"></canvas>
                </div>
            </div>
            <div class="dashboard-card card-price-tracker new-tracker-layout">
                <div class="card-header" style="position:relative;">
                    <div style="display: flex; flex-direction: column;">
                    <h3 class="card-title tracker-title">Price Increase Tracker</h3>
                        <?php if (!empty($notifications['price_increase_period_label'])): ?>
                            <span class="card-date-label" style="font-size:0.95em; color:var(--text-muted); font-weight:400; margin-top:2px;">
                                (<?= htmlspecialchars($notifications['price_increase_period_label']) ?>)
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body tracker-body">
                    <ul class="tracker-list">
                        <?php if (!empty($notifications['price_increase_items'])): ?>
                            <?php foreach ($notifications['price_increase_items'] as $item): ?>
                                <li class="tracker-list-item">
                                    <div class="notification-item-card">
                                        <span class="tracker-item-name" title="<?= htmlspecialchars($item['item_name']) ?>">
                                            <?= htmlspecialchars(truncate_text($item['item_name'], $maxItemNameLength_tracker)) ?>
                                        </span>
                                        <span class="tracker-item-details">
                                            ₱<?= number_format($item['old_price'], 2) ?> to ₱<?= number_format($item['new_price'], 2) ?>
                                            <span class="increase">(▲₱<?= number_format($item['difference'], 2) ?>)</span>
                                        </span>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li class="tracker-list-item">
                                <div class="notification-item-card no-data">
                                    No significant price increases found recently.
                                </div>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>

            <!-- ======================= ROW 3: Item & Category Group Insights ======================= -->
            <?php
            $latestPeriodLabel_r2 = htmlspecialchars($r2['latestPeriodLabel'] ?? 'N/A');
            $previousPeriodLabel_r2 = htmlspecialchars($r2['previousPeriodLabel'] ?? 'N/A');
            ?>
            <div class="dashboard-card card-most-spent">
                <div class="card-header-row3">
                    <div class="card-titles">
                        <h3 class="card-title">Most Spent-On Item</h3>
                        <span class="card-date-label">(<?= $latestPeriodLabel_r2 ?>)</span>
                    </div>
                </div>
                <div class="card-body-row3">
                    <?php if (!empty($r2['mostExpensiveLatest'])): $item = $r2['mostExpensiveLatest']; ?>
                        <p class="metric-primary" title="<?= htmlspecialchars($item['item_name'] ?? 'N/A') ?>"><?= htmlspecialchars(truncate_text($item['item_name'] ?? 'N/A', $maxItemNameLength_Row3)) ?></p>
                        <p class="metric-secondary">Total: ₱<?= number_format($item['total_spent_period'] ?? 0, 2) ?> <span class="dimmed">(<?= (int)($item['purchase_count'] ?? 0) ?> times)</span></p>
                    <?php else: ?>
                        <p class="metric-primary no-data">N/A</p><p class="metric-secondary no-data">No data</p>
                    <?php endif; ?>
                </div>
                <div class="card-footer-row3">
                    <?php if (!empty($r2['mostExpensivePrevious'])): $prevItem = $r2['mostExpensivePrevious']; ?>
                        <p title="<?= htmlspecialchars($prevItem['item_name'] ?? 'N/A') ?>">vs <?= htmlspecialchars(truncate_text($prevItem['item_name'] ?? 'N/A', $maxItemNameLength_Row3)) ?></p>
                        <p><span class="footer-icon"><i data-feather="clock"></i></span> (<?= $previousPeriodLabel_r2 ?>)</p>
                    <?php else: ?>
                        <p class="no-data">No previous data</p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="dashboard-card card-most-bought">
                <div class="card-header-row3">
                    <div class="card-titles">
                       <h3 class="card-title">Most Bought Item</h3>
                       <span class="card-date-label">(<?= $latestPeriodLabel_r2 ?>)</span>
                    </div>
                </div>
                <div class="card-body-row3">
                    <?php if (!empty($r2['mostPopularLatest'])): $item = $r2['mostPopularLatest']; ?>
                        <p class="metric-primary" title="<?= htmlspecialchars($item['item_name'] ?? 'N/A') ?>"><?= htmlspecialchars(truncate_text($item['item_name'] ?? 'N/A', $maxItemNameLength_Row3)) ?></p>
                        <p class="metric-secondary">Purchased <?= number_format((int)($item['purchase_count'] ?? 0)) ?> times</p>
                    <?php else: ?>
                        <p class="metric-primary no-data">N/A</p><p class="metric-secondary no-data">No data</p>
                    <?php endif; ?>
                </div>
                <div class="card-footer-row3">
                    <?php if (!empty($r2['mostPopularPrevious'])): $prevItem = $r2['mostPopularPrevious']; ?>
                        <p title="<?= htmlspecialchars($prevItem['item_name'] ?? 'N/A') ?>">vs <?= htmlspecialchars(truncate_text($prevItem['item_name'] ?? 'N/A', $maxItemNameLength_Row3)) ?></p>
                        <p><span class="footer-icon"><i data-feather="clock"></i></span> (<?= $previousPeriodLabel_r2 ?>)</p>
                    <?php else: ?>
                        <p class="no-data">No previous data</p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="dashboard-card card-top-grouping">
               <div class="card-header-row3">
                     <div class="card-titles">
                       <h3 class="card-title">Top Spending Group</h3>
                       <span class="card-date-label">(<?= htmlspecialchars($r1['latestWeekLabel'] ?? 'N/A') ?>)</span>
                    </div>
               </div>
               <div class="card-body-row3">
                    <?php if (!empty($r2['mostPopularGroupLatest'])): $group = $r2['mostPopularGroupLatest']; ?>
                       <p class="metric-primary" title="<?= htmlspecialchars($group['group_name'] ?? 'N/A') ?>"><?= htmlspecialchars($group['group_name'] ?? 'N/A') ?></p>
                       <p class="metric-secondary"><?= number_format($group['percentage'] ?? 0, 1) ?>% of this week's expense</p>
                   <?php else: ?>
                        <p class="metric-primary no-data">N/A</p><p class="metric-secondary no-data">No data</p>
                   <?php endif; ?>
               </div>
               <div class="card-footer-row3">
                    <?php if (!empty($r2['mostPopularGroupPrevious'])): $prevGroup = $r2['mostPopularGroupPrevious']; ?>
                       <p title="<?= htmlspecialchars($prevGroup['group_name'] ?? 'N/A') ?>">vs <?= htmlspecialchars($prevGroup['group_name'] ?? 'N/A') ?></p>
                        <p><span class="footer-icon"><i data-feather="clock"></i></span> (<?= htmlspecialchars($r1['previousWeekLabel'] ?? 'N/A') ?>)</p>
                   <?php else: ?>
                       <p class="no-data">No previous data</p>
                   <?php endif; ?>
               </div>
            </div>
            <div class="dashboard-card card-bottom-grouping">
                <div class="card-header-row3">
                    <div class="card-titles">
                       <h3 class="card-title">Lowest Spending Group</h3>
                        <span class="card-date-label">(<?= htmlspecialchars($r1['latestWeekLabel'] ?? 'N/A') ?>)</span>
                   </div>
                </div>
                <div class="card-body-row3">
                    <?php
                    $leastGroupLatest = $r2['leastPopularGroupLatest'] ?? null; $mostGroupLatest = $r2['mostPopularGroupLatest'] ?? null;
                    $showLeastLatest = $leastGroupLatest && (!$mostGroupLatest || $leastGroupLatest['group_name'] !== $mostGroupLatest['group_name']);
                    ?>
                    <?php if ($showLeastLatest): ?>
                       <p class="metric-primary" title="<?= htmlspecialchars($leastGroupLatest['group_name']) ?>"><?= htmlspecialchars($leastGroupLatest['group_name']) ?></p>
                       <p class="metric-secondary"><?= number_format($leastGroupLatest['percentage'] ?? 0, 1) ?>% of this week's expense</p>
                    <?php elseif ($leastGroupLatest): ?>
                        <p class="metric-primary no-data">--</p><p class="metric-secondary no-data">Only one group or no distinct least</p>
                    <?php else: ?>
                        <p class="metric-primary no-data">N/A</p><p class="metric-secondary no-data">No data</p>
                    <?php endif; ?>
                </div>
                <div class="card-footer-row3">
                    <?php
                    $leastGroupPrev = $r2['leastPopularGroupPrevious'] ?? null; $mostGroupPrev = $r2['mostPopularGroupPrevious'] ?? null;
                    $showLeastPrev = $leastGroupPrev && (!$mostGroupPrev || $leastGroupPrev['group_name'] !== $mostGroupPrev['group_name']);
                    ?>
                    <?php if ($showLeastPrev): ?>
                       <p title="<?= htmlspecialchars($leastGroupPrev['group_name']) ?>">vs <?= htmlspecialchars($leastGroupPrev['group_name']) ?></p>
                        <p><span class="footer-icon"><i data-feather="clock"></i></span> (<?= htmlspecialchars($r1['previousWeekLabel'] ?? 'N/A') ?>)</p>
                   <?php elseif($leastGroupPrev): ?>
                        <p class="no-data">vs --</p><p><span class="footer-icon"><i data-feather="clock"></i></span> (<?= htmlspecialchars($r1['previousWeekLabel'] ?? 'N/A') ?>)</p>
                   <?php else: ?>
                        <p class="no-data">No previous data</p>
                   <?php endif; ?>
                </div>
            </div>

        </div> <!-- /dashboard-grid -->
    <?php else: // No $lastTripDate ?>
        <div class="alert alert-info" role="alert">
            No purchase data found for <?= ($loggedInUserRole === 'admin') ? 'any user' : 'your account' ?>.
            Please <a href="add_expense_form.php">add an expense</a> to see dashboard statistics.
        </div>
    <?php endif; ?>

</main>

<?php
require_once 'footer.php'; // Includes $pageScripts
?>