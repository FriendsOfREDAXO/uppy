(function($) {
    $(document).on('rex:ready', function() {
        // Modal öffnen und Checkboxen basierend auf Input setzen
        $("#uppy-types-modal").on("show.bs.modal", function() {
            var currentTypes = $("#uppy-allowed-types").val().split(",");
            currentTypes = currentTypes.map(function(t) { return t.trim(); });
            
            $(".uppy-type-cb").prop("checked", false);
            
            $(".uppy-type-cb").each(function() {
                if (currentTypes.indexOf($(this).val()) > -1) {
                    $(this).prop("checked", true);
                }
            });
        });
        
        // Auswahl übernehmen
        $("#uppy-apply-types").on("click", function() {
            var selectedTypes = [];
            $(".uppy-type-cb:checked").each(function() {
                selectedTypes.push($(this).val());
            });
            
            $("#uppy-allowed-types").val(selectedTypes.join(","));
            $("#uppy-types-modal").modal("hide");
        });
        
        // Auth-Warning Toggle
        function toggleAuthWarning() {
            if ($("#uppy-auth-disable").is(":checked")) {
                $("#uppy-auth-warning").slideDown();
            } else {
                $("#uppy-auth-warning").slideUp();
            }
        }
        
        // Event-Handler neu binden (falls vorhanden, erst entfernen um doppelte Bindings bei Pjax zu meiden)
        $("#uppy-auth-disable").off("change", toggleAuthWarning).on("change", toggleAuthWarning);
        
        // Initial ausführen
        toggleAuthWarning();
    });
})(jQuery);
