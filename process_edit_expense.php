<?php
// process_edit_expense.php

// --- THIS MUST BE AT THE VERY TOP ---
define('FRUGALFOLIO_ACCESS', true);

// --- BOOTSTRAP: Handles session start, DB connection, and defines require_login() ---
require_once 'auth_bootstrap.php';

// --- AUTHENTICATION: Ensure only logged-in users can access this ---
require_login();
// User is logged in. $conn, $loggedInUserId, $loggedInUserRole available.

// --- Get Input Data ---
$expenseId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);
$itemNameInput = trim($_POST['item_name'] ?? '');
$categoryIds = $_POST['category_ids'] ?? [];
$unitValue = trim($_POST['unit'] ?? '');
$quantityValue = filter_var($_POST['quantity'] ?? null, FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);
$weightInput = trim($_POST['weight'] ?? '');
$weightValue = filter_var($weightInput, FILTER_VALIDATE_FLOAT, ["options" => ["min_range" => 0]]);
$pricePerUnitValue = filter_var($_POST['price_per_unit'] ?? null, FILTER_VALIDATE_FLOAT); // Returns float, false, or null
$isWeightBasedValue = isset($_POST['is_weight_based']) ? 1 : 0;
$shopValue = trim($_POST['shop'] ?? '');
$purchaseDateValue = trim($_POST['purchase_date'] ?? '');

$errors = []; // Initialize as array

// 0. Validate the ID itself
if ($expenseId === false || $expenseId === null) {
    $_SESSION['page_message'] = "Invalid or missing expense ID for update.";
    $_SESSION['message_type'] = 'danger';
    header("Location: view_expenses.php");
    exit;
}

// --- VALIDATION ---
if (empty($itemNameInput)) $errors[] = "Item Name is required.";
if (strlen($itemNameInput) > 255) $errors[] = "Item Name cannot exceed 255 characters.";
if ($quantityValue === false || $quantityValue === null) $errors[] = "Quantity must be a positive whole number.";

// Price per Unit Validation
if ($pricePerUnitValue === false || $pricePerUnitValue === null || $pricePerUnitValue <= 0) {
    $errors[] = "Price / Unit must be a positive number.";
} elseif ($pricePerUnitValue > 999999.99) {
    $errors[] = "Price / Unit is too large.";
} elseif ($pricePerUnitValue > 3000) { // Only check this if it's a valid positive number
    // Check if more critical price errors already exist for this field
    $priceErrorExists = false;
    if (is_array($errors)) { // Should always be true here, but good for safety
        foreach ($errors as $err) {
            if (strpos($err, "Price / Unit must be a positive number.") !== false || strpos($err, "Price / Unit is too large.") !== false) {
                $priceErrorExists = true;
                break;
            }
        }
    }
    if (!$priceErrorExists) {
        $errors[] = "Warning: Price per Unit seems very high (over 3000). Please double-check.";
    }
}


if (empty($shopValue)) $errors[] = "Shop is required.";
if (strlen($shopValue) > 50) $errors[] = "Shop name cannot exceed 50 characters.";
if (empty($purchaseDateValue)) {
    $errors[] = "Purchase Date is required.";
} else {
    $purchaseDateObj = DateTime::createFromFormat('Y-m-d', $purchaseDateValue);
    $dateErrors = DateTime::getLastErrors();
    if ($purchaseDateObj === false || $dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0) {
        $errors[] = "Purchase Date ('" . htmlspecialchars($purchaseDateValue) . "') is not a valid date (YYYY-MM-DD).";
    } else {
        $currentDate = new DateTime(); $currentDate->setTime(0,0,0);
        if ($purchaseDateObj > $currentDate) $errors[] = "Purchase Date cannot be in the future.";
        $twoYearsAgo = (new DateTime())->modify('-2 years');
        if ($purchaseDateObj < $twoYearsAgo) $errors[] = "Warning: Purchase Date is very old. Please double-check.";
    }
}
if (empty($unitValue) || !in_array($unitValue, ['N/A', 'G', 'KG', 'ML', 'L'])) $errors[] = "A valid Unit is required.";
if (empty($categoryIds) || !is_array($categoryIds)) {
    $errors[] = "At least one Category must be selected."; $categoryIds = [];
} else {
    foreach ($categoryIds as $catId) {
        if (filter_var($catId, FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]) === false) {
            $errors[] = "Invalid category ID submitted."; break;
        }
    }
}
// Weight Validation
if ($weightValue === false && $weightInput !== '' && $weightInput !== null) { // false means not a valid float
    $errors[] = "Weight must be a valid number (e.g., 0.500).";
} elseif ($weightValue !== null && $weightValue < 0) { // null means empty or successfully filtered to 0 if input was "0"
    $errors[] = "Weight cannot be negative.";
}
if ($isWeightBasedValue == 1) {
    if ($weightValue === null || $weightValue <= 0) { // For weight-based, it must be provided and > 0
        $errors[] = "Weight must be a positive number when 'Is Weight Based' is checked.";
    }
} else { // Not weight-based
    if ($weightValue === null) $weightValue = 0.000; // If empty or invalid (but not negative), default to 0.000
}
// --- END VALIDATION ---

