<?php
// autocomplete_suggestions.php

// --- THIS MUST BE AT THE VERY TOP ---
define('FRUGALFOLIO_ACCESS', true);

// --- BOOTSTRAP: Handles session start, DB connection, and defines require_login() ---
require_once 'auth_bootstrap.php';

// --- AUTHENTICATION: Ensure only logged-in users can access this endpoint ---
require_login();
// If we reach here, the user is logged in. $conn is available.

// --- Get the search term ---
$term = isset($_GET['term']) ? trim($_GET['term']) : '';
$suggestions = [];

// Only search if term is not empty
if (!empty($term) && isset($conn) && $conn instanceof mysqli) {
    $sql = "SELECT DISTINCT item_name
            FROM grocery_expenses
            WHERE item_name LIKE ?
            ORDER BY item_name ASC
            LIMIT 10";

    // For case-insensitivity, using UPPER on both sides is more reliable across DBs/collations
    // $sql = "SELECT DISTINCT item_name FROM grocery_expenses WHERE UPPER(item_name) LIKE UPPER(?) ORDER BY item_name ASC LIMIT 10";
    $searchTerm = "%" . $term . "%"; // If using UPPER in SQL, $term can be used directly or also uppercased.
                                     // Your original had strtoupper($term) for $searchTerm, which would require item_name LIKE ? with $searchTerm = "%".strtoupper($term)."%"
                                     // If your DB collation for item_name is case-insensitive (e.g., utf8mb4_general_ci), LIKE is often case-insensitive by default.
                                     // For consistency, let's stick to the explicit strtoupper for the bound parameter if the column isn't guaranteed CI or you're not using UPPER() in SQL.
    $searchTermForBind = "%" . strtoupper($term) . "%";


    $stmt = $conn->prepare($sql);
    if ($stmt) {
        // If using UPPER(item_name) LIKE UPPER(?), bind $searchTerm directly.
        // If using item_name LIKE ?, and you want case-insensitivity via PHP, bind $searchTermForBind.
        // Let's assume your DB handles LIKE case-insensitively or your item_names are consistently cased.
        // For safety against different DB setups, explicit UPPER in SQL is better.
        // If sticking to current SQL: item_name LIKE ?
        // And you want to match 'Apple' if user types 'apple', then DB must be CI or item_name stored as 'APPLE' and term uppercased.
        // Given $searchTerm = "%" . strtoupper($term) . "%"; in original, it implies comparison against possibly mixed-case item_name.
        // So, to make it truly case-insensitive with current SQL, DB must be CI for item_name column.
        // If not, the SQL should be: WHERE UPPER(item_name) LIKE ? and bind strtoupper($searchTerm).

        // Sticking to your last provided autocomplete_suggestions.php where $searchTerm was "%" . strtoupper($term) . "%"
        // This implies your database `item_name` column might be case sensitive, and you expect to match if user types 'apple' and DB has 'APPLE'.
        // Or, your database `item_name` column is case INsensitive.
        // Let's assume the latter for now, or that item names are consistently stored in uppercase.
        // If `item_name` can be 'Apple' and you search for 'apple', `LIKE '%APPLE%'` won't match 'Apple' unless the DB column is case-insensitive.
        // For robustness:
        // $sql = "SELECT DISTINCT item_name FROM grocery_expenses WHERE UPPER(item_name) LIKE ? ORDER BY item_name ASC LIMIT 10";
        // $searchTermForBind = "%" . strtoupper($term) . "%";

        $stmt->bind_param('s', $searchTermForBind); // Use $searchTermForBind
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $suggestions[] = $row['item_name'];
                }
                $result->free();
            } else {
                error_log("Autocomplete: Get result failed - " . $stmt->error);
            }
        } else {
            error_log("Autocomplete: Execute failed - " . $stmt->error);
        }
        $stmt->close();
    } else {
        error_log("Autocomplete: Prepare failed - " . $conn->error);
    }

    $conn->close();

} else {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
}

header('Content-Type: application/json');
echo json_encode($suggestions);
exit();
?>