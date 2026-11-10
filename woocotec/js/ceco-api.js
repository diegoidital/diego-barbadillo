jQuery(document).ready(function ($) {
    var nonce = (window.cecoStock && cecoStock.nonce) || '';
    var $log = $('#import-log');

    function log(text) {
        $log.append($('<p>').text(text));
    }

    // Importación manual: 1) descargar catálogo de Plytix (si hace falta) 2) generar el CSV.
    $('#start-import').on('click', function (e) {
        e.preventDefault();
        var $btn = $(this).prop('disabled', true);
        var force = $('#force-catalog').is(':checked') ? 1 : 0;
        $log.empty();
        log('1/2 Comprobando el catálogo de Plytix (la descarga son unos 280 MB y puede tardar varios minutos)...');

        $.post(ajaxurl, { action: 'ceco_download_catalog', nonce: nonce, force: force })
            .done(function (response) {
                var msg = (response && response.data && response.data.message) || 'Respuesta inesperada.';
                if (!response.success) {
                    log('❌ ' + msg);
                    $btn.prop('disabled', false);
                    return;
                }
                log(msg);
                log('2/2 Generando el CSV con precios, stock y fichas de producto...');

                $.post(ajaxurl, { action: 'import_products' })
                    .done(function (r) {
                        var m = (r && r.data && r.data.message) || 'Respuesta inesperada.';
                        log(r.success ? m : '❌ Error al crear el CSV: ' + m);
                        if (r.success) {
                            log('Siguiente paso: All Import › Manage Imports › ejecutar «Productos Cecotec».');
                        }
                    })
                    .fail(function (xhr, status, error) {
                        log('❌ Error en la solicitud AJAX: ' + (error || status));
                    })
                    .always(function () {
                        $btn.prop('disabled', false);
                    });
            })
            .fail(function (xhr, status, error) {
                log('❌ Error al descargar el catálogo: ' + (error || status) + '. Si el servidor corta la petición, el catálogo se descarga igualmente cada noche por cron.');
                $btn.prop('disabled', false);
            });
    });

    // Sincronización manual del stock con el feed de Cecotec
    $('#start-stock-sync').on('click', function (e) {
        e.preventDefault();
        var $btn = $(this).prop('disabled', true);
        $('#stock-last-sync').text('Sincronizando stock, puede tardar unos minutos...');

        $.post(ajaxurl, { action: 'ceco_sync_stock', nonce: nonce })
            .done(function (response) {
                var msg = (response && response.data && response.data.message) || 'Respuesta inesperada.';
                $('#stock-last-sync').text(response.success ? msg : '❌ ' + msg);
            })
            .fail(function (xhr, status, error) {
                $('#stock-last-sync').text('❌ Error en la solicitud AJAX: ' + (error || status));
            })
            .always(function () {
                $btn.prop('disabled', false);
            });
    });
});
