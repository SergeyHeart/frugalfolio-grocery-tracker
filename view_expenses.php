<?php
// view_expenses.php

// --- THIS MUST BE AT THE VERY TOP ---
define('FRUGALFOLIO_ACCESS', true);

// --- BOOTSTRAP: Handles session start, DB connection, and defines require_login() ---
require_once 'auth_bootstrap.php';

// --- AUTHENTICATION: Ensure only logged-in users can access this page ---
require_login();
// User is logged in. $conn, $loggedInUserId, $loggedInUserRole available.

// --- Configuration & Page Setup ---
$pageTitle = "View Expenses - FrugalFolio";
$defaultLimit = 25;
$allowedSortColumns = ['id', 'item_name', 'quantity', 'weight', 'price_per_unit', 'total_price', 'shop', 'purchase_date', 'item_categories']; // Added item_categories
$defaultSortColumn = 'purchase_date';
$defaultSortDirection = 'DESC';

$pageStylesheets = [
    '/css/table.css',
    '/css/form.css',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css'
];

// --- Get Flash Messages & Highlight ID ---
$successMessage = $_SESSION['success_message'] ?? null; unset($_SESSION['success_message']);
$infoMessage = $_SESSION['info_message'] ?? null; unset($_SESSION['info_message']);
$pageMessage = $_SESSION['page_message'] ?? null; $pageMessageType = $_SESSION['message_type'] ?? 'info';
unset($_SESSION['page_message']); unset($_SESSION['message_type']);
$highlightIds = $_SESSION['highlight_ids'] ?? []; unset($_SESSION['highlight_ids']);
// Also handle single highlight_id for backwards compatibility from add/edit single
if (isset($_SESSION['highlight_id']) && !in_array($_SESSION['highlight_id'], $highlightIds)) {
    $highlightIds[] = $_SESSION['highlight_id'];
}
unset($_SESSION['highlight_id']);


// --- Get & Validate Input Parameters ---
$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]) ?: 1;
$limit = filter_var($_GET['limit'] ?? $defaultLimit, FILTER_VALIDATE_INT, ["options" => ["min_range" => 5, "max_range" => 100]]) ?: $defaultLimit;
$sortColumnInput = $_GET['sort'] ?? $defaultSortColumn;
$sortColumn = in_array($sortColumnInput, $allowedSortColumns) ? $sortColumnInput : $defaultSortColumn;
$sortDirectionInput = strtoupper($_GET['dir'] ?? $defaultSortDirection);
$sortDirection = ($sortDirectionInput === 'ASC') ? 'ASC' : 'DESC';
$searchTerm = isset($_GET['search_term']) ? trim($_GET['search_term']) : '';

// --- Initialize Variables ---
$errorMessage = null;
$expenses = [];
$totalRows = 0;
$totalPages = 0;

// --- SQL Construction for Filtering and Sorting ---
$baseSqlWhereParts = []; // Parts for the WHERE clause
$bindParams = [];        // Parameters for binding
$bindTypes = '';         // Parameter types string

// 1. User ID Filter (applied to all queries for non-admins)
if ($loggedInUserRole !== 'admin') {
    $baseSqlWhereParts[] = "ge.user_id = ?";
    $bindParams[] = $loggedInUserId;
    $bindTypes .= 'i';
}

// 2. Search Term Filter
if (!empty($searchTerm)) {
    $searchTermWildcard = "%" . $searchTerm . "%";
    // Search item_name, shop directly. Category search is more complex due to GROUP_CONCAT.
    // For simplicity, direct category search here might require a subquery or HAVING clause on the concatenated string.
    // We will search item_name and shop for now.
    $searchConditions = [];
    $searchColumns = ['item_name', 'shop']; // Remove 'ge.' alias to match the main query context
    foreach ($searchColumns as $col) {
        $searchConditions[] = "`" . str_replace('`','',$col) . "` LIKE ?"; // Allow aliased columns
        $bindParams[] = $searchTermWildcard;
        $bindTypes .= 's';
    }
    if (!empty($searchConditions)) {
         // If searching categories, the condition for categories needs to be applied AFTER grouping.
         // This can be done with a HAVING clause.
         // Example for searching categories (add to $searchConditions logic if implemented):
         // $searchConditions[] = "GROUP_CONCAT(c.category_name SEPARATOR ', ') LIKE ?";
         // $bindParams[] = $searchTermWildcard;
         // $bindTypes .= 's';
         // For now, sticking to item_name and shop for simplicity in WHERE
        $baseSqlWhereParts[] = "(" . implode(' OR ', $searchConditions) . ")";
    }
}

