// js/tooltips.js
document.addEventListener('DOMContentLoaded', function () {
    const infoTooltips = document.querySelectorAll('.info-tooltip');
    let activeTooltip = null; // To manage a single active tooltip

    // Create a single tooltip element that will be reused
    const tooltipElement = document.createElement('div');
    tooltipElement.className = 'dynamic-tooltip';
    tooltipElement.style.position = 'fixed'; // Use fixed to position relative to viewport
    tooltipElement.style.visibility = 'hidden';
    tooltipElement.style.opacity = '0';
    tooltipElement.style.transition = 'opacity 0.2s ease-in-out, visibility 0.2s ease-in-out';
    tooltipElement.style.zIndex = '1070'; // High z-index (Bootstrap tooltip is 1070, adjust if needed)
    // Basic styling (can be moved to CSS)
    tooltipElement.style.background = '#333';
    tooltipElement.style.color = '#fff';
    tooltipElement.style.padding = '7px 12px';
    tooltipElement.style.borderRadius = '4px';
    tooltipElement.style.fontSize = '0.85em';
    tooltipElement.style.lineHeight = '1.4';
    tooltipElement.style.maxWidth = '250px'; // Max width for the tooltip
    tooltipElement.style.textAlign = 'center';
    tooltipElement.style.boxShadow = '0 2px 5px rgba(0,0,0,0.2)';
    tooltipElement.style.pointerEvents = 'none'; // Tooltip itself should not be interactive

    document.body.appendChild(tooltipElement);

    infoTooltips.forEach(iconContainer => {
        const tooltipText = iconContainer.dataset.tooltip;
        if (tooltipText) {
            iconContainer.addEventListener('mouseenter', (event) => {
                tooltipElement.textContent = tooltipText;
                positionTooltip(event.currentTarget, tooltipElement);
                tooltipElement.style.visibility = 'visible';
                tooltipElement.style.opacity = '1';
                activeTooltip = tooltipElement;
            });

            iconContainer.addEventListener('mouseleave', () => {
                tooltipElement.style.opacity = '0';
                tooltipElement.style.visibility = 'hidden';
                activeTooltip = null;
            });

            // Optional: Add focus/blur for accessibility (keyboard navigation)
            iconContainer.setAttribute('tabindex', '0'); // Make the span focusable
            iconContainer.style.outline = 'none'; // Remove default focus outline if desired

            iconContainer.addEventListener('focus', (event) => {
                tooltipElement.textContent = tooltipText;
                positionTooltip(event.currentTarget, tooltipElement);
                tooltipElement.style.visibility = 'visible';
                tooltipElement.style.opacity = '1';
                activeTooltip = tooltipElement;
            });

            iconContainer.addEventListener('blur', () => {
                tooltipElement.style.opacity = '0';
                tooltipElement.style.visibility = 'hidden';
                activeTooltip = null;
            });
        }
    });

    function positionTooltip(targetElement, tooltipEl) {
        const rect = targetElement.getBoundingClientRect();
        const tooltipRect = tooltipEl.getBoundingClientRect(); // Get its current size to help position

        let top = rect.top - tooltipRect.height - 10; // 10px gap above the target
        let left = rect.left + (rect.width / 2) - (tooltipRect.width / 2);

        // Adjust if tooltip goes off-screen top (unlikely for top-positioned)
        if (top < 0) {
            top = rect.bottom + 10; // Position below if no space above
        }

        // Adjust if tooltip goes off-screen left
        if (left < 0) {
            left = 5; // 5px padding from edge
        }
        // Adjust if tooltip goes off-screen right
        if (left + tooltipRect.width > window.innerWidth) {
            left = window.innerWidth - tooltipRect.width - 5;
        }

        tooltipEl.style.top = `${top}px`;
        tooltipEl.style.left = `${left}px`;
    }

    // Re-position active tooltip on window scroll or resize (debounced)
    let resizeScrollTimer;
    function handleReposition() {
        if (activeTooltip && activeTooltip.style.visibility === 'visible') {
            // Find the original target for the active tooltip. This is tricky.
            // For simplicity, this example doesn't re-find the target on scroll.
            // A more robust solution might store the current target element.
            // For now, we'll just hide it on scroll/resize to avoid misplacement.
            activeTooltip.style.opacity = '0';
            activeTooltip.style.visibility = 'hidden';
            activeTooltip = null;
        }
    }

    window.addEventListener('scroll', () => {
        clearTimeout(resizeScrollTimer);
        resizeScrollTimer = setTimeout(handleReposition, 50);
    }, true); // Use capture phase for scroll

    window.addEventListener('resize', () => {
        clearTimeout(resizeScrollTimer);
        resizeScrollTimer = setTimeout(handleReposition, 50);
    });

});