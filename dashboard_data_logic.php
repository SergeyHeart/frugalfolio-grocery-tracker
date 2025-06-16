<?php
/**
 * dashboard_data_logic.php - Data Fetching Layer for the FrugalFolio Dashboard.
 *
 * This script is responsible for all database interactions and calculations
 * required to populate the dashboard. It returns a structured array of data.
 * It handles user-specific data filtering based on the logged-in user's role.
 */

if (!defined('FRUGALFOLIO_ACCESS')) {
    die('Direct access to this script is not permitted.');
}

// Ensure essential session variables and DB connection are available from auth_bootstrap.php
if (!isset($_SESSION['user_id'], $conn, $loggedInUserId, $loggedInUserRole)) {
    error_log("dashboard_data_logic.php: Essential session or DB variables not set. Bootstrap likely not run or incomplete.");
    return [
        'lastTripDate' => null, 'errorMessage' => "Application setup error. Please contact support.",
        'row1' => [], 'row2' => [],
        'notifications' => ['price_increase_count' => 0, 'price_increase_items' => []]
    ];
}

// --- Global Definitions & Helper Functions ---
$categoryGroupMapping = [
    'Fresh Produce'             => ['VEGETABLES', 'FRUIT'],
    'Meat & Seafood'            => ['MEAT', 'FISH', 'SEAFOOD'],
    'Dairy & Bakery'            => ['DAIRY', 'BREAD'],
    'Pantry Staples'            => ['CANNED GOODS', 'NOODLES-PASTA', 'RICE', 'CEREAL'],
    'Cooking Essentials'        => ['BAKING', 'OIL', 'SPICES', 'CONDIMENTS', 'SPREAD'],
    'Beverages & Others'        => ['JUNK FOOD', 'BEVERAGE', 'COFFEE'],
    'Household & Personal Care' => ['HOUSEHOLD SUPPLIES', 'LAUNDRY', 'PERSONAL CARE'],
    'Miscellaneous'             => ['MISCELLANEOUS', 'SEEDS', 'PET FOOD']
];
$defaultCategoryGroup = 'Miscellaneous';

/**
 * Assigns a category name to a predefined group.
 */
function getCategoryGroup(string $categoryName, array $mapping, string $defaultGroup): string {
    $categoryNameUpper = strtoupper(trim($categoryName));
    foreach ($mapping as $group => $categoriesInGroup) {
        if (in_array($categoryNameUpper, array_map('strtoupper', array_map('trim', $categoriesInGroup)))) {
            return $group;
        }
    }
    return $defaultGroup;
}

/**
 * Builds the user-specific part of a WHERE clause.
 */
function getUserWhereClauseSql(string $userRole, string $tableAlias = 'ge'): string {
    if ($userRole === 'admin') {
        return ""; // Admin sees all
    }
    $prefix = !empty($tableAlias) ? trim($tableAlias) . "." : "";
    return " AND " . $prefix . "user_id = ? ";
}

/**
 * Adds user_id to bind parameters if the user is not an admin.
 */
function addUserToBindParams(string $userRole, int $userId, string &$types, array &$params): void {
    if ($userRole !== 'admin') {
        $types .= 'i';
        $params[] = $userId;
    }
}

/**
 * Fetches a single aggregated statistic for a given period, with user filtering.
 * SQL should contain placeholders for start_date, end_date, and optionally {{USER_WHERE_CLAUSE}}.
 */
function fetchPeriodStat(mysqli $conn, string $sql, ?string $startDate, ?string $endDate, string $userRole, int $userId, string $baseTypes = 'ss', array $baseParams = []): ?array {
    if (!$startDate || !$endDate) return null;

    $finalSql = $sql;
    $finalTypes = $baseTypes; // For start_date, end_date
    $finalParams = array_merge([$startDate, $endDate], $baseParams);

    $userWhereSqlFragment = "";
    if ($userRole !== 'admin') {
        $userWhereSqlFragment = " AND ge.user_id = ? "; // Assuming 'ge' alias or adjust SQL
        $finalTypes .= 'i';
        $finalParams[] = $userId;
    }
    // Replace placeholder in SQL
    $finalSql = str_replace('{{USER_WHERE_CLAUSE}}', $userWhereSqlFragment, $finalSql);
    // For cases where the user filter is the *only* condition after a WHERE
    $finalSql = str_replace('{{USER_FILTER_NO_AND}}', ltrim($userWhereSqlFragment, ' AND '), $finalSql);


    $stmt = $conn->prepare($finalSql);
    if (!$stmt) {
        error_log("Prepare failed in fetchPeriodStat for SQL: " . $finalSql . " Error: " . $conn->error);
        return null;
    }
    if (!empty($finalTypes)) {
        $stmt->bind_param($finalTypes, ...$finalParams);
    }
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        $data = $result->fetch_assoc(); // Expecting a single row
        if ($result) $result->free();
        $stmt->close();
        return $data ?: null; // Return null if no row found
    } else {
        error_log("Execute failed in fetchPeriodStat: " . $stmt->error . " SQL: " . $finalSql);
        $stmt->close();
        return null;
    }
}

