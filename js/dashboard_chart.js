// js/dashboard_chart.js - REVISED for Grouped Bar Chart

document.addEventListener('DOMContentLoaded', function() {

    // --- Find the Canvas ---
    const barCanvas = document.getElementById('categoryBarChart'); // Use the new ID
    if (!barCanvas) {
        console.error("Canvas element #categoryBarChart not found!");
        // Optionally display an error message in the chart container
        const chartContainer = document.querySelector('.card-chart .chart-body-area');
        if (chartContainer) {
             chartContainer.innerHTML = '<p style="color: red; text-align: center; padding-top: 20px;">Chart canvas error.</p>';
        }
        return; // Stop if canvas not found
    }
    const barCtx = barCanvas.getContext('2d');
    if (!barCtx) {
         console.error("Failed to get 2D context for #categoryBarChart!");
          if (barCanvas.parentNode) {
             barCanvas.parentNode.innerHTML = '<p style="color: red; text-align: center; padding-top: 20px;">Chart context error.</p>';
         }
        return; // Stop if context fails
    }

    // --- Fetch Data ---
    fetch('/Frugalfolio/get_category_spending.php')
        .then(response => {
            if (!response.ok) {
                // Try to get more info for non-JSON errors
                return response.text().then(text => {
                    throw new Error(`Network response was not ok (${response.status}). Server response: ${text.substring(0, 200)}...`);
                });
            }
            const contentType = response.headers.get("content-type");
            if (contentType && contentType.includes("application/json")) {
                return response.json();
            } else {
                return response.text().then(text => {
                    throw new Error(`Expected JSON, got non-JSON response: ${text.substring(0, 200)}...`);
                });
            }
        })
        .then(data => {
            // --- Data Validation ---
            if (data.error) {
                console.error("Error from PHP (Bar Chart):", data.error_details || data.error);
                if (barCanvas.parentNode) barCanvas.parentNode.innerHTML = '<p style="color: red; text-align: center; padding-top: 20px;">Could not load comparison data.</p>';
                return;
            }
            // Check if essential data parts exist and labels array is not empty
             if (!data || !data.latest_month || !data.previous_month || !Array.isArray(data.all_labels) || data.all_labels.length === 0 || !Array.isArray(data.latest_month.data) || !Array.isArray(data.previous_month.data)) {
                 console.warn("No valid category comparison data received for bar chart, or structure is incorrect.", data);
                 if (!data.error && barCanvas.parentNode) {
                     barCanvas.parentNode.innerHTML = '<p style="color: #888; text-align: center; padding-top: 20px;">Not enough data for monthly comparison.</p>';
                 }
                 return;
             }
             // Further check: Ensure data arrays have lengths matching labels
             if (data.latest_month.data.length !== data.all_labels.length || data.previous_month.data.length !== data.all_labels.length) {
                  console.warn("Data array length mismatch with labels.", data);
                  if (barCanvas.parentNode) barCanvas.parentNode.innerHTML = '<p style="color: #888; text-align: center; padding-top: 20px;">Data inconsistency found.</p>';
                  return;
             }


            // --- Chart Configuration ---
            const previousMonthLabel = data.previous_month.period_label || 'Previous Month';
            const latestMonthLabel = data.latest_month.period_label || 'Latest Month';

            // Define modern colors
            const previousMonthColor = 'rgba(160, 174, 192, 0.7)'; // Cool Gray
            const latestMonthColor = 'rgba(56, 178, 172, 0.7)';   // Teal
            const previousMonthBorder = 'rgba(160, 174, 192, 1)';
            const latestMonthBorder = 'rgba(56, 178, 172, 1)';

            new Chart(barCtx, {
                type: 'bar',
                data: {
                    labels: data.all_labels, // Category group names from PHP
                    datasets: [
                        {
                            label: previousMonthLabel,
                            data: data.previous_month.data,
                            backgroundColor: previousMonthColor,
                            borderColor: previousMonthBorder,
                            borderWidth: 1,
                            borderRadius: 5, // Rounded corners for bars
                            borderSkipped: false, // Apply radius to all corners
                        },
                        {
                            label: latestMonthLabel,
                            data: data.latest_month.data,
                            backgroundColor: latestMonthColor,
                            borderColor: latestMonthBorder,
                            borderWidth: 1,
                            borderRadius: 5,
                            borderSkipped: false,
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: { // Y-axis (Spending Amount)
                            beginAtZero: true,
                            grid: {
                                color: '#e2e8f0', // Lighter grid lines
                                // drawBorder: false, // Optional: remove y-axis border line
                            },
                            ticks: {
                                color: '#64748b', // Axis labels color
                                // Callback to format ticks as currency
                                callback: function(value, index, values) {
                                    return '₱' + value.toLocaleString(); // Format as currency
                                }
                            }
                        },
                        x: { // X-axis (Category Groups)
                             grid: {
                                display: false, // Hide vertical grid lines
                                drawBorder: false,
                             },
                             ticks: {
                                 color: '#64748b' // Axis labels color
                             }
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 20, // More padding for legend
                                boxWidth: 15,
                                color: '#475569' // Legend text color
                            }
                        },
                        tooltip: {
                            // Enabled by default, customize callbacks for better formatting
                            backgroundColor: 'rgba(0, 0, 0, 0.8)', // Darker tooltip
                            titleFont: { size: 14 },
                            bodyFont: { size: 12 },
                            padding: 10,
                            boxPadding: 5, // Padding within the tooltip box
                            callbacks: {
                                // Format the title (Category Group)
                                title: function(tooltipItems) {
                                    return tooltipItems[0].label; // The category group name
                                },
                                // Format the label for each dataset (Month: Amount)
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    if (context.parsed.y !== null) {
                                        // Format value as currency
                                        label += '₱' + context.parsed.y.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                                    }
                                    return label;
                                }
                            }
                        },
                        // Datalabels plugin is registered but not used here for cleaner bars
                        // To enable, remove this or set display: true/auto and configure formatter
                        datalabels: {
                           display: false,
                        }
                    },
                    // Interaction tuning (optional)
                    interaction: {
                         mode: 'index', // Show tooltips for all bars at the same index
                         intersect: false,
                    },
                    // Hover effects are built-in, can be customized further if needed
                     onHover: (event, chartElement) => {
                        // Change cursor to pointer on hover over bars
                        event.native.target.style.cursor = chartElement[0] ? 'pointer' : 'default';
                     }
                }
            });

        })
        .catch(error => {
            console.error('Error fetching or processing bar chart data:', error);
             if (barCanvas.parentNode) {
                 barCanvas.parentNode.innerHTML = `<p style="color: red; text-align: center; padding-top: 20px;">Error loading comparison data. ${error.message}</p>`;
             }
        });

}); // End DOMContentLoaded