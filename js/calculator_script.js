// calculator_script.js
document.addEventListener('DOMContentLoaded', function() {

    // --- Element References ---
    const itemNameInput = document.getElementById('calc_item_name');
    const suggestionsDiv = document.getElementById('calc_suggestions');
    const currentItemDiv = document.getElementById('calc_current_item_details');
    const selectedNameSpan = document.getElementById('calc_selected_name');
    const selectedUnitSpan = document.getElementById('calc_selected_unit');
    const selectedPriceSpan = document.getElementById('calc_selected_price');
    const selectedIsWtSpan = document.getElementById('calc_selected_is_wt');
    const quantityInput = document.getElementById('calc_quantity');
    const weightInput = document.getElementById('calc_weight');
    const itemEstimateSpan = document.getElementById('calc_item_estimate');
    const addToListBtn = document.getElementById('add_to_list_btn');
    const shoppingListTable = document.getElementById('shopping_list_table');
    const listGroupFresh = document.getElementById('list-group-fresh');
    const listGroupPantry = document.getElementById('list-group-pantry');
    const listGroupHousehold = document.getElementById('list-group-household');
    const listGroupOther = document.getElementById('list-group-other');
    const listPlaceholderBody = document.getElementById('list-placeholder-body');
    const listTotalPriceCell = document.getElementById('list_total_price');
    const notFoundMsg = document.getElementById('calc_item_not_found');
    const clearListBtn = document.getElementById('clear_list_btn');
    const priceChangeWarningDiv = document.getElementById('calc_price_change_warning');

    let currentItemDetails = null;
    let shoppingList = [];
    let highlightedIndex = -1;

    const categoryMap = typeof allCategoriesMap !== 'undefined' ? allCategoriesMap : {};
    const categoryGroups = {
        fresh: [2, 22, 1, 26, 24, 12], pantry: [7, 18, 6, 41, 37, 33, 13, 19, 29, 15],
        household: [8, 11, 20, 5], other: [3, 21, 4, 15, 43]
    };

    function getItemGroup(itemCategoryIds) {
        if (!Array.isArray(itemCategoryIds) || itemCategoryIds.length === 0) return 'other';
        const numericCategoryIds = itemCategoryIds.map(id => parseInt(id, 10));
        if (numericCategoryIds.some(id => categoryGroups.fresh.includes(id))) return 'fresh';
        if (numericCategoryIds.some(id => categoryGroups.pantry.includes(id))) return 'pantry';
        if (numericCategoryIds.some(id => categoryGroups.household.includes(id))) return 'household';
        return 'other';
    }

    function roundToDecimalPlace(num, decimals) {
        const factor = Math.pow(10, decimals);
        return Math.round(num * factor) / factor;
    }

    function escapeHTML(str) {
        if (str === null || str === undefined) return '';
        const div = document.createElement('div');
        div.textContent = String(str);
        return div.innerHTML;
    }

    function calculateItemEstimate() {
        if (!currentItemDetails) return 0;
        const quantity = parseFloat(quantityInput.value) || 0;
        const weight = parseFloat(weightInput.value) || 0;
        const price = parseFloat(currentItemDetails.price_per_unit) || 0;
        const isWeightBased = currentItemDetails.is_weight_based == 1;
        let total = 0;
        if (isWeightBased && weight > 0 && price > 0) {
            total = roundToDecimalPlace(weight * price, 1);
        } else if (!isWeightBased && quantity > 0 && price > 0) {
            total = roundToDecimalPlace(quantity * price, 2);
        }
        return total;
    }

    function updateItemEstimateDisplay() {
        itemEstimateSpan.textContent = calculateItemEstimate().toFixed(2);
    }

    function renderShoppingList() {
        listGroupFresh.innerHTML = ''; listGroupPantry.innerHTML = '';
        listGroupHousehold.innerHTML = ''; listGroupOther.innerHTML = '';
        let runningTotal = 0; let itemCount = shoppingList.length;
        listPlaceholderBody.style.display = (itemCount === 0) ? '' : 'none';

        if (itemCount > 0) {
            shoppingList.forEach((item, index) => {
                const row = document.createElement('tr');
                row.dataset.index = index;
                const displayQtyWt = item.isWt ? (item.wt || 0).toFixed(3) : (item.qty || 0);
                const displayUnit = item.unit === 'N/A' || !item.unit ? '' : escapeHTML(item.unit);
                let categoryNames = 'N/A';
                if (item.category_ids && Array.isArray(item.category_ids) && item.category_ids.length > 0) {
                    categoryNames = item.category_ids.map(id =>
                        categoryMap[id] ? escapeHTML(categoryMap[id]) : 'Unknown'
                    ).join(', ');
                }
                row.innerHTML = `
                    <td>${escapeHTML(item.name)}</td><td>${categoryNames}</td>
                    <td style="text-align: right;">${displayQtyWt}</td><td>${displayUnit}</td>
                    <td style="text-align: right;">₱${(item.estPrice || 0).toFixed(2)}</td>
                    <td><button type="button" class="btn btn-danger btn-sm remove-item-btn" title="Remove Item">X</button></td>
                `;
                const group = getItemGroup(item.category_ids);
                let targetTbody;
                switch (group) {
                    case 'fresh': targetTbody = listGroupFresh; break;
                    case 'pantry': targetTbody = listGroupPantry; break;
                    case 'household': targetTbody = listGroupHousehold; break;
                    default: targetTbody = listGroupOther;
                }
                targetTbody.appendChild(row);
                runningTotal += (item.estPrice || 0);
            });
        }
        listTotalPriceCell.textContent = `₱${runningTotal.toFixed(2)}`;
    }

    function fetchAndDisplayItemDetails(itemName) {
        notFoundMsg.style.display = 'none';
        if (priceChangeWarningDiv) {
            priceChangeWarningDiv.style.display = 'none';
            priceChangeWarningDiv.innerHTML = '';
        }

        fetch(`get_latest_item_price.php?item_name=${encodeURIComponent(itemName)}`)
            .then(response => {
                if (!response.ok) throw new Error('Network response error: ' + response.statusText);
                return response.json();
            })
            .then(details => {
                if (details) {
                    currentItemDetails = details;
                    currentItemDiv.style.display = 'block';
                    selectedNameSpan.textContent = escapeHTML(details.item_name);
                    selectedUnitSpan.textContent = escapeHTML(details.unit);
                    selectedPriceSpan.textContent = parseFloat(details.price_per_unit || 0).toFixed(2);
                    selectedIsWtSpan.textContent = details.is_weight_based == 1 ? 'Yes' : 'No';

                    let fieldToFocusAndScroll = null;

                    if (details.is_weight_based == 1) {
                        quantityInput.value = '1'; quantityInput.disabled = true;
                        weightInput.value = parseFloat(details.weight || 0.500).toFixed(3);
                        weightInput.disabled = false;
                        fieldToFocusAndScroll = weightInput;
                    } else {
                        quantityInput.value = details.quantity || 1; quantityInput.disabled = false;
                        weightInput.value = parseFloat(details.weight || 0.000).toFixed(3);
                        weightInput.disabled = true;
                        fieldToFocusAndScroll = quantityInput;
                    }
                    updateItemEstimateDisplay();

                    if (priceChangeWarningDiv && details.price_comparison && details.price_comparison.difference > 0) {
                        const pc = details.price_comparison;
                        priceChangeWarningDiv.innerHTML = `<i class="fas fa-exclamation-triangle"></i> ${escapeHTML(`Note: Price increased by ₱${pc.difference.toFixed(2)} (from ₱${pc.old_price.toFixed(2)} on ${pc.old_date} to ₱${pc.new_price.toFixed(2)} on ${pc.new_date}).`)}`;
                        priceChangeWarningDiv.style.display = 'block';
                    }

                    if (fieldToFocusAndScroll) {
                        setTimeout(() => {
                            fieldToFocusAndScroll.focus();
                            fieldToFocusAndScroll.select();
                            currentItemDiv.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        }, 50);
                    }
                } else {
                    currentItemDetails = null;
                    currentItemDiv.style.display = 'none';
                    notFoundMsg.style.display = 'block';
                    if (priceChangeWarningDiv) priceChangeWarningDiv.style.display = 'none';
                }
            })
            .catch(error => {
                console.error('Error fetching item details:', error);
                currentItemDetails = null;
                currentItemDiv.style.display = 'none';
                notFoundMsg.textContent = 'Error fetching item details.';
                notFoundMsg.style.display = 'block';
                if (priceChangeWarningDiv) priceChangeWarningDiv.style.display = 'none';
            });
    }

    function resetInputArea() {
        itemNameInput.value = '';
        currentItemDiv.style.display = 'none';
        currentItemDetails = null;
        quantityInput.value = '1'; quantityInput.disabled = false;
        weightInput.value = '0.000'; weightInput.disabled = false;
        itemEstimateSpan.textContent = '0.00';
        notFoundMsg.style.display = 'none';
        suggestionsDiv.innerHTML = ''; suggestionsDiv.style.display = 'none';
        highlightedIndex = -1;
        if (priceChangeWarningDiv) {
            priceChangeWarningDiv.style.display = 'none';
            priceChangeWarningDiv.innerHTML = '';
        }
        
        itemNameInput.focus(); // Focus still happens on the input itself
    // --- MODIFICATION: Scroll the input area into view after reset ---
    if (calculatorInputArea) { // Check if the container element exists
        setTimeout(() => { // Use a small timeout
            calculatorInputArea.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            // You can try 'start' or 'center' if 'nearest' isn't quite right,
            // but 'nearest' or 'start' is usually good for bringing the top of the input area into view.
        }, 50); // Small delay to allow focus to settle, especially on mobile
    }
    }

    function selectSuggestion(suggestionElement) {
        if (suggestionElement) {
            const selectedText = suggestionElement.textContent;
            itemNameInput.value = selectedText;
            suggestionsDiv.innerHTML = '';
            suggestionsDiv.style.display = 'none';
            highlightedIndex = -1;
            fetchAndDisplayItemDetails(selectedText);
        }
    }

    function updateHighlight() {
        const suggestionItems = suggestionsDiv.querySelectorAll('div');
        suggestionItems.forEach((item, index) => {
            item.classList.remove('suggestion-highlight');
            item.style.backgroundColor = 'white';
        });
        if (highlightedIndex > -1 && highlightedIndex < suggestionItems.length) {
            const highlightedItem = suggestionItems[highlightedIndex];
            highlightedItem.classList.add('suggestion-highlight');
            highlightedItem.style.backgroundColor = '#e9ecef';
            highlightedItem.scrollIntoView({ block: 'nearest' });
        }
    }

    // --- NEW: Function to handle "Done" action on number inputs ---
    function handleNumberInputDone(event) {
        event.target.blur();
        if (selectedNameSpan) {
            selectedNameSpan.classList.add('temp-highlight');
            setTimeout(() => selectedNameSpan.classList.remove('temp-highlight'), 1500);
        }
        if (itemEstimateSpan) {
            const strongTag = itemEstimateSpan.closest('.form-group')?.querySelector('strong');
            if(strongTag){
                strongTag.classList.add('temp-highlight-text');
                 setTimeout(() => strongTag.classList.remove('temp-highlight-text'), 1500);
            } else { 
                itemEstimateSpan.classList.add('temp-highlight-text');
                setTimeout(() => itemEstimateSpan.classList.remove('temp-highlight-text'), 1500);
            }
        }
        if (addToListBtn) {
            addToListBtn.scrollIntoView({ behavior: 'smooth', block: 'center' });
            addToListBtn.classList.add('temp-highlight-button');
            setTimeout(() => addToListBtn.classList.remove('temp-highlight-button'), 1500);
        }
    }
    // --- END NEW FUNCTION ---

    if (itemNameInput) {
        itemNameInput.addEventListener('input', function() {
            const term = this.value;
            suggestionsDiv.innerHTML = ''; suggestionsDiv.style.display = 'none';
            currentItemDiv.style.display = 'none'; notFoundMsg.style.display = 'none';
            if (priceChangeWarningDiv) priceChangeWarningDiv.style.display = 'none';
            currentItemDetails = null; highlightedIndex = -1;

            if (term.length < 2) return;

            fetch(`autocomplete_suggestions.php?term=${encodeURIComponent(term)}`)
                .then(response => response.ok ? response.json() : Promise.reject('Network error fetching suggestions'))
                .then(data => {
                    if (data && data.length > 0) {
                        suggestionsDiv.style.width = `${itemNameInput.offsetWidth}px`;
                        suggestionsDiv.style.display = 'block';
                        data.forEach(itemText => {
                            const div = document.createElement('div');
                            div.textContent = escapeHTML(itemText);
                            div.style.padding = '5px 10px'; div.style.cursor = 'pointer';
                            div.onmouseover = () => {
                                suggestionsDiv.querySelectorAll('div').forEach(i => i.style.backgroundColor = 'white');
                                div.style.backgroundColor = '#e9ecef';
                                const items = Array.from(suggestionsDiv.querySelectorAll('div'));
                                highlightedIndex = items.indexOf(div);
                            };
                            div.onmouseout = () => { div.style.backgroundColor = 'white'; };
                            div.onclick = () => selectSuggestion(div);
                            suggestionsDiv.appendChild(div);
                        });
                    }
                })
                .catch(error => console.error('Error fetching suggestions:', error));
        });

        itemNameInput.addEventListener('keydown', function(e) {
            if (suggestionsDiv.style.display !== 'block') return;
            const suggestionItems = suggestionsDiv.querySelectorAll('div');
            if (suggestionItems.length === 0) return;
            switch (e.key) {
                case 'ArrowDown': e.preventDefault(); highlightedIndex = (highlightedIndex + 1) % suggestionItems.length; updateHighlight(); break;
                case 'ArrowUp': e.preventDefault(); highlightedIndex = (highlightedIndex - 1 + suggestionItems.length) % suggestionItems.length; updateHighlight(); break;
                case 'Enter': e.preventDefault(); if (highlightedIndex > -1) { selectSuggestion(suggestionItems[highlightedIndex]); } break;
                case 'Escape': e.preventDefault(); suggestionsDiv.style.display = 'none'; highlightedIndex = -1; break;
            }
        });
    }


    document.addEventListener('click', function(e) {
        if (suggestionsDiv.style.display === 'block' && !itemNameInput.contains(e.target) && !suggestionsDiv.contains(e.target)) {
            suggestionsDiv.style.display = 'none'; highlightedIndex = -1;
        }
    });

    [quantityInput, weightInput].forEach(input => {
        if (input) {
            input.addEventListener('input', updateItemEstimateDisplay);
            input.addEventListener('keydown', function(event) {
                if (event.key === 'Enter' || event.keyCode === 13) {
                    event.preventDefault();
                    handleNumberInputDone(event);
                }
            });
        }
    });

    if (addToListBtn) {
        addToListBtn.addEventListener('click', function() {
            if (!currentItemDetails) {
                alert('Please select a valid item first.');
                itemNameInput.focus();
                return;
            }
            const estimate = calculateItemEstimate();
            if (estimate <= 0) {
                alert('Please enter a valid quantity or weight to get an estimated price.');
                if (!quantityInput.disabled) quantityInput.focus();
                else if (!weightInput.disabled) weightInput.focus();
                return;
            }
            shoppingList.push({
                name: currentItemDetails.item_name, qty: parseInt(quantityInput.value, 10) || 0,
                wt: parseFloat(weightInput.value) || 0, unit: currentItemDetails.unit,
                price: parseFloat(currentItemDetails.price_per_unit), isWt: currentItemDetails.is_weight_based == 1,
                estPrice: estimate, category_ids: currentItemDetails.category_ids || []
            });
            renderShoppingList();
            resetInputArea(); // This function now handles both focus and scroll for itemNameInput
        });
    }

    if (shoppingListTable) {
        shoppingListTable.addEventListener('click', function(event) {
            if (event.target.classList.contains('remove-item-btn')) {
                const row = event.target.closest('tr');
                if (row && row.dataset.index !== undefined) {
                    const indexToRemove = parseInt(row.dataset.index, 10);
                    if (!isNaN(indexToRemove) && indexToRemove >= 0 && indexToRemove < shoppingList.length) {
                        shoppingList.splice(indexToRemove, 1);
                        renderShoppingList();
                    }
                }
            }
        });
    }

    if (clearListBtn) {
        clearListBtn.addEventListener('click', function() {
            if (shoppingList.length > 0) {
                if (confirm('Are you sure you want to clear the entire list?')) {
                    shoppingList = []; renderShoppingList(); itemNameInput.focus();
                }
            } else {
                alert("The list is already empty.");
            }
        });
    }

    renderShoppingList();
    if(itemNameInput) itemNameInput.focus();
});