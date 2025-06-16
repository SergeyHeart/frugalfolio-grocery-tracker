<?php
// process_add_expense.php

// --- THIS MUST BE AT THE VERY TOP ---
define('FRUGALFOLIO_ACCESS', true);

// --- BOOTSTRAP: Handles session start, DB connection, and defines require_login() ---
require_once 'auth_bootstrap.php';

// --- AUTHENTICATION: Ensure only logged-in users can access this ---
require_login();
// User is logged in. $conn, $loggedInUserId, $loggedInUserRole available.

// --- Get Input Data ---
$isNewItem = isset($_POST['new_item_check']) && $_POST['new_item_check'] == '1';
$itemNameInput = trim($_POST['item_name'] ?? '');
$categoryIds = $_POST['category_ids'] ?? [];
$unitValue = trim($_POST['unit'] ?? '');
$quantityValue = filter_var($_POST['quantity'] ?? null, FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);
$weightInput = trim($_POST['weight'] ?? '');
$weightValue = filter_var($weightInput, FILTER_VALIDATE_FLOAT, ["options" => ["min_range" => 0]]);
$pricePerUnitValue = filter_var($_POST['price_per_unit'] ?? null, FILTER_VALIDATE_FLOAT);
$isWeightBasedValue = isset($_POST['is_weight_based']) ? 1 : 0;
$shopValue = trim($_POST['shop'] ?? '');
$purchaseDateValue = trim($_POST['purchase_date'] ?? '');
$addAnother = isset($_POST['add_another']) && $_POST['add_another'] == '1';

$assignedUserIdInput = null;
if ($loggedInUserRole === 'admin') {
    $assignedUserIdInput = filter_input(INPUT_POST, 'assign_to_user_id', FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);
}

// --- Validation ---
$errors = [];
$finalUserIdForExpense = $loggedInUserId;

if ($loggedInUserRole === 'admin') {
    if (empty($assignedUserIdInput)) {
        $errors['assign_to_user_id'] = "Admin must select a user to assign the expense to.";
    } else {
        $sqlCheckUser = "SELECT user_id FROM users WHERE user_id = ? AND role = 'user'";
        $stmtCheckUser = $conn->prepare($sqlCheckUser);
        if ($stmtCheckUser) {
            $stmtCheckUser->bind_param('i', $assignedUserIdInput);
            $stmtCheckUser->execute();
            $resultCheckUser = $stmtCheckUser->get_result();
            if ($resultCheckUser->num_rows === 1) {
                $finalUserIdForExpense = $assignedUserIdInput;
            } else {
                $errors['assign_to_user_id'] = "Invalid user selected for assignment.";
            }
            $stmtCheckUser->close();
        } else {
            $errors['assign_to_user_id'] = "Database error validating assigned user.";
            error_log("Process Add Expense (Admin): User validation query failed - " . $conn->error);
        }
    }
}

// --- Standard Validations (abbreviated for brevity, assume they are the same as last version) ---
if (empty($itemNameInput)) $errors['item_name'] = "Item Name is required.";
if (strlen($itemNameInput) > 255) $errors['item_name_length'] = "Item Name cannot exceed 255 characters.";
if ($quantityValue === false || $quantityValue === null) $errors['quantity'] = "Quantity must be a positive whole number.";
if ($pricePerUnitValue === false || $pricePerUnitValue === null || $pricePerUnitValue <= 0) $errors['price_per_unit'] = "Price / Unit must be a positive number.";
elseif ($pricePerUnitValue > 999999.99) $errors['price_per_unit_large'] = "Price / Unit is too large.";
if ($pricePerUnitValue > 3000 && !isset($errors['price_per_unit']) && !isset($errors['price_per_unit_large'])) {
    $errors['price_per_unit_warn'] = "Warning: Price per Unit seems very high (over 3000). Please double-check.";
}
if (empty($shopValue)) $errors['shop'] = "Shop is required.";
if (strlen($shopValue) > 50) $errors['shop_length'] = "Shop name cannot exceed 50 characters.";
if (empty($purchaseDateValue)) {
    $errors['purchase_date'] = "Purchase Date is required.";
} else {
    $purchaseDateObj = DateTime::createFromFormat('Y-m-d', $purchaseDateValue);
    $dateErrors = DateTime::getLastErrors();
    if ($purchaseDateObj === false || $dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0) {
        $errors['purchase_date_invalid'] = "Purchase Date ('" . htmlspecialchars($purchaseDateValue) . "') is not a valid date (YYYY-MM-DD).";
    } else {
        $currentDate = new DateTime(); $currentDate->setTime(0,0,0);
        if ($purchaseDateObj > $currentDate) $errors['purchase_date_future'] = "Purchase Date cannot be in the future.";
        $twoYearsAgo = (new DateTime())->modify('-2 years');
        if ($purchaseDateObj < $twoYearsAgo) $errors['purchase_date_old'] = "Warning: Purchase Date is very old. Please double-check.";
    }
}
if (empty($unitValue) || !in_array($unitValue, ['N/A', 'G', 'KG', 'ML', 'L'])) $errors['unit'] = "A valid Unit is required.";
if (empty($categoryIds) || !is_array($categoryIds)) {
    $errors['category_ids'] = "At least one Category must be selected."; $categoryIds = [];
} else {
    foreach ($categoryIds as $catId) {
        if (filter_var($catId, FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]) === false) {
            $errors['category_id_invalid'] = "Invalid category ID submitted."; break;
        }
    }
}
// Weight Validation
if ($weightValue === false && $weightInput !== '' && $weightInput !== null) {
    $errors['weight_invalid'] = "Weight must be a valid number (e.g., 0.500).";
} elseif ($weightValue !== null && $weightValue < 0) {
    $errors['weight_negative'] = "Weight cannot be negative.";
}
if ($isWeightBasedValue == 1) {
    if ($weightValue === null || $weightValue <= 0) {
        $errors['weight_required_positive'] = "Weight must be a positive number when 'Is Weight Based' is checked.";
    }
} else {
    if ($weightValue === null) $weightValue = 0.000;
}

