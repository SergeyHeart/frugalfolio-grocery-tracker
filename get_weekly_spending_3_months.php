<?php
// get_weekly_spending_3_months.php
// Fetches weekly spending for the last ~3 months (user-specific) and adds comparison points.

// --- THIS MUST BE AT THE VERY TOP ---
define('FRUGALFOLIO_ACCESS', true);

// --- BOOTSTRAP: Handles session start, DB connection, and defines require_login() ---
require_once 'auth_bootstrap.php';

// --- AUTHENTICATION: Ensure only logged-in users can access this endpoint ---
require_login();
// User is logged in. $conn, $loggedInUserId, $loggedInUserRole available.

header('Content-Type: application/json');

$outputData = [
    'labels' => [],
    'data' => [],
    'period_label' => 'Last ~3 Months (from latest data)',
    'start_date' => null,
    'end_date' => null,
    'latest_week_value' => null,
    'previous_week_value' => null,
    'change_vs_previous_percent' => null,
    'period_high_value' => null,
    'period_high_label' => null,
    'period_low_value' => null,
    'period_low_label' => null,
    'error' => null, // For error reporting
    'error_details' => null
];
$latestDate = null;
$monthsToFetch = 3;

try {
    // --- Build User-Specific WHERE Clause part ---
    $userWhereSqlPart = "";
    $userParams = [];
    $userTypes = "";

    if ($loggedInUserRole !== 'admin') {
        $userWhereSqlPart = " AND ge.user_id = ? ";
        $userParams[] = $loggedInUserId;
        $userTypes .= "i";
    }

    // --- 1. Find the latest purchase date (User-Specific) ---
    $sqlLatestDate = "SELECT MAX(ge.purchase_date) as latest_date FROM grocery_expenses ge WHERE 1=1 {$userWhereSqlPart}";

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
        // No data for this user/scope, return empty structure
        echo json_encode($outputData);
        if (isset($conn) && $conn instanceof mysqli) { $conn->close(); }
        exit;
    }

    $endDate = $latestDate;
    $startDate = date('Y-m-d', strtotime($endDate . " -" . $monthsToFetch . " months"));

    $outputData['start_date'] = $startDate;
    $outputData['end_date'] = $endDate;
    $outputData['period_label'] = "Last ~{$monthsToFetch} Months ({$startDate} to {$endDate})";

    // --- 2. Query weekly spending for the period (User-Specific) ---
    $sql = "SELECT
                YEARWEEK(ge.purchase_date, 1) as year_week_sort,
                DATE_FORMAT(MIN(ge.purchase_date), 'W%v') as week_label,
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
    if (!$stmt) { throw new Exception("Prepare weekly spending (3mo) failed: " . $conn->error); }

    $allParams = array_merge([$startDate, $endDate], $userParams);
    $allTypes = "ss" . $userTypes;

    $stmt->bind_param($allTypes, ...$allParams);

    if (!$stmt->execute()) { throw new Exception("Execute weekly spending (3mo) failed: " . $stmt->error); }

    $result = $stmt->get_result();
    if (!$result) { throw new Exception("Get result weekly spending (3mo) failed: " . $stmt->error); }

    $tempData = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $label = $row['week_label']; // Already formatted
            $value = (float)$row['total_spent'];
            $outputData['labels'][] = $label;
            $outputData['data'][] = $value;
            $tempData[] = ['label' => $label, 'value' => $value];
        }

        // --- 3. Calculate Insights (same logic as before, now on user-specific data) ---
        $numWeeks = count($tempData);
        if ($numWeeks > 0) {
            $latestWeek = $tempData[$numWeeks - 1];
            $outputData['latest_week_value'] = $latestWeek['value'];

            if ($numWeeks > 1) {
                $previousWeek = $tempData[$numWeeks - 2];
                $outputData['previous_week_value'] = $previousWeek['value'];
                if ($previousWeek['value'] > 0) {
                    $diff = $latestWeek['value'] - $previousWeek['value'];
                    $outputData['change_vs_previous_percent'] = ($diff / $previousWeek['value']) * 100;
                }
            }

            $high = $tempData[0];
            $low = $tempData[0];
            foreach ($tempData as $week) {
                if ($week['value'] > $high['value']) { $high = $week; }
                if ($week['value'] < $low['value']) { $low = $week; }
            }
            $outputData['period_high_value'] = $high['value'];
            $outputData['period_high_label'] = $high['label'];
            $outputData['period_low_value'] = $low['value'];
            $outputData['period_low_label'] = $low['label'];
        }
    }
    $result->free();
    $stmt->close();

} catch (Exception $e) {
    error_log("Error fetching 3-month weekly spending data for user {$loggedInUserId}: " . $e->getMessage());
    $outputData['error'] = 'Could not retrieve 3-month weekly spending data.';
    $outputData['error_details'] = $e->getMessage();
}

// Close connection
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}

echo json_encode($outputData);
exit;
?>