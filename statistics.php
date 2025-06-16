<?php
// statistics.php

// --- THIS MUST BE AT THE VERY TOP ---
define('FRUGALFOLIO_ACCESS', true);

// --- BOOTSTRAP: Handles session start, DB connection, and defines require_login() ---
require_once 'auth_bootstrap.php';

// --- AUTHENTICATION: Ensure only logged-in users can access this page ---
require_login();
// User is logged in. $conn, $loggedInUserId, $loggedInUserRole available.

$pageTitle = "Spending Statistics - FrugalFolio"; // Set page title
$pageStylesheets = [
    '/css/table.css',
    '/css/statistics_style.css',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css'
];
$pageScripts = [
    '/js/statistics_charts.js'
];

// --- Initialize ALL data variables ---
$categoryDetails = [];
$overallTotalSpend = 0;
$topItemsRecent = [];
$priceIncreases = [];
$priceDecreases = [];
$comparisonData = [
    'latest_month_label' => null, 'previous_month_label' => null,
    'latest_month_total' => 0.00, 'previous_month_total' => 0.00,
    'latest_month_top_items' => [], 'previous_month_top_items' => [],
    'difference' => 0.00, 'percentage_change' => 0.00
];
$errorMessage = null;
$latestDate = null;

// --- Helper functions for user filtering (similar to dashboard_data_logic.php) ---
// These are local to this script to keep it self-contained for this logic.
function getUserWhereClauseStats(string $userRole, string $tableAlias = 'ge'): string {
    if ($userRole == 'admin') {
        return ""; // Admin sees all
    } else {
        $prefix = !empty($tableAlias) ? trim($tableAlias) . "." : "";
        return " WHERE " . $prefix . "user_id = ? "; // Use WHERE for standalone, AND if appended
    }
}

function getUserWhereClauseStatsAnd(string $userRole, string $tableAlias = 'ge'): string {
    if ($userRole == 'admin') {
        return "";
    } else {
        $prefix = !empty($tableAlias) ? trim($tableAlias) . "." : "";
        return " AND " . $prefix . "user_id = ? ";
    }
}

function addUserToBindParamsStats(string $userRole, int $userId, string &$types, array &$params): void {
    if ($userRole != 'admin') {
        $types .= 'i';
        $params[] = $userId;
    }
}
// --- END Helper functions ---