/**
 * Calculates total spending per category group for a given period.
 */
function getSpendingByGroup(mysqli $conn, ?string $startDate, ?string $endDate, array $mapping, string $defaultGroup, string $userRole, int $userId): array {
    $spendingByGroup = [];
    if (!$startDate || !$endDate) return [];

    $userWhereSql = getUserWhereClauseSql($userRole, 'ge'); // Will be " AND ge.user_id = ?" or empty
    $sql = "SELECT c.category_name, SUM(ge.total_price) as total_spent
            FROM grocery_expenses ge
            JOIN expense_categories ec ON ge.id = ec.grocery_expense_id
            JOIN categories c ON ec.category_id = c.category_id
            WHERE ge.purchase_date BETWEEN ? AND ? {$userWhereSql}
            GROUP BY c.category_name";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Prepare group spending failed: " . $conn->error);
        return [];
    }
    $types = 'ss'; // For start_date, end_date
    $params = [$startDate, $endDate];
    addUserToBindParams($userRole, $userId, $types, $params); // Adds user_id if needed

    $stmt->bind_param($types, ...$params);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $group = getCategoryGroup($row['category_name'], $mapping, $defaultGroup);
            $spendingByGroup[$group] = ($spendingByGroup[$group] ?? 0) + (float)$row['total_spent'];
        }
        if ($result) $result->free();
    } else {
        error_log("Execute group spending failed: " . $stmt->error);
    }
    $stmt->close();
    return $spendingByGroup;
}

// --- Initialize Main Data Structure ---
$dashboardData = [
    'lastTripDate' => null,
    'errorMessage' => null,
    'row1' => [
        'latestWeekTotal'           => 0.00, /* ... other weekly ... */
        'latestWeekLabel'           => "N/A",
        'previousWeekTotal'         => 0.00,
        'previousWeekLabel'         => "N/A",
        'weeklySpendChangePercent'  => null,
        'avgWeeklySpend'            => 0.00,
        'avgWeeklySpendPeriodLabel' => "N/A",

        // --- Monthly Average Card Data (Card 1.3) ---
        'avgMonthlySpend_Recent3Mo'         => 0.00,
        'avgMonthlySpend_Recent3Mo_Label'   => "N/A", // Still useful for concise display if needed elsewhere
        'avgMonthlySpend_Previous3Mo'       => 0.00,
        'avgMonthlySpend_Previous3Mo_Label' => "N/A", // Still useful
        'avgMonthlySpend_CombinedTooltipLabel' => "Details N/A", // <<< NEW FOR TOOLTIP
        'monthlyAvgChangePercent'           => null,

        // These might become redundant or be used for other purposes
        'mostRecentFullMonthTotal'  => null,
        'mostRecentFullMonthLabel'  => "N/A",
        'lastNFullMonthsTotals'     => [], // Could still be useful for a detailed breakdown chart

        'top_increase_group'        => ['group_name' => null, 'avg_increase' => 0.00, 'item_count' => 0, 'period_label' => "N/A"],
    ],
    'row2' => [ // Renamed from previous structure, corresponds to Row 3 on dashboard
        'latestPeriodLabel'         => "N/A", // For Most Spent/Bought items (e.g., 28 days)
        'previousPeriodLabel'       => "N/A",
        'mostExpensiveLatest'       => null, 'mostExpensivePrevious'   => null,
        'mostPopularLatest'         => null, 'mostPopularPrevious'     => null,
        'mostPopularGroupLatest'    => null, 'mostPopularGroupPrevious'=> null, // For Top/Least Group (Weekly)
        'leastPopularGroupLatest'   => null, 'leastPopularGroupPrevious' => null,
    ],
    'notifications' => ['price_increase_count' => 0, 'price_increase_items' => []]
];

// --- Main Data Processing ---
$lastTripDate = null;
$latest_week_start = null; $latest_week_end = null;
$previous_week_start = null; $previous_week_end = null;
$stmt = null; // General purpose statement variable, closed after each use.

