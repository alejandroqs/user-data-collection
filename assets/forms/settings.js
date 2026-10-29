(function () {
    'use strict';
    document.addEventListener('click', function (event) {
        var button = event.target.closest('.udc-media-select, .udc-media-remove');
        if (!button) return;
        var row = button.closest('.udc-media-setting');
        var input = row.querySelector('input');
        var name = row.querySelector('.udc-media-name');
        if (button.classList.contains('udc-media-remove')) {
            input.value = '0'; name.textContent = button.dataset.empty; return;
        }
        var frame = wp.media({title: button.dataset.title, multiple: false,
            library: {type: ['application/pdf', 'image/jpeg', 'image/png', 'image/webp', 'image/avif']}});
        frame.on('select', function () {
            var attachment = frame.state().get('selection').first().toJSON();
            input.value = attachment.id;
            name.textContent = attachment.filename || attachment.title;
        });
        frame.open();
    });
}());
