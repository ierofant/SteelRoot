window.initCommentReplies = function (root) {
    root = root || document;
    var details = Array.from(root.querySelectorAll('details.comment-item__reply'));
    var mainForm = root.querySelector('.comments-block__form');

    details.forEach(function (det) {
        det.addEventListener('toggle', function () {
            if (det.open) {
                // accordion: close all other reply forms
                details.forEach(function (other) {
                    if (other !== det) other.open = false;
                });
                // hide main form
                if (mainForm) mainForm.hidden = true;
            } else {
                // if no other reply is open, restore main form
                var anyOpen = details.some(function (d) { return d.open; });
                if (!anyOpen && mainForm) mainForm.hidden = false;
            }
        });
    });
};

document.addEventListener('DOMContentLoaded', function () {
    window.initCommentReplies(document);
    document.querySelectorAll('[data-comment-emoji]').forEach(function (button) {
        button.addEventListener('click', function () {
            var emoji = button.getAttribute('data-comment-emoji') || '';
            var form = button.closest('form');
            var textarea = form ? form.querySelector('textarea[name="body"]') : null;
            if (!textarea || !emoji) {
                return;
            }

            var start = typeof textarea.selectionStart === 'number' ? textarea.selectionStart : textarea.value.length;
            var end = typeof textarea.selectionEnd === 'number' ? textarea.selectionEnd : textarea.value.length;
            var prefix = textarea.value.slice(0, start);
            var suffix = textarea.value.slice(end);
            var needsSpaceBefore = prefix !== '' && !/\s$/.test(prefix);
            var needsSpaceAfter = suffix !== '' && !/^\s/.test(suffix);
            var insert = (needsSpaceBefore ? ' ' : '') + emoji + (needsSpaceAfter ? ' ' : '');

            textarea.value = prefix + insert + suffix;
            var caret = prefix.length + insert.length;
            textarea.focus();
            if (typeof textarea.setSelectionRange === 'function') {
                textarea.setSelectionRange(caret, caret);
            }
        });
    });

    document.querySelectorAll('[data-comment-check-all]').forEach(function (toggle) {
        toggle.addEventListener('change', function () {
            document.querySelectorAll('.comments-admin-table input[name="ids[]"]').forEach(function (checkbox) {
                checkbox.checked = toggle.checked;
            });
        });
    });

    document.querySelectorAll('[data-comments-bulk-form]').forEach(function (form) {
        form.addEventListener('submit', function () {
            form.querySelectorAll('input[name="ids[]"]').forEach(function (input) {
                input.remove();
            });
            var sourceSelector = form.getAttribute('data-comments-source') || '.comments-admin-table';
            document.querySelectorAll(sourceSelector + ' input[name="ids[]"]:checked').forEach(function (checkbox) {
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'ids[]';
                input.value = checkbox.value;
                form.appendChild(input);
            });
        });
    });

    document.querySelectorAll('form[data-comment-confirm="delete"]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            var action = form.querySelector('[name="bulk_action"]');
            var shouldConfirm = action ? ['delete', 'purge'].indexOf(action.value) !== -1 : true;
            if (!shouldConfirm) {
                return;
            }
            var message = action && action.value === 'purge'
                ? 'Delete selected comments permanently?'
                : 'Delete selected comments?';
            if (!window.confirm(message)) {
                event.preventDefault();
            }
        });
    });

    document.querySelectorAll('form[data-comment-confirm="purge"]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!window.confirm('Delete this comment permanently?')) {
                event.preventDefault();
            }
        });
    });
});
