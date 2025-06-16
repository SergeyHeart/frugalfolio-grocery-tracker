<?php
// get_weekly_spending.php
// Fetches total spending per week for the last ~12 weeks (user-specific).

// --- THIS MUST BE AT THE VERY TOP ---
define('FRUGALFOLIO_ACCESS', true);

// --- BOOTSTRAP: Handles session start, DB connection, and defines require_login() ---
require_once 'auth_bootstrap.php';

// --- AUTHENTICATION: Ensure only logged-in users can access this endpoint ---
require_login();
// User is logged in. $conn, $loggedInUserId, $loggedInUserRole available.

header('Content-Type: application/json');

$chartData = [
    'labels' => [],
    'data' => [],
    'period_label' => 'Last 12 Weeks (from latest data)',
    'error' => null, // For error reporting
    'error_details' => null
];
$latestDate = null;
$weeksToFetch = 12;

try {
    // --- Build User-Specific WHERE Clause part (for main query and max_date query) ---
    $userWhereSqlPart = ""; // This will be like "AND ge.user_id = ?" or empty
    $userParams = [];       // Params for user_id if needed
    $userTypes = "";        // Type 'i' for user_id if needed

    if ($loggedInUserRole !== 'admin') {
        $userWhereSqlPart = " AND ge.user_id = ? "; // Note the leading AND
        $userParams[] = $loggedInUserId;
        $userTypes .= "i";
    }

    // --- 1. Find the latest purchase date (User-Specific) ---
    $sqlLatestDate = "SELECT MAX(ge.purchase_date) as latest_date FROM grocery_expenses ge WHERE 1=1 {$userWhereSqlPart}";
    // The "WHERE 1=1" is a common trick to make appending "AND ..." clauses easier.

    $stmtDate = $conn->prepare($sqlLatestDate);
    if (!$stmtDate) throw new Exception("Prepare latest date failed: " . $conn->error);

    if (!empty($userTypes)) {
        $stmtDate->bind_param($userTypes, ...$userParams);
    }

    if (!$stmtDate->execute()) throw new Exception("Execute latest date failed: " . $stmtDate->error);
    $resultDate = $stmtDate->get_result();

    if ($resultDate && $resultDate->num_rows > 0) {
        $rowDate = $resultDate->fetch_assoc();
        $latestDate = $rowDate['latest_date'];
    }
    if ($resultDate) $resultDate->free();
    $stmtDate->close();

    if (!$latestDate) {
        // No data for this user/scope, return empty chart data structure
        echo json_encode($chartData);
        if (isset($conn) && $conn instanceof mysqli) { $conn->close(); }
        exit;
    }

    $endDate = $latestDate;
    $startDate = date('Y-m-d', strtotime($endDate . ' -' . ($weeksToFetch - 1) . ' weeks Monday'));
    $chartData['period_label'] = "Approx Last {$weeksToFetch} Weeks (ending {$endDate})";

    // --- 2. Query to get total spending grouped by Year and Week (User-Specific) ---
    // Add $userWhereSqlPart to the existing WHERE clause
    $sql = "SELECT
                YEARWEEK(ge.purchase_date, 1) as year_week_sort,
                DATE_FORMAT(ge.purchase_date, '%Y-W%v') as week_label,
                SUM(ge.total_price) as total_spent
            FROM
                grocery_expenses ge
            WHERE
                ge.purchase_date BETWEEN ? AND ?
                {$userWhereSqlPart}
            GROUP BY
                year_week_sort
            ORDER BY
                year_week_sort ASC";

    $stmt = $conn->prepare($sql);
    if (!$stmt) { throw new Exception("Prepare weekly spending failed: " . $conn->error); }

    // Combine parameters: startDate, endDate, and then user_id (if applicable)
    $allParams = array_merge([$startDate, $endDate], $userParams);
    $allTypes = "ss" . $userTypes; // 's' for startDate, 's' for endDate

    $stmt->bind_param($allTypes, ...$allParams);

    if (!$stmt->execute()) { throw new Exception("Execute weekly spending failed: " . $stmt->error); }

    $result = $stmt->get_result();
    if (!$result) { throw new Exception("Get result weekly spending failed: " . $stmt->error); }

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $chartData['labels'][] = $row['week_label'];
            $chartData['data'][] = (float)$row['total_spent'];
        }
    }
    $result->free();
    $stmt->close();

} catch (Exception $e) {
    error_log("Error fetching weekly spending data for user {$loggedInUserId}: " . $e->getMessage());
    $chartData['error'] = 'Could not retrieve weekly spending data.';
    $chartData['error_details'] = $e->getMessage();
    // No exit here, echo $chartData at the end
}

// Close connection
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}

echo json_encode($chartData);
exit;
?>