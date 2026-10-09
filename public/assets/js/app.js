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


// Phase 3b: Staff combobox on the sale form.
//
// As the cashier types in #staff_search, a floating list of matching staff
// appears below it. Clicking (or keyboard-selecting) a result writes that
// staff's id into the hidden #worker_id <select>, which remains the actual
// form field. Server-side validation of worker_id is unchanged — this is
// pure UI convenience.
//
// Behavior decisions:
//   - Dropdown only opens once the user has typed at least 1 character.
//   - Enter with no highlighted result auto-picks if there is exactly ONE match.
//   - Click-outside / Escape closes the dropdown without changing the selection.
//   - If the input text no longer matches the currently-selected staff,
//     the hidden select is cleared so a stale value can't be submitted.
document.addEventListener('DOMContentLoaded', function () {
    var wrapper = document.getElementById('staff-combobox');
    var searchInput = document.getElementById('staff_search');
    var resultsBox = document.getElementById('staff-results');
    var workerSelect = document.getElementById('worker_id');

    if (!wrapper || !searchInput || !resultsBox || !workerSelect) {
        return; // not on the sale form — nothing to do
    }

    // Snapshot staff once from the hidden <select>, so the native select
    // remains the single source of truth and we never diverge from PHP.
    var staff = [];
    for (var i = 0; i < workerSelect.options.length; i++) {
        var opt = workerSelect.options[i];
        if (opt.value === '') continue; // skip placeholder
        staff.push({ id: opt.value, name: opt.textContent.trim() });
    }

    var highlightIndex = -1; // -1 = nothing highlighted
    var visibleMatches = []; // current filtered list, mirror of what's rendered

    function escapeHtml(value) {
        var div = document.createElement('div');
        div.textContent = value === undefined || value === null ? '' : String(value);
        return div.innerHTML;
    }

    function openResults() {
        resultsBox.hidden = false;
        searchInput.setAttribute('aria-expanded', 'true');
    }

    function closeResults() {
        resultsBox.hidden = true;
        resultsBox.innerHTML = '';
        highlightIndex = -1;
        visibleMatches = [];
        searchInput.setAttribute('aria-expanded', 'false');
    }

    function setHighlight(newIndex) {
        var items = resultsBox.querySelectorAll('.staff-combobox__item');
        if (items.length === 0) return;
        if (newIndex < 0) newIndex = items.length - 1;
        if (newIndex >= items.length) newIndex = 0;
        highlightIndex = newIndex;
        items.forEach(function (el, idx) {
            el.classList.toggle('is-highlighted', idx === highlightIndex);
        });
        var highlighted = items[highlightIndex];
        if (highlighted && highlighted.scrollIntoView) {
            highlighted.scrollIntoView({ block: 'nearest' });
        }
    }

    function pick(staffMember) {
        // Set the real form field.
        workerSelect.value = staffMember.id;
        // Show the chosen name in the visible input.
        searchInput.value = staffMember.name;
        closeResults();
    }

    function render(query) {
        var q = query.trim().toLowerCase();

        // Requirement: no dropdown on empty input — must type something first.
        if (q === '') {
            closeResults();
            return;
        }

        visibleMatches = staff.filter(function (s) {
            return s.name.toLowerCase().indexOf(q) !== -1;
        });

        if (visibleMatches.length === 0) {
            resultsBox.innerHTML = '<div class="staff-combobox__empty">No staff match "' + escapeHtml(query) + '".</div>';
            openResults();
            highlightIndex = -1;
            return;
        }

        resultsBox.innerHTML = visibleMatches.map(function (s, idx) {
            return '<div class="staff-combobox__item" role="option" data-index="' + idx + '" data-id="' + escapeHtml(s.id) + '">'
                + escapeHtml(s.name)
                + '</div>';
        }).join('');
        openResults();
        highlightIndex = -1;
    }

    // ---- Events ----

    searchInput.addEventListener('input', function () {
        // If the typed text no longer matches the currently-selected staff,
        // clear the hidden select so the form can't silently submit a stale id.
        var currentSelectedId = workerSelect.value;
        if (currentSelectedId !== '') {
            var stillMatches = false;
            for (var i = 0; i < staff.length; i++) {
                if (staff[i].id === currentSelectedId &&
                    staff[i].name.toLowerCase() === searchInput.value.trim().toLowerCase()) {
                    stillMatches = true;
                    break;
                }
            }
            if (!stillMatches) {
                workerSelect.value = '';
            }
        }
        render(searchInput.value);
    });

    searchInput.addEventListener('keydown', function (e) {
        var isOpen = !resultsBox.hidden;
        var items = isOpen ? resultsBox.querySelectorAll('.staff-combobox__item') : [];

        if (e.key === 'ArrowDown') {
            if (isOpen && items.length > 0) {
                e.preventDefault();
                setHighlight(highlightIndex + 1);
            }
        } else if (e.key === 'ArrowUp') {
            if (isOpen && items.length > 0) {
                e.preventDefault();
                setHighlight(highlightIndex - 1);
            }
        } else if (e.key === 'Enter') {
            if (isOpen) {
                if (highlightIndex >= 0 && visibleMatches[highlightIndex]) {
                    e.preventDefault();
                    pick(visibleMatches[highlightIndex]);
                } else if (items.length === 1 && visibleMatches[0]) {
                    // Requirement: auto-pick when exactly one match and no highlight.
                    e.preventDefault();
                    pick(visibleMatches[0]);
                }
                // Otherwise: do nothing (don't submit the form by accident
                // when the cashier just typed a partial name).
            }
        } else if (e.key === 'Escape') {
            if (isOpen) {
                e.preventDefault();
                closeResults();
            }
        }
    });

    resultsBox.addEventListener('mousedown', function (e) {
        // mousedown (not click) so the pick fires before input blur closes the list.
        var item = e.target.closest('.staff-combobox__item');
        if (!item) return;
        e.preventDefault();
        var idx = parseInt(item.getAttribute('data-index'), 10);
        if (!isNaN(idx) && visibleMatches[idx]) {
            pick(visibleMatches[idx]);
        }
    });

    resultsBox.addEventListener('mousemove', function (e) {
        var item = e.target.closest('.staff-combobox__item');
        if (!item) return;
        var items = resultsBox.querySelectorAll('.staff-combobox__item');
        items.forEach(function (el, idx) {
            if (el === item) {
                highlightIndex = idx;
                el.classList.add('is-highlighted');
            } else {
                el.classList.remove('is-highlighted');
            }
        });
    });

    // Click outside closes the list. Use mousedown on document so it runs
    // even if the user clicks on a non-focusable area.
    document.addEventListener('mousedown', function (e) {
        if (!wrapper.contains(e.target)) {
            closeResults();
        }
    });

    // If the user tabs away with the input containing text that matches
    // exactly one staff and no pick was made, do NOT auto-commit — that's
    // the D3 "no auto-select on blur" rule. But if the input text no longer
    // matches the selected staff, clearing the hidden select already happened
    // on input — nothing more to do here.
    searchInput.addEventListener('blur', function () {
        // Small delay so a mousedown on a result can fire first.
        setTimeout(function () {
            if (!wrapper.contains(document.activeElement)) {
                closeResults();
            }
        }, 120);
    });
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

// Phase 7: Heartbeat - Live updates for dashboard pages only
document.addEventListener('DOMContentLoaded', function () {
    var currentRoute = new URLSearchParams(window.location.search).get('route') || '';

    // Heartbeat only ever DOES anything on these three pages (see
    // updatePageContent() below) — polling elsewhere would just hit the
    // server every 5 seconds for zero visible effect, so we don't.
    var dashboardRoutes = ['admin/dashboard', 'cashier/dashboard', 'worker/dashboard'];

    if (dashboardRoutes.indexOf(currentRoute) === -1) {
        return;
    }

    // Small helper since table rows are now rebuilt via innerHTML from
    // live JSON data — escape anything that came from the database
    // (branch/worker names) the same way htmlspecialchars() does server-side.
    function escapeHtml(value) {
        var div = document.createElement('div');
        div.textContent = value === undefined || value === null ? '' : String(value);
        return div.innerHTML;
    }

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
        return;
    }
    
    // Initialize with current timestamp
    // Start from the SERVER's clock (not the browser's). A phone whose clock
    // is a few minutes ahead would otherwise miss every change made in that gap.
    var lastUpdate = (window.SALONFLOW && SALONFLOW.INITIAL_TIMESTAMP)
        ? parseInt(SALONFLOW.INITIAL_TIMESTAMP, 10)
        : Math.floor(Date.now() / 1000);
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

            // Server says this person has records to confirm: go straight to the queue.
            if (data.redirect) {
                window.location.href = data.redirect;
                return;
            }
            
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
            updateStaffDashboard(data);
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

        // Sidebar "Appeals" badge
        var appealsBadge = document.getElementById('appealsBadge');
        if (appealsBadge && data.data.openAppeals !== undefined) {
            var openAppeals = parseInt(data.data.openAppeals, 10) || 0;
            appealsBadge.textContent = openAppeals;
            appealsBadge.style.display = openAppeals > 0 ? '' : 'none';
        }

        // Dashboard "appeals waiting" button: shown only while an appeal is open
        var appealsAlert = document.getElementById('appealsAlert');
        if (appealsAlert && data.data.openAppeals !== undefined) {
            var openCount = parseInt(data.data.openAppeals, 10) || 0;
            document.getElementById('appealsAlertCount').textContent = openCount;
            document.getElementById('appealsAlertText').textContent = openCount === 1 ? 'appeal is' : 'appeals are';
            appealsAlert.style.display = openCount > 0 ? 'flex' : 'none';
        }
        
        var summary = data.data.todaySummary;
        
        // Update revenue cards
        var todayRevenue = document.getElementById('todayRevenue');
        var weekRevenue = document.getElementById('weekRevenue');
        var monthRevenue = document.getElementById('monthRevenue');
        
        if (todayRevenue && summary.total_revenue !== undefined) {
            todayRevenue.textContent = '' + sfMoney(parseFloat(summary.total_revenue));
        }
        if (weekRevenue && data.data.weekSummary) {
            weekRevenue.textContent = '' + sfMoney(parseFloat(data.data.weekSummary.total_revenue));
        }
        if (monthRevenue && data.data.monthSummary) {
            monthRevenue.textContent = '' + sfMoney(parseFloat(data.data.monthSummary.total_revenue));
        }
        
        // Update today's glance cards
        var cashTotal = document.getElementById('cashTotal');
        var transferTotal = document.getElementById('transferTotal');
        var posTotal = document.getElementById('posTotal');
        var tipsTotal = document.getElementById('tipsTotal');
        var commissionsTotal = document.getElementById('commissionsTotal');
        var staffCommissionTipsToday = document.getElementById('staffCommissionTipsToday');
        var salonEarnings = document.getElementById('salonEarnings');
        
        if (cashTotal && summary.cash_total !== undefined) {
            cashTotal.textContent = '' + sfMoney(parseFloat(summary.cash_total));
        }
        if (transferTotal && summary.transfer_total !== undefined) {
            transferTotal.textContent = '' + sfMoney(parseFloat(summary.transfer_total));
        }
        if (posTotal && summary.pos_total !== undefined) {
            posTotal.textContent = '' + sfMoney(parseFloat(summary.pos_total));
        }
        if (tipsTotal && summary.tips_total !== undefined) {
            tipsTotal.textContent = '' + sfMoney(parseFloat(summary.tips_total));
        }
        if (commissionsTotal && summary.worker_commissions !== undefined) {
            commissionsTotal.textContent = '' + sfMoney(parseFloat(summary.worker_commissions));
        }
        // Prefer the pre-computed staff_payout field from ReportModel::summary();
        // fall back to summing the two parts by hand if it's missing (older
        // cached response, or ReportModel not yet updated).
        if (staffCommissionTipsToday) {
            if (summary.staff_payout !== undefined) {
                staffCommissionTipsToday.textContent = '' + sfMoney(parseFloat(summary.staff_payout));
            } else if (summary.worker_commissions !== undefined && summary.tips_total !== undefined) {
                var combinedToday = parseFloat(summary.worker_commissions) + parseFloat(summary.tips_total);
                staffCommissionTipsToday.textContent = '' + sfMoney(combinedToday);
            }
        }
        if (salonEarnings && summary.salon_earnings !== undefined) {
            salonEarnings.textContent = '' + sfMoney(parseFloat(summary.salon_earnings));
        }
        
        // Update Revenue + Cashback cards
        var todayRevenueTips = document.getElementById('todayRevenueTips');
        var weekRevenueTips = document.getElementById('weekRevenueTips');
        var monthRevenueTips = document.getElementById('monthRevenueTips');

        if (todayRevenueTips && data.data.todaySummary) {
            var todayTotal = parseFloat(data.data.todaySummary.total_revenue) + parseFloat(data.data.todaySummary.tips_total);
            todayRevenueTips.textContent = '' + sfMoney(todayTotal);
        }
        if (weekRevenueTips && data.data.weekSummary) {
            var weekTotal = parseFloat(data.data.weekSummary.total_revenue) + parseFloat(data.data.weekSummary.tips_total);
            weekRevenueTips.textContent = '' + sfMoney(weekTotal);
        }
        if (monthRevenueTips && data.data.monthSummary) {
            var monthTotal = parseFloat(data.data.monthSummary.total_revenue) + parseFloat(data.data.monthSummary.tips_total);
            monthRevenueTips.textContent = '' + sfMoney(monthTotal);
        }

        // Rebuild the Branch Revenue and Staff Performance tables — the
        // backend already sends branchBreakdown/workerPerformance in every
        // heartbeat response, but nothing used to consume it, so these
        // tables never actually refreshed.
        var branchTableBody = document.getElementById('branchTableBody');
        if (branchTableBody && data.data.branchBreakdown) {
            if (data.data.branchBreakdown.length === 0) {
                branchTableBody.innerHTML = '<tr><td colspan="3" class="empty-row">No branches yet.</td></tr>';
            } else {
                branchTableBody.innerHTML = data.data.branchBreakdown.map(function (b) {
                    return '<tr>' +
                        '<td>' + escapeHtml(b.name) + '</td>' +
                        '<td>' + parseInt(b.record_count, 10) + '</td>' +
                        '<td class="amount">' + sfMoney(parseFloat(b.revenue)) + '</td>' +
                        '</tr>';
                }).join('');
            }
        }

        var workerTableBody = document.getElementById('workerTableBody');
        if (workerTableBody && data.data.workerPerformance) {
            if (data.data.workerPerformance.length === 0) {
                workerTableBody.innerHTML = '<tr><td colspan="7" class="empty-row">No staff yet.</td></tr>';
            } else {
                workerTableBody.innerHTML = data.data.workerPerformance.map(function (w) {
                    var commission = parseFloat(w.commission);
                    var tips = parseFloat(w.tips);
                    return '<tr>' +
                        '<td>' + escapeHtml(w.full_name) + '</td>' +
                        '<td>' + escapeHtml(w.branch_name) + '</td>' +
                        '<td>' + parseInt(w.record_count, 10) + '</td>' +
                        '<td class="amount">' + sfMoney(parseFloat(w.revenue)) + '</td>' +
                        '<td class="amount">' + sfMoney(commission) + '</td>' +
                        '<td class="amount">' + sfMoney(tips) + '</td>' +
                        '<td class="amount">' + sfMoney((commission + tips)) + '</td>' +
                        '</tr>';
                }).join('');
            }
        }
    }
    
    // Update cashier dashboard
    function updateCashierDashboard(data) {
        if (!data.data || !data.data.summary) return;

        // Stat cards (including Total Revenue + Cashback and Tips), formatted the same
        // way the page was first rendered. Helpers live near the bottom of this file.
        sfApplyCashierSummary(data.data.summary);

        // Sidebar "Appeals" badge (first appeals this cashier can handle)
        var cashierAppealsBadge = document.getElementById('cashierAppealsBadge');
        if (cashierAppealsBadge && data.data.openAppeals !== undefined) {
            var openForCashier = parseInt(data.data.openAppeals, 10) || 0;
            cashierAppealsBadge.textContent = openForCashier;
            cashierAppealsBadge.style.display = openForCashier > 0 ? '' : 'none';
        }

        // Staff cards: each worker's sale count and revenue for today
        if (data.data.workers) {
            sfApplyStaffStats(data.data.workers);
        }
    }

    // Update staff dashboard
    function updateStaffDashboard(data) {
        if (!data.data || !data.data.todaySummary) return;

        // Backend returns todaySummary/weekSummary/monthSummary (same shape
        // as the Admin dashboard's response) — this used to look for a
        // single "summary" key that never existed, so it silently did
        // nothing. Also updates all three periods now, not just one.
        var periods = [
            { key: 'todaySummary', prefix: 'today' },
            { key: 'weekSummary', prefix: 'week' },
            { key: 'monthSummary', prefix: 'month' }
        ];

        periods.forEach(function (period) {
            var summary = data.data[period.key];
            if (!summary) return;

            var salesEl = document.getElementById(period.prefix + 'Sales');
            var revenueEl = document.getElementById(period.prefix + 'Revenue');
            var commissionEl = document.getElementById(period.prefix + 'Commission');
            var tipsEl = document.getElementById(period.prefix + 'Tips');
            var commissionTipsEl = document.getElementById(period.prefix + 'CommissionTips');

            if (salesEl && summary.record_count !== undefined) {
                salesEl.textContent = summary.record_count || 0;
            }
            if (revenueEl && summary.revenue !== undefined) {
                revenueEl.textContent = '' + sfMoney(parseFloat(summary.revenue));
            }
            if (commissionEl && summary.commission !== undefined) {
                commissionEl.textContent = '' + sfMoney(parseFloat(summary.commission));
            }
            if (tipsEl && summary.tips !== undefined) {
                tipsEl.textContent = '' + sfMoney(parseFloat(summary.tips));
            }
            // Combined card — prefer the pre-computed staff_payout field,
            // fall back to summing commission + tips if it's absent.
            if (commissionTipsEl) {
                if (summary.staff_payout !== undefined) {
                    commissionTipsEl.textContent = '' + sfMoney(parseFloat(summary.staff_payout));
                } else if (summary.commission !== undefined && summary.tips !== undefined) {
                    var combinedOwn = parseFloat(summary.commission) + parseFloat(summary.tips);
                    commissionTipsEl.textContent = '' + sfMoney(combinedOwn);
                }
            }
        });
    }
    
    // Update the last updated text every 60 seconds
    setInterval(function() {
        updateLastUpdatedText(lastUpdate);
    }, 60000);
    
    // Check for updates every 5 seconds
    setInterval(checkForUpdates, 5000);
    
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

// Phase 8: Hamburger Menu - Mobile Navigation
document.addEventListener('DOMContentLoaded', function () {
    const navToggle = document.getElementById('navToggle');
    const navMenu = document.getElementById('navMenu');
    const navClose = document.getElementById('navClose');
    
    // Don't run if no toggle exists
    if (!navToggle || !navMenu) return;
    
    // Create overlay
    const overlay = document.createElement('div');
    overlay.className = 'nav-overlay';
    document.body.appendChild(overlay);
    
    // Toggle menu (for hamburger click)
    function toggleMenu() {
        navToggle.classList.toggle('active');
        navMenu.classList.toggle('open');
        overlay.classList.toggle('active');
        document.body.style.overflow = navMenu.classList.contains('open') ? 'hidden' : '';
    }
    
    // Close menu
    function closeMenu() {
        navToggle.classList.remove('active');
        navMenu.classList.remove('open');
        overlay.classList.remove('active');
        document.body.style.overflow = '';
    }
    
    // Hamburger click
    navToggle.addEventListener('click', toggleMenu);
    
    // Close button click (inside sidebar)
    if (navClose) {
        navClose.addEventListener('click', closeMenu);
        
        // Keyboard support for close button
        navClose.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                closeMenu();
            }
        });
    }
    
    // Keyboard support for hamburger
    navToggle.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            toggleMenu();
        }
    });
    
    // Close on overlay click
    overlay.addEventListener('click', closeMenu);
    
    // Close on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && navMenu.classList.contains('open')) {
            closeMenu();
        }
    });
    
    // Close on link click (mobile only)
    const navLinks = navMenu.querySelectorAll('.admin-nav__link, .cashier-nav__link, .worker-nav__link');
    navLinks.forEach(link => {
        link.addEventListener('click', function() {
            if (window.innerWidth <= 768) {
                closeMenu();
            }
        });
    });
    
    // Close on window resize to desktop
    window.addEventListener('resize', function() {
        if (window.innerWidth > 768 && navMenu.classList.contains('open')) {
            closeMenu();
        }
    });
});


