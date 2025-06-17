// Receipt Scanner JavaScript
document.addEventListener('DOMContentLoaded', function() {
    const fileInput = document.getElementById('receipt-files');
    const uploadLabel = document.querySelector('.upload-label');
    const previewSection = document.querySelector('.preview-section');
    const processingSection = document.querySelector('.processing-section');
    const resultsSection = document.querySelector('.results-section');
    const progressBar = document.querySelector('.progress');
    const statusText = document.querySelector('.status-text');

    // Handle drag and drop
    uploadLabel.addEventListener('dragover', (e) => {
        e.preventDefault();
        uploadLabel.classList.add('dragover');
    });

    uploadLabel.addEventListener('dragleave', () => {
        uploadLabel.classList.remove('dragover');
    });

    uploadLabel.addEventListener('drop', (e) => {
        e.preventDefault();
        uploadLabel.classList.remove('dragover');
        const files = e.dataTransfer.files;
        handleFiles(files);
    });

    // Handle file input change
    fileInput.addEventListener('change', (e) => {
        const files = e.target.files;
        handleFiles(files);
    });

    // Click to upload
    uploadLabel.addEventListener('click', () => {
        fileInput.click();
    });

    function handleFiles(files) {
        if (files.length === 0) return;

        previewSection.style.display = 'block';
        previewSection.innerHTML = ''; // Clear previous previews

        Array.from(files).forEach(file => {
            if (!file.type.startsWith('image/')) return;

            const reader = new FileReader();
            reader.onload = (e) => {
                const preview = document.createElement('div');
                preview.className = 'preview-item';
                preview.innerHTML = `
                    <img src="${e.target.result}" alt="Receipt preview">
                    <button class="remove-btn" title="Remove"><i class="fas fa-times"></i></button>
                `;
                previewSection.appendChild(preview);

                // Add remove functionality
                preview.querySelector('.remove-btn').addEventListener('click', () => {
                    preview.remove();
                    if (previewSection.children.length === 0) {
                        previewSection.style.display = 'none';
                    }
                });
            };
            reader.readAsDataURL(file);
        });
    }
});
