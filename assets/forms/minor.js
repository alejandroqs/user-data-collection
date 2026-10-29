document.addEventListener('DOMContentLoaded', function () {
    'use strict';
    document.querySelectorAll('.udc-minor').forEach(function (block) {
        var form = block.closest('form');
        var checkbox = block.querySelector('[name=udc_minor_declared]');
        var dob = form.querySelector('[name=udc_dob]');
        var notice = block.querySelector('.udc-minor-notice');
        var error = block.querySelector('.udc-minor-error');
        function update(showError) {
            notice.hidden = !checkbox.checked;
            var today = block.dataset.today;
            var value = dob.value;
            var age = Number(today.slice(0, 4)) - Number(value.slice(0, 4));
            if (today.slice(5) < value.slice(5)) age--;
            var invalid = /^\d{4}-\d{2}-\d{2}$/.test(value) && value <= today && age < 18 && !checkbox.checked;
            checkbox.setCustomValidity(invalid ? block.dataset.error : '');
            checkbox.setAttribute('aria-invalid', invalid && showError ? 'true' : 'false');
            error.textContent = invalid && showError ? block.dataset.error : '';
            return invalid;
        }
        checkbox.addEventListener('change', function () { update(true); });
        dob.addEventListener('change', function () { update(true); });
        checkbox.addEventListener('invalid', function () { update(true); });
        form.addEventListener('submit', function (event) {
            if (update(true)) { event.preventDefault(); checkbox.focus(); }
        });
        window.addEventListener('pageshow', function () { update(false); });
        update(false);
    });
});