// ---------------------------------------------------------------------------
// Cashier dashboard helpers - shared by the heartbeat (live updates) and the
// quick-sale popup, so both write numbers into the page the same way.
// ---------------------------------------------------------------------------
function sfMoney(value) {
    return '\u20A6' + (parseFloat(value) || 0).toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

function sfSetText(id, text) {
    var el = document.getElementById(id);
    if (el) { el.textContent = text; }
}

// summary = the object from TransactionModel::summaryForBranchToday()
function sfApplyCashierSummary(summary) {
    if (!summary) { return; }

    var revenue = parseFloat(summary.total_revenue) || 0;
    var tips = parseFloat(summary.tips_total) || 0;
    var commissions = parseFloat(summary.worker_commissions_total) || 0;

    sfSetText('todayRecords', String(parseInt(summary.record_count, 10) || 0));
    sfSetText('todayRevenue', sfMoney(revenue));
    sfSetText('todayRevenueTips', sfMoney(revenue + tips));
    sfSetText('cashTotal', sfMoney(summary.cash_total));
    sfSetText('transferTotal', sfMoney(summary.transfer_total));
    sfSetText('posTotal', sfMoney(summary.pos_total));
    sfSetText('tipsTotal', sfMoney(tips));
    sfSetText('staffCommissionTips', sfMoney(commissions + tips));
}

// Updates one staff card. flash=true gives it the brief green highlight.
function sfSetStaffCard(worker, flash) {
    var card = document.querySelector('#staffList .staff-card[data-worker-id="' + worker.id + '"]');
    if (!card) { return; }

    var count = parseInt(worker.sale_count, 10) || 0;
    card.querySelector('.js-count').textContent = count;
    card.querySelector('.js-count-label').textContent = count === 1 ? 'sale' : 'sales';
    card.querySelector('.js-revenue').textContent = sfMoney(worker.revenue);

    if (flash) {
        card.classList.add('is-updated');
        setTimeout(function () { card.classList.remove('is-updated'); }, 1600);
    }
}

function sfApplyStaffStats(workers) {
    (workers || []).forEach(function (w) { sfSetStaffCard(w, false); });
}


// ---------------------------------------------------------------------------
// Quick Sale list (lives on the cashier dashboard, #quickSale)
//
// What this block does, in order:
//   1. Search box  -> hides staff cards that don't match what was typed.
//   2. Tap a card  -> slides the "record sale" sheet up with that worker filled in.
//   3. Save        -> sends the form in the BACKGROUND (fetch), then closes the
//                     sheet and updates that worker's card in place. No reload.
//
// Like the Combination toggle further up, this only controls what the cashier
// SEES. The real validation is still CashierController::validateSale() on the
// server, so nothing here is a security layer.
// ---------------------------------------------------------------------------
document.addEventListener('DOMContentLoaded', function () {
    var page = document.getElementById('quickSale');
    if (!page) {
        return; // not on the quick-sale page
    }

    var search = document.getElementById('staffSearch');
    var list = document.getElementById('staffList');
    var noMatch = document.getElementById('staffNoMatch');
    var toast = document.getElementById('qsToast');
    var topbar = document.querySelector('.topbar');

    var sheet = document.getElementById('qsSheet');       // null when the day is closed
    var backdrop = document.getElementById('qsBackdrop');
    var form = document.getElementById('qsForm');
    var workerIdInput = document.getElementById('qsWorkerId');
    var workerName = document.getElementById('qsWorkerName');
    var amount = document.getElementById('qsAmount');
    var method = document.getElementById('qsMethod');
    var combo = document.getElementById('qsCombo');
    var tip = document.getElementById('qsTip');
    var note = document.getElementById('qsNote');
    var errorBox = document.getElementById('qsError');
    var saveBtn = document.getElementById('qsSave');
    var closeBtn = document.getElementById('qsClose');

    var cards = list ? Array.prototype.slice.call(list.querySelectorAll('.staff-card')) : [];
    var lastCard = null;
    var saving = false;
    var toastTimer = null;

    // --- Keep the search box pinned right under the sticky top bar ----------
    function setTopbarHeight() {
        if (topbar) {
            document.documentElement.style.setProperty('--topbar-h', topbar.offsetHeight + 'px');
        }
    }
    setTopbarHeight();
    window.addEventListener('resize', setTopbarHeight);

    // --- Formatting helpers --------------------------------------------------
    function formatMoney(value) {
        return '\u20A6' + Number(value).toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function showToast(message) {
        if (!toast) { return; }
        clearTimeout(toastTimer);
        toast.textContent = message;
        toast.hidden = false;
        void toast.offsetWidth; // let the browser register "hidden" is gone before animating
        toast.classList.add('is-visible');
        toastTimer = setTimeout(function () {
            toast.classList.remove('is-visible');
            setTimeout(function () { toast.hidden = true; }, 250);
        }, 2600);
    }

    // --- 1. Search -----------------------------------------------------------
    function applyFilter() {
        if (!search) { return; }
        var term = search.value.trim().toLowerCase();
        var visible = 0;

        cards.forEach(function (card) {
            var name = (card.getAttribute('data-worker-name') || '').toLowerCase();
            var match = term === '' || name.indexOf(term) !== -1;
            card.hidden = !match;
            if (match) { visible++; }
        });

        if (noMatch) { noMatch.hidden = visible !== 0; }
    }

    if (search) {
        search.addEventListener('input', applyFilter);

        // Pressing Enter/Go on the phone keyboard opens the first match, so a
        // cashier can type "eme" and hit Enter without lifting a finger to tap.
        search.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter') { return; }
            e.preventDefault();
            var first = cards.filter(function (c) { return !c.hidden && !c.disabled; })[0];
            if (first) { first.click(); }
        });
    }

    // --- Sheet helpers -------------------------------------------------------
    if (!sheet || !form) {
        return; // day is closed: cards are disabled, so there's no sheet to wire up
    }

    function showError(message) {
        if (!message) {
            errorBox.hidden = true;
            errorBox.textContent = '';
            return;
        }
        errorBox.textContent = message;
        errorBox.hidden = false;
        // Make sure the message is in view even if the sheet was scrolled.
        errorBox.scrollIntoView({ block: 'nearest' });
    }

    function setSaving(isSaving) {
        saving = isSaving;
        saveBtn.disabled = isSaving;
        saveBtn.textContent = isSaving ? 'Saving...' : 'Save Sale';
    }

    function updateComboVisibility() {
        var isCombination = method.value === 'combination';
        combo.hidden = !isCombination;

        if (!isCombination) {
            // Same rule as the old form: hidden boxes go back to 0 so a stale
            // value can't sneak into a cash/transfer/pos sale.
            combo.querySelectorAll('input[type="number"]').forEach(function (input) {
                input.value = '0';
            });
        }
    }
    method.addEventListener('change', updateComboVisibility);

    function resetForm() {
        amount.value = '';
        method.value = 'transfer'; // default payment method
        tip.value = '';
        note.value = '';
        updateComboVisibility();
        showError('');
        setSaving(false);
    }

    // When the phone keyboard opens, the browser's "visual viewport" gets
    // shorter. We lift the sheet above the keyboard and cap its height, so the
    // Save button never ends up hidden behind the keys.
    function fitSheet() {
        var vv = window.visualViewport;
        if (!vv || sheet.hidden) { return; }
        var covered = window.innerHeight - vv.height - vv.offsetTop;
        sheet.style.bottom = Math.max(0, covered) + 'px';
        sheet.style.maxHeight = Math.floor(vv.height - 12) + 'px';
    }

    if (window.visualViewport) {
        window.visualViewport.addEventListener('resize', fitSheet);
        window.visualViewport.addEventListener('scroll', fitSheet);
    }

    // --- 2. Open / close the sheet -------------------------------------------
    function openSheet(card) {
        lastCard = card;
        workerIdInput.value = card.getAttribute('data-worker-id');
        workerName.textContent = card.getAttribute('data-worker-name');
        resetForm();

        backdrop.hidden = false;
        sheet.hidden = false;
        void sheet.offsetWidth; // so the slide-up animation actually plays
        backdrop.classList.add('is-open');
        sheet.classList.add('is-open');
        document.body.style.overflow = 'hidden';
        fitSheet();

        // Focus inside the tap handler so the phone opens the number keypad.
        amount.focus({ preventScroll: true });
    }

    function closeSheet(returnFocus) {
        if (saving) { return; } // don't abandon a save that's in flight

        sheet.classList.remove('is-open');
        backdrop.classList.remove('is-open');
        document.body.style.overflow = '';

        setTimeout(function () {
            if (!sheet.classList.contains('is-open')) {
                sheet.hidden = true;
                backdrop.hidden = true;
                sheet.style.bottom = '';
                sheet.style.maxHeight = '';
            }
        }, 260);

        if (returnFocus && lastCard) { lastCard.focus(); }
    }

    cards.forEach(function (card) {
        card.addEventListener('click', function () { openSheet(card); });
    });
    closeBtn.addEventListener('click', function () { closeSheet(true); });
    backdrop.addEventListener('click', function () { closeSheet(true); });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && sheet.classList.contains('is-open')) {
            closeSheet(true);
        }
    });

    // --- 3. Save in the background -------------------------------------------
    function updateCard(worker) {
        sfSetStaffCard(worker, true); // brief green flash so the cashier SEES which card changed
    }

    // Builds an Error that carries a message safe to show the cashier.
    function userError(message, extra) {
        var err = new Error(message);
        err.userMessage = message;
        if (extra) { err.sessionExpired = !!extra.sessionExpired; }
        return err;
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (saving) { return; } // a double-tap on Save must never send twice

        showError('');

        // A record needs an amount, a tip, or both. Tip-only is allowed, so an
        // empty Amount is fine as long as a Tip is entered.
        var amountValue = parseFloat(amount.value) || 0;
        var tipValue = parseFloat(tip.value) || 0;
        if (amountValue < 0 || tipValue < 0) {
            showError('Amount and cashback cannot be negative.');
            return;
        }
        if (amountValue <= 0 && tipValue <= 0) {
            showError('Enter an amount, a cashback, or both.');
            amount.focus();
            return;
        }

        var savedName = workerName.textContent;
        setSaving(true);

        fetch(page.getAttribute('data-submit-url'), {
            method: 'POST',
            body: new FormData(form),
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(function (response) {
            var type = response.headers.get('content-type') || '';
            var isJson = type.indexOf('application/json') !== -1;

            // Session expired: the server redirected us to the login chooser.
            if (response.redirected && !isJson) {
                throw userError('Your session has expired. Taking you to log in...', { sessionExpired: true });
            }
            // Security token expired: PHP answers with plain text and status 419.
            if (response.status === 419) {
                throw userError('This page has expired. Refresh the page and try again.');
            }
            if (!isJson) {
                throw userError('Unexpected reply from the server. Check Today\'s Records before trying again.');
            }
            return response.json();
        })
        .then(function (data) {
            if (!data.success) {
                throw userError(data.error || 'Could not save the sale. Please try again.');
            }

            setSaving(false);
            if (data.worker) { updateCard(data.worker); }
            if (data.summary) { sfApplyCashierSummary(data.summary); } // stat cards at the top
            closeSheet(false);

            // Fresh start for the next customer: clear the search, show everyone.
            if (search) { search.value = ''; applyFilter(); }

            var savedText = amountValue > 0 ? formatMoney(amountValue) : formatMoney(tipValue) + ' cashback';
            showToast('Saved \u2713 ' + savedText + ' for ' + savedName);
        })
        .catch(function (err) {
            setSaving(false);

            if (err && err.sessionExpired) {
                showError(err.userMessage);
                setTimeout(function () {
                    window.location.href = page.getAttribute('data-login-url');
                }, 1500);
                return;
            }

            // userMessage exists for errors we raised ourselves; anything else
            // (e.g. no signal) is a network failure.
            showError(err && err.userMessage
                ? err.userMessage
                : 'Could not reach the server. Check your connection, then check Today\'s Records before trying again.');
        });
    });
});


