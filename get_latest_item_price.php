<?php
// get_latest_item_price.php

// --- THIS MUST BE AT THE VERY TOP ---
define('FRUGALFOLIO_ACCESS', true);

// --- BOOTSTRAP: Handles session start, DB connection, and defines require_login() ---
require_once 'auth_bootstrap.php';

// --- AUTHENTICATION: Ensure only logged-in users can access this endpoint ---
require_login();
// If we reach here, user is logged in. $conn is available.

header('Content-Type: application/json');

$itemName = isset($_GET['item_name']) ? trim($_GET['item_name']) : '';
$details = null; // Initialize as null

if (!empty($itemName) && isset($conn) && $conn instanceof mysqli) {
    // It's good practice to use the same case as in the database for comparisons if the collation is case-sensitive.
    // However, your original code used strtoupper for $itemNameUpper which suggests you might want case-insensitivity
    // at the application level, or your DB handles it. For consistency with original, we'll keep it.
    // If your item_name in DB is consistently uppercase, this is fine. Otherwise, consider DB functions like UPPER().
    $itemNameForQuery = $itemName; // Using the direct input, assuming DB collation handles case or names are consistent.
                                   // If item_name in DB is always uppercase, use: $itemNameForQuery = strtoupper($itemName);


    $mostRecentExpenseId = null;
    $mostRecentPurchaseDate = null;

    // 1. Get the most recent expense details, its ID, and its purchase date (globally)
    $sqlDetails = "SELECT
                       id, item_name, quantity, weight, unit, price_per_unit,
                       is_weight_based, purchase_date
                   FROM grocery_expenses
                   WHERE item_name = ?
                   ORDER BY purchase_date DESC, id DESC
                   LIMIT 1";

    $stmtDetails = $conn->prepare($sqlDetails);

    if ($stmtDetails) {
        $stmtDetails->bind_param('s', $itemNameForQuery);
        if ($stmtDetails->execute()) {
            $resultDetails = $stmtDetails->get_result();
            if ($resultDetails && $resultDetails->num_rows > 0) {
                $details = $resultDetails->fetch_assoc();
                $mostRecentExpenseId = $details['id'];
                $mostRecentPurchaseDate = $details['purchase_date'];

                $latestWeight = (float)$details['weight'];
                $details['quantity'] = (int)$details['quantity'];
                $details['price_per_unit'] = (float)$details['price_per_unit'];
                $details['is_weight_based'] = (int)$details['is_weight_based'];
                $details['category_ids'] = [];
                $details['price_comparison'] = null;

                $calculatedWeight = $latestWeight;

                if ($details['is_weight_based'] == 1 && $mostRecentPurchaseDate) {
                    $avgWeightEndDate = date('Y-m-d', strtotime($mostRecentPurchaseDate . ' -1 day'));
                    $avgWeightStartDate = date('Y-m-d', strtotime($avgWeightEndDate . ' -3 months'));

                    // Average weight query (global)
                    $sqlAvgWeight = "SELECT AVG(weight) as avg_w
                                     FROM grocery_expenses
                                     WHERE item_name = ?
                                       AND purchase_date BETWEEN ? AND ?
                                       AND is_weight_based = 1";
                    $stmtAvgWeight = $conn->prepare($sqlAvgWeight);
                    if ($stmtAvgWeight) {
                        $stmtAvgWeight->bind_param('sss', $itemNameForQuery, $avgWeightStartDate, $avgWeightEndDate);
                        if ($stmtAvgWeight->execute()) {
                            $resultAvgWeight = $stmtAvgWeight->get_result();
                            if ($resultAvgWeight && $avgWeightRow = $resultAvgWeight->fetch_assoc()) {
                                if ($avgWeightRow['avg_w'] !== null) {
                                    $calculatedWeight = (float)$avgWeightRow['avg_w'];
                                }
                            }
                            if ($resultAvgWeight) $resultAvgWeight->free();
                        } else { error_log("Get Latest Price: Execute failed for avg weight - " . $stmtAvgWeight->error); }
                        $stmtAvgWeight->close();
                    } else { error_log("Get Latest Price: Prepare failed for avg weight - " . $conn->error); }
                }
                $details['weight'] = $calculatedWeight;

                if ($mostRecentPurchaseDate) {
                    $comparisonEndDate = date('Y-m-d', strtotime($mostRecentPurchaseDate . ' -1 day'));
                    $comparisonStartDate = date('Y-m-d', strtotime($comparisonEndDate . ' -3 months'));
                    // Previous price query (global)
                    $sqlPreviousPrice = "SELECT price_per_unit, purchase_date
                                         FROM grocery_expenses
                                         WHERE item_name = ?
                                           AND purchase_date BETWEEN ? AND ?
                                           AND is_weight_based = ? AND unit = ?
                                         ORDER BY purchase_date DESC, id DESC LIMIT 1";
                    $stmtPrevPrice = $conn->prepare($sqlPreviousPrice);
                    if ($stmtPrevPrice) {
                        $stmtPrevPrice->bind_param('sssis', $itemNameForQuery, $comparisonStartDate, $comparisonEndDate, $details['is_weight_based'], $details['unit']);
                        if ($stmtPrevPrice->execute()) {
                            $resultPrevPrice = $stmtPrevPrice->get_result();
                            if ($resultPrevPrice && $prevPriceRow = $resultPrevPrice->fetch_assoc()) {
                                $oldPrice = (float)$prevPriceRow['price_per_unit'];
                                $currentPrice = (float)$details['price_per_unit'];
                                if ($currentPrice > $oldPrice) {
                                    $details['price_comparison'] = [
                                        'old_price' => $oldPrice, 'old_date' => $prevPriceRow['purchase_date'],
                                        'new_price' => $currentPrice, 'new_date' => $mostRecentPurchaseDate,
                                        'difference' => round($currentPrice - $oldPrice, 2)
                                    ];
                                }
                            }
                            if ($resultPrevPrice) $resultPrevPrice->free();
                        } else { error_log("Get Latest Price: Execute failed for previous price - " . $stmtPrevPrice->error); }
                        $stmtPrevPrice->close();
                    } else { error_log("Get Latest Price: Prepare failed for previous price - " . $conn->error); }
                }
            }
            if ($resultDetails) $resultDetails->free();
        } else { error_log("Get Latest Price: Execute failed for details - " . $stmtDetails->error); }
        $stmtDetails->close();

        if ($mostRecentExpenseId !== null) {
           // Categories query (global, linked to the globally fetched expense)
           $sqlCategories = "SELECT category_id FROM expense_categories WHERE grocery_expense_id = ?";
           $stmtCategories = $conn->prepare($sqlCategories);
           if ($stmtCategories) {
               $stmtCategories->bind_param('i', $mostRecentExpenseId);
               if ($stmtCategories->execute()) {
                   $resultCategories = $stmtCategories->get_result();
                   if ($resultCategories) {
                       while ($catRow = $resultCategories->fetch_assoc()) {
                           $details['category_ids'][] = (int)$catRow['category_id'];
                       }
                       $resultCategories->free();
                   } else { error_log("Get Latest Price: Get result failed for categories - " . $stmtCategories->error); }
               } else { error_log("Get Latest Price: Execute failed for categories - " . $stmtCategories->error); }
                $stmtCategories->close();
           } else { error_log("Get Latest Price: Prepare failed for categories - " . $conn->error); }
        }
    } else { error_log("Get Latest Price: Prepare failed for details - " . $conn->error); }

    // Close connection (opened by auth_bootstrap.php, closed by this top-level script)
    $conn->close();

} else {
    if (empty($itemName)) {
        // error_log("Get Latest Price: Item name was empty."); // Optional
    }
    if (!(isset($conn) && $conn instanceof mysqli)) {
        error_log("Get Latest Price: DB connection not available.");
    }
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
}

echo json_encode($details);
exit();
?>