$isBulkEditing = isset($_SESSION['bulk_edit_total']);
if ($isBulkEditing && !isset($_SESSION['bulk_edit_made_change'])) {
    $_SESSION['bulk_edit_made_change'] = false;
}

if (!empty($errors)) {
    $_SESSION['form_errors'] = $errors;
    $_SESSION['form_data'] = $_POST;
    header("Location: edit_expense_form.php?id=" . $expenseId);
    exit;
}

$originalData = null;
$originalCategoryIds = [];

try {
    $sqlOrig = "SELECT * FROM grocery_expenses WHERE id = ?";
    $paramsOrig = [$expenseId]; $typesOrig = "i";
    if ($loggedInUserRole !== 'admin') {
        $sqlOrig .= " AND user_id = ?";
        $paramsOrig[] = $loggedInUserId; $typesOrig .= "i";
    }
    $stmtOrig = $conn->prepare($sqlOrig);
    if (!$stmtOrig) throw new Exception("Prepare original data failed: " . $conn->error);
    $stmtOrig->bind_param($typesOrig, ...$paramsOrig);
    if (!$stmtOrig->execute()) throw new Exception("Execute original data failed: " . $stmtOrig->error);
    $resultOrig = $stmtOrig->get_result();
    $originalData = $resultOrig->fetch_assoc();
    if ($resultOrig) $resultOrig->free(); // Free result before closing statement
    $stmtOrig->close(); $stmtOrig = null;

    if (!$originalData) throw new Exception("Expense item not found or you are not authorized to edit it.");

    $sqlOrigCats = "SELECT category_id FROM expense_categories WHERE grocery_expense_id = ?";
    $stmtOrigCats = $conn->prepare($sqlOrigCats);
    if (!$stmtOrigCats) throw new Exception("Prepare original categories failed: " . $conn->error);
    $stmtOrigCats->bind_param('i', $expenseId);
    if (!$stmtOrigCats->execute()) throw new Exception("Execute original categories failed: " . $stmtOrigCats->error);
    $resultOrigCats = $stmtOrigCats->get_result();
    while($row = $resultOrigCats->fetch_assoc()) {
        $originalCategoryIds[] = (int)$row['category_id'];
    }
    if ($resultOrigCats) $resultOrigCats->free();
    $stmtOrigCats->close(); $stmtOrigCats = null;

} catch (Exception $e) {
    error_log("Process Edit: Error fetching original data for expense ID {$expenseId}, user {$loggedInUserId}: " . $e->getMessage());
    if (!$originalData) { // This implies the main item wasn't found/authorized
        $_SESSION['page_message'] = "Expense item not found or you are not authorized to access it.";
        $_SESSION['message_type'] = 'danger';
        if ($isBulkEditing) {
            unset($_SESSION['bulk_edit_ids'], $_SESSION['bulk_edit_total'], $_SESSION['bulk_edit_made_change'], $_SESSION['initial_bulk_ids']);
        }
        header("Location: view_expenses.php");
    } else { // Other error, like fetching categories after item was found
        $_SESSION['form_errors'] = ["Could not retrieve original data to check for changes. Update aborted."];
        $_SESSION['form_data'] = $_POST;
        header("Location: edit_expense_form.php?id=" . $expenseId);
    }
    exit;
}