// ---------------------------------------------------------------------------
// sfToast(message): small, non-blocking confirmation message.
// A message saved in sessionStorage under "sfToast" is shown on the next page
// (used when the staff queue empties and the page jumps to the dashboard).
// ---------------------------------------------------------------------------
(function () {
    var timer = null;

    function ensureToast() {
        var el = document.getElementById('sfToast');
        if (el) { return el; }
        var style = document.createElement('style');
        style.textContent =
            '#sfToast{position:fixed;left:50%;bottom:28px;transform:translate(-50%,20px);' +
            'background:#101828;color:#fff;padding:12px 20px;border-radius:999px;font-size:.95rem;' +
            'font-weight:600;box-shadow:0 8px 24px rgba(0,0,0,.25);opacity:0;pointer-events:none;' +
            'transition:opacity .2s ease,transform .2s ease;z-index:9999;max-width:90vw;text-align:center}' +
            '#sfToast.is-visible{opacity:1;transform:translate(-50%,0)}';
        document.head.appendChild(style);
        el = document.createElement('div');
        el.id = 'sfToast';
        el.setAttribute('role', 'status');
        el.setAttribute('aria-live', 'polite');
        document.body.appendChild(el);
        return el;
    }

    window.sfToast = function (message) {
        var el = ensureToast();
        clearTimeout(timer);
        el.textContent = message;
        void el.offsetWidth;
        el.classList.add('is-visible');
        timer = setTimeout(function () { el.classList.remove('is-visible'); }, 2200);
    };

    function showPending() {
        try {
            var msg = window.sessionStorage.getItem('sfToast');
            if (msg) {
                window.sessionStorage.removeItem('sfToast');
                window.sfToast(msg);
            }
        } catch (e) { /* storage unavailable: no toast, nothing breaks */ }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', showPending);
    } else {
        showPending();
    }
})();