// New validation rules
if ($weightValue > 0 && (empty($unitValue) || strtolower($unitValue) === 'n/a')) {
    $errors[] = "If weight is greater than 0, a valid unit must be provided.";
}
if (strtolower($unitValue) !== 'n/a' && ($quantityValue === 0 || $quantityValue === '0' || $quantityValue === null)) {
    $errors[] = "If unit is not 'N/A', quantity must be greater than 0.";
}
if ($quantityValue === 0 || $quantityValue === '0' || $quantityValue === null) {
    $errors[] = "Quantity must be greater than 0.";
}

// Database Existence Check for Item Name
if (!isset($errors['item_name']) && !empty($itemNameInput)) {
    $itemNameUpper = strtoupper($itemNameInput);
    $sqlCheck = "SELECT 1 FROM grocery_expenses WHERE item_name = ? LIMIT 1";
    $stmtCheck = $conn->prepare($sqlCheck);
    $itemCheckError = false;
    if ($stmtCheck) {
        $stmtCheck->bind_param('s', $itemNameUpper);
        if ($stmtCheck->execute()) {
            $resultCheck = $stmtCheck->get_result();
            if ($resultCheck) {
                $itemExists = ($resultCheck->num_rows > 0);
                $resultCheck->free();
                if (!$isNewItem && !$itemExists) {
                    $errors['item_name_not_found'] = "Item '" . htmlspecialchars($itemNameInput) . "' not found. Check 'New Item?' or correct the name.";
                } elseif ($isNewItem && $itemExists) {
                    $errors['item_name_exists'] = "Item '" . htmlspecialchars($itemNameInput) . "' already exists. Uncheck 'New Item?' or enter a unique name.";
                }
            } else { $itemCheckError = true; }
        } else { $itemCheckError = true; }
        $stmtCheck->close();
    } else { $itemCheckError = true; }
    if ($itemCheckError) {
        $errors['item_check_db'] = "Database error checking item existence. Please try again.";
        error_log("Process Add Expense: Item existence check failed - " . $conn->error);
    }
}
// --- END Standard Validations ---

// --- Store data for "Add Another" and potential form repopulation ---
$_SESSION['last_purchase_date_used'] = $purchaseDateValue;
// You might want to remember shop and unit too for "Add Another"
// $_SESSION['last_shop_used'] = $shopValue;
// $_SESSION['last_unit_used'] = $unitValue;

if ($addAnother) {
    $_SESSION['add_another_checked'] = true;
    // --- NEW: Store assigned user ID if admin and "Add Another" ---
    if ($loggedInUserRole === 'admin' && !empty($finalUserIdForExpense) && empty($errors['assign_to_user_id']) ) { // Ensure no error with assigned user
        $_SESSION['last_assigned_user_id_used'] = $finalUserIdForExpense;
    }
    // --- END: Store assigned user ID ---
} else {
    unset($_SESSION['add_another_checked']);
    unset($_SESSION['last_assigned_user_id_used']); // Clear if not adding another
    // unset($_SESSION['last_shop_used']);
    // unset($_SESSION['last_unit_used']);
}

if (!empty($errors)) {
    $_SESSION['form_errors'] = $errors;
    $_SESSION['form_data'] = $_POST;
    header("Location: add_expense_form.php");
    exit;
}