$dataChanged = false;
$itemNameUpperForCompare = strtoupper($itemNameInput);
$validWeightForComparison = ($weightValue !== null && is_numeric($weightValue));
$formattedWeightValue = $validWeightForComparison ? number_format((float)$weightValue, 3, '.', '') : null;
$formattedPriceValue = ($pricePerUnitValue !== null && is_numeric($pricePerUnitValue)) ? number_format((float)$pricePerUnitValue, 2, '.', '') : null;

if (
    strtoupper($originalData['item_name']) !== $itemNameUpperForCompare ||
    (int)$originalData['quantity'] != $quantityValue ||
    ($validWeightForComparison ? (number_format((float)$originalData['weight'], 3, '.', '') !== $formattedWeightValue) : ($originalData['weight'] !== null && $weightValue === null)) ||
    $originalData['unit'] !== $unitValue ||
    ($formattedPriceValue !== null ? (number_format((float)$originalData['price_per_unit'], 2, '.', '') !== $formattedPriceValue) : ($originalData['price_per_unit'] !== null && $pricePerUnitValue === null)) ||
    (int)$originalData['is_weight_based'] != $isWeightBasedValue ||
    $originalData['shop'] !== $shopValue ||
    $originalData['purchase_date'] !== $purchaseDateValue
) {
    $dataChanged = true;
}
sort($originalCategoryIds);
$submittedCategoryIdsInt = array_map('intval', $categoryIds);
sort($submittedCategoryIdsInt);
if ($originalCategoryIds !== $submittedCategoryIdsInt) {
    $dataChanged = true;
}

if ($isBulkEditing && $dataChanged) {
    $_SESSION['bulk_edit_made_change'] = true;
}

$updatePerformedSuccessfully = false;
$stmtUpdate = null; $stmtDeleteCats = null; $stmtInsertCat = null; // Initialize here

if ($dataChanged) {
    try {
        $conn->begin_transaction();

        $sqlUpdate = "UPDATE grocery_expenses SET
                            item_name = ?, quantity = ?, weight = ?, unit = ?,
                            price_per_unit = ?, is_weight_based = ?, shop = ?,
                            purchase_date = ?, total_price = ?
                       WHERE id = ?";
        $updateWhereParams = [$expenseId]; $updateWhereTypes = "i";
        if ($loggedInUserRole !== 'admin') {
            $sqlUpdate .= " AND user_id = ?";
            $updateWhereParams[] = $loggedInUserId; $updateWhereTypes .= "i";
        }
        $stmtUpdate = $conn->prepare($sqlUpdate);
        if (!$stmtUpdate) { throw new Exception("Prepare update failed: " . $conn->error); }
        $calculatedTotalPrice = 0;
        if ($isWeightBasedValue == 1 && $weightValue > 0 && $pricePerUnitValue > 0) {
            $calculatedTotalPrice = round(($weightValue * $pricePerUnitValue), 1);
        } else if ($isWeightBasedValue == 0 && $quantityValue > 0 && $pricePerUnitValue > 0) {
            $calculatedTotalPrice = round(($quantityValue * $pricePerUnitValue), 2);
        }
        $formattedTotalPrice = number_format($calculatedTotalPrice, 2, '.', '');
        $itemNameUpperForUpdate = strtoupper($itemNameInput);
        $bindParams = [$itemNameUpperForUpdate, $quantityValue, $weightValue, $unitValue, $pricePerUnitValue, $isWeightBasedValue, $shopValue, $purchaseDateValue, $formattedTotalPrice];
        $bindTypes = 'sidsdisss';
        $finalBindParams = array_merge($bindParams, $updateWhereParams);
        $finalBindTypes = $bindTypes . $updateWhereTypes;
        $stmtUpdate->bind_param($finalBindTypes, ...$finalBindParams);
        if (!$stmtUpdate->execute()) { throw new Exception("Execute update failed: " . $stmtUpdate->error); }
        if ($loggedInUserRole !== 'admin' && $stmtUpdate->affected_rows === 0) {
            throw new Exception("Update did not affect any rows. Item may have been modified or deleted by another process, or an authorization issue occurred.");
        }
        $stmtUpdate->close(); $stmtUpdate = null; // Null after close

        $sqlDeleteCats = "DELETE FROM expense_categories WHERE grocery_expense_id = ?";
        $stmtDeleteCats = $conn->prepare($sqlDeleteCats);
        if (!$stmtDeleteCats) throw new Exception("Prepare delete categories failed: " . $conn->error);
        $stmtDeleteCats->bind_param('i', $expenseId);
        if (!$stmtDeleteCats->execute()) throw new Exception("Execute delete categories failed: " . $stmtDeleteCats->error);
        $stmtDeleteCats->close(); $stmtDeleteCats = null; // Null after close

        if (!empty($submittedCategoryIdsInt)) {
            $sqlInsertCatLink = "INSERT INTO expense_categories (grocery_expense_id, category_id) VALUES (?, ?)";
            $stmtInsertCat = $conn->prepare($sqlInsertCatLink);
            if (!$stmtInsertCat) throw new Exception("Prepare insert category link failed: " . $conn->error);
            foreach ($submittedCategoryIdsInt as $validCatId) {
                 if ($validCatId > 0) {
                    $stmtInsertCat->bind_param('ii', $expenseId, $validCatId);
                    if (!$stmtInsertCat->execute() && $conn->errno != 1062) {
                        throw new Exception("Execute insert category link failed (CatID: {$validCatId}): " . $stmtInsertCat->error);
                    }
                 }
            }
            $stmtInsertCat->close(); $stmtInsertCat = null; // Null after close
        }
        $conn->commit();
        $updatePerformedSuccessfully = true;
    } catch (Exception $e) {
        if ($conn->inTransaction()) $conn->rollback();
        error_log("Error updating expense ID {$expenseId} for user {$loggedInUserId}: " . $e->getMessage());
        $_SESSION['form_errors'] = ["An error occurred while updating: " . htmlspecialchars($e->getMessage())];
        $_SESSION['form_data'] = $_POST;
        header("Location: edit_expense_form.php?id=" . $expenseId);
        exit;
    } finally {
        // These are now safe because variables are nulled after successful close in try
        if ($stmtUpdate instanceof mysqli_stmt) $stmtUpdate->close();
        if ($stmtDeleteCats instanceof mysqli_stmt) $stmtDeleteCats->close();
        if ($stmtInsertCat instanceof mysqli_stmt) $stmtInsertCat->close();
    }
}

