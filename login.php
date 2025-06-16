<?php
// login.php

// --- THIS MUST BE AT THE VERY TOP, BEFORE ANY INCLUDES ---
define('FRUGALFOLIO_ACCESS', true);
// --- THIS IS ALSO NEEDED FOR PUBLIC PAGES, BEFORE AUTH_BOOTSTRAP ---
define('FRUGALFOLIO_ACCESS_PUBLIC_PAGE', true);

// Include the bootstrap file. It handles session_start() and db_connection.php
require_once 'auth_bootstrap.php';
// Now, session is started, $conn is available, and $loggedInUserId etc. are defined (will be null if not logged in).

// If user is already logged in (check done using variables from bootstrap), redirect.
// $loggedInUserId is defined in auth_bootstrap.php as $_SESSION['user_id'] ?? null;
if ($loggedInUserId) {
    // Check if there's a redirect URL from a previous attempt to access a protected page
    if (isset($_SESSION['redirect_url'])) {
        $redirectUrl = $_SESSION['redirect_url'];
        unset($_SESSION['redirect_url']); // Clear it after use
        header('Location: ' . $redirectUrl);
    } else {
        header('Location: dashboard.php'); // Default redirect
    }
    exit;
}


// --- Login Logic (uses $conn from auth_bootstrap.php) ---
$loginError = null;
$submittedUsernameValue = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $submittedUsername = trim($_POST['username'] ?? '');
    $submittedPassword = trim($_POST['password'] ?? '');
    $submittedUsernameValue = htmlspecialchars($submittedUsername);

    if (empty($submittedUsername) || empty($submittedPassword)) {
        $loginError = "Username and password are required.";
    } else {
        // $conn is already available from auth_bootstrap.php
        $sql = "SELECT user_id, username, password_hash, display_name, role FROM users WHERE username = ?";
        $stmt = $conn->prepare($sql);

        if ($stmt) {
            $stmt->bind_param("s", $submittedUsername);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                if (password_verify($submittedPassword, $user['password_hash'])) {
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['display_name'] = $user['display_name'];
                    $_SESSION['role'] = $user['role'];
                    session_regenerate_id(true);

                    // --- Redirect Logic After Successful Login ---
                    if (isset($_SESSION['redirect_url'])) {
                        $redirectUrl = $_SESSION['redirect_url'];
                        unset($_SESSION['redirect_url']); // Clear it after use
                        header('Location: ' . $redirectUrl);
                    } else {
                        header('Location: dashboard.php'); // Default redirect
                    }
                    exit;
                } else {
                    $loginError = "Invalid username or password.";
                }
            } else {
                $loginError = "Invalid username or password.";
            }
            $stmt->close();
        } else {
            $loginError = "An error occurred during login. Please try again later.";
            error_log("Login SQL Prepare Error: " . $conn->error);
        }
        // $conn->close(); // Connection will be closed at the end of this script
    }
}

$pageTitle = "Login - FrugalFolio";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="/Frugalfolio/css/base.css">
    <link rel="stylesheet" href="/Frugalfolio/css/login_style.css">
</head>
<body class="login-page-body">

    <div class="login-container">
        <div class="login-box">

            <div class="login-header">
                <div class="logo-title-wrapper">
                     <img src="/Frugalfolio/images/logo.png" alt="FrugalFolio Logo" class="login-logo">
                     <span class="login-brand-title">FrugalFolio</span>
                </div>
                <h2>Welcome Back!</h2>
                <p>Please sign in to continue</p>
            </div>

            <?php if ($loginError): ?>
                <div class="login-error-message" style="background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 10px; border-radius: 5px; margin-bottom:15px;">
                    <?= htmlspecialchars($loginError) ?>
                </div>
            <?php endif; ?>

            <form action="login.php" method="post" class="login-form">
                <div class="form-group">
                    <label for="username"><i class="fas fa-user"></i> Username</label>
                    <input type="text" id="username" name="username" required placeholder="Enter your username" value="<?= $submittedUsernameValue ?>">
                </div>
                <div class="form-group">
                    <label for="password"><i class="fas fa-lock"></i> Password</label>
                    <input type="password" id="password" name="password" required placeholder="Enter your password">
                </div>
                 <div class="form-actions">
                     <button type="submit" class="btn-login">Login</button>
                 </div>
                 <div class="login-footer">
                     <!-- Future links can go here -->
                 </div>
            </form>

        </div>
    </div>

</body>
</html>

<?php
// Close the database connection at the end of the script
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
?>