// js/statistics_charts.js
document.addEventListener('DOMContentLoaded', function() {

    // --- Tab Switching Logic ---
    const tabLinks = document.querySelectorAll('.stats-tabs a.tab-link');
    const tabPanels = document.querySelectorAll('.stats-content .tab-content-panel');

    if (tabLinks.length > 0 && tabPanels.length > 0) {
        tabLinks.forEach(link => {
            link.addEventListener('click', function(event) {
                event.preventDefault(); // Stop link default behavior

                const targetPanelSelector = this.getAttribute('data-tab-target');
                const targetPanel = document.querySelector(targetPanelSelector);

                if (targetPanel) {
                    // Deactivate all tabs and panels
                    tabLinks.forEach(lnk => lnk.parentNode.classList.remove('active'));
                    tabPanels.forEach(panel => panel.classList.remove('active'));

                    // Activate the clicked tab and target panel
                    this.parentNode.classList.add('active'); // Add active to the <li>
                    targetPanel.classList.add('active');

                    // Note: Chart.js might need re-rendering/resize if its panel was hidden.
                    // This often happens automatically with responsive:true, but keep in mind.
                    // You might need to find the Chart instance and call .resize() if needed.

                } else {
                    console.error(`Tab target panel not found: ${targetPanelSelector}`);
                }
            });
        });
    } else {
        if(tabLinks.length === 0) console.error("No tab links found with '.stats-tabs a.tab-link'");
        if(tabPanels.length === 0) console.error("No tab panels found with '.stats-content .tab-content-panel'");
    }
    // --- End Tab Switching Logic ---


    // --- Monthly Spending Line Chart ---
    const monthlyCtx = document.getElementById('monthlySpendingChart');
    const monthlyErrorDiv = document.getElementById('monthlySpendingChartError');

    if (monthlyCtx && monthlyErrorDiv) {
        fetch('/Frugalfolio/get_monthly_spending.php') // Assumes this PHP file exists and returns correct JSON
            .then(response => {
                if (!response.ok) throw new Error(`Network error: ${response.statusText}`);
                return response.json();
            })
            .then(data => {
                monthlyErrorDiv.textContent = ''; // Clear previous errors
                if (data.error) { throw new Error(`Data error: ${data.error}`); }
                if (!data || !Array.isArray(data.labels) || !Array.isArray(data.data) || data.labels.length === 0) {
                    console.warn("No valid monthly spending data received.");
                    monthlyErrorDiv.textContent = 'No monthly spending data available.';
                    return;
                }
                // Create the Line Chart
                new Chart(monthlyCtx, {
                    type: 'line',
                    data: {
                        labels: data.labels,
                        datasets: [{
                            label: 'Total Spent per Month',
                            data: data.data,
                            fill: false,
                            borderColor: 'rgb(75, 192, 192)', // Teal
                            tension: 0.1
                        }]
                    },
                    options: { // Basic options - add currency formatting etc. back if needed
                         responsive: true,
                         maintainAspectRatio: false,
                         scales: { y: { beginAtZero: true } }
                         // Add plugins (tooltip, title, legend) options back as needed
                    }
                });
            })
            .catch(error => {
                console.error('Error fetching/rendering monthly chart:', error);
                monthlyErrorDiv.textContent = 'Error loading monthly spending data.';
            });
    } else {
         if(!monthlyCtx) console.error("Canvas element #monthlySpendingChart not found!");
         if(!monthlyErrorDiv) console.error("Error display div #monthlySpendingChartError not found!");
    }
    // --- End Monthly Chart Logic ---


    // --- Weekly Spending Bar Chart ---
    const weeklyCtx = document.getElementById('weeklySpendingChart');
    const weeklyErrorDiv = document.getElementById('weeklySpendingChartError');

    if (weeklyCtx && weeklyErrorDiv) {
        fetch('/Frugalfolio/get_weekly_spending.php') // Assumes this PHP file exists and returns correct JSON
            .then(response => {
                if (!response.ok) throw new Error(`Network error: ${response.statusText}`);
                return response.json();
            })
            .then(data => {
                weeklyErrorDiv.textContent = '';
                if (data.error) { throw new Error(`Data error: ${data.error} ${data.details || ''}`); }
                if (!data || !Array.isArray(data.labels) || !Array.isArray(data.data) || data.labels.length === 0) {
                    console.warn("No valid weekly spending data received.");
                    weeklyErrorDiv.textContent = 'No weekly spending data available for the period.';
                    return;
                }
                // Create the Bar Chart
                new Chart(weeklyCtx, {
                    type: 'bar',
                    data: {
                        labels: data.labels,
                        datasets: [{
                            label: 'Total Spent per Week',
                            data: data.data,
                            backgroundColor: 'rgba(54, 162, 235, 0.6)', // Blue
                            borderColor: 'rgba(54, 162, 235, 1)',
                            borderWidth: 1
                        }]
                    },
                    options: { // Basic options - add currency formatting etc. back if needed
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: { y: { beginAtZero: true } }
                         // Add plugins (tooltip, title, legend) options back as needed
                    }
                });
            })
            .catch(error => {
                console.error('Error fetching/rendering weekly chart:', error);
                weeklyErrorDiv.textContent = 'Error loading weekly spending data.';
            });
    } else {
        if(!weeklyCtx) console.error("Canvas element #weeklySpendingChart not found!");
        if(!weeklyErrorDiv) console.error("Error display div #weeklySpendingChartError not found!");
    }
    // --- End Weekly Chart Logic ---

    // Removed DataTables initialization logic

}); // End DOMContentLoaded