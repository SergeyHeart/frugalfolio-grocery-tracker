    // Use const/let for variable declarations
    const quantityInput = document.getElementById('quantity');
    const weightInput = document.getElementById('weight');
    const priceInput = document.getElementById('price_per_unit');
    const isWeightBasedCheckbox = document.getElementById('is_weight_based');
    const totalPreviewInput = document.getElementById('total_price_preview');
    const newItemCheckbox = document.getElementById('new_item_check');
    const itemNameInput = document.getElementById('item_name');
    const categoryCheckboxes = document.querySelectorAll('input[name="category_ids[]"]');
    const unitSelect = document.getElementById('unit');
    const shopInput = document.getElementById('shop');
    const suggestionsDiv = document.createElement('div');
    const expenseForm = document.querySelector('.expense-form');

    let highlightedIndex = -1; // State for keyboard navigation

    // Setup suggestionsDiv appearance and DOM placement
    suggestionsDiv.id = 'autocomplete-suggestions';
    // Styles are better handled in CSS, but kept here if easier for now
    suggestionsDiv.style.border = '1px solid #ccc';
    suggestionsDiv.style.position = 'absolute';
    suggestionsDiv.style.backgroundColor = 'white';
    suggestionsDiv.style.zIndex = '100';
    suggestionsDiv.style.display = 'none';
    suggestionsDiv.style.maxHeight = '150px';
    suggestionsDiv.style.overflowY = 'auto';
    suggestionsDiv.style.width = `calc(${itemNameInput.offsetWidth}px)`; // Match input width initially
    itemNameInput.parentNode.style.position = 'relative'; // Needed for absolute positioning
    itemNameInput.parentNode.appendChild(suggestionsDiv);

    // --- Reusable Functions ---

    function selectSuggestion(suggestionElement) {
        if (suggestionElement) {
            itemNameInput.value = suggestionElement.textContent;
            suggestionsDiv.innerHTML = '';
            suggestionsDiv.style.display = 'none';
            highlightedIndex = -1;
            fetchItemDetails(suggestionElement.textContent);
        }
    }

    function updateHighlight() {
        const suggestionItems = suggestionsDiv.querySelectorAll('div');
        suggestionItems.forEach((item, index) => {
            if (index === highlightedIndex) {
                item.classList.add('suggestion-highlight');
                item.scrollIntoView({ block: 'nearest' });
            } else {
                item.classList.remove('suggestion-highlight');
            }
        });
    }

    function fetchItemDetails(selectedItemName) {
        if (newItemCheckbox.checked) return; // Respect checkbox

        fetch(`get_item_details.php?item_name=${encodeURIComponent(selectedItemName)}`)
             .then(response => {
                if (!response.ok) { throw new Error(`HTTP error! status: ${response.status}`); }
                return response.json();
             })
            .then(details => {
                if (details) {
                    quantityInput.value = details.quantity || 1;
                    weightInput.value = parseFloat(details.weight || 0).toFixed(3);
                    unitSelect.value = details.unit || 'N/A';
                    priceInput.value = parseFloat(details.price_per_unit || 0).toFixed(2);
                    isWeightBasedCheckbox.checked = (details.is_weight_based == 1);
                    shopInput.value = details.shop || 'KCC'; // Default to KCC if not provided

                    // Handle categories
                    categoryCheckboxes.forEach(cb => cb.checked = false); // Clear first
                    if (details.categories && Array.isArray(details.categories)) {
                        details.categories.forEach(catId => {
                            const checkbox = document.getElementById(`category_${catId}`);
                            if (checkbox) checkbox.checked = true;
                        });
                    }
                    calculatePreview(); // Update preview after filling
                } else {
                    console.warn('No details found for item:', selectedItemName);
                }
            })
            .catch(error => console.error('Error fetching item details:', error));
    }

    function calculatePreview() {
        const quantity = parseFloat(quantityInput.value) || 0;
        const weight = parseFloat(weightInput.value) || 0;
        const price = parseFloat(priceInput.value) || 0;
        const isWeightBased = isWeightBasedCheckbox.checked;
        let total = 0;

        if (isWeightBased && weight > 0 && price > 0) {
             let subtotal = weight * price;
             total = Math.round(subtotal * 10) / 10; // Round to 1 decimal
        } else if (!isWeightBased && quantity > 0 && price > 0) {
             let subtotal = quantity * price;
             total = Math.round(subtotal * 100) / 100; // Round to 2 decimals
        }
        totalPreviewInput.value = total.toFixed(2);
    }

    // --- Event Listeners ---

    // Autocomplete Fetch
    itemNameInput.addEventListener('input', function() {
        const term = this.value;
        suggestionsDiv.innerHTML = ''; // Clear previous
        suggestionsDiv.style.display = 'none';
        highlightedIndex = -1;

        if (term.length < 2 || newItemCheckbox.checked) return;

        fetch(`autocomplete_suggestions.php?term=${encodeURIComponent(term)}`)
            .then(response => { if (!response.ok) throw new Error('Network response was not ok'); return response.json(); })
            .then(data => {
                if (data.length > 0) {
                    suggestionsDiv.style.width = `${itemNameInput.offsetWidth}px`; // Adjust width
                    suggestionsDiv.style.display = 'block';
                    data.forEach(itemText => {
                        const div = document.createElement('div');
                        div.textContent = itemText;
                        div.style.padding = '5px 10px';
                        div.style.cursor = 'pointer';
                        // Hover handled by CSS now
                        div.addEventListener('click', () => selectSuggestion(div)); // Use listener
                        suggestionsDiv.appendChild(div);
                    });
                }
            })
            .catch(error => console.error('Error fetching suggestions:', error));
    });

    // Keyboard Navigation for Autocomplete
    itemNameInput.addEventListener('keydown', function(e) {
        const suggestionItems = suggestionsDiv.querySelectorAll('div');
        if (suggestionsDiv.style.display !== 'block' || suggestionItems.length === 0) return;

        switch (e.key) {
            case 'ArrowDown':
                e.preventDefault();
                highlightedIndex = (highlightedIndex + 1) % suggestionItems.length;
                updateHighlight();
                break;
            case 'ArrowUp':
                e.preventDefault();
                highlightedIndex = (highlightedIndex - 1 + suggestionItems.length) % suggestionItems.length;
                updateHighlight();
                break;
            case 'Enter':
                e.preventDefault();
                if (highlightedIndex > -1) {
                    selectSuggestion(suggestionItems[highlightedIndex]);
                }
                break;
            case 'Escape':
                suggestionsDiv.style.display = 'none';
                highlightedIndex = -1;
                break;
        }
    });

    // Hide suggestions on outside click
    document.addEventListener('click', function(e) {
         if (suggestionsDiv.style.display === 'block' && !itemNameInput.contains(e.target) && !suggestionsDiv.contains(e.target)) {
            suggestionsDiv.style.display = 'none';
            highlightedIndex = -1;
        }
    });

    // New Item Checkbox logic
     newItemCheckbox.addEventListener('change', function() {
        if (this.checked) {
             suggestionsDiv.innerHTML = '';
             suggestionsDiv.style.display = 'none';
             highlightedIndex = -1;
        }
    });

    // Recalculate preview on input change
    quantityInput.addEventListener('input', calculatePreview);
    weightInput.addEventListener('input', calculatePreview);
    priceInput.addEventListener('input', calculatePreview);
    isWeightBasedCheckbox.addEventListener('change', calculatePreview);

    // High Price Confirmation on Submit
    expenseForm.addEventListener('submit', function(event) {
        const price = parseFloat(priceInput.value) || 0;
        const priceThreshold = 2000; // Your threshold

        if (price > priceThreshold) {
            const userConfirmed = confirm(`The Price per Unit (${price.toFixed(2)}) seems very high (over ${priceThreshold}). Are you sure?`);
            if (!userConfirmed) {
                event.preventDefault(); // Stop submission
            }
        }
    });

    // Initial calculation on page load
    calculatePreview();