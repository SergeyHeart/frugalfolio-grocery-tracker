<?php
// auth_bootstrap.php

// This file is MEANT to be included.
// The FRUGALFOLIO_ACCESS constant should be defined in the SCRIPT THAT INCLUDES THIS.
// However, for includes *within* this bootstrap file, like db_connection,
// we need to ensure the constant is defined here first if the outer script might not have.
// A better approach for db_connection is to ensure it's always included by a script that *has* defined FRUGALFOLIO_ACCESS.

// Let's ensure FRUGALFOLIO_ACCESS is defined before db_connection.php is required.
// The top-level script (e.g., autocomplete_suggestions.php) should define it.
// This script (auth_bootstrap.php) *relies* on it being defined by the caller.

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// If FRUGALFOLIO_ACCESS is not defined by the calling script, it's an issue.
// However, to prevent db_connection.php from dying IF auth_bootstrap.php itself
// is the point where FRUGALFOLIO_ACCESS is conceptually "activated" for its children,
// we can define it here.
// BUT, the ideal pattern is:
// top_script.php: define('FRUGALFOLIO_ACCESS', true); require 'auth_bootstrap.php';
// auth_bootstrap.php: require 'db_connection.php'; // db_connection sees the define from top_script

// The issue arises if db_connection.php is required *before* the calling script's
// FRUGALFOLIO_ACCESS definition is "visible" or if auth_bootstrap might be included
// in a context where the top-level script didn't define it (which would be a structural flaw).

// Let's assume the calling script (like autocomplete_suggestions.php) already defines FRUGALFOLIO_ACCESS.
// The problem might be if $conn check leads to re-including db_connection without context.

// Revised logic for requiring db_connection.php:
// Ensure db_connection.php is only included if $conn is not already set AND
// FRUGALFOLIO_ACCESS is defined (which it should be by the calling script).

if (!isset($conn) || !($conn instanceof mysqli)) {
    if (!defined('FRUGALFOLIO_ACCESS')) {
        // This is a critical error: auth_bootstrap was called without the guard.
        // This shouldn't happen if all entry scripts define FRUGALFOLIO_ACCESS.
        error_log("auth_bootstrap.php: FRUGALFOLIO_ACCESS not defined by calling script when attempting to include db_connection.php");
        die("Critical application setup error in auth_bootstrap."); // Prevent further execution
    }
    // Now it's safe to require db_connection.php, as FRUGALFOLIO_ACCESS is confirmed to be defined.
    require_once __DIR__ . '/db_connection.php';
}


function require_login() {
    // Check if this page has been explicitly marked as public
    if (defined('FRUGALFOLIO_IS_PUBLIC_PAGE') && FRUGALFOLIO_IS_PUBLIC_PAGE === true) { // Corrected constant name
        return;
    }

    if (!isset($_SESSION['user_id'])) {
        if (!empty($_SERVER['REQUEST_URI'])) {
            $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
        }
        header('Location: login.php');
        exit;
    }
}

$loggedInUserId = $_SESSION['user_id'] ?? null;
$loggedInUserRole = $_SESSION['role'] ?? null;
$loggedInUserDisplayName = $_SESSION['display_name'] ?? null;
?>