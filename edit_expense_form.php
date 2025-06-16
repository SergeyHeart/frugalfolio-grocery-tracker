<?php
// edit_expense_form.php

define('FRUGALFOLIO_ACCESS', true);
require_once 'auth_bootstrap.php';
require_login();

$pageTitle = "Edit Expense - FrugalFolio";
// --- MODIFICATION: Add Font Awesome ---
$pageStylesheets = [
    '/css/form.css', // Assuming /Frugalfolio is part of the path or handled by header.php
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css' // Add this
];
// --- END MODIFICATION ---
$pageScripts = ['/js/expense_form.js']; // Assuming /Frugalfolio is part of the path or handled

$expenseId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);
$expenseData = null;
$currentCategoryIds = [];
$formErrors = $_SESSION['form_errors'] ?? [];
$formData = $_SESSION['form_data'] ?? [];
unset($_SESSION['form_errors']);

$successMessage = null;
if (!isset($_SESSION['bulk_edit_total']) && isset($_SESSION['success_message'])) {
    $successMessage = $_SESSION['success_message'];
}
unset($_SESSION['success_message']);

$isBulkEditing = isset($_SESSION['bulk_edit_total']) && isset($_SESSION['bulk_edit_ids']);
$bulkEditTotal = $_SESSION['bulk_edit_total'] ?? 0;
$bulkEditRemaining = $isBulkEditing ? count($_SESSION['bulk_edit_ids']) + 1 : 0;
$bulkEditCurrentNumber = $isBulkEditing ? ($bulkEditTotal - $bulkEditRemaining + 1) : 0;

if (!$expenseId) {
    if (!isset($formErrors['expense_id'])) $formErrors['expense_id'] = "Invalid or missing expense ID.";
} else {
    if (empty($formData) || !isset($formData['id']) || $formData['id'] != $expenseId) {
        try {
            $sql = "SELECT * FROM grocery_expenses WHERE id = ?";
            $params = [$expenseId]; $types = "i";
            if ($loggedInUserRole !== 'admin') {
                $sql .= " AND user_id = ?";
                $params[] = $loggedInUserId; $types .= "i";
            }
            $stmt = $conn->prepare($sql);
            if (!$stmt) throw new Exception("Prepare expense failed: " . $conn->error);
            $stmt->bind_param($types, ...$params);
            if (!$stmt->execute()) throw new Exception("Execute expense failed: " . $stmt->error);
            $result = $stmt->get_result();
            $expenseData = $result->fetch_assoc();
            if ($result) $result->free(); // Free result before closing statement
            $stmt->close(); $stmt = null; // Nullify after close

            if (!$expenseData) {
                $formErrors['auth'] = "Expense ID " . $expenseId . " not found or you are not authorized to edit it.";
                error_log("User {$loggedInUserId} ({$loggedInUserRole}) failed to fetch/authorize expense ID {$expenseId} for edit.");
                $expenseId = null;
            } else {
                $pageTitle = "Edit: " . htmlspecialchars($expenseData['item_name']) . " - FrugalFolio";
                $sqlCats = "SELECT category_id FROM expense_categories WHERE grocery_expense_id = ?";
                $stmtCats = $conn->prepare($sqlCats);
                if (!$stmtCats) throw new Exception("Prepare categories failed: " . $conn->error);
                $stmtCats->bind_param('i', $expenseId);
                if (!$stmtCats->execute()) throw new Exception("Execute categories failed: " . $stmtCats->error);
                $resultCats = $stmtCats->get_result();
                while ($row = $resultCats->fetch_assoc()) {
                    $currentCategoryIds[] = (int)$row['category_id'];
                }
                if ($resultCats) $resultCats->free();
                $stmtCats->close(); $stmtCats = null; // Nullify after close
                
                $formData = $expenseData;
                $formData['category_ids'] = $currentCategoryIds;
            }
        } catch (Exception $e) {
            // Ensure statements are closed if an exception occurs mid-way
            if (isset($stmt) && $stmt instanceof mysqli_stmt) $stmt->close();
            if (isset($stmtCats) && $stmtCats instanceof mysqli_stmt) $stmtCats->close();

            $formErrors['fetch'] = "Error fetching expense data: " . htmlspecialchars($e->getMessage());
            error_log("Error fetching/authorizing expense ID $expenseId for edit by user {$loggedInUserId}: " . $e->getMessage());
            $expenseData = null; $expenseId = null;
        }
    } else {
        $expenseId = $formData['id'];
        $pageTitle = "Edit: " . htmlspecialchars($formData['item_name'] ?? 'Expense') . " (Retry) - FrugalFolio";
        $currentCategoryIds = $formData['category_ids'] ?? [];
    }
}

if (empty($formErrors) && (isset($expenseData) && $expenseData !== null || !$expenseId )) {
    unset($_SESSION['form_data']);
}

$allCategories = [];
if (isset($conn) && $conn instanceof mysqli && !$conn->connect_error) {
    $sql_all_cat = "SELECT category_id, category_name FROM categories ORDER BY category_name";
    $result_all_cat = $conn->query($sql_all_cat);
    if ($result_all_cat) {
        while ($row_cat = $result_all_cat->fetch_assoc()) {
            $allCategories[] = $row_cat;
        }
        $result_all_cat->free();
    } else {
        error_log("Error fetching all categories in edit_expense_form.php: " . $conn->error);
    }
} else {
    if (!isset($formErrors['db_connection'])) {
        $formErrors['db_connection'] = "Database connection error. Cannot load category list.";
        error_log("DB connection not available for all_categories fetch in edit_expense_form.php.");
    }
}