// --- Proceed with INSERT ---
$stmtInsert = null;
$stmtInsertCat = null;
$newExpenseId = null;

try {
    $conn->begin_transaction();

    $sqlInsertExpense = "INSERT INTO grocery_expenses
                         (item_name, quantity, weight, unit, price_per_unit, is_weight_based, shop, purchase_date, total_price, user_id)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmtInsert = $conn->prepare($sqlInsertExpense);
    if (!$stmtInsert) { throw new Exception("Prepare expense failed: " . $conn->error); }

    $calculatedTotalPrice = 0;
    if ($isWeightBasedValue == 1 && $weightValue > 0 && $pricePerUnitValue > 0) {
        $calculatedTotalPrice = round(($weightValue * $pricePerUnitValue), 1);
    } else if ($isWeightBasedValue == 0 && $quantityValue > 0 && $pricePerUnitValue > 0) {
        $calculatedTotalPrice = round(($quantityValue * $pricePerUnitValue), 2);
    }
    $formattedTotalPrice = number_format($calculatedTotalPrice, 2, '.', '');
    $itemNameUpperForInsert = strtoupper($itemNameInput);

    $stmtInsert->bind_param('sidsdisssi',
        $itemNameUpperForInsert, $quantityValue, $weightValue, $unitValue,
        $pricePerUnitValue, $isWeightBasedValue, $shopValue,
        $purchaseDateValue, $formattedTotalPrice, $finalUserIdForExpense
    );

    if (!$stmtInsert->execute()) { throw new Exception("Execute expense failed: " . $stmtInsert->error . " (Item: {$itemNameUpperForInsert})"); }
    $newExpenseId = $conn->insert_id;
    if ($newExpenseId <= 0) { throw new Exception("Failed to get new expense ID after insert."); }
    $stmtInsert->close(); $stmtInsert = null;

    if (!empty($categoryIds)) {
        $sqlInsertCatLink = "INSERT INTO expense_categories (grocery_expense_id, category_id) VALUES (?, ?)";
        $stmtInsertCat = $conn->prepare($sqlInsertCatLink);
        if (!$stmtInsertCat) { throw new Exception("Prepare category link failed: " . $conn->error); }
        foreach ($categoryIds as $catId) {
            $validCatId = filter_var($catId, FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);
            if ($validCatId) {
                $stmtInsertCat->bind_param('ii', $newExpenseId, $validCatId);
                if (!$stmtInsertCat->execute() && $conn->errno != 1062) {
                    throw new Exception("Execute category link failed for cat_id {$validCatId}: " . $stmtInsertCat->error);
                }
            }
        }
        $stmtInsertCat->close(); $stmtInsertCat = null;
    }
    $conn->commit();

    $_SESSION['success_message'] = "Expense item '" . htmlspecialchars($itemNameInput) . "' added successfully!";
    $_SESSION['highlight_id'] = $newExpenseId;
    unset($_SESSION['form_errors']);
    unset($_SESSION['form_data']); // Clear form data on successful submission

    // 'add_another_checked' and 'last_assigned_user_id_used' are already set correctly
    // if $addAnother was true.

    if ($addAnother) {
        header("Location: add_expense_form.php");
    } else {
        // If not adding another, ensure these session vars for "Add Another" are cleared
        unset($_SESSION['add_another_checked']);
        unset($_SESSION['last_assigned_user_id_used']);
        // unset($_SESSION['last_shop_used']);
        // unset($_SESSION['last_unit_used']);
        unset($_SESSION['last_purchase_date_used']); // Also clear date if not adding another
        header("Location: view_expenses.php");
    }
    exit;

} catch (Exception $e) {
    if ($conn->inTransaction()) { $conn->rollback(); }
    $userLogIdentifier = ($loggedInUserRole === 'admin') ? "Admin {$loggedInUserId} (assigning to {$finalUserIdForExpense})" : "User {$loggedInUserId}";
    error_log("Error adding expense for {$userLogIdentifier}: " . $e->getMessage());
    $_SESSION['form_errors'] = ["An error occurred while saving the expense. Details: " . htmlspecialchars($e->getMessage())];
    $_SESSION['form_data'] = $_POST; // Preserve submitted data on DB error
    // 'add_another_checked' and 'last_assigned_user_id_used' (if applicable) are already set by logic before try-catch
    header("Location: add_expense_form.php");
    exit;
} finally {
    if ($stmtInsert instanceof mysqli_stmt) { $stmtInsert->close(); }
    if ($stmtInsertCat instanceof mysqli_stmt) { $stmtInsertCat->close(); }
    if (isset($conn) && $conn instanceof mysqli) {
       $conn->close();
    }
}
?>