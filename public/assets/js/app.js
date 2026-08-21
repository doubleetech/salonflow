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