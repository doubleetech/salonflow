// SalonFlow front-end JS
//
// Phase 3: the sale-record form has a "Combination" payment option.
// When it's picked, we reveal three extra fields (cash/transfer/pos
// portions); for any other method, those fields stay hidden and their
// values are forced to 0 so they don't accidentally get submitted.
//
// This ONLY controls what the user sees. The server (CashierController's
// validateSale method) does its own checking of the numbers regardless —
// never trust anything JavaScript does as the real security or validation
// layer, since a user can always disable JS or edit the page.

document.addEventListener('DOMContentLoaded', function () {
    var methodSelect = document.getElementById('payment_method');
    var comboFields = document.getElementById('combo-fields');

    if (!methodSelect || !comboFields) {
        return; // we're not on the sale form — nothing to do
    }

    function updateComboVisibility() {
        var isCombination = methodSelect.value === 'combination';
        comboFields.hidden = !isCombination;

        if (!isCombination) {
            // Reset hidden fields to 0 so a stale value can't sneak into
            // a "cash" sale that briefly had "combination" selected earlier.
            var comboInputs = comboFields.querySelectorAll('input[type="number"]');
            comboInputs.forEach(function (input) {
                input.value = '0';
            });
        }
    }

    methodSelect.addEventListener('change', updateComboVisibility);
    updateComboVisibility(); // run once on load, e.g. when editing an existing combination sale
});

// Phase 4: the Reports filter form has a "Report Type" dropdown. Daily/
// Weekly/Monthly all use a single "Date" field (it just means different
// things depending on the type — e.g. for Weekly it picks which week).
// "Custom Range" swaps that out for separate Start/End date fields.
document.addEventListener('DOMContentLoaded', function () {
    var periodSelect = document.getElementById('period');
    var dateField = document.getElementById('date-field');
    var customFields = document.getElementById('custom-fields');

    if (!periodSelect || !dateField || !customFields) {
        return; // we're not on the reports filter form — nothing to do
    }

    function updatePeriodFields() {
        var isCustom = periodSelect.value === 'custom';
        dateField.hidden = isCustom;
        customFields.hidden = !isCustom;
    }

    periodSelect.addEventListener('change', updatePeriodFields);
    updatePeriodFields();
});

// Phase 5: Date format DD-MM-YYYY for report filters
document.addEventListener('DOMContentLoaded', function () {
    var dateInputs = document.querySelectorAll('.date-input');
    
    dateInputs.forEach(function(input) {
        // Auto-format as user types (DD-MM-YYYY)
        input.addEventListener('input', function(e) {
            // Remove all non-digits
            var value = this.value.replace(/\D/g, '');
            
            // Limit to 8 digits (DDMMYYYY)
            if (value.length > 8) {
                value = value.substring(0, 8);
            }
            
            // Format as DD-MM-YYYY
            if (value.length > 2) {
                value = value.substring(0, 2) + '-' + value.substring(2);
            }
            if (value.length > 5) {
                value = value.substring(0, 5) + '-' + value.substring(5);
            }
            
            this.value = value;
        });
        
        // Validate on blur
        input.addEventListener('blur', function() {
            var pattern = /^\d{2}-\d{2}-\d{4}$/;
            if (!pattern.test(this.value) && this.value.length > 0) {
                this.style.borderColor = 'red';
                this.title = 'Please enter date in DD-MM-YYYY format';
            } else {
                this.style.borderColor = '';
                this.title = '';
            }
        });
        
        // Remove validation styling when user starts typing again
        input.addEventListener('input', function() {
            this.style.borderColor = '';
        });
    });
});

// Phase 6: Date picker with calendar icon
document.addEventListener('DOMContentLoaded', function () {
    // Get all date inputs with wrappers
    var dateInputs = document.querySelectorAll('.date-input');
    
    dateInputs.forEach(function(input) {
        // Find the wrapper and icon
        var wrapper = input.closest('.date-input-wrapper');
        if (!wrapper) return;
        
        var icon = wrapper.querySelector('.date-icon');
        if (!icon) return;
        
        // Initialize Flatpickr
        var fp = flatpickr(input, {
            dateFormat: 'd-m-Y',
            allowInput: true,
            altInput: false,
            disableMobile: true,
            position: 'auto',
            closeOnSelect: true,
            // Set default date if input has value
            defaultDate: input.value || null
        });
        
        // Open calendar when icon is clicked
        icon.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            fp.open();
        });
        
        // Also open on input click (makes it more user-friendly)
        input.addEventListener('click', function() {
            fp.open();
        });
        
        // Style the icon to show it's clickable
        icon.style.cursor = 'pointer';
        icon.title = 'Click to select date';
    });
});