require_once 'header.php';
?>

<main class="content-area">
    <h2 class="expense-form-title"><?= htmlspecialchars(str_replace(" - FrugalFolio", "", $pageTitle)) ?></h2>

    <?php if ($isBulkEditing && $bulkEditTotal > 0): ?>
        <div class="alert alert-info bulk-edit-progress" role="alert">
            Editing item <strong><?= $bulkEditCurrentNumber ?> of <?= $bulkEditTotal ?></strong> in bulk.
            (<?= max(0, $bulkEditRemaining - 1) ?> more remaining after this one)
        </div>
    <?php endif; ?>

    <?php if (!empty($successMessage)): ?>
        <div class="alert alert-success" role="alert">
            <?= htmlspecialchars($successMessage) ?>
        </div>
    <?php endif; ?>
    <?php if (!empty($formErrors)): ?>
        <div class="form-errors alert alert-danger">
            <strong>Please correct the following errors:</strong>
            <ul>
                <?php foreach ($formErrors as $errorKey => $error): ?>
                    <li><?= htmlspecialchars($error) // Assuming $error is a string now ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <?php if ($expenseId && !empty($formData)): ?>
        <form action="process_edit_expense.php" method="post" class="expense-form">
            <input type="hidden" name="id" value="<?= htmlspecialchars($expenseId) ?>">

            <div class="form-group">
                <label for="item_name">Item Name:*</label>
                <input type="text" id="item_name" name="item_name" required maxlength="255"
                       value="<?= htmlspecialchars($formData['item_name'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label>Category:*</label>
                <div class="category-checkbox-group">
                    <?php
                    $currentCategoryIdsInForm = $formData['category_ids'] ?? []; // Use data intended for the form
                    if (!empty($allCategories)):
                        foreach ($allCategories as $category):
                            $cat_id = $category['category_id'];
                            $is_checked = !empty($currentCategoryIdsInForm) && in_array($cat_id, $currentCategoryIdsInForm);
                    ?>
                            <div class="checkbox-item">
                                <input type="checkbox"
                                       id="category_<?= htmlspecialchars($cat_id) ?>"
                                       name="category_ids[]"
                                       value="<?= htmlspecialchars($cat_id) ?>"
                                       <?= $is_checked ? 'checked' : '' ?>>
                                <label for="category_<?= htmlspecialchars($cat_id) ?>">
                                    <?= htmlspecialchars($category['category_name']) ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>No categories found in database.</p>
                    <?php endif; ?>
                </div>
                 <small class="form-text">Select one or more categories.</small>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="quantity">Quantity:*</label>
                    <input type="number" id="quantity" name="quantity" required min="1" step="1"
                           value="<?= htmlspecialchars($formData['quantity'] ?? '1') ?>">
                </div>
                <div class="form-group">
                    <label for="weight">Weight:</label>
                    <input type="number" id="weight" name="weight" step="0.001" min="0"
                           value="<?= htmlspecialchars(number_format(floatval($formData['weight'] ?? 0), 3, '.', '')) ?>">
                </div>
                <div class="form-group">
                    <label for="unit">Unit:*</label>
                    <select id="unit" name="unit" required>
                        <?php
                        $currentUnit = $formData['unit'] ?? 'N/A';
                        $unitOptions = ['N/A', 'G', 'KG', 'ML', 'L'];
                        foreach ($unitOptions as $option): ?>
                            <option value="<?= $option ?>" <?= ($currentUnit == $option) ? 'selected' : '' ?>><?= $option ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

             <div class="form-row">
                <div class="form-group">
                    <label for="price_per_unit">Price / Unit:*</label>
                    <input type="number" id="price_per_unit" name="price_per_unit" required step="0.01" min="0"
                           value="<?= htmlspecialchars(number_format(floatval($formData['price_per_unit'] ?? 0), 2, '.', '')) ?>">
                </div>
                 <div class="form-group form-check-inline centered">
                    <input type="checkbox" id="is_weight_based" name="is_weight_based" value="1"
                           <?= !empty($formData['is_weight_based']) ? 'checked' : '' ?>>
                    <label for="is_weight_based">Is Weight Based?</label>
                 </div>
             </div>

            <div class="form-group">
                <label for="total_price_preview">Total Price (Preview):</label>
                <input type="text" id="total_price_preview" name="total_price_preview" readonly disabled>
                 <small class="form-text">Note: Actual total price is calculated on save.</small>
            </div>

             <div class="form-row">
                <div class="form-group">
                    <label for="shop">Shop:*</label>
                    <input type="text" id="shop" name="shop" required maxlength="50"
                           value="<?= htmlspecialchars($formData['shop'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="purchase_date">Purchase Date:*</label>
                    <input type="date" id="purchase_date" name="purchase_date" required
                           value="<?= htmlspecialchars($formData['purchase_date'] ?? '') ?>">
                </div>
             </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <?= ($isBulkEditing && $bulkEditRemaining > 1) ? 'Update & Next' : 'Update Expense' ?>
                </button>
                <a href="view_expenses.php" class="btn btn-secondary">Cancel <?= $isBulkEditing ? '(Bulk Edit)' : '' ?></a>
            </div>

        </form>
    <?php else: ?>
        <?php if (empty($formErrors)): ?>
            <p>Could not load expense data for editing. It may have been deleted or you might not have permission.</p>
        <?php endif; ?>
        <a href="view_expenses.php" class="btn btn-secondary">Back to Expenses List</a>
    <?php endif; ?>
</main>

<?php
// Connection closing is important
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
require_once 'footer.php';
?>