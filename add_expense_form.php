<?php
// add_expense_form.php

define('FRUGALFOLIO_ACCESS', true);
require_once 'auth_bootstrap.php'; // $loggedInUserRole, $loggedInUserId, $conn are available
require_login();

$pageTitle = "Add New Expense - FrugalFolio";
$pageStylesheets = [
    '/css/form.css',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css'
];
$pageScripts = ['/js/expense_form.js'];

// --- Retrieve Session Data ---
$formErrors = $_SESSION['form_errors'] ?? [];
$formData = $_SESSION['form_data'] ?? []; // Used for repopulating on error
$successMessage = $_SESSION['success_message'] ?? null;

// ---- Get Remembered Data for "Add Another", Date, and Assigned User ----
$addAnotherChecked = isset($_SESSION['add_another_checked']) && $_SESSION['add_another_checked'];
$rememberedPurchaseDate = $_SESSION['last_purchase_date_used'] ?? date('Y-m-d');
// --- NEW: Get remembered assigned user ID ---
$rememberedAssignedUserId = null;
if ($loggedInUserRole === 'admin' && $addAnotherChecked) { // Only remember if "Add Another" was checked
    $rememberedAssignedUserId = $_SESSION['last_assigned_user_id_used'] ?? null;
}
// --- END: Get remembered assigned user ID ---


// --- Clear session data AFTER retrieving it ---
unset($_SESSION['form_errors']);
if ($successMessage && !$addAnotherChecked) {
    unset($_SESSION['form_data']);
} elseif (!$successMessage && empty($formErrors)) { // Clear only if no errors and no success (fresh load)
    unset($_SESSION['form_data']);
}
unset($_SESSION['success_message']);


// --- Fetch categories ---
$categories = [];
if (isset($conn) && $conn instanceof mysqli) {
    $sql_cat = "SELECT category_id, category_name FROM categories ORDER BY category_name";
    $result_cat = $conn->query($sql_cat);
    if ($result_cat && $result_cat->num_rows > 0) {
        while ($row_cat = $result_cat->fetch_assoc()) {
            $categories[] = $row_cat;
        }
        $result_cat->free();
    } else {
        error_log("Error fetching categories in add_expense_form.php: " . ($conn->error ?: "No categories found or query failed."));
    }
} else {
    error_log("DB connection not available for category fetch in add_expense_form.php");
    if (empty($formErrors)) {
        $formErrors[] = "Database connection error. Cannot load categories.";
    }
}

// --- Fetch users IF current user is admin ---
$usersList = [];
if ($loggedInUserRole === 'admin') {
    if (isset($conn) && $conn instanceof mysqli) {
        $sql_users = "SELECT user_id, username, display_name FROM users WHERE role = 'user' ORDER BY display_name, username";
        $result_users = $conn->query($sql_users);
        if ($result_users && $result_users->num_rows > 0) {
            while ($row_user = $result_users->fetch_assoc()) {
                $usersList[] = $row_user;
            }
            $result_users->free();
        } else {
            error_log("Error fetching users for admin in add_expense_form.php: " . ($conn->error ?: "No users found or query failed."));
        }
    } else {
        if (empty($formErrors)) {
            $formErrors[] = "Database connection error. Cannot load users list for admin.";
        }
    }
}