// Phase 7: Heartbeat - Live updates for all pages
document.addEventListener('DOMContentLoaded', function () {
    // Check if we're on a page that should have heartbeat
    // We want it on ALL admin and cashier pages
    var currentRoute = new URLSearchParams(window.location.search).get('route') || '';
    
    // Check if this is an admin or cashier page (not login pages)
    var isAdminPage = currentRoute.startsWith('admin/') && currentRoute !== 'admin-login';
    var isCashierPage = currentRoute.startsWith('cashier/') && currentRoute !== 'cashier-login';
    var isWorkerPage = currentRoute.startsWith('worker/') && currentRoute !== 'worker-login';
    
    // Also include the main routes without prefix
    var isMainPage = ['admin/dashboard', 'admin/reports', 'admin/branches', 'admin/workers', 
                      'admin/cashiers', 'admin/closures', 'admin/audit-log',
                      'cashier/dashboard', 'cashier/sales', 'cashier/reports',
                      'worker/dashboard', 'worker/reports'].includes(currentRoute);
    
    // If not a page that needs heartbeat, exit
    if (!isAdminPage && !isCashierPage && !isWorkerPage && !isMainPage) {
        console.log('Heartbeat not needed for this page:', currentRoute);
        return;
    }
    
    console.log('Heartbeat initialized for:', currentRoute);
    
    // Get the last updated element
    var lastUpdatedElement = document.getElementById('lastUpdated');
    
    // If no lastUpdated element, create one in the topbar
    if (!lastUpdatedElement) {
        var topbarUser = document.querySelector('.topbar__user');
        if (topbarUser) {
            var indicator = document.createElement('span');
            indicator.className = 'heartbeat-indicator';
            indicator.innerHTML = '<span class="live-dot"></span><span class="last-updated" id="lastUpdated">Just now</span>';
            indicator.style.cssText = 'display:flex;align-items:center;gap:8px;margin-right:15px;font-size:12px;color:#666;';
            topbarUser.parentNode.insertBefore(indicator, topbarUser);
            lastUpdatedElement = document.getElementById('lastUpdated');
        }
    }
    
    if (!lastUpdatedElement) {
        console.warn('Could not find or create lastUpdated element');
        return;
    }
    
    // Initialize with current timestamp
    var lastUpdate = Math.floor(Date.now() / 1000);
    var isUpdating = false;
    var appUrl = window.SALONFLOW ? SALONFLOW.APP_URL : '';
    
    // Function to format time ago
    function timeAgo(timestamp) {
        var seconds = Math.floor((Date.now() / 1000) - timestamp);
        if (seconds < 60) return 'Just now';
        var minutes = Math.floor(seconds / 60);
        if (minutes < 60) return minutes + 'm ago';
        var hours = Math.floor(minutes / 60);
        if (hours < 24) return hours + 'h ago';
        var days = Math.floor(hours / 24);
        return days + 'd ago';
    }
    
    // Function to update the "last updated" text
    function updateLastUpdatedText(timestamp) {
        if (lastUpdatedElement) {
            lastUpdatedElement.textContent = timeAgo(timestamp);
        }
    }
    
    // Function to check for updates
    function checkForUpdates() {
        if (isUpdating) return;
        isUpdating = true;
        
        // Determine which heartbeat endpoint to use based on route
        var endpoint = 'admin/heartbeat';
        if (currentRoute.startsWith('cashier/')) {
            endpoint = 'cashier/heartbeat';
        } else if (currentRoute.startsWith('worker/')) {
            endpoint = 'worker/heartbeat';
        }
        
        var url = appUrl + '/index.php?route=' + endpoint + '&last_update=' + lastUpdate;
        
        fetch(url, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(function(response) {
            if (response.status === 304) {
                // No new data
                isUpdating = false;
                updateLastUpdatedText(lastUpdate);
                return null;
            }
            return response.json();
        })
        .then(function(data) {
            isUpdating = false;
            
            if (!data || !data.success) {
                return;
            }
            
            // Update last update timestamp
            lastUpdate = data.timestamp;
            updateLastUpdatedText(lastUpdate);
            
            // Update page content
            updatePageContent(data);
        })
        .catch(function(error) {
            console.error('Heartbeat error:', error);
            isUpdating = false;
        });
    }
    
    // Function to update page content based on current page
    function updatePageContent(data) {
        // For dashboard pages - update stats
        if (currentRoute === 'admin/dashboard') {
            updateAdminDashboard(data);
        } else if (currentRoute === 'cashier/dashboard') {
            updateCashierDashboard(data);
        } else if (currentRoute === 'worker/dashboard') {
            updateWorkerDashboard(data);
        } else if (currentRoute === 'admin/reports' || currentRoute === 'cashier/reports' || currentRoute === 'worker/reports') {
            
            console.log('Reports page - heartbeat active');
        }
        
        // Flash animation for any stat cards on the page
        var cards = document.querySelectorAll('.stat-card');
        cards.forEach(function(card) {
            card.classList.add('flash-update');
            setTimeout(function() {
                card.classList.remove('flash-update');
            }, 500);
        });
    }
    
    // Update admin dashboard
    function updateAdminDashboard(data) {
        if (!data.data || !data.data.todaySummary) return;
        
        var summary = data.data.todaySummary;
        
        // Update revenue cards
        var todayRevenue = document.getElementById('todayRevenue');
        var weekRevenue = document.getElementById('weekRevenue');
        var monthRevenue = document.getElementById('monthRevenue');
        
        if (todayRevenue && summary.total_revenue !== undefined) {
            todayRevenue.textContent = '₦' + parseFloat(summary.total_revenue).toFixed(2);
        }
        if (weekRevenue && data.data.weekSummary) {
            weekRevenue.textContent = '₦' + parseFloat(data.data.weekSummary.total_revenue).toFixed(2);
        }
        if (monthRevenue && data.data.monthSummary) {
            monthRevenue.textContent = '₦' + parseFloat(data.data.monthSummary.total_revenue).toFixed(2);
        }
        
        // Update today's glance cards
        var cashTotal = document.getElementById('cashTotal');
        var transferTotal = document.getElementById('transferTotal');
        var posTotal = document.getElementById('posTotal');
        var tipsTotal = document.getElementById('tipsTotal');
        var commissionsTotal = document.getElementById('commissionsTotal');
        var salonEarnings = document.getElementById('salonEarnings');
        
        if (cashTotal && summary.cash_total !== undefined) {
            cashTotal.textContent = '₦' + parseFloat(summary.cash_total).toFixed(2);
        }
        if (transferTotal && summary.transfer_total !== undefined) {
            transferTotal.textContent = '₦' + parseFloat(summary.transfer_total).toFixed(2);
        }
        if (posTotal && summary.pos_total !== undefined) {
            posTotal.textContent = '₦' + parseFloat(summary.pos_total).toFixed(2);
        }
        if (tipsTotal && summary.tips_total !== undefined) {
            tipsTotal.textContent = '₦' + parseFloat(summary.tips_total).toFixed(2);
        }
        if (commissionsTotal && summary.worker_commissions !== undefined) {
            commissionsTotal.textContent = '₦' + parseFloat(summary.worker_commissions).toFixed(2);
        }
        if (salonEarnings && summary.salon_earnings !== undefined) {
            salonEarnings.textContent = '₦' + parseFloat(summary.salon_earnings).toFixed(2);
        }
    }
    
    // Update cashier dashboard
    function updateCashierDashboard(data) {
        if (!data.data || !data.data.summary) return;
        
        var summary = data.data.summary;
        
        var todayRecords = document.getElementById('todayRecords');
        var todayRevenue = document.getElementById('todayRevenue');
        var cashTotal = document.getElementById('cashTotal');
        var transferTotal = document.getElementById('transferTotal');
        var posTotal = document.getElementById('posTotal');
        
        if (todayRecords && summary.record_count !== undefined) {
            todayRecords.textContent = summary.record_count || 0;
        }
        if (todayRevenue && summary.total_revenue !== undefined) {
            todayRevenue.textContent = '₦' + parseFloat(summary.total_revenue).toFixed(2);
        }
        if (cashTotal && summary.cash_total !== undefined) {
            cashTotal.textContent = '₦' + parseFloat(summary.cash_total).toFixed(2);
        }
        if (transferTotal && summary.transfer_total !== undefined) {
            transferTotal.textContent = '₦' + parseFloat(summary.transfer_total).toFixed(2);
        }
        if (posTotal && summary.pos_total !== undefined) {
            posTotal.textContent = '₦' + parseFloat(summary.pos_total).toFixed(2);
        }
    }
    
    // Update worker dashboard
    function updateWorkerDashboard(data) {
        if (!data.data || !data.data.summary) return;
        
        var summary = data.data.summary;
        
        var salesCount = document.getElementById('salesCount');
        var revenueTotal = document.getElementById('revenueTotal');
        var commissionTotal = document.getElementById('commissionTotal');
        var tipsTotal = document.getElementById('tipsTotal');
        
        if (salesCount && summary.record_count !== undefined) {
            salesCount.textContent = summary.record_count || 0;
        }
        if (revenueTotal && summary.revenue !== undefined) {
            revenueTotal.textContent = '₦' + parseFloat(summary.revenue).toFixed(2);
        }
        if (commissionTotal && summary.commission !== undefined) {
            commissionTotal.textContent = '₦' + parseFloat(summary.commission).toFixed(2);
        }
        if (tipsTotal && summary.tips !== undefined) {
            tipsTotal.textContent = '₦' + parseFloat(summary.tips).toFixed(2);
        }
    }
    
    // Update the last updated text every 60 seconds
    setInterval(function() {
        updateLastUpdatedText(lastUpdate);
    }, 60000);
    
    // Check for updates every 5 seconds
    setInterval(checkForUpdates, 5000);
    console.log('Heartbeat running. Checking every 5 seconds.');
    
    // Also check when the page becomes visible again
    document.addEventListener('visibilitychange', function() {
        if (!document.hidden) {
            checkForUpdates();
        }
    });
    
    // Initial check after 1 second
    setTimeout(function() {
        checkForUpdates();
    }, 1000);
});