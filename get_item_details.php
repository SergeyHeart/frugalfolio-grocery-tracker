<?php
// get_item_details.php

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

if (!empty($itemName) && isset($conn) && $conn instanceof mysqli) { // Ensure $conn is valid
    // 1. Get the most recent expense details for this EXACT item name (globally)
    $sqlDetails = "SELECT
                       id, quantity, weight, unit, price_per_unit,
                       is_weight_based, shop
                   FROM grocery_expenses
                   WHERE item_name = ?
                   ORDER BY purchase_date DESC, id DESC
                   LIMIT 1";

    $stmtDetails = $conn->prepare($sqlDetails);

    if ($stmtDetails) {
        $stmtDetails->bind_param('s', $itemName); // Case-sensitive match for item_name as per original
        if ($stmtDetails->execute()) {
            $resultDetails = $stmtDetails->get_result();

            if ($resultDetails && $resultDetails->num_rows > 0) {
                $details = $resultDetails->fetch_assoc();
                $mostRecentExpenseId = $details['id'];
                $details['categories'] = []; // Initialize categories array

                $resultDetails->free();

                // 2. Get the categories associated with THAT specific expense ID
                // (No user_id filter here as categories are linked to the globally fetched expense)
                $sqlCategories = "SELECT category_id
                                  FROM expense_categories
                                  WHERE grocery_expense_id = ?";
                $stmtCategories = $conn->prepare($sqlCategories);
                if ($stmtCategories) {
                    $stmtCategories->bind_param('i', $mostRecentExpenseId);
                    if ($stmtCategories->execute()) {
                        $resultCategories = $stmtCategories->get_result();
                        if ($resultCategories) {
                            while ($catRow = $resultCategories->fetch_assoc()) {
                                $details['categories'][] = (int)$catRow['category_id']; // Cast to int
                            }
                            $resultCategories->free();
                        } else {
                            error_log("Get Item Details: Categories get_result failed - " . $stmtCategories->error);
                        }
                    } else {
                        error_log("Get Item Details: Categories execute failed - " . $stmtCategories->error);
                    }
                    $stmtCategories->close();
                } else {
                    error_log("Get Item Details: Categories prepare failed - " . $conn->error);
                }
            }
            // No 'else' needed here; if no item found, $details remains null.
        } else {
            error_log("Get Item Details: Details execute failed - " . $stmtDetails->error);
        }
        $stmtDetails->close();
    } else {
        error_log("Get Item Details: Details prepare failed - " . $conn->error);
    }

    // Close connection (opened by auth_bootstrap.php, closed by this top-level script)
    $conn->close();

} else {
    // Handle cases where $itemName is empty or $conn is not available
    if (empty($itemName)) {
        // error_log("Get Item Details: Item name was empty."); // Optional log
    }
    if (!(isset($conn) && $conn instanceof mysqli)) {
        error_log("Get Item Details: DB connection not available.");
    }
    // If $conn was opened by bootstrap and we get here due to empty item name,
    // we should still close it.
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
}

// Return the details (or null if not found/error) as JSON
echo json_encode($details);
exit();
?>