require_once 'header.php';
?>

    <main class="content-area">
        <h2 class="expense-form-title">Add New Expense Item</h2>

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
                        <li><?= htmlspecialchars(is_array($error) ? $errorKey .': '.implode(', ', $error) : $error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form action="process_add_expense.php" method="post" class="expense-form" id="addExpenseForm">
            <?php if ($loggedInUserRole === 'admin'): ?>
                <div class="form-group">
                    <label for="assign_to_user_id">Assign Expense To User:*</label>
                    <select id="assign_to_user_id" name="assign_to_user_id" required>
                        <option value="">-- Select User --</option>
                        <?php if (!empty($usersList)): ?>
                            <?php foreach ($usersList as $user):
                                $isSelected = false;
                                if (!empty($formData['assign_to_user_id']) && $formData['assign_to_user_id'] == $user['user_id']) {
                                    $isSelected = true;
                                } elseif ($rememberedAssignedUserId && $rememberedAssignedUserId == $user['user_id'] && empty($formData['assign_to_user_id'])) {
                                    $isSelected = true;
                                }
                            ?>
                                <option value="<?= htmlspecialchars($user['user_id']) ?>" <?= $isSelected ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($user['display_name'] ?: $user['username']) ?> (ID: <?= htmlspecialchars($user['user_id']) ?>)
                                </option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <option value="" disabled>No users available to assign</option>
                        <?php endif; ?>
                    </select>
                    <?php if (empty($usersList) && $loggedInUserRole === 'admin'): ?>
                        <small class="form-text error-text" style="color: red;">No 'user' role accounts found to assign the expense to. Please ensure standard users exist.</small>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="form-group form-check-inline">
                 <input type="checkbox" id="new_item_check" name="new_item_check" value="1"
                        <?php echo (!empty($formData['new_item_check']) && $formData['new_item_check'] == '1') ? 'checked' : ''; ?>>
                 <label for="new_item_check">New Item?</label>
                 <small class="form-text">(Check this for a new item. Uncheck to add another record for an existing item and see suggestions.)</small>
            </div>

            <div class="form-group">
                <label for="item_name">Item Name:*</label>
                <input type="text" id="item_name" name="item_name" required maxlength="255" autocomplete="off"
                       value="<?= htmlspecialchars($formData['item_name'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label>Category:*</label>
                <div class="category-checkbox-group">
                    <?php $selectedCategories = $formData['category_ids'] ?? []; ?>
                    <?php if (!empty($categories)): foreach ($categories as $category): ?>
                        <?php $cat_id = $category['category_id']; $is_checked = in_array($cat_id, (array)$selectedCategories); // Cast to array ?>
                        <div class="checkbox-item">
                            <input type="checkbox" id="category_<?= htmlspecialchars($cat_id) ?>" name="category_ids[]" value="<?= htmlspecialchars($cat_id) ?>" <?= $is_checked ? 'checked' : '' ?>>
                            <label for="category_<?= htmlspecialchars($cat_id) ?>"><?= htmlspecialchars($category['category_name']) ?></label>
                        </div>
                    <?php endforeach; else: ?>
                        <p>No categories found. Please add categories first.</p>
                    <?php endif; ?>
                </div>
                <small class="form-text">Select one or more categories.</small>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="quantity">Quantity:*</label>
                    <input type="number" id="quantity" name="quantity" required min="1" step="1" value="<?= htmlspecialchars($formData['quantity'] ?? '1') ?>">
                </div>
                <div class="form-group">
                    <label for="weight">Weight:</label>
                    <input type="number" id="weight" name="weight" step="0.001" min="0" value="<?= htmlspecialchars(isset($formData['weight']) ? number_format(floatval($formData['weight']), 3, '.', '') : '0.000') ?>">
                </div>
                <div class="form-group">
                    <label for="unit">Unit:*</label>
                    <select id="unit" name="unit" required>
                        <?php $currentUnit = $formData['unit'] ?? ($addAnotherChecked && isset($_SESSION['last_unit_used']) ? $_SESSION['last_unit_used'] : 'N/A');
                              $unitOptions = ['N/A', 'G', 'KG', 'ML', 'L']; ?>
                        <?php foreach ($unitOptions as $option): ?>
                            <option value="<?= $option ?>" <?= ($currentUnit == $option) ? 'selected' : '' ?>><?= $option ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="price_per_unit">Price / Unit:*</label>
                    <input type="number" id="price_per_unit" name="price_per_unit" required step="0.01" min="0" value="<?= htmlspecialchars(isset($formData['price_per_unit']) ? number_format(floatval($formData['price_per_unit']), 2, '.', '') : '0.00') ?>">
                </div>
                <div class="form-group form-check-inline centered">
                    <input type="checkbox" id="is_weight_based" name="is_weight_based" value="1" <?= (!empty($formData['is_weight_based']) && $formData['is_weight_based'] == '1') ? 'checked' : '' ?>>
                    <label for="is_weight_based">Is Weight Based?</label>
                </div>
            </div>

            <div class="form-group">
                <label for="total_price_preview">Total Price (Preview):</label>
                <input type="text" id="total_price_preview" name="total_price_preview" readonly disabled>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="shop">Shop:*</label>
                    <input type="text" id="shop" name="shop" required maxlength="50" value="<?= htmlspecialchars($formData['shop'] ?? ($addAnotherChecked && isset($_SESSION['last_shop_used']) ? $_SESSION['last_shop_used'] : 'KCC')) ?>">
                </div>
                <div class="form-group">
                    <label for="purchase_date">Purchase Date:*</label>
                    <input type="date" id="purchase_date" name="purchase_date" required value="<?= htmlspecialchars($formData['purchase_date'] ?? $rememberedPurchaseDate) ?>">
                </div>
            </div>

            <div class="form-actions">
                <div class="form-check-inline" style="justify-content: flex-start; margin-bottom: 10px; padding-top:0;">
                    <input type="checkbox" id="add_another" name="add_another" value="1" <?= $addAnotherChecked ? 'checked' : '' ?>>
                    <label for="add_another" style="font-weight: normal;">Add another item after this one</label>
                </div>
                <button type="submit" id="addExpenseSubmitBtn" class="btn btn-primary">Add Expense</button>
                <a href="view_expenses.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </main>

<?php
if (!empty($successMessage) && $addAnotherChecked) {
    // Corrected Heredoc Syntax
    echo <<<JS
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const itemNameField = document.getElementById('item_name');
            if (itemNameField) {
                itemNameField.focus();
            }
        });
    </script>
JS; // Closing identifier must be on its own line, with no leading/trailing whitespace
}

if (!$addAnotherChecked) {
    unset($_SESSION['last_assigned_user_id_used']);
}
unset($_SESSION['add_another_checked']);


if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
require_once 'footer.php';
?>