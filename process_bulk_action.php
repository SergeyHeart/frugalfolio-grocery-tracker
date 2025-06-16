<?php
// process_bulk_action.php

// --- THIS MUST BE AT THE VERY TOP ---
define('FRUGALFOLIO_ACCESS', true);

// --- BOOTSTRAP: Handles session start, DB connection, and defines require_login() ---
require_once 'auth_bootstrap.php';

// --- AUTHENTICATION: Ensure only logged-in users can access this ---
require_login();
// User is logged in. $conn, $loggedInUserId, $loggedInUserRole available.

// --- Get Submitted Data ---
$action = $_POST['bulk_action'] ?? null;
$selectedIdsRaw = $_POST['selected_ids'] ?? [];

// --- Input Validation ---
$errors = [];
$validIds = [];

if (empty($action) || !in_array($action, ['delete', 'edit'])) {
    $errors[] = "Invalid or no bulk action was selected.";
}

if (empty($selectedIdsRaw) || !is_array($selectedIdsRaw)) {
    $errors[] = "No expense items were selected.";
} else {
    foreach ($selectedIdsRaw as $id) {
        $validatedId = filter_var($id, FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);
        if ($validatedId !== false) {
            $validIds[] = $validatedId;
        } else {
            error_log("Invalid ID submitted in bulk action by user {$loggedInUserId}: " . htmlspecialchars($id));
        }
    }
    if (empty($validIds)) {
        $errors[] = "No valid expense items were selected for the action.";
    }
}

if (!empty($errors)) {
    $_SESSION['page_message'] = implode("<br>", $errors);
    $_SESSION['message_type'] = 'danger';
    header("Location: view_expenses.php");
    exit;
}

// --- Authorization Check & Filtering for Non-Admins ---
$authorizedIds = [];
$stmtCheckOwner = null; // Initialize statement variable

if ($loggedInUserRole === 'admin') {
    $authorizedIds = $validIds;
} else {
    if (!empty($validIds)) {
        $placeholders = implode(',', array_fill(0, count($validIds), '?'));
        $sqlCheckOwner = "SELECT id FROM grocery_expenses WHERE user_id = ? AND id IN ({$placeholders})";
        
        $stmtCheckOwner = $conn->prepare($sqlCheckOwner);
        if ($stmtCheckOwner) {
            $types = 'i' . str_repeat('i', count($validIds));
            $params = array_merge([$loggedInUserId], $validIds);
            $stmtCheckOwner->bind_param($types, ...$params);
            
            if ($stmtCheckOwner->execute()) {
                $resultOwner = $stmtCheckOwner->get_result();
                while ($row = $resultOwner->fetch_assoc()) {
                    $authorizedIds[] = $row['id'];
                }
                if ($resultOwner) $resultOwner->free();
            } else {
                error_log("Bulk Action: Execute failed for ownership check - User {$loggedInUserId}: " . $stmtCheckOwner->error);
                $errors[] = "An error occurred while verifying item ownership.";
            }
            $stmtCheckOwner->close();
            $stmtCheckOwner = null; // Nullify after close
        } else {
            error_log("Bulk Action: Prepare failed for ownership check - User {$loggedInUserId}: " . $conn->error);
            $errors[] = "An error occurred while preparing to verify item ownership.";
        }
    }
    $unauthorizedCount = count(array_diff($validIds, $authorizedIds));
    if ($unauthorizedCount > 0) {
        if (empty($authorizedIds)) {
             $errors[] = "You are not authorized to perform this action on the selected items.";
        }
    }
}
// Explicitly close stmtCheckOwner if it's still an object (e.g., prepare failed before close)
if ($stmtCheckOwner instanceof mysqli_stmt) {
    $stmtCheckOwner->close();
}


if (!empty($errors)) {
    $_SESSION['page_message'] = implode("<br>", $errors);
    $_SESSION['message_type'] = 'danger';
    header("Location: view_expenses.php");
    exit;
}

if (empty($authorizedIds)) {
    $_SESSION['page_message'] = "No items available for the selected action after authorization.";
    $_SESSION['message_type'] = 'warning';
    header("Location: view_expenses.php");
    exit;
}

// --- Perform Action (using $authorizedIds) ---

if ($action === 'delete') {
    $stmtDeleteCats = null; // Initialize here
    $stmtDeleteExp = null;  // Initialize here
    $idsCount = count($authorizedIds);
    $placeholders = implode(',', array_fill(0, $idsCount, '?'));
    $types = str_repeat('i', $idsCount);

    try {
        $conn->begin_transaction();

        $sqlDeleteCats = "DELETE FROM expense_categories WHERE grocery_expense_id IN ({$placeholders})";
        $stmtDeleteCats = $conn->prepare($sqlDeleteCats);
        if (!$stmtDeleteCats) { throw new Exception("Prepare delete categories failed: " . $conn->error); }
        $stmtDeleteCats->bind_param($types, ...$authorizedIds);
        if (!$stmtDeleteCats->execute()) { throw new Exception("Execute delete categories failed: " . $stmtDeleteCats->error); }
        $stmtDeleteCats->close();
        $stmtDeleteCats = null; // <--- FIX: Nullify after close

        $sqlDeleteExp = "DELETE FROM grocery_expenses WHERE id IN ({$placeholders})";
        $stmtDeleteExp = $conn->prepare($sqlDeleteExp);
        if (!$stmtDeleteExp) { throw new Exception("Prepare delete expenses failed: " . $conn->error); }
        $stmtDeleteExp->bind_param($types, ...$authorizedIds);
        if (!$stmtDeleteExp->execute()) { throw new Exception("Execute delete expenses failed: " . $stmtDeleteExp->error); }
        $affectedRows = $stmtDeleteExp->affected_rows;
        $stmtDeleteExp->close();
        $stmtDeleteExp = null; // <--- FIX: Nullify after close

        $conn->commit();
        $_SESSION['page_message'] = "Successfully deleted {$affectedRows} expense item(s).";
        $_SESSION['message_type'] = 'success';

    } catch (Exception $e) {
        if ($conn->inTransaction()) $conn->rollback();
        error_log("Error processing bulk delete for user {$loggedInUserId}: " . $e->getMessage());
        $_SESSION['page_message'] = "An error occurred during bulk deletion. Please try again.";
        $_SESSION['message_type'] = 'danger';
    } finally {
        // These checks are now safe
        if ($stmtDeleteCats instanceof mysqli_stmt) { $stmtDeleteCats->close(); }
        if ($stmtDeleteExp instanceof mysqli_stmt) { $stmtDeleteExp->close(); }
    }
    // Connection is closed after header redirect (or at end of script if edit action)
    if (isset($conn) && $conn instanceof mysqli) { $conn->close(); }
    header("Location: view_expenses.php");
    exit;

} elseif ($action === 'edit') {
    $_SESSION['bulk_edit_ids'] = $authorizedIds;
    $_SESSION['bulk_edit_total'] = count($authorizedIds);
    $_SESSION['initial_bulk_ids'] = $authorizedIds;

    $firstIdToEdit = array_shift($_SESSION['bulk_edit_ids']);

    // Close connection before redirecting for 'edit' action
    if (isset($conn) && $conn instanceof mysqli) { $conn->close(); }

    if ($firstIdToEdit) {
        header("Location: edit_expense_form.php?id=" . $firstIdToEdit);
    } else {
        $_SESSION['page_message'] = "No items found to start editing after authorization.";
        $_SESSION['message_type'] = 'warning';
        header("Location: view_expenses.php");
    }
    exit;
}

// Fallback
$_SESSION['page_message'] = "Unknown bulk action or no authorized items.";
$_SESSION['message_type'] = 'danger';
if (isset($conn) && $conn instanceof mysqli) { $conn->close(); }
header("Location: view_expenses.php");
exit;
?>