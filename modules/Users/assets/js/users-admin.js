document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('users-bulk-delete-form');
    if (!form) {
        return;
    }

    var selectAll = document.querySelector('[data-users-select-all]');
    var checkboxes = Array.prototype.slice.call(document.querySelectorAll('[data-users-select]'));
    var submitButton = document.querySelector('[data-users-bulk-delete]');
    var hint = document.querySelector('[data-users-selection-hint]');

    var syncState = function () {
        var selected = checkboxes.filter(function (checkbox) {
            return checkbox.checked;
        });
        var count = selected.length;

        if (submitButton) {
            submitButton.disabled = count === 0;
        }

        if (hint) {
            hint.textContent = count > 0
                ? 'Selected: ' + count
                : 'Select users in the table to delete them together.';
        }

        if (selectAll) {
            selectAll.checked = count > 0 && count === checkboxes.length;
            selectAll.indeterminate = count > 0 && count < checkboxes.length;
        }

        checkboxes.forEach(function (checkbox) {
            var row = checkbox.closest('tr');
            if (!row) {
                return;
            }
            row.classList.toggle('is-selected', checkbox.checked);
        });
    };

    if (selectAll) {
        selectAll.addEventListener('change', function () {
            checkboxes.forEach(function (checkbox) {
                checkbox.checked = selectAll.checked;
            });
            syncState();
        });
    }

    checkboxes.forEach(function (checkbox) {
        checkbox.addEventListener('change', syncState);
    });

    syncState();
});