try {
    // 1. Determine User's Last Trip Date
    $userWhereForDates = getUserWhereClauseSql($loggedInUserRole, 'ge');
    $sqlLatestDate = "SELECT MAX(ge.purchase_date) as latest_date FROM grocery_expenses ge WHERE 1=1 {$userWhereForDates}";
    $stmt = $conn->prepare($sqlLatestDate);
    if (!$stmt) throw new Exception("Prepare latest date query failed: " . $conn->error);
    $typesDate = ""; $paramsDate = [];
    addUserToBindParams($loggedInUserRole, $loggedInUserId, $typesDate, $paramsDate);
    if (!empty($typesDate)) $stmt->bind_param($typesDate, ...$paramsDate);
    if (!$stmt->execute()) throw new Exception("Execute latest date query failed: " . $stmt->error);
    $result = $stmt->get_result();
    $rowDate = $result->fetch_assoc();
    if ($result) $result->free();
    $stmt->close(); $stmt = null;

    if (!$rowDate || !$rowDate['latest_date']) {
        $dashboardData['errorMessage'] = "No purchase data found for your account.";
        // Return early as most calculations depend on this
        return $dashboardData;
    }
    $lastTripDate = $rowDate['latest_date'];
    $dashboardData['lastTripDate'] = $lastTripDate;

    // 2. Define "Latest Active Week" and "Previous Active Week"
    // These are calendar weeks (Mon-Sun) based on the $lastTripDate.
    $sqlWeekOfLastTrip = "SELECT DATE_SUB(?, INTERVAL WEEKDAY(?) DAY) AS week_start, DATE_ADD(DATE_SUB(?, INTERVAL WEEKDAY(?) DAY), INTERVAL 6 DAY) AS week_end";
    $stmt = $conn->prepare($sqlWeekOfLastTrip);
    if (!$stmt) throw new Exception("Prepare week definition failed: " . $conn->error);
    $stmt->bind_param('ssss', $lastTripDate, $lastTripDate, $lastTripDate, $lastTripDate);
    if (!$stmt->execute()) throw new Exception("Execute week definition failed: " . $stmt->error);
    $result = $stmt->get_result();
    $weekOfLastTrip = $result->fetch_assoc();
    if ($result) $result->free();
    $stmt->close(); $stmt = null;

    if ($weekOfLastTrip) {
        $latest_week_start = $weekOfLastTrip['week_start'];
        $latest_week_end   = $weekOfLastTrip['week_end'];
        $previous_week_start = date('Y-m-d', strtotime($latest_week_start . ' -7 days'));
        $previous_week_end   = date('Y-m-d', strtotime($latest_week_end . ' -7 days'));

        $dashboardData['row1']['latestWeekLabel']   = date('M d', strtotime($latest_week_start)) . " - " . date('M d', strtotime($latest_week_end));
        $dashboardData['row1']['previousWeekLabel'] = date('M d', strtotime($previous_week_start)) . " - " . date('M d', strtotime($previous_week_end));
    } else {
        throw new Exception("Could not define active week from last trip date."); // Should not happen if lastTripDate is valid
    }

    // 3. Define 28-day periods for Row 2 (Most Spent/Popular Items)
    $r2_latest_end   = $lastTripDate;
    $r2_latest_start = date('Y-m-d', strtotime($r2_latest_end . ' -27 days'));
    $r2_previous_end   = date('Y-m-d', strtotime($r2_latest_start . ' -1 day'));
    $r2_previous_start = date('Y-m-d', strtotime($r2_previous_end . ' -27 days'));
    $dashboardData['row2']['latestPeriodLabel']   = date('M d', strtotime($r2_latest_start)) . " - " . date('M d', strtotime($r2_latest_end));
    $dashboardData['row2']['previousPeriodLabel'] = date('M d', strtotime($r2_previous_start)) . " - " . date('M d', strtotime($r2_previous_end));


    // --- CALCULATE ROW 1 METRICS ---

    // 1.1: Recent Week's Total & Comparison
    $userWhere = getUserWhereClauseSql($loggedInUserRole, 'ge');
    $sqlWeeklyTotal = "SELECT SUM(ge.total_price) as total FROM grocery_expenses ge WHERE ge.purchase_date BETWEEN ? AND ? {$userWhere}";
    
    $latestWeekData = fetchPeriodStat($conn, $sqlWeeklyTotal, $latest_week_start, $latest_week_end, $loggedInUserRole, $loggedInUserId, 'ss');
    $dashboardData['row1']['latestWeekTotal'] = (float)($latestWeekData['total'] ?? 0.00);

    $previousWeekData = fetchPeriodStat($conn, $sqlWeeklyTotal, $previous_week_start, $previous_week_end, $loggedInUserRole, $loggedInUserId, 'ss');
    $dashboardData['row1']['previousWeekTotal'] = (float)($previousWeekData['total'] ?? 0.00);

    if ($dashboardData['row1']['previousWeekTotal'] > 0) {
        $diff = $dashboardData['row1']['latestWeekTotal'] - $dashboardData['row1']['previousWeekTotal'];
        $dashboardData['row1']['weeklySpendChangePercent'] = ($diff / $dashboardData['row1']['previousWeekTotal']) * 100;
    } elseif ($dashboardData['row1']['latestWeekTotal'] > 0) {
        $dashboardData['row1']['weeklySpendChangePercent'] = 100;
    } else {
        $dashboardData['row1']['weeklySpendChangePercent'] = 0;
    }

    // 1.2: Average Weekly Spending (e.g., over 12 weeks ending with latest active week)
    $avgWeeklyPeriodWeeks = 12;
    $avgWeekly_endDate   = $latest_week_end; // End with the most recent active week
    $avgWeekly_startDate = date('Y-m-d', strtotime($avgWeekly_endDate . " -{$avgWeeklyPeriodWeeks} weeks"));
    
    $userWhereForAvg = getUserWhereClauseSql($loggedInUserRole, 'ge_inner');
    $sqlAvgWeekly = "SELECT AVG(weekly_total) as average_spend
                     FROM (
                         SELECT SUM(ge_inner.total_price) as weekly_total
                         FROM grocery_expenses ge_inner
                         WHERE ge_inner.purchase_date BETWEEN ? AND ? {$userWhereForAvg}
                         GROUP BY YEARWEEK(ge_inner.purchase_date, 1)
                     ) AS weekly_sums";
    $avgWeeklyData = fetchPeriodStat($conn, $sqlAvgWeekly, $avgWeekly_startDate, $avgWeekly_endDate, $loggedInUserRole, $loggedInUserId, 'ss');
    $dashboardData['row1']['avgWeeklySpend'] = (float)($avgWeeklyData['average_spend'] ?? 0.00);
    if ($avgWeeklyData) { // Only set label if average was computed
         $dashboardData['row1']['avgWeeklySpendPeriodLabel'] = date('M d, Y', strtotime($avgWeekly_startDate)) . " - " . date('M d, Y', strtotime($avgWeekly_endDate));
    } else {
         $dashboardData['row1']['avgWeeklySpendPeriodLabel'] = "No data for period";
    }

    // --- 1.3: REVISED Average Monthly Spending (Recent 3 Months vs. Prior 3 Months) ---
    $numMonthsForBlock = 3;

    // Define end date for the "most recent 3-month block"
    // This is the end of the full calendar month PRIOR to the month of the $lastTripDate.
    $recent3Mo_endDate_obj = new DateTime($lastTripDate);
    $recent3Mo_endDate_obj->modify('last day of previous month'); // End of month prior to lastTripDate's month
    $recent3Mo_endDate = $recent3Mo_endDate_obj->format('Y-m-t');

    // Calculate start date for the recent 3-month block
    $recent3Mo_startDate_obj = new DateTime($recent3Mo_endDate);
    $recent3Mo_startDate_obj->modify('first day of this month'); // Go to start of end month
    $recent3Mo_startDate_obj->modify('-' . ($numMonthsForBlock - 1) . ' months'); // Go back (N-1) full months
    $recent3Mo_startDate = $recent3Mo_startDate_obj->format('Y-m-01');

    // Define end date for the "previous 3-month block"
    $previous3Mo_endDate_obj = new DateTime($recent3Mo_startDate);
    $previous3Mo_endDate_obj->modify('-1 day'); // End of month just before recent block starts
    $previous3Mo_endDate = $previous3Mo_endDate_obj->format('Y-m-t');

    // Calculate start date for the previous 3-month block
    $previous3Mo_startDate_obj = new DateTime($previous3Mo_endDate);
    $previous3Mo_startDate_obj->modify('first day of this month');
    $previous3Mo_startDate_obj->modify('-' . ($numMonthsForBlock - 1) . ' months');
    $previous3Mo_startDate = $previous3Mo_startDate_obj->format('Y-m-01');


    $userWhereMonthly = getUserWhereClauseSql($loggedInUserRole, 'ge');
    $sqlMonthlyBlockAvg = "SELECT AVG(monthly_sum) as average_monthly_spend
                           FROM (
                               SELECT SUM(ge.total_price) as monthly_sum
                               FROM grocery_expenses ge
                               WHERE ge.purchase_date BETWEEN ? AND ? {$userWhereMonthly}
                               GROUP BY YEAR(ge.purchase_date), MONTH(ge.purchase_date)
                           ) AS monthly_sums_for_period";

    // Calculate for "Most Recent 3-Month Block"
    $recent3MoData = fetchPeriodStat($conn, $sqlMonthlyBlockAvg, $recent3Mo_startDate, $recent3Mo_endDate, $loggedInUserRole, $loggedInUserId, 'ss');
    $dashboardData['row1']['avgMonthlySpend_Recent3Mo'] = (float)($recent3MoData['average_monthly_spend'] ?? 0.00);
    $recent3Mo_sLabel_display = null; $recent3Mo_eLabel_display = null; // For display
    if ($recent3MoData && $dashboardData['row1']['avgMonthlySpend_Recent3Mo'] > 0) {
        $recent3Mo_sLabel_display = date('M Y', strtotime($recent3Mo_startDate));
        $recent3Mo_eLabel_display = date('M Y', strtotime($recent3Mo_endDate));
        $dashboardData['row1']['avgMonthlySpend_Recent3Mo_Label'] = ($recent3Mo_sLabel_display === $recent3Mo_eLabel_display) ? $recent3Mo_sLabel_display : "{$recent3Mo_sLabel_display} - {$recent3Mo_eLabel_display}";
    } else {
        $dashboardData['row1']['avgMonthlySpend_Recent3Mo_Label'] = "No data for recent 3mo period";
    }
    
    // Calculate for "Previous 3-Month Block"
    $previous3MoData = fetchPeriodStat($conn, $sqlMonthlyBlockAvg, $previous3Mo_startDate, $previous3Mo_endDate, $loggedInUserRole, $loggedInUserId, 'ss');
    $dashboardData['row1']['avgMonthlySpend_Previous3Mo'] = (float)($previous3MoData['average_monthly_spend'] ?? 0.00);
    $previous3Mo_sLabel_display = null; $previous3Mo_eLabel_display = null; // For display
    if ($previous3MoData && $dashboardData['row1']['avgMonthlySpend_Previous3Mo'] > 0) {
        $previous3Mo_sLabel_display = date('M Y', strtotime($previous3Mo_startDate));
        $previous3Mo_eLabel_display = date('M Y', strtotime($previous3Mo_endDate));
        $dashboardData['row1']['avgMonthlySpend_Previous3Mo_Label'] = ($previous3Mo_sLabel_display === $previous3Mo_eLabel_display) ? $previous3Mo_sLabel_display : "{$previous3Mo_sLabel_display} - {$previous3Mo_eLabel_display}";
    } else {
        $dashboardData['row1']['avgMonthlySpend_Previous3Mo_Label'] = "No data for prior 3mo period";
    }

    // Create the combined tooltip label for Card 1.3
    $card1_3_tooltipLabel = "Details N/A";
    if ($recent3Mo_sLabel_display && $previous3Mo_sLabel_display) {
        $recentPeriodText = $dashboardData['row1']['avgMonthlySpend_Recent3Mo_Label']; // Use already formatted label
        $previousPeriodText = $dashboardData['row1']['avgMonthlySpend_Previous3Mo_Label']; // Use already formatted label
        $card1_3_tooltipLabel = "Recent 3-mo avg ({$recentPeriodText}) vs. Prior 3-mo avg ({$previousPeriodText})";
    } elseif ($recent3Mo_sLabel_display) {
        $recentPeriodText = $dashboardData['row1']['avgMonthlySpend_Recent3Mo_Label'];
        $card1_3_tooltipLabel = "Average based on period: {$recentPeriodText}. No prior period data for comparison.";
    }
    $dashboardData['row1']['avgMonthlySpend_CombinedTooltipLabel'] = $card1_3_tooltipLabel;

    // Calculate percentage change
    if ($dashboardData['row1']['avgMonthlySpend_Previous3Mo'] > 0) {
        $diffMonthlyAvg = $dashboardData['row1']['avgMonthlySpend_Recent3Mo'] - $dashboardData['row1']['avgMonthlySpend_Previous3Mo'];
        $dashboardData['row1']['monthlyAvgChangePercent'] = ($diffMonthlyAvg / $dashboardData['row1']['avgMonthlySpend_Previous3Mo']) * 100;
    } elseif ($dashboardData['row1']['avgMonthlySpend_Recent3Mo'] > 0) {
        $dashboardData['row1']['monthlyAvgChangePercent'] = 100;
    } else {
        $dashboardData['row1']['monthlyAvgChangePercent'] = 0;
    }


    if ($dashboardData['row1']['avgMonthlySpend_Previous3Mo'] > 0) {
        $diffMonthlyAvg = $dashboardData['row1']['avgMonthlySpend_Recent3Mo'] - $dashboardData['row1']['avgMonthlySpend_Previous3Mo'];
        $dashboardData['row1']['monthlyAvgChangePercent'] = ($diffMonthlyAvg / $dashboardData['row1']['avgMonthlySpend_Previous3Mo']) * 100;
    } elseif ($dashboardData['row1']['avgMonthlySpend_Recent3Mo'] > 0) {
        $dashboardData['row1']['monthlyAvgChangePercent'] = 100;
    } else {
        $dashboardData['row1']['monthlyAvgChangePercent'] = 0;
    }
    
    // For Card 1.3 main metric, we'll display the most recent 3-month average
    // The old 'avgMonthlySpend' can be removed or repurposed if this new avg is the primary one for display.
    // Let's assume 'avgMonthlySpend_Recent3Mo' is now the primary value for the card's main metric.
    // We'll also need to update dashboard.php to use these new keys.

    // --- Fetch most recent full month total and label (still useful for a different comparison if needed) ---
    // This could be used if Card 1.3 compared avg of recent 3mo vs. *the single most recent full month*
    // For now, the comparison is 3mo vs 3mo.
    $userWhereSqlMonthlySingle = getUserWhereClauseSql($loggedInUserRole, 'ge');
    $sqlMostRecentFullMonth = "SELECT SUM(ge.total_price) as total, DATE_FORMAT(ge.purchase_date, '%b %Y') as label
                               FROM grocery_expenses ge
                               WHERE ge.purchase_date BETWEEN ? AND ? {$userWhereSqlMonthlySingle}
                               GROUP BY YEAR(ge.purchase_date), MONTH(ge.purchase_date)
                               ORDER BY ge.purchase_date DESC LIMIT 1"; // Get the single most recent full month in range
    $mrfm_endDate = date('Y-m-t', strtotime($lastTripDate . " -1 month"));
    $mrfm_startDate = date('Y-m-01', strtotime($mrfm_endDate));
    $mostRecentFullMonthData = fetchPeriodStat($conn, $sqlMostRecentFullMonth, $mrfm_startDate, $mrfm_endDate, $loggedInUserRole, $loggedInUserId, 'ss');
    if($mostRecentFullMonthData){
        $dashboardData['row1']['mostRecentFullMonthTotal'] = (float)($mostRecentFullMonthData['total'] ?? 0.00);
        $dashboardData['row1']['mostRecentFullMonthLabel'] = $mostRecentFullMonthData['label'] ?? 'N/A';
    }


    // 1.4: Top Affected Category Average Price Increase (6 month period ending $lastTripDate)
    $topIncrease_endDate = $lastTripDate;
    $topIncrease_startDate = date('Y-m-d', strtotime($topIncrease_endDate . ' -6 months'));
    $dashboardData['row1']['top_increase_group']['period_label'] = date('M d, Y', strtotime($topIncrease_startDate)) . " - " . date('M d, Y', strtotime($topIncrease_endDate));
    
    $userWhereForRankedCTE = getUserWhereClauseSql($loggedInUserRole, 'g');
    $sqlGroupIncrease = "
        WITH RankedPurchases AS (
            SELECT g.id, g.item_name, g.purchase_date, g.price_per_unit, c.category_name
                   " . ($conn->server_version >= 80000 ? ", ROW_NUMBER() OVER(PARTITION BY g.item_name ORDER BY g.purchase_date DESC, g.id DESC) as rn" : "") . "
            FROM grocery_expenses g
            JOIN expense_categories ec ON g.id = ec.grocery_expense_id
            JOIN categories c ON ec.category_id = c.category_id
            WHERE 1=1 {$userWhereForRankedCTE} " . ($conn->server_version < 80000 ? "ORDER BY g.item_name, g.purchase_date DESC, g.id DESC" : "") . "
        ),
        PreparedRankedPurchases AS ( " . // Manual ROW_NUMBER() for MySQL < 8.0
            ($conn->server_version < 80000 ? 
            "SELECT rp.*, @rn := IF(@current_item = rp.item_name, @rn + 1, 1) AS rn, @current_item := rp.item_name
             FROM RankedPurchases rp, (SELECT @rn := 0, @current_item := '') AS vars" 
             : "SELECT * FROM RankedPurchases" ) . "
        ),
        ComparedPrices AS (
            SELECT curr.item_name, curr.category_name, curr.price_per_unit as current_price,
                   prev.price_per_unit as previous_price
            FROM PreparedRankedPurchases curr
            LEFT JOIN PreparedRankedPurchases prev ON curr.item_name = prev.item_name AND prev.rn = (curr.rn + 1)
            WHERE curr.rn = 1 AND curr.purchase_date BETWEEN ? AND ?
        )
        SELECT item_name, category_name, (current_price - previous_price) as price_difference
        FROM ComparedPrices WHERE previous_price IS NOT NULL AND current_price > previous_price";

    $stmt = $conn->prepare($sqlGroupIncrease);
    if (!$stmt) throw new Exception("Prepare Top Increase Group failed: " . $conn->error);
    $typesGI = ""; $paramsGI = [];
    addUserToBindParams($loggedInUserRole, $loggedInUserId, $typesGI, $paramsGI); // For RankedPurchases CTE
    $typesGI .= 'ss'; array_push($paramsGI, $topIncrease_startDate, $topIncrease_endDate); // For ComparedPrices CTE
    $stmt->bind_param($typesGI, ...$paramsGI);

    if (!$stmt->execute()) throw new Exception("Execute Top Increase Group failed: " . $stmt->error);
    $result = $stmt->get_result();
    $groupIncreaseAccumulator = [];
    while ($row = $result->fetch_assoc()) {
        $group = getCategoryGroup($row['category_name'], $categoryGroupMapping, $defaultCategoryGroup);
        $groupIncreaseAccumulator[$group]['total_increase'] = ($groupIncreaseAccumulator[$group]['total_increase'] ?? 0) + (float)$row['price_difference'];
        $groupIncreaseAccumulator[$group]['item_count'] = ($groupIncreaseAccumulator[$group]['item_count'] ?? 0) + 1;
    }
    if ($result) $result->free();
    $stmt->close(); $stmt = null;

    $topGroupName = null; $maxAvgIncrease = -1.0; // Compare by average increase per item in group
    foreach ($groupIncreaseAccumulator as $groupName => $data) {
        if (($data['item_count'] ?? 0) > 0) {
            $avgInc = $data['total_increase'] / $data['item_count'];
            if ($avgInc > $maxAvgIncrease) {
                $maxAvgIncrease = $avgInc;
                $topGroupName = $groupName;
            }
        }
    }
    if ($topGroupName !== null && ($groupIncreaseAccumulator[$topGroupName]['item_count'] ?? 0) > 0) {
        $topGroupData = $groupIncreaseAccumulator[$topGroupName];
        $dashboardData['row1']['top_increase_group'] = [
            'group_name'    => $topGroupName,
            'avg_increase'  => $maxAvgIncrease, // Already calculated average
            'item_count'    => $topGroupData['item_count'],
            'period_label'  => $dashboardData['row1']['top_increase_group']['period_label'] // Keep original period label
        ];
    }


    // --- CALCULATE ROW 2 (Now Row 3 on UI) METRICS ---
    // Most Spent Item (28-day periods: $r2_latest_start, $r2_latest_end, etc.)
    $sqlMostExpensive = "SELECT item_name, SUM(total_price) as total_spent_period, COUNT(id) as purchase_count
                         FROM grocery_expenses ge WHERE purchase_date BETWEEN ? AND ? {{USER_WHERE_CLAUSE}}
                         GROUP BY item_name ORDER BY total_spent_period DESC, item_name ASC LIMIT 1";
    $dashboardData['row2']['mostExpensiveLatest']   = fetchPeriodStat($conn, $sqlMostExpensive, $r2_latest_start, $r2_latest_end, $loggedInUserRole, $loggedInUserId);
    $dashboardData['row2']['mostExpensivePrevious'] = fetchPeriodStat($conn, $sqlMostExpensive, $r2_previous_start, $r2_previous_end, $loggedInUserRole, $loggedInUserId);

    // Most Popular Item (by purchase count, non-weight-based, 28-day periods)
    $sqlMostPopular = "SELECT item_name, COUNT(id) as purchase_count
                       FROM grocery_expenses ge WHERE purchase_date BETWEEN ? AND ? AND is_weight_based = 0 {{USER_WHERE_CLAUSE}}
                       GROUP BY item_name ORDER BY purchase_count DESC, item_name ASC LIMIT 1";
    $dashboardData['row2']['mostPopularLatest']   = fetchPeriodStat($conn, $sqlMostPopular, $r2_latest_start, $r2_latest_end, $loggedInUserRole, $loggedInUserId);
    $dashboardData['row2']['mostPopularPrevious'] = fetchPeriodStat($conn, $sqlMostPopular, $r2_previous_start, $r2_previous_end, $loggedInUserRole, $loggedInUserId);

    // Top/Least Spending Category Group (Weekly, using $latest_week_... and $previous_week_... dates)
    if ($latest_week_start && $latest_week_end) {
        $latestWeekSpendingByGroup = getSpendingByGroup($conn, $latest_week_start, $latest_week_end, $categoryGroupMapping, $defaultCategoryGroup, $loggedInUserRole, $loggedInUserId);
        if ($dashboardData['row1']['latestWeekTotal'] > 0 && !empty($latestWeekSpendingByGroup)) {
            uasort($latestWeekSpendingByGroup, function($a, $b) { return $b <=> $a; }); // Sort descending
            $dashboardData['row2']['mostPopularGroupLatest'] = ['group_name' => key($latestWeekSpendingByGroup), 'percentage' => (current($latestWeekSpendingByGroup) / $dashboardData['row1']['latestWeekTotal']) * 100];
            if (count($latestWeekSpendingByGroup) > 1) { // Only set least if there's more than one group
                 end($latestWeekSpendingByGroup); // Move to last element
                 $dashboardData['row2']['leastPopularGroupLatest'] = ['group_name' => key($latestWeekSpendingByGroup), 'percentage' => (current($latestWeekSpendingByGroup) / $dashboardData['row1']['latestWeekTotal']) * 100];
            }
        }
    }
    if ($previous_week_start && $previous_week_end) {
        $previousWeekSpendingByGroup = getSpendingByGroup($conn, $previous_week_start, $previous_week_end, $categoryGroupMapping, $defaultCategoryGroup, $loggedInUserRole, $loggedInUserId);
        if ($dashboardData['row1']['previousWeekTotal'] > 0 && !empty($previousWeekSpendingByGroup)) {
            uasort($previousWeekSpendingByGroup, function($a, $b) { return $b <=> $a; });
            $dashboardData['row2']['mostPopularGroupPrevious'] = ['group_name' => key($previousWeekSpendingByGroup), 'percentage' => (current($previousWeekSpendingByGroup) / $dashboardData['row1']['previousWeekTotal']) * 100];
            if (count($previousWeekSpendingByGroup) > 1) {
                end($previousWeekSpendingByGroup);
                $dashboardData['row2']['leastPopularGroupPrevious'] = ['group_name' => key($previousWeekSpendingByGroup), 'percentage' => (current($previousWeekSpendingByGroup) / $dashboardData['row1']['previousWeekTotal']) * 100];
            }
        }
    }

    // --- Price Increase Notifications (Tracker) ---
    $trackerLimit = 5; // Number of items for notification
    $trackerPeriodMonths = 3;
    $tracker_endDate = $lastTripDate;
    $tracker_startDate = date('Y-m-d', strtotime($tracker_endDate . ' -' . $trackerPeriodMonths . ' months'));
    $trackerPeriodLabel = date('M d', strtotime($tracker_startDate)) . ' - ' . date('M d', strtotime($tracker_endDate));
    $userWhereTrackerRanked = getUserWhereClauseSql($loggedInUserRole, 'ge_ranked');
    $userWhereTrackerOuter  = getUserWhereClauseSql($loggedInUserRole, 'ge_outer');
    // SQL for price tracker remains largely the same, relies on user-filtered CTEs
    $sqlTracker = "
        WITH LatestPurchasePerItem AS (
            SELECT item_name, MAX(purchase_date) as latest_purchase_date,
                   MAX(CASE WHEN rn_manual = 1 THEN price_per_unit END) as latest_price_per_unit
            FROM (
                SELECT ge_ranked.item_name, ge_ranked.purchase_date, ge_ranked.price_per_unit,
                       @rn := IF(@current_item_ranked = ge_ranked.item_name, @rn + 1, 1) AS rn_manual,
                       @current_item_ranked := ge_ranked.item_name
                FROM (SELECT * FROM grocery_expenses ORDER BY item_name, purchase_date DESC, id DESC) ge_ranked,
                     (SELECT @rn := 0, @current_item_ranked := '') AS vars_ranked
                WHERE 1=1 {$userWhereTrackerRanked}
            ) AS RankedPurchases GROUP BY item_name
        ), PriorPeriodPrice AS (
            SELECT ge_outer.item_name, MAX(ge_outer.price_per_unit) as max_prior_price
            FROM grocery_expenses ge_outer
            JOIN LatestPurchasePerItem lpi ON ge_outer.item_name = lpi.item_name
            WHERE ge_outer.purchase_date < lpi.latest_purchase_date
              AND ge_outer.purchase_date >= DATE_SUB(lpi.latest_purchase_date, INTERVAL 3 MONTH)
              {$userWhereTrackerOuter}
            GROUP BY ge_outer.item_name
        )
        SELECT lpi.item_name, lpi.latest_purchase_date, ppp.max_prior_price AS previous_period_price,
               lpi.latest_price_per_unit, (lpi.latest_price_per_unit - ppp.max_prior_price) as price_difference
        FROM LatestPurchasePerItem lpi JOIN PriorPeriodPrice ppp ON lpi.item_name = ppp.item_name
        WHERE lpi.latest_price_per_unit IS NOT NULL AND ppp.max_prior_price IS NOT NULL
          AND lpi.latest_price_per_unit > ppp.max_prior_price
          AND (lpi.latest_price_per_unit - ppp.max_prior_price) > 0.01 -- Only if difference is more than 1 cent
        ORDER BY (lpi.latest_price_per_unit - ppp.max_prior_price) DESC, lpi.latest_purchase_date DESC LIMIT ?";

    $stmt = $conn->prepare($sqlTracker);
    if ($stmt) {
        $typesTR = ""; $paramsTR = [];
        addUserToBindParams($loggedInUserRole, $loggedInUserId, $typesTR, $paramsTR); // For ge_ranked (first user_id)
        addUserToBindParams($loggedInUserRole, $loggedInUserId, $typesTR, $paramsTR); // For ge_outer (second user_id)
        $typesTR .= 'i'; $paramsTR[] = $trackerLimit; // For LIMIT
        
        $stmt->bind_param($typesTR, ...$paramsTR);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            $notificationItems = [];
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $notificationItems[] = [
                        'item_name'  => $row['item_name'],
                        'old_price'  => (float)$row['previous_period_price'],
                        'new_price'  => (float)$row['latest_price_per_unit'],
                        'difference' => (float)$row['price_difference'],
                        'latest_date'=> $row['latest_purchase_date']
                    ];
                }
                if ($result) $result->free();
            } else { error_log("Tracker Get Result failed: " . $stmt->error . " SQL: " . $sqlTracker); }
            $dashboardData['notifications']['price_increase_count'] = count($notificationItems);
            $dashboardData['notifications']['price_increase_items'] = $notificationItems;
            $dashboardData['notifications']['price_increase_period_label'] = $trackerPeriodLabel;
            $_SESSION['price_notification_count'] = $dashboardData['notifications']['price_increase_count'];
            $_SESSION['price_notifications'] = $dashboardData['notifications']['price_increase_items'];
        } else { error_log("Tracker Execute failed: " . $stmt->error . " SQL: " . $sqlTracker); }
        $stmt->close(); $stmt = null;
    } else { error_log("Tracker Prepare failed: " . $conn->error . " SQL: " . $sqlTracker); }

} catch (Exception $e) {
    error_log("Dashboard Data Logic Error (User: {$loggedInUserId}): " . $e->getMessage() . "\nTrace: " . $e->getTraceAsString());
    $dashboardData['errorMessage'] = "Could not fetch all dashboard data. Please try again later or contact support.";
    // In case of a severe error, ensure sensitive parts of $dashboardData are reset or nulled if partially filled
    // For now, the initial structure is returned with the error message.
} finally {
    // Ensure connection is closed if it's still open
    if (isset($conn) && $conn instanceof mysqli && $conn->thread_id) {
       $conn->close();
    }
}

return $dashboardData;
?>