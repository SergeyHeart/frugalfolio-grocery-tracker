<?php
// get_monthly_spending.php
// Fetches total spending per month (user-specific) and outputs as JSON for Chart.js

// --- THIS MUST BE AT THE VERY TOP ---
define('FRUGALFOLIO_ACCESS', true);

// --- BOOTSTRAP: Handles session start, DB connection, and defines require_login() ---
require_once 'auth_bootstrap.php';

// --- AUTHENTICATION: Ensure only logged-in users can access this endpoint ---
require_login();
// If we reach here, user is logged in. $conn, $loggedInUserId, $loggedInUserRole are available.

header('Content-Type: application/json');

$chartData = [
    'labels' => [],
    'data' => [],
    'error' => null, // Add error field to the output structure
    'error_details' => null
];

try {
    // --- Build User-Specific WHERE Clause ---
    $userWhereSql = "";
    $params = [];
    $types = "";

    if ($loggedInUserRole !== 'admin') {
        $userWhereSql = " WHERE ge.user_id = ? ";
        $params[] = $loggedInUserId;
        $types .= "i";
    }
    // If admin, $userWhereSql remains empty, showing all data.

    // Query to get total spending grouped by Year and Month
    $sql = "SELECT
                DATE_FORMAT(ge.purchase_date, '%Y-%m') as month_year_sort,
                DATE_FORMAT(ge.purchase_date, '%b %Y') as month_year_label,
                SUM(ge.total_price) as total_spent
            FROM
                grocery_expenses ge
            {$userWhereSql}
            GROUP BY
                YEAR(ge.purchase_date), MONTH(ge.purchase_date)
            ORDER BY
                month_year_sort ASC";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Database prepare failed: " . $conn->error);
    }

    if (!empty($types)) {
        $stmt->bind_param($types, ...$params);
    }

    if (!$stmt->execute()) {
        throw new Exception("Database execute failed: " . $stmt->error);
    }

    $result = $stmt->get_result();
    if (!$result) {
        throw new Exception("Getting result set failed: " . $stmt->error);
    }

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $chartData['labels'][] = $row['month_year_label'];
            $chartData['data'][] = (float)$row['total_spent'];
        }
    }
    // If no rows, labels and data will be empty, which is handled by the chart script.

    $result->free();
    $stmt->close();

} catch (Exception $e) {
    error_log("Error fetching monthly spending data for user {$loggedInUserId}: " . $e->getMessage());
    $chartData['error'] = 'Could not retrieve monthly spending data.';
    $chartData['error_details'] = $e->getMessage();
    // No exit here, echo $chartData at the end
}

// Close connection (opened by auth_bootstrap.php, closed by this top-level script)
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}

echo json_encode($chartData);
exit;
?>