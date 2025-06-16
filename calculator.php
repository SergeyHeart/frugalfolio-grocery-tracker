<?php
// calculator.php

define('FRUGALFOLIO_ACCESS', true); // Ensure this is at the top
require_once 'auth_bootstrap.php';
require_login();

$pageTitle = "Expenses Calculator - FrugalFolio";

$allCategories = [];
if (isset($conn) && $conn instanceof mysqli) {
    $sql_all_cat = "SELECT category_id, category_name FROM categories ORDER BY category_name";
    $result_all_cat = $conn->query($sql_all_cat);
    if ($result_all_cat) {
        while ($row_cat = $result_all_cat->fetch_assoc()) {
            $allCategories[$row_cat['category_id']] = $row_cat['category_name'];
        }
        $result_all_cat->free();
    } else {
        error_log("Error fetching categories: " . $conn->error);
    }
} else {
     error_log("DB connection not available for category fetch in calculator.php");
}

$pageStylesheets = [
    '/css/form.css',
    '/css/calculator_style.css', // This will need adjustments
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css'
];
$pageScripts = ['/js/calculator_script.js'];

require_once 'header.php';
?>

<main class="content-area">
    <h2 class="calculator-title">Grocery Expense Calculator</h2>
    <p>Estimate your next shopping trip cost based on recent prices.</p>

    <div class="calculator-container">

        <div class="calculator-input-area">
            <h3>Add Item to List</h3>
            <div class="form-group" style="position: relative;">
                <label for="calc_item_name">Search Item:</label>
                <input type="text" id="calc_item_name" placeholder="Start typing item name...">
                <div id="calc_suggestions"></div>
            </div>

            <div id="calc_current_item_details" style="display: none; margin-top: 15px; padding: 10px; border: 1px dashed #ccc;">
                <strong>Selected:</strong> <span id="calc_selected_name"></span><br>
                Unit: <span id="calc_selected_unit">N/A</span> |
                Price/Unit: ₱<span id="calc_selected_price">0.00</span> |
                Priced by Wt: <span id="calc_selected_is_wt">No</span>
                <hr>
                <div class="form-row">
                    <div class="form-group">
                        <label for="calc_quantity">Quantity:</label>
                        <input type="number" id="calc_quantity" value="1" min="1" step="1">
                    </div>
                    <div class="form-group">
                        <label for="calc_weight">Weight:</label>
                        <input type="number" id="calc_weight" value="0.000" step="0.001" min="0">
                    </div>
                </div>
                 <div class="form-group">
                     <strong>Estimated Price for Item: ₱<span id="calc_item_estimate">0.00</span></strong>
                 </div>
                 <div id="calc_price_change_warning" style="color: red; font-size: 0.9em; margin-top: 5px; display:none;"></div>
                 <?php // --- MOVE "ADD TO LIST" BUTTON BACK HERE --- ?>
                 <button type="button" id="add_to_list_btn" class="btn btn-primary btn-sm" style="margin-top: 10px;">Add to List</button>
            </div>
             <p id="calc_item_not_found" style="color: red; display:none; margin-top: 10px;">Item not found in past expenses.</p>
        </div>

        <div class="calculator-list-area">
            <h3>Estimated List</h3>
            <div class="shopping-list-table-wrapper">
                <table id="shopping_list_table">
                    <thead>
                        <tr>
                            <th>Item Name</th>
                            <th>Category</th>
                            <th>Qty/Wt</th>
                            <th>Unit</th>
                            <th>Est. Price</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody class="list-group-header"><tr class="group-header-row"><td colspan="6">Fresh / Perishables</td></tr></tbody>
                    <tbody id="list-group-fresh"></tbody>
                    <tbody class="list-group-header"><tr class="group-header-row"><td colspan="6">Pantry / Dry Goods</td></tr></tbody>
                    <tbody id="list-group-pantry"></tbody>
                    <tbody class="list-group-header"><tr class="group-header-row"><td colspan="6">Household / Personal</td></tr></tbody>
                    <tbody id="list-group-household"></tbody>
                    <tbody class="list-group-header"><tr class="group-header-row"><td colspan="6">Other Items</td></tr></tbody>
                    <tbody id="list-group-other"></tbody>
                    <tbody id="list-placeholder-body">
                        <tr id="list_placeholder_row">
                            <td colspan="6" style="text-align: center; color: #888;"><i>List is empty</i></td>
                        </tr>
                    </tbody>
                    <?php // --- ADD TABLE FOOTER (TFOOT) BACK FOR TOTAL --- ?>
                    <tfoot>
                        <tr>
                            <td colspan="4" style="text-align: right; font-weight: bold;">Estimated Total:</td>
                            <td id="list_total_price" style="text-align: right; font-weight: bold;">₱0.00</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <?php // --- ADD CLEAR LIST BUTTON CONTAINER BACK --- ?>
            <div class="clear-button-container" style="text-align: right; margin-top: 15px;">
                <button type="button" id="clear_list_btn" class="btn btn-warning btn-sm">Clear List</button>
            </div>
        </div>

    </div><!-- end calculator-container -->

    <?php // --- REMOVE THE STICKY FOOTER DIV --- ?>
    <!-- 
    <div class="calculator-summary-footer">
        ...
    </div>
    -->

</main>

<script>
    const allCategoriesMap = <?= json_encode($allCategories, JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>

<?php
require_once 'footer.php';
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
?>