// --- Main Data Fetching Block ---
try {
    // --- Build base user filter parts ---
    $userWhere = getUserWhereClauseStats($loggedInUserRole, 'ge'); // For queries starting with FROM grocery_expenses
    $userWhereAnd = getUserWhereClauseStatsAnd($loggedInUserRole, 'ge'); // For queries that already have a WHERE
    $userWhereIpddAnd = getUserWhereClauseStatsAnd($loggedInUserRole, 'ipd'); // For CTE ItemPeriodData alias
    $userWhereGeRankedAnd = getUserWhereClauseStatsAnd($loggedInUserRole, 'g'); // For Price Change CTE (g alias)

    $userParamsBase = []; // Parameters for just user_id
    $userTypesBase = "";
    addUserToBindParamsStats($loggedInUserRole, $loggedInUserId, $userTypesBase, $userParamsBase);

    // --- Fetch Overall Total Spending (User-Specific) ---
    $sqlTotal = "SELECT SUM(ge.total_price) as grand_total FROM grocery_expenses ge {$userWhere}";
    $stmtTotal = $conn->prepare($sqlTotal);
    if (!$stmtTotal) throw new Exception("Prepare Overall Total failed: " . $conn->error);
    if (!empty($userTypesBase)) {
        $stmtTotal->bind_param($userTypesBase, ...$userParamsBase);
    }
    if (!$stmtTotal->execute()) throw new Exception("Execute Overall Total failed: " . $stmtTotal->error);
    $resultTotal = $stmtTotal->get_result();
    if ($resultTotal && $resultTotal->num_rows > 0) {
        $rowTotal = $resultTotal->fetch_assoc();
        $overallTotalSpend = (float)($rowTotal['grand_total'] ?? 0);
    }
    if($resultTotal) $resultTotal->free();
    $stmtTotal->close();


    // --- Fetch Detailed Category Data (User-Specific, NO DOUBLE COUNTING) ---
    // Use the same logic as the dashboard chart: assign each expense to its first category only
    $sqlCatDetails = "WITH FirstCategoryPerExpense AS (
        SELECT ge.id, MIN(c.category_id) AS category_id, MIN(c.category_name) AS category_name
        FROM grocery_expenses ge
        JOIN expense_categories ec ON ge.id = ec.grocery_expense_id
        JOIN categories c ON ec.category_id = c.category_id
        {$userWhere}
        GROUP BY ge.id
    )
    SELECT
        fce.category_id,
        fce.category_name,
        SUM(ge.total_price) as total_spent,
        COUNT(ge.id) as item_count
    FROM FirstCategoryPerExpense fce
    JOIN grocery_expenses ge ON fce.id = ge.id
    GROUP BY fce.category_id, fce.category_name
    ORDER BY total_spent DESC";
    $stmtCatDetails = $conn->prepare($sqlCatDetails);
    if (!$stmtCatDetails) throw new Exception("Prepare Category Details failed: " . $conn->error);
    if (!empty($userTypesBase)) {
        $stmtCatDetails->bind_param($userTypesBase, ...$userParamsBase);
    }
    if (!$stmtCatDetails->execute()) throw new Exception("Execute Category Details failed: " . $stmtCatDetails->error);
    $resultCatDetails = $stmtCatDetails->get_result();
    if ($resultCatDetails) {
        while ($row = $resultCatDetails->fetch_assoc()) {
            $row['total_spent'] = (float)$row['total_spent'];
            $row['item_count'] = (int)$row['item_count'];
            $row['percentage'] = ($overallTotalSpend > 0) ? ($row['total_spent'] / $overallTotalSpend) * 100 : 0;
            $row['average_item_cost'] = ($row['item_count'] > 0) ? $row['total_spent'] / $row['item_count'] : 0;
            $categoryDetails[] = $row;
        }
        $resultCatDetails->free();
    }
    $stmtCatDetails->close();


    // --- Fetch Latest Date (User-Specific, used for subsequent queries) ---
    $sqlLatestDate = "SELECT MAX(ge.purchase_date) as latest_date FROM grocery_expenses ge {$userWhere}";
    $stmtLatestD = $conn->prepare($sqlLatestDate);
    if (!$stmtLatestD) throw new Exception("Prepare Latest Date failed: " . $conn->error);
    if (!empty($userTypesBase)) {
        $stmtLatestD->bind_param($userTypesBase, ...$userParamsBase);
    }
    if (!$stmtLatestD->execute()) throw new Exception("Execute Latest Date failed: " . $stmtLatestD->error);
    $resultDate = $stmtLatestD->get_result();
    if ($resultDate && $resultDate->num_rows > 0) {
        $dateRow = $resultDate->fetch_assoc();
        $latestDate = $dateRow['latest_date'];
    }
    if ($resultDate) $resultDate->free();
    $stmtLatestD->close();


    if ($latestDate) {
        $startDate3Mo = date('Y-m-d', strtotime($latestDate . ' -3 months'));
        $endDate = $latestDate;

        // --- Fetch Top Items by SPEND (Last ~3 Months, User-Specific) ---
        $sqlTopRecent = "SELECT ge.item_name, SUM(ge.total_price) as total_spent, COUNT(*) as purchase_count
                         FROM grocery_expenses ge
                         WHERE ge.purchase_date BETWEEN ? AND ?
                         {$userWhereAnd}
                         GROUP BY ge.item_name ORDER BY total_spent DESC LIMIT 10";
        $stmtTopRecent = $conn->prepare($sqlTopRecent);
        if (!$stmtTopRecent) throw new Exception("Prepare Top Recent Items failed: " . $conn->error);
        
        $topRecentTypes = "ss" . $userTypesBase;
        $topRecentParams = array_merge([$startDate3Mo, $endDate], $userParamsBase);
        $stmtTopRecent->bind_param($topRecentTypes, ...$topRecentParams);
        
        if (!$stmtTopRecent->execute()) throw new Exception("Execute Top Recent Items failed: " . $stmtTopRecent->error);
        $resultTopRecent = $stmtTopRecent->get_result();
        if ($resultTopRecent) {
            $topItemsRecent = $resultTopRecent->fetch_all(MYSQLI_ASSOC);
            $resultTopRecent->free();
        }
        $stmtTopRecent->close();


        // --- Fetch Price Changes (Last ~3 Months, User-Specific) ---
        // The user filter needs to be applied within the CTEs where `grocery_expenses` is first accessed.
        $sqlPriceChange = "
            WITH ItemPeriodData AS (
                SELECT id, item_name, purchase_date, price_per_unit, is_weight_based, unit, user_id
                FROM grocery_expenses 
                WHERE purchase_date BETWEEN ? AND ? -- This WHERE is for the period
            ),
            UserFilteredItemData AS ( -- Filter by user after period selection
                SELECT ipd.*
                FROM ItemPeriodData ipd
                WHERE 1=1 {$userWhereIpddAnd} -- Applies user_id = ? or empty if admin
            ),
            ItemConsistencyCheck AS (
                SELECT item_name, MIN(is_weight_based) as min_is_wb, MAX(is_weight_based) as max_is_wb,
                       MIN(unit) as min_unit, MAX(unit) as max_unit, COUNT(*) as purchase_count_period
                FROM UserFilteredItemData GROUP BY item_name -- Use user-filtered data
            ),
            FilteredConsistentItems AS (
                SELECT ufid.item_name, ufid.purchase_date, ufid.price_per_unit, icc.purchase_count_period,
                       ROW_NUMBER() OVER(PARTITION BY ufid.item_name ORDER BY ufid.purchase_date ASC, ufid.id ASC) as rn_asc,
                       ROW_NUMBER() OVER(PARTITION BY ufid.item_name ORDER BY ufid.purchase_date DESC, ufid.id DESC) as rn_desc
                FROM UserFilteredItemData ufid JOIN ItemConsistencyCheck icc ON ufid.item_name = icc.item_name
                WHERE icc.purchase_count_period >= 2 AND icc.min_is_wb = icc.max_is_wb AND icc.min_unit = icc.max_unit
                      AND (icc.min_is_wb = 0 OR (icc.min_is_wb = 1 AND icc.min_unit = 'KG'))
            ),
            FirstLastPrice AS (
                SELECT item_name,
                       MAX(CASE WHEN rn_asc = 1 THEN price_per_unit END) as first_price,
                       MAX(CASE WHEN rn_asc = 1 THEN purchase_date END) as first_date,
                       MAX(CASE WHEN rn_desc = 1 THEN price_per_unit END) as last_price,
                       MAX(CASE WHEN rn_desc = 1 THEN purchase_date END) as last_date,
                       MAX(purchase_count_period) as purchase_count
                FROM FilteredConsistentItems GROUP BY item_name
            )
            SELECT item_name, first_price, first_date, last_price, last_date,
                   (last_price - first_price) as price_diff, purchase_count
            FROM FirstLastPrice
            WHERE first_price IS NOT NULL AND last_price IS NOT NULL AND first_price != last_price";

        $stmtChange = $conn->prepare($sqlPriceChange);
        if (!$stmtChange) throw new Exception("Prepare Price Change failed: " . $conn->error . " SQL: " . $sqlPriceChange);

        $priceChangeTypes = "ss" . $userTypesBase; // startDate, endDate, then potentially user_id
        $priceChangeParams = array_merge([$startDate3Mo, $endDate], $userParamsBase);
        $stmtChange->bind_param($priceChangeTypes, ...$priceChangeParams);

        if (!$stmtChange->execute()) throw new Exception("Execute Price Change failed: " . $stmtChange->error);
        $resultChange = $stmtChange->get_result();
        $changes = [];
        if ($resultChange) {
            $changes = $resultChange->fetch_all(MYSQLI_ASSOC);
            $resultChange->free();
            if (!empty($changes)) {
                foreach ($changes as &$change) {
                     if ($change['first_price'] != 0) {
                          $change['percent_change'] = (($change['last_price'] - $change['first_price']) / $change['first_price']) * 100;
                     } elseif ($change['last_price'] > 0) { $change['percent_change'] = INF; }
                     else { $change['percent_change'] = 0; }
                } unset($change);
                usort($changes, function($a, $b) { return $b['percent_change'] <=> $a['percent_change']; });
                $priceIncreases = array_slice($changes, 0, 10);
                usort($changes, function($a, $b) { return $a['percent_change'] <=> $b['percent_change']; });
                $priceDecreases = array_slice($changes, 0, 10);
            }
        }
        $stmtChange->close();


        // --- Fetch Month-over-Month Comparison Data (User-Specific) ---
        $monthsFound = [];
        $sqlMonths = "SELECT DISTINCT DATE_FORMAT(ge.purchase_date, '%Y-%m') as month_year
                      FROM grocery_expenses ge
                      {$userWhere}
                      ORDER BY month_year DESC
                      LIMIT 2";
        $stmtMonths = $conn->prepare($sqlMonths);
        if (!$stmtMonths) throw new Exception("Prepare Distinct Months failed: " . $conn->error);
        if (!empty($userTypesBase)) {
            $stmtMonths->bind_param($userTypesBase, ...$userParamsBase);
        }
        if (!$stmtMonths->execute()) throw new Exception("Execute Distinct Months failed: " . $stmtMonths->error);
        $resultMonths = $stmtMonths->get_result();
        if ($resultMonths) {
            while($row = $resultMonths->fetch_assoc()) { $monthsFound[] = $row['month_year']; }
            $resultMonths->free();
        }
        $stmtMonths->close();

        if (!empty($monthsFound)) {
            $latestMonthYear = $monthsFound[0];
            $comparisonData['latest_month_label'] = date('F Y', strtotime($latestMonthYear . '-01'));

            // User filter for the UNION query. Needs to be applied to both parts of UNION.
            $userWhereMonthUnion = "";
            $userParamsMonthUnion = []; // user_id will be bound twice if not admin
            $userTypesMonthUnion = "";
            if ($loggedInUserRole !== 'admin') {
                $userWhereMonthUnion = " AND ge.user_id = ? ";
                $userParamsMonthUnion = [$loggedInUserId, $loggedInUserId]; // For total and for items
                $userTypesMonthUnion = "ii";
            }

            $sqlFetchMonthData = "SELECT
                                    'total' as type, NULL as item_name, NULL as purchase_count,
                                    SUM(ge.total_price) as amount
                                 FROM grocery_expenses ge WHERE DATE_FORMAT(ge.purchase_date, '%Y-%m') = ? {$userWhereMonthUnion}
                                 UNION ALL
                                 (SELECT 'item' as type, ge.item_name, COUNT(*) as purchase_count, SUM(ge.total_price) as amount
                                  FROM grocery_expenses ge WHERE DATE_FORMAT(ge.purchase_date, '%Y-%m') = ? {$userWhereMonthUnion}
                                  GROUP BY ge.item_name ORDER BY amount DESC LIMIT 10)";
            $stmtMonthData = $conn->prepare($sqlFetchMonthData);
            if (!$stmtMonthData) throw new Exception("Prepare Month Data failed: " . $conn->error);

            // Fetch LATEST month
            $latestMonthParams = array_merge([$latestMonthYear], $userParamsBase); // For total sum part
            $latestMonthItemsParams = array_merge([$latestMonthYear], $userParamsBase); // For items part
            $finalLatestParams = array_merge([$latestMonthYear], $userParamsBase, [$latestMonthYear], $userParamsBase);
            $finalLatestTypes = "s" . $userTypesBase . "s" . $userTypesBase;

            $stmtMonthData->bind_param($finalLatestTypes, ...$finalLatestParams);

            if (!$stmtMonthData->execute()) throw new Exception("Execute Month Data (Latest) failed: " . $stmtMonthData->error);
            $resultLatest = $stmtMonthData->get_result();
            if ($resultLatest) {
                while($row = $resultLatest->fetch_assoc()){
                    if ($row['type'] === 'total') { $comparisonData['latest_month_total'] = (float)($row['amount'] ?? 0); }
                    else { $comparisonData['latest_month_top_items'][] = $row; }
                }
                $resultLatest->free();
            }

            if (count($monthsFound) >= 2) {
                $previousMonthYear = $monthsFound[1];
                $comparisonData['previous_month_label'] = date('F Y', strtotime($previousMonthYear . '-01'));

                $finalPreviousParams = array_merge([$previousMonthYear], $userParamsBase, [$previousMonthYear], $userParamsBase);
                $finalPreviousTypes = "s" . $userTypesBase . "s" . $userTypesBase;
                $stmtMonthData->bind_param($finalPreviousTypes, ...$finalPreviousParams);

                if (!$stmtMonthData->execute()) throw new Exception("Execute Month Data (Previous) failed: " . $stmtMonthData->error);
                $resultPrevious = $stmtMonthData->get_result();
                if ($resultPrevious) {
                    while($row = $resultPrevious->fetch_assoc()){
                         if ($row['type'] === 'total') { $comparisonData['previous_month_total'] = (float)($row['amount'] ?? 0); }
                         else { $comparisonData['previous_month_top_items'][] = $row; }
                    }
                    $resultPrevious->free();
                }
                $comparisonData['difference'] = $comparisonData['latest_month_total'] - $comparisonData['previous_month_total'];
                if ($comparisonData['previous_month_total'] > 0) {
                    $comparisonData['percentage_change'] = ($comparisonData['difference'] / $comparisonData['previous_month_total']) * 100;
                }
            } else {
                $comparisonData['previous_month_label'] = "No Previous Month Data";
            }
            $stmtMonthData->close();
        }
    } // END if ($latestDate)

} catch (Exception $e) {
    error_log("Error fetching statistics data for user {$loggedInUserId}: " . $e->getMessage());
    $errorMessage = "Could not retrieve all statistics data. Details: " . htmlspecialchars($e->getMessage());
    $categoryDetails = []; $overallTotalSpend = 0; $topItemsRecent = [];
    $priceIncreases = []; $priceDecreases = []; $comparisonData = null;
} finally {
    // Connection is closed by footer.php or at the end of this script if it were the final one.
    // Since this script includes header/footer, they will handle connection closing if $conn is passed.
    // However, since this script is the top-level data fetcher before presentation,
    // it's better to close it here explicitly.
    if (isset($conn) && $conn instanceof mysqli) { $conn->close(); }
}

require_once 'header.php';
require_once 'statistics_content.php'; // This will use the $variables populated above
require_once 'footer.php';
?>