$finalWhereClause = "";
if (!empty($baseSqlWhereParts)) {
    $finalWhereClause = " WHERE " . implode(' AND ', $baseSqlWhereParts);
}


// --- Database Interaction ---
try {
    // --- Part 1: Count Total Rows (with filters) ---
    // For COUNT, we don't need GROUP_CONCAT or GROUP BY if not searching by category directly.
    // If searching categories, COUNT becomes more complex.
    // Current count is simplified assuming search is on direct columns of grocery_expenses.
    $countSql = "SELECT COUNT(DISTINCT ge.id) FROM grocery_expenses ge {$finalWhereClause}";
    // If category search was added and used HAVING, the count query would need to be:
    // $countSql = "SELECT COUNT(*) FROM (SELECT ge.id FROM grocery_expenses ge LEFT JOIN ... {$finalWhereClause} GROUP BY ge.id HAVING ...) AS subquery_count";

    $stmtCount = $conn->prepare($countSql);
    if (!$stmtCount) throw new Exception("Count prepare failed: " . $conn->error . " SQL: " . $countSql);
    if (!empty($bindTypes)) { // Only bind if there are params (user_id or search)
        $stmtCount->bind_param($bindTypes, ...$bindParams);
    }
    if (!$stmtCount->execute()) throw new Exception("Count execute failed: " . $stmtCount->error);
    $resultCount = $stmtCount->get_result();
    $totalRows = $resultCount->fetch_row()[0];
    $resultCount->free();
    $stmtCount->close();

    // --- Part 2: Fetch Paginated Data ---
    if ($totalRows > 0) {
        $totalPages = ceil($totalRows / $limit);
        $page = max(1, min($page, $totalPages)); // Ensure page is within valid range
        $offset = ($page - 1) * $limit;

        // Determine ORDER BY column (handle item_categories separately as it's an alias)
        $orderByColSql = ($sortColumn === 'item_categories') ? 'item_categories' : "`ge`.`" . $conn->real_escape_string($sortColumn) . "`";
        $orderByClause = "ORDER BY " . $orderByColSql . " " . $conn->real_escape_string($sortDirection) . ", ge.id DESC";


        $sql = "SELECT
                    ge.id, ge.item_name, ge.quantity, ge.weight, ge.unit,
                    ge.price_per_unit, ge.total_price, ge.shop, ge.purchase_date,
                    GROUP_CONCAT(DISTINCT c.category_name ORDER BY c.category_name SEPARATOR ', ') AS item_categories
                FROM
                    grocery_expenses ge
                LEFT JOIN
                    expense_categories ec ON ge.id = ec.grocery_expense_id
                LEFT JOIN
                    categories c ON ec.category_id = c.category_id
                {$finalWhereClause}
                GROUP BY
                    ge.id, ge.item_name, ge.quantity, ge.weight, ge.unit,
                    ge.price_per_unit, ge.total_price, ge.shop, ge.purchase_date 
                    -- ^ Ensure all non-aggregated selected columns are in GROUP BY
                {$orderByClause}
                LIMIT ? OFFSET ?";

        $stmt = $conn->prepare($sql);
        if (!$stmt) throw new Exception("Main data prepare failed: " . $conn->error . " SQL: " . $sql);

        // Combine types and params for main query (original filters + limit & offset)
        $mainQueryBindTypes = $bindTypes . 'ii';
        $mainQueryBindParams = array_merge($bindParams, [$limit, $offset]);

        $stmt->bind_param($mainQueryBindTypes, ...$mainQueryBindParams);
        if (!$stmt->execute()) throw new Exception("Main data execute failed: " . $stmt->error);
        $result = $stmt->get_result();
        $expenses = $result->fetch_all(MYSQLI_ASSOC);
        $result->free();
        $stmt->close();
    } else {
        $expenses = [];
        $totalPages = 0;
    }

} catch (Exception $e) {
    error_log("Database Error in view_expenses.php (User: {$loggedInUserId}): " . $e->getMessage());
    $errorMessage = "An error occurred: " . htmlspecialchars($e->getMessage());
    // $errorMessage = "An error occurred while retrieving expenses. Please try again later."; // User-friendly
    $expenses = []; $totalRows = 0; $totalPages = 0;
} finally {
    // Connection is closed by footer.php which is included by this script
    // However, for robustness, if this script structure changes, explicit close here is good.
    if (isset($conn) && $conn instanceof mysqli) {
       $conn->close();
    }
}

// --- Load Presentation ---
require_once 'header.php';
require_once 'templates/expenses_table.php'; // This file uses the variables prepared above
require_once 'footer.php';
?>