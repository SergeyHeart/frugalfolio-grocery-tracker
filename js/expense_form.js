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

const addExpenseButton = document.querySelector('.expense-form .form-actions button[type="submit"]');
// Or by ID if you added one: const addExpenseButton = document.getElementById('addExpenseSubmitBtn');
const formActionsContainer = document.querySelector('.expense-form .form-actions');


let highlightedIndex = -1;

// --- Setup suggestionsDiv ---
if (itemNameInput) {
    suggestionsDiv.id = 'autocomplete-suggestions';
    // CSS should ideally handle these styles
    suggestionsDiv.style.border = '1px solid #ccc';
    suggestionsDiv.style.position = 'absolute';
    suggestionsDiv.style.backgroundColor = 'white';
    suggestionsDiv.style.zIndex = '100';
    suggestionsDiv.style.display = 'none';
    suggestionsDiv.style.maxHeight = '150px';
    suggestionsDiv.style.overflowY = 'auto';
    if (itemNameInput.parentNode) {
         itemNameInput.parentNode.style.position = 'relative';
         itemNameInput.parentNode.appendChild(suggestionsDiv);
    }
} else {
    console.warn("Item name input not found, autocomplete disabled.");
}

// --- Reusable Functions ---

function selectSuggestion(suggestionElement) {
    if (suggestionElement && itemNameInput && suggestionsDiv) {
        itemNameInput.value = suggestionElement.textContent;
        suggestionsDiv.innerHTML = '';
        suggestionsDiv.style.display = 'none';
        highlightedIndex = -1;

        if (typeof fetchItemDetails === "function") {
             fetchItemDetails(suggestionElement.textContent); // This will now handle the focus
        }

        // Scroll to action button after a short delay
        setTimeout(() => {
            if (addExpenseButton && addExpenseButton.offsetParent !== null) { // Check if visible
                addExpenseButton.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            } else if (formActionsContainer && formActionsContainer.offsetParent !== null) {
                formActionsContainer.scrollIntoView({ behavior: 'smooth', block: 'end' });
            }
        }, 150); // Increased delay slightly to allow focus to settle before scroll
    }
}

function updateHighlight() {
     if(suggestionsDiv) {
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
}

function fetchItemDetails(selectedItemName) {
    if (!newItemCheckbox || !quantityInput || !weightInput || !unitSelect || !priceInput || !isWeightBasedCheckbox || !shopInput || !categoryCheckboxes) {
        console.warn("One or more form elements missing for fetchItemDetails.");
        return;
    }
    if (newItemCheckbox.checked) return;

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
                shopInput.value = details.shop || 'KCC';

                categoryCheckboxes.forEach(cb => cb.checked = false);
                if (details.categories && Array.isArray(details.categories)) {
                    details.categories.forEach(catId => {
                        const checkbox = document.getElementById(`category_${catId}`);
                        if (checkbox) checkbox.checked = true;
                    });
                }
                calculatePreview();

                // --- ADD AUTOFOCUS LOGIC HERE ---
                // Using a minimal timeout to ensure the DOM has updated from any autofill
                // before trying to focus. Also helps if the keyboard is involved.
                setTimeout(() => {
                    if (details.is_weight_based == 1) {
                        if (weightInput && weightInput.offsetParent !== null) { // Check if visible
                            weightInput.focus();
                            // Optional: Select text in weight input for easy replacement
                            // weightInput.select();
                        }
                    } else {
                        if (priceInput && priceInput.offsetParent !== null) { // Check if visible
                            priceInput.focus();
                            // Optional: Select text in price input
                            // priceInput.select();
                        }
                    }
                }, 50); // 50ms delay, adjust if needed
                // --- END AUTOFOCUS LOGIC ---

            } else {
                console.warn('No details found for item:', selectedItemName);
                // Optional: Clear fields if no details found, or leave as is
                // quantityInput.value = '1';
                // weightInput.value = '0.000';
                // priceInput.value = '0.00';
                // etc.
            }
        })
        .catch(error => {
            console.error('Error fetching item details:', error);
            // Potentially clear fields or show a user-facing error here
        });
}

