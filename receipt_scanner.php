<?php
define('FRUGALFOLIO_ACCESS', true);
require_once 'auth_bootstrap.php';
$pageTitle = "Receipt Scanner - FrugalFolio";
require_once 'header.php';
require_once 'navigation.php';
?>

<main class="main-content receipt-scanner-page">
    <div class="content-header">
        <h1>Receipt Scanner</h1>
        <p class="subtitle">Upload your grocery receipts for automatic expense tracking</p>
    </div>

    <div class="receipt-scanner-container">
        <div class="upload-section">
            <label for="receipt-files" class="upload-label">
                <i class="fas fa-cloud-upload-alt"></i>
                <span>Drop files here or click to upload</span>
            </label>
            <input type="file" id="receipt-files" accept="image/*" multiple class="file-input" style="display: none;">
        </div>
        
        <div class="preview-section" style="display: none;">
            <!-- Preview will be shown here -->
        </div>

        <div class="processing-section" style="display: none;">
            <div class="processing-status">
                <i class="fas fa-cog fa-spin"></i>
                <span class="status-text">Processing receipt...</span>
                <div class="progress-bar">
                    <div class="progress"></div>
                </div>
            </div>
        </div>

        <div class="results-section" style="display: none;">
            <h3>Extracted Items</h3>
            <div class="extracted-items">
                <!-- Results will be shown here -->
            </div>
            <div class="action-buttons">
                <button class="btn btn-primary process-another">Process Another Receipt</button>
                <button class="btn btn-success save-items">Save Items</button>
            </div>
        </div>
    </div>
</main>

<script src="/Frugalfolio/js/receipt_scanner.js?v=<?= time() ?>"></script>
<?php require_once 'footer.php'; ?>
