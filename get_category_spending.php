<?php
// get_category_spending.php - REVISED for User-Specific Data & Bar Chart

// --- THIS MUST BE AT THE VERY TOP ---
define('FRUGALFOLIO_ACCESS', true);

// --- BOOTSTRAP: Handles session start, DB connection, and defines require_login() ---
require_once 'auth_bootstrap.php';

// --- AUTHENTICATION: Ensure only logged-in users can access this endpoint ---
require_login();
// If we reach here, the user is logged in. $conn, $loggedInUserId, $loggedInUserRole are available.

header('Content-Type: application/json');
// $conn is already available from auth_bootstrap.php

// --- Category Group Mapping (Same as dashboard_data_logic.php) ---
$categoryGroupMapping = [
    'Fresh Produce' => ['VEGETABLES', 'FRUIT'],
    'Meat & Seafood' => ['MEAT', 'FISH', 'SEAFOOD'],
    'Dairy & Bakery' => ['DAIRY', 'BREAD'],
    'Pantry Staples' => ['CANNED GOODS', 'NOODLES-PASTA', 'RICE', 'CEREAL'],
    'Cooking Essentials' => ['BAKING', 'OIL', 'SPICES', 'CONDIMENTS', 'SPREAD'],
    'Beverages & Others' => ['JUNK FOOD', 'BEVERAGE', 'COFFEE'],
    'Household & Personal Care' => ['HOUSEHOLD SUPPLIES', 'LAUNDRY', 'PERSONAL CARE'],
    'Miscellaneous' => ['MISCELLANEOUS', 'SEEDS', 'PET FOOD']
];
$defaultCategoryGroup = 'Miscellaneous';

function getCategoryGroup(string $categoryName, array $mapping, string $defaultGroup): string {
    $categoryNameUpper = strtoupper(trim($categoryName));
    foreach ($mapping as $group => $categoriesInGroup) {
        if (in_array($categoryNameUpper, array_map('strtoupper', array_map('trim', $categoriesInGroup)))) {
            return $group;
        }
    }
    return $defaultGroup;
}
// --- END Category Group Mapping ---

// --- Helper functions for user filtering (similar to dashboard_data_logic.php) ---
function getUserWhereClauseSql_local(string $userRole, string $tableAlias = 'ge'): string {
    if ($userRole == 'admin') {
        return ""; // Admin sees all
    } else {
        $prefix = !empty($tableAlias) ? trim($tableAlias) . "." : "";
        return " AND " . $prefix . "user_id = ? ";
    }
}

function addUserToBindParams_local(string $userRole, int $userId, string &$types, array &$params): void {
    if ($userRole != 'admin') {
        $types .= 'i';
        $params[] = $userId;
    }
}
// --- END Helper functions ---


// --- Initialize Output Structure ---
$output = [
    'all_labels' => [],
    'latest_month' => ['data' => [], 'period_label' => 'N/A', 'year' => null, 'month' => null],
    'previous_month' => ['data' => [], 'period_label' => 'N/A', 'year' => null, 'month' => null],
    'error' => null,
    'error_details' => null
];
$latestMonthGroups = [];
$previousMonthGroups = [];
$allGroupLabels = [];