function calculatePreview() {
     if(!quantityInput || !weightInput || !priceInput || !isWeightBasedCheckbox || !totalPreviewInput) {
         return;
     }
    const quantity = parseFloat(quantityInput.value) || 0;
    const weight = parseFloat(weightInput.value) || 0;
    const price = parseFloat(priceInput.value) || 0;
    const isWeightBased = isWeightBasedCheckbox.checked;
    let total = 0;

    if (isWeightBased && weight > 0 && price > 0) {
         let subtotal = weight * price;
         total = Math.round(subtotal * 10) / 10;
    } else if (!isWeightBased && quantity > 0 && price > 0) {
         let subtotal = quantity * price;
         total = Math.round(subtotal * 100) / 100;
    }
    if (totalPreviewInput) { // Check if totalPreviewInput exists
        totalPreviewInput.value = total.toFixed(2);
    }
}

// --- Event Listeners ---
if (itemNameInput && newItemCheckbox && suggestionsDiv) {
    itemNameInput.addEventListener('input', function() {
        const term = this.value;
        if (suggestionsDiv) {
            suggestionsDiv.innerHTML = '';
            suggestionsDiv.style.display = 'none';
        }
        highlightedIndex = -1;

        if (term.length < 2 || (newItemCheckbox && newItemCheckbox.checked)) return;

        fetch(`autocomplete_suggestions.php?term=${encodeURIComponent(term)}`)
            .then(response => { if (!response.ok) throw new Error('Network response was not ok'); return response.json(); })
            .then(data => {
                if (data.length > 0 && suggestionsDiv) {
                    suggestionsDiv.style.width = `${itemNameInput.offsetWidth}px`;
                    suggestionsDiv.style.display = 'block';
                    data.forEach(itemText => {
                        const div = document.createElement('div');
                        div.textContent = itemText;
                        div.style.padding = '5px 10px'; // Consider moving to CSS
                        div.style.cursor = 'pointer';  // Consider moving to CSS
                        div.addEventListener('click', () => selectSuggestion(div));
                        suggestionsDiv.appendChild(div);
                    });
                     updateHighlight();
                }
            })
            .catch(error => console.error('Error fetching suggestions:', error));
    });

    itemNameInput.addEventListener('keydown', function(e) {
        if (!suggestionsDiv) return;
        const suggestionItems = suggestionsDiv.querySelectorAll('div');
        if (suggestionsDiv.style.display !== 'block' || suggestionItems.length === 0) return;
         switch (e.key) {
            case 'ArrowDown': e.preventDefault(); highlightedIndex = (highlightedIndex + 1) % suggestionItems.length; updateHighlight(); break;
            case 'ArrowUp': e.preventDefault(); highlightedIndex = (highlightedIndex - 1 + suggestionItems.length) % suggestionItems.length; updateHighlight(); break;
            case 'Enter': e.preventDefault(); if (highlightedIndex > -1 && highlightedIndex < suggestionItems.length) { selectSuggestion(suggestionItems[highlightedIndex]); } break;
            case 'Escape': suggestionsDiv.style.display = 'none'; highlightedIndex = -1; break;
         }
    });

     if(newItemCheckbox) {
         newItemCheckbox.addEventListener('change', function() {
            if (this.checked && suggestionsDiv) {
                 suggestionsDiv.innerHTML = '';
                 suggestionsDiv.style.display = 'none';
                 highlightedIndex = -1;
            }
        });
     }


     document.addEventListener('click', function(e) {
         if (suggestionsDiv && suggestionsDiv.style.display === 'block' && itemNameInput && !itemNameInput.contains(e.target) && !suggestionsDiv.contains(e.target)) {
            suggestionsDiv.style.display = 'none';
            highlightedIndex = -1;
        }
    });
}

// Add null checks for all event listeners
if (quantityInput) quantityInput.addEventListener('input', calculatePreview);
if (weightInput) weightInput.addEventListener('input', calculatePreview);
if (priceInput) priceInput.addEventListener('input', calculatePreview);
if (isWeightBasedCheckbox) isWeightBasedCheckbox.addEventListener('change', calculatePreview);

if (expenseForm && priceInput) {
    expenseForm.addEventListener('submit', function(event) {
        const priceVal = parseFloat(priceInput.value) || 0; // Ensure priceVal is defined
        const priceThreshold = 2000;
        if (priceVal > priceThreshold) {
            const userConfirmed = confirm(`The Price per Unit (${priceVal.toFixed(2)}) seems very high (over ${priceThreshold}). Are you sure?`);
            if (!userConfirmed) {
                event.preventDefault();
            }
        }
    });
}

// Initial calculation only if all elements are present
if (quantityInput && weightInput && priceInput && isWeightBasedCheckbox && totalPreviewInput) {
    calculatePreview();
}