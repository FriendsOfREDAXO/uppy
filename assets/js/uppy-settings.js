/**
 * Uppy Settings Page – Dateitypen-Sync
 *
 * Synchronisiert die Accordion-Checkboxen und das Freitext-Feld
 * mit dem sichtbaren readonly Textfeld #uppy-allowed-types.
 */
$(document).on('rex:ready', function () {
    var $input = $('#uppy-allowed-types');
    var $custom = $('#uppy-custom-types');

    if (!$input.length) {
        return;
    }

    function syncToInput() {
        var types = [];

        $('.uppy-type-cb:checked').each(function () {
            types.push($(this).val());
        });

        // Eigene MIME-Types hinzufügen
        if ($custom.length && $.trim($custom.val())) {
            var extra = $custom.val().split(',');
            for (var i = 0; i < extra.length; i++) {
                var t = $.trim(extra[i]);
                if (t && types.indexOf(t) === -1) {
                    types.push(t);
                }
            }
        }

        $input.val(types.join(','));

        // Badge-Zähler in den Akkordeon-Headern aktualisieren
        $('#uppy-types-accordion .panel').each(function () {
            var count = $(this).find('.uppy-type-cb:checked').length;
            var $badge = $(this).find('.panel-title .badge');
            if (count > 0) {
                if ($badge.length) {
                    $badge.text(count);
                } else {
                    $(this).find('.panel-title a').append(' <span class="badge">' + count + '</span>');
                }
            } else {
                $badge.remove();
            }
        });
    }

    $(document).on('change', '.uppy-type-cb', syncToInput);

    if ($custom.length) {
        $custom.on('input', syncToInput);
    }
});