try {
    // --- 1. Find Latest Month with Data (User-Specific) ---
    $userWhereMaxDate = getUserWhereClauseSql_local($loggedInUserRole, 'ge');
    $sqlLatestMonth = "SELECT MAX(ge.purchase_date) as max_date FROM grocery_expenses ge WHERE 1=1 {$userWhereMaxDate}";

    $stmtMaxDate = $conn->prepare($sqlLatestMonth);
    if (!$stmtMaxDate) throw new Exception("Prepare Max Date failed: " . $conn->error);

    $typesMaxDate = ""; $paramsMaxDate = [];
    addUserToBindParams_local($loggedInUserRole, $loggedInUserId, $typesMaxDate, $paramsMaxDate);
    if (!empty($typesMaxDate)) {
        $stmtMaxDate->bind_param($typesMaxDate, ...$paramsMaxDate);
    }

    if (!$stmtMaxDate->execute()) throw new Exception("Execute Max Date failed: " . $stmtMaxDate->error);
    $resultLatest = $stmtMaxDate->get_result();

    if (!$resultLatest || $resultLatest->num_rows === 0) {
        throw new Exception("No data found in grocery_expenses table for this user/scope.");
    }
    $latestDateRow = $resultLatest->fetch_assoc();
    $latestDate = $latestDateRow['max_date'];
    if (!$latestDate) {
         throw new Exception("Could not determine the latest purchase date for this user/scope.");
    }
    $resultLatest->free();
    $stmtMaxDate->close();

    $latestYear = date('Y', strtotime($latestDate));
    $latestMonth = date('m', strtotime($latestDate));

    $previousMonthTime = strtotime($latestYear . '-' . $latestMonth . '-01 -1 month');
    $previousYear = date('Y', $previousMonthTime);
    $previousMonth = date('m', $previousMonthTime);

    $output['latest_month']['period_label'] = date('M Y', strtotime($latestDate));
    $output['latest_month']['year'] = $latestYear;
    $output['latest_month']['month'] = $latestMonth;

    $output['previous_month']['period_label'] = date('M Y', $previousMonthTime);
    $output['previous_month']['year'] = $previousYear;
    $output['previous_month']['month'] = $previousMonth;

    // --- 3. Prepare SQL to fetch data per category FOR A GIVEN MONTH (User-Specific) ---
    $userWhereFetchMonth = getUserWhereClauseSql_local($loggedInUserRole, 'ge');
    $sqlFetchMonth = "
        WITH FirstCategoryPerExpense AS (
            SELECT ge.id, MIN(c.category_name) AS category_name
            FROM grocery_expenses ge
            JOIN expense_categories ec ON ge.id = ec.grocery_expense_id
            JOIN categories c ON ec.category_id = c.category_id
            WHERE YEAR(ge.purchase_date) = ? AND MONTH(ge.purchase_date) = ?
            {$userWhereFetchMonth}
            GROUP BY ge.id
        )
        SELECT
            fc.category_name,
            SUM(ge.total_price) as total_spent
        FROM FirstCategoryPerExpense fc
        JOIN grocery_expenses ge ON fc.id = ge.id
        GROUP BY fc.category_name
        HAVING total_spent > 0
    ";

    $stmt = $conn->prepare($sqlFetchMonth);
    if (!$stmt) { throw new Exception("Prepare statement failed: " . $conn->error); }

    // --- 4. Function to Fetch and Process a Month's Data (User-Specific) ---
    function processMonthData($stmt, $year, $month, $mapping, $defaultGroupName, &$targetGroupArray, &$allLabelsList, $userRole, $userId) {
        global $categoryGroupMapping, $defaultCategoryGroup;

        $typesProcess = 'ii'; // year, month
        $paramsProcess = [$year, $month];
        addUserToBindParams_local($userRole, $userId, $typesProcess, $paramsProcess); // Add user_id if needed

        $stmt->bind_param($typesProcess, ...$paramsProcess);
        if (!$stmt->execute()) { throw new Exception("Execute failed for {$year}-{$month}: " . $stmt->error); }
        $result = $stmt->get_result();
        if (!$result) { throw new Exception("Get result failed for {$year}-{$month}: " . $stmt->error); }

        $monthGroups = [];
        while ($row = $result->fetch_assoc()) {
            $groupName = getCategoryGroup($row['category_name'], $categoryGroupMapping, $defaultCategoryGroup);
            $monthGroups[$groupName] = ($monthGroups[$groupName] ?? 0) + (float)$row['total_spent'];
            if (!in_array($groupName, $allLabelsList)) {
                $allLabelsList[] = $groupName;
            }
        }
        $result->free();
        $targetGroupArray = $monthGroups;
    }

    // --- 5. Execute for Latest and Previous Month (Pass user context) ---
    processMonthData($stmt, $latestYear, $latestMonth, $categoryGroupMapping, $defaultCategoryGroup, $latestMonthGroups, $allGroupLabels, $loggedInUserRole, $loggedInUserId);
    processMonthData($stmt, $previousYear, $previousMonth, $categoryGroupMapping, $defaultCategoryGroup, $previousMonthGroups, $allGroupLabels, $loggedInUserRole, $loggedInUserId);

    $stmt->close();

    sort($allGroupLabels);
    $output['all_labels'] = $allGroupLabels;

    foreach ($allGroupLabels as $label) {
        $output['latest_month']['data'][] = $latestMonthGroups[$label] ?? 0.00;
        $output['previous_month']['data'][] = $previousMonthGroups[$label] ?? 0.00;
    }

} catch (Exception $e) {
    error_log("Error fetching category spending for chart (user: {$loggedInUserId}): " . $e->getMessage());
    $output['error'] = 'Could not retrieve category spending data.';
    $output['error_details'] = $e->getMessage();
}

// Close connection (it was opened by auth_bootstrap.php)
// This script is the top-level handler for this request, so it should close it.
if (isset($conn) && $conn instanceof mysqli) { $conn->close(); }

echo json_encode($output);
exit;
?>