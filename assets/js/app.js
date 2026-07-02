document.addEventListener('DOMContentLoaded', function () {
    var i18n = window.AppI18n || {};

    var noExpiryCheckbox = document.getElementById('no_expiry');
    var expiresAtInput = document.getElementById('expires_at');

    if (noExpiryCheckbox && expiresAtInput) {
        var toggleExpiryInput = function () {
            var unlimited = noExpiryCheckbox.checked;
            expiresAtInput.disabled = unlimited;
            expiresAtInput.required = !unlimited;
            if (unlimited) {
                expiresAtInput.value = '';
            }
        };

        noExpiryCheckbox.addEventListener('change', toggleExpiryInput);
        toggleExpiryInput();
    }

    var fileInput = document.getElementById('file');
    var displayNameInput = document.getElementById('display_name');
    var extensionLabel = document.getElementById('display_extension');

    var getFileExtension = function (filename) {
        var index = filename.lastIndexOf('.');
        if (index <= 0) {
            return '';
        }
        return filename.slice(index + 1).toLowerCase();
    };

    var getFileBasename = function (filename) {
        var index = filename.lastIndexOf('.');
        if (index <= 0) {
            return filename;
        }
        return filename.slice(0, index);
    };

    var updateDisplayExtension = function () {
        if (!extensionLabel) {
            return;
        }

        if (!fileInput || !fileInput.files.length) {
            extensionLabel.textContent = i18n.extension_unselected || 'Not selected';
            return;
        }

        var selectedFile = fileInput.files[0];
        var extension = getFileExtension(selectedFile.name);
        extensionLabel.textContent = extension ? '.' + extension : (i18n.extension_unselected || 'Not selected');

        if (displayNameInput && displayNameInput.value.trim() === '' && selectedFile.name) {
            displayNameInput.value = getFileBasename(selectedFile.name);
        }
    };

    if (fileInput) {
        fileInput.addEventListener('change', updateDisplayExtension);
        updateDisplayExtension();
    }

    document.querySelectorAll('.copy-btn').forEach(function (button) {
        button.addEventListener('click', async function () {
            var targetId = button.getAttribute('data-copy-target');
            var source = document.getElementById(targetId);
            if (!source) {
                return;
            }

            var text = source.value || source.textContent || '';
            try {
                if (navigator.clipboard && window.isSecureContext) {
                    await navigator.clipboard.writeText(text);
                } else {
                    source.hidden = false;
                    source.select();
                    document.execCommand('copy');
                    source.hidden = true;
                }
                var original = button.textContent;
                button.textContent = i18n.copy_success || 'Copied';
                setTimeout(function () {
                    button.textContent = original;
                }, 1500);
            } catch (error) {
                button.textContent = i18n.copy_failed || 'Copy failed';
            }
        });
    });

    document.querySelectorAll('.confirm-delete-form').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            var message = form.getAttribute('data-confirm') || i18n.confirm_delete || 'Are you sure you want to delete this?';
            if (!window.confirm(message)) {
                event.preventDefault();
            }
        });
    });
});
