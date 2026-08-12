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
