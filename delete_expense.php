<?php
// delete_expense.php

// Define that this script is an authorized entry point
define('FRUGALFOLIO_ACCESS', true);

// Include bootstrap: handles session start, DB connection, defines require_login(), $loggedInUserId, $loggedInUserRole
require_once 'auth_bootstrap.php';

// --- AUTHENTICATION: Ensure only logged-in users can access this ---
require_login();
// $conn, $loggedInUserId, $loggedInUserRole are now available.

// --- Get and Validate Expense ID from URL ---
$expenseId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);

$message = '';
$messageType = 'danger'; // Default to error

if (!$expenseId) {
    $message = "Invalid or missing expense ID for deletion.";
} else {
    // --- AUTHORIZATION CHECK ---
    $canDelete = false;
    if ($loggedInUserRole === 'admin') {
        $canDelete = true; // Admins can delete any expense
    } else {
        // Non-admin users can only delete their own expenses.
        // Check if the expense belongs to the current user.
        $sqlCheckOwner = "SELECT id FROM grocery_expenses WHERE id = ? AND user_id = ?";
        $stmtCheckOwner = $conn->prepare($sqlCheckOwner);
        if ($stmtCheckOwner) {
            $stmtCheckOwner->bind_param('ii', $expenseId, $loggedInUserId);
            $stmtCheckOwner->execute();
            $stmtCheckOwner->store_result(); // Needed to get num_rows
            if ($stmtCheckOwner->num_rows === 1) {
                $canDelete = true;
            }
            $stmtCheckOwner->close();
        } else {
            error_log("Delete Expense: Failed to prepare ownership check - " . $conn->error);
            $message = "An error occurred while verifying expense ownership.";
        }
    }

    if (!$canDelete && empty($message)) { // If $message is already set, an error occurred during check
        $message = "You are not authorized to delete this expense item (ID {$expenseId}).";
        $messageType = 'danger'; // Or 'warning'
        error_log("User {$loggedInUserId} ({$loggedInUserRole}) attempted to delete expense {$expenseId} without authorization.");
    } elseif ($canDelete) {
        // Proceed with deletion
        $stmtDeleteCats = null;
        $stmtDeleteExp = null;

        try {
            $conn->begin_transaction();

            // 1. Delete from expense_categories first
            $sqlDeleteCats = "DELETE FROM expense_categories WHERE grocery_expense_id = ?";
            $stmtDeleteCats = $conn->prepare($sqlDeleteCats);
            if (!$stmtDeleteCats) { throw new Exception("Prepare delete categories failed: " . $conn->error); }
            $stmtDeleteCats->bind_param('i', $expenseId);
            if (!$stmtDeleteCats->execute()) { throw new Exception("Execute delete categories failed: " . $stmtDeleteCats->error); }
            // No need to check affected_rows here usually, as an expense might not have categories
            $stmtDeleteCats->close();
            $stmtDeleteCats = null;

            // 2. Delete from grocery_expenses
            $sqlDeleteExp = "DELETE FROM grocery_expenses WHERE id = ?";
            // If not admin, we technically already confirmed ownership, but for safety,
            // the WHERE clause for non-admins could also include `AND user_id = ?`.
            // However, since $canDelete is true, we trust the initial check.
            // For an admin, this deletes any ID.
            $stmtDeleteExp = $conn->prepare($sqlDeleteExp);
            if (!$stmtDeleteExp) { throw new Exception("Prepare delete expense failed: " . $conn->error); }
            $stmtDeleteExp->bind_param('i', $expenseId);
            if (!$stmtDeleteExp->execute()) { throw new Exception("Execute delete expense failed: " . $stmtDeleteExp->error); }

            $affectedRows = $stmtDeleteExp->affected_rows;
            $stmtDeleteExp->close();
            $stmtDeleteExp = null;

            $conn->commit();

            if ($affectedRows > 0) {
                $message = "Successfully deleted expense item ID {$expenseId}.";
                $messageType = 'success';
            } else {
                // This means the expense ID was valid (passed authorization if non-admin)
                // but wasn't found during the DELETE operation itself.
                $message = "Expense item ID {$expenseId} not found (it may have been deleted by another process).";
                $messageType = 'warning';
            }

        } catch (Exception $e) {
            if ($conn->inTransaction()) { // Check if a transaction was actually started
                $conn->rollback();
            }
            error_log("Error deleting expense ID {$expenseId} for user {$loggedInUserId}: " . $e->getMessage());
            $message = "An error occurred while deleting the expense.";
            $messageType = 'danger';
        } finally {
            // These are already nulled out or closed in try, but good for safety if an exception occurs before.
            if ($stmtDeleteCats instanceof mysqli_stmt) { $stmtDeleteCats->close(); }
            if ($stmtDeleteExp instanceof mysqli_stmt) { $stmtDeleteExp->close(); }
            // Connection is closed by this script as it's the entry point.
            // No, auth_bootstrap opened it. This script is an entry point. It should close it.
        }
    }
}

// --- Set Session Message & Redirect ---
$_SESSION['page_message'] = $message;
$_SESSION['message_type'] = $messageType;

// Close the DB connection before redirecting
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}

header("Location: view_expenses.php");
exit;
?>