if ($isBulkEditing) {
    if (isset($_SESSION['bulk_edit_ids']) && !empty($_SESSION['bulk_edit_ids'])) {
        $nextIdToEdit = array_shift($_SESSION['bulk_edit_ids']);
        unset($_SESSION['success_message'], $_SESSION['info_message']);
        header("Location: edit_expense_form.php?id=" . $nextIdToEdit);
        exit;
    } else {
        if (isset($_SESSION['bulk_edit_made_change']) && $_SESSION['bulk_edit_made_change'] === true) {
            $_SESSION['page_message'] = "Bulk edit finished. Changes saved successfully.";
            $_SESSION['message_type'] = 'success';
        } else {
            $_SESSION['page_message'] = "Bulk edit finished. No changes were made to any selected items.";
            $_SESSION['message_type'] = 'info';
        }
        if (isset($_SESSION['initial_bulk_ids'])) {
             $_SESSION['highlight_ids'] = $_SESSION['initial_bulk_ids'];
        }
        unset($_SESSION['bulk_edit_ids'], $_SESSION['bulk_edit_total'], $_SESSION['bulk_edit_made_change'], $_SESSION['initial_bulk_ids']);
        unset($_SESSION['success_message'], $_SESSION['info_message']);
        header("Location: view_expenses.php");
        exit;
    }
} else {
    if ($dataChanged && $updatePerformedSuccessfully) {
        $_SESSION['success_message'] = "Expense item '" . htmlspecialchars($itemNameInput) . "' updated successfully!";
        $_SESSION['highlight_id'] = $expenseId;
     } elseif (!$dataChanged) {
        $_SESSION['info_message'] = "No changes detected for item '" . htmlspecialchars($itemNameInput) . "'.";
        $_SESSION['highlight_id'] = $expenseId;
     }
     unset($_SESSION['page_message'], $_SESSION['message_type']);
     header("Location: view_expenses.php");
     exit;
}

if (isset($conn) && $conn instanceof mysqli) {
   $conn->close();
}
?>