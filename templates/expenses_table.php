<?php
//expenses_table.php
/**
 * Template for displaying the expenses table, pagination, and controls.
 *
 * Expects the following variables from the including file (e.g., view_expenses.php):
 * @var array $expenses        Array of expense data rows.
 * @var int   $page            Current page number.
 * @var int   $totalPages      Total number of pages.
 * @var int   $limit           Number of rows per page.
 * @var int   $totalRows       Total number of expense rows matching criteria.
 * @var string|null $errorMessage  Error message to display, if any.
 * @var string|null $successMessage Success message to display, if any.
 * @var int|null $highlightId    ID of the row to highlight.
 */

// --- Prepare data needed specifically for links within this template ---

// Get current sort parameters for link generation
$currentSortCol = $_GET['sort'] ?? 'purchase_date'; // Use default from controller if needed
$currentSortDir = strtolower($_GET['dir'] ?? 'desc');

// Helper function to build query strings for links, preserving existing GET params
function build_query_string(array $params_to_add_or_override = [], array $params_to_remove = []) {
    $current_params = $_GET;
    // Remove specified params
    foreach ($params_to_remove as $param_key) {
        unset($current_params[$param_key]);
    }
    // Add or override params
    $final_params = array_merge($current_params, $params_to_add_or_override);
    return http_build_query($final_params);
}

// Query string for pagination links (keeps current sort, limit, search etc., removes page)
$pagination_query_string = build_query_string(['limit' => $limit], ['page']);
if ($pagination_query_string) $pagination_query_string .= '&';

// Query string for sorting links (keeps current limit, search etc., removes page, sort, dir)
$base_query_string_for_sort = build_query_string(['limit' => $limit], ['page', 'sort', 'dir']);
if ($base_query_string_for_sort) $base_query_string_for_sort .= '&';

// Helper function to render table header sort links
function renderSortLink($label, $columnName, $currentSortCol, $currentSortDir, $baseQueryString) {
    $linkClass = ($currentSortCol === $columnName) ? 'sorted' : '';
    $nextDir = 'asc';
    $sortIndicator = '';
    if ($currentSortCol === $columnName) {
        $nextDir = ($currentSortDir === 'asc') ? 'desc' : 'asc';
        $sortIndicator = ($currentSortDir === 'asc') ? ' ▲' : ' ▼';
    }
    $sortUrl = "?{$baseQueryString}sort={$columnName}&dir={$nextDir}";
    // Use htmlspecialchars on output values
    echo "<th class='{$linkClass}'><a href='{$sortUrl}'>" . htmlspecialchars($label) . $sortIndicator . "</a></th>";
}

?>

<main class="content-area">
    <div class="page-header">
        <h2>View Expenses</h2>
        <a href="add_expense_form.php" class="btn btn-primary">Add New Expense</a>
    </div>

    <?php if (!empty($successMessage)): ?>
        <div class="alert alert-success" role="alert">
            <?= htmlspecialchars($successMessage) ?>
        </div>
    <?php endif; ?>

	
    <?php // Display General Page / Bulk Action Messages
    if (!empty($pageMessage)):
        $alertClass = 'alert-' . htmlspecialchars(strtolower($pageMessageType ?: 'info'));
    ?>
        <div class="alert <?= $alertClass ?>" role="alert">
            <?= htmlspecialchars($pageMessage) ?>
        </div>
    <?php endif; ?>

    <?php // Display Info Message (This block looks fine now)
    if (!empty($infoMessage)): ?>
        <div class="alert alert-info" role="alert">
            <?= htmlspecialchars($infoMessage) ?>
        </div>
    <?php endif; ?>

    <?php // Display DB Error Message (if set) - CORRECTED BLOCK
    if (!empty($errorMessage)): ?>
        <div class="alert alert-danger" role="alert"> 
           <?= htmlspecialchars($errorMessage) ?> <?php // Use echo/short echo tag ?>
        </div>
    <?php endif; ?>

    <div class="table-controls">
		<form action="view_expenses.php" method="get" class="search-form" style="display: inline-block; margin-right: 20px;">
            <?php 
                // Preserve current sort, limit if they exist (but not page or existing search)
                foreach ($_GET as $key => $value) {
                    if ($key !== 'search_term' && $key !== 'page') { 
                       echo '<input type="hidden" name="' . htmlspecialchars($key) . '" value="' . htmlspecialchars($value) . '">';
                    }
                }
                // Get current search term for repopulating the input
                $currentSearchTerm = $_GET['search_term'] ?? ''; 
            ?>
            <label for="search_term">Search:</label>
            <input type="search" id="search_term" name="search_term" placeholder="Item name, shop..." 
                   value="<?= htmlspecialchars($currentSearchTerm) ?>"> 
            <button type="submit" class="btn btn-secondary btn-sm">Search</button> 
            <?php // Add a clear button if a search is active ?>
            <?php if (!empty($currentSearchTerm)): ?>
                <a href="view_expenses.php?<?= build_query_string([], ['search_term', 'page']) ?>" class="btn btn-outline btn-sm">Clear</a> 
            <?php endif; ?>
        </form>
		
        <form action="view_expenses.php" method="get" style="display: inline-block;"> <?php // Consider moving style to CSS ?>
            <?php
                // Preserve existing GET params (like sort, dir, search) in hidden fields
                // This ensures limit changes don't reset other filters/sorts
                foreach ($_GET as $key => $value) {
                    if ($key !== 'limit' && $key !== 'page') { // Don't include limit or page itself
                       echo '<input type="hidden" name="' . htmlspecialchars($key) . '" value="' . htmlspecialchars($value) . '">';
                    }
                }
            ?>
            <label for="limit">Rows per page:</label>
            <select name="limit" id="limit" onchange="this.form.submit()">
                <?php foreach ([10, 25, 50, 100] as $lim): ?>
                    <option value="<?= $lim ?>" <?= ($limit == $lim) ? 'selected' : '' ?>><?= $lim ?></option>
                <?php endforeach; ?>
            </select>
        </form>

        <?php /* TODO: Add Search form here */ ?>
    </div>

    <form action="process_bulk_action.php" method="post" id="expenses-form">
        <div class="expenses-table-container"> <?php // Added wrapper for responsive scroll ?>
            <table class="expenses-table">
                <thead>
                    <tr>
                        <th><input type="checkbox" id="check-all" title="Select/Deselect All on This Page"></th>
                        <?php renderSortLink('ID', 'id', $currentSortCol, $currentSortDir, $base_query_string_for_sort); ?>
                        <?php renderSortLink('Item Name', 'item_name', $currentSortCol, $currentSortDir, $base_query_string_for_sort); ?>
						<th>Category</th> <?php // ** ADDED Category Header ** (Decide if sortable) ?>
                        <?php renderSortLink('Qty', 'quantity', $currentSortCol, $currentSortDir, $base_query_string_for_sort); ?>
                        <?php renderSortLink('Weight', 'weight', $currentSortCol, $currentSortDir, $base_query_string_for_sort); ?>
                        <th>Unit</th> <?php // Keeping Unit non-sortable ?>
                        <?php renderSortLink('Price/Unit', 'price_per_unit', $currentSortCol, $currentSortDir, $base_query_string_for_sort); ?>
                        <?php renderSortLink('Total Price', 'total_price', $currentSortCol, $currentSortDir, $base_query_string_for_sort); ?>
                        <?php renderSortLink('Shop', 'shop', $currentSortCol, $currentSortDir, $base_query_string_for_sort); // Made shop sortable ?>
                        <?php renderSortLink('Purchase Date', 'purchase_date', $currentSortCol, $currentSortDir, $base_query_string_for_sort); ?>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
					<?php if (!empty($expenses)): ?>
						<?php foreach ($expenses as $expense): ?>
							<?php // ** MODIFIED HIGHLIGHT CHECK **
							$isHighlighted = !empty($highlightIds) && in_array($expense['id'], $highlightIds);
							?>
							<tr <?= $isHighlighted ? 'class="highlight-row"' : '' ?>>
								<td><input type="checkbox" name="selected_ids[]" value="<?= $expense['id'] ?>" class="row-checkbox"></td>
								<td><?= htmlspecialchars($expense['id']) ?></td>
								<td><?= htmlspecialchars($expense['item_name']) ?></td>
								<td><?= htmlspecialchars($expense['item_categories'] ?? 'N/A') ?></td> <?php // ** ADDED Category Cell ** ?>
								<td style="text-align: right;"><?= htmlspecialchars($expense['quantity']) ?></td>
								<td style="text-align: right;"><?= number_format(htmlspecialchars($expense['weight']), 3) ?></td>
								<td><?= htmlspecialchars($expense['unit']) ?></td>
								<td style="text-align: right;">₱<?= number_format(htmlspecialchars($expense['price_per_unit']), 2) ?></td>
								<td style="text-align: right;">₱<?= number_format(htmlspecialchars($expense['total_price']), 2) ?></td>
								<td><?= htmlspecialchars($expense['shop']) ?></td>
								<td><?= htmlspecialchars($expense['purchase_date']) ?></td>
								
								<?php // ** START CHANGES: Action Cell ** ?>
								<td class="actions-cell"> <?php // Add a class for specific styling ?>
									<a href="edit_expense_form.php?id=<?= $expense['id'] ?>" class="action-icon" title="Edit">
										<i class="fas fa-pencil-alt"></i> <?php // Edit icon ?>
									</a>
									<a href="delete_expense.php?id=<?= $expense['id'] ?>" class="action-icon delete-link" title="Delete" 
									   onclick="return confirm('Delete item ID <?= $expense['id'] ?>: <?= htmlspecialchars(addslashes($expense['item_name'])) ?>?')">
										<i class="fas fa-trash-alt"></i> <?php // Delete icon ?>
									</a>
								</td>
								<?php // ** END CHANGES: Action Cell ** ?>

							</tr>
						<?php endforeach; ?>
					<?php else: ?>
						<tr>
							<td colspan="11" class="no-expenses">No expenses found matching your criteria.</td>
						</tr>
					<?php endif; ?>
				</tbody>
            </table>
        </div><!-- end expenses-table-container -->

        <?php if (!empty($expenses)): ?>
        <div class="bulk-actions">
            <label for="bulk_action">With selected:</label>
            <select name="bulk_action" id="bulk_action">
				<option value="">--Select Action--</option>
				<option value="edit">Edit Selected</option>
				<option value="delete">Delete</option>
				<!-- Add other bulk actions later -->
			</select>
            <button type="submit" class="btn btn-secondary" onclick="return confirm('Are you sure you want to perform the selected bulk action?')">Apply</button> <?php // Added btn class ?>
        </div>
        <?php endif; ?>

    </form>

    <div class="pagination">
        <?php if ($totalPages > 1): ?>

            <?php if ($page > 1): ?>
                <a href="?page=1&<?= $pagination_query_string ?>">First</a>
                <a href="?page=<?= $page - 1 ?>&<?= $pagination_query_string ?>">Previous</a>
            <?php else: ?>
                <span>First</span>
                <span>Previous</span>
            <?php endif; ?>

            <?php // Optional: Add page number links here later ?>
            <span class="current-page"> Page <?= $page ?> of <?= $totalPages ?> </span>

            <?php if ($page < $totalPages): ?>
                <a href="?page=<?= $page + 1 ?>&<?= $pagination_query_string ?>">Next</a>
                <a href="?page=<?= $totalPages ?>&<?= $pagination_query_string ?>">Last</a>
            <?php else: ?>
                <span>Next</span>
                <span>Last</span>
            <?php endif; ?>

        <?php endif; ?>
         <p>Total Records: <?= $totalRows ?></p>
    </div>

</main>

<?php // Recommend moving JS to footer.php or a dedicated script.js file ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // --- Check All Functionality ---
        const checkAll = document.getElementById('check-all');
        if (checkAll) {
            checkAll.addEventListener('click', function(event) {
                const checkboxes = document.querySelectorAll('.row-checkbox');
                checkboxes.forEach(checkbox => {
                    checkbox.checked = event.target.checked;
                });
            });
        }

        // --- Highlight Fade Logic for Potentially Multiple Rows ---
        
        // Select ALL elements that have the highlight class
        const highlightedRows = document.querySelectorAll('.highlight-row'); 

        // Check if the NodeList contains any elements
        if (highlightedRows.length > 0) { 
            console.log(`Highlight rows found: ${highlightedRows.length}`); // Debugging

            // Set a timer to remove the class from ALL found elements
            setTimeout(() => {
                console.log('Removing highlight class from rows...'); // Debugging
                highlightedRows.forEach(row => { // Loop through the NodeList
                    row.classList.remove('highlight-row');
                });
            }, 5000); // Timeout in milliseconds (5 seconds)
        } else {
             console.log('No highlight rows found on page load.'); // Debugging message if no rows have the class
        }
        
    });
</script>