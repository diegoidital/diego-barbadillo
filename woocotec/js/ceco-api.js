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

// Limpieza de borradores antiguos de Cecotec
jQuery(document).ready(function ($) {
    var nonce = (window.cecoStock && cecoStock.nonce) || '';
    var foundIds = [];
    var $result = $('#old-drafts-result');

    function esc(text) {
        return $('<div>').text(text == null ? '' : String(text)).html();
    }

    $('#find-old-drafts').on('click', function (e) {
        e.preventDefault();
        var $btn = $(this).prop('disabled', true);
        $('#trash-old-drafts').prop('disabled', true);
        foundIds = [];
        $result.text('Buscando...');

        $.post(ajaxurl, { action: 'ceco_find_old_drafts', nonce: nonce })
            .done(function (r) {
                if (!r.success) {
                    $result.text('❌ ' + ((r.data && r.data.message) || 'Error'));
                    return;
                }
                var items = r.data.items || [];
                foundIds = items.map(function (it) { return it.id; });
                if (!items.length) {
                    $result.html('<p>✅ No hay borradores antiguos que coincidan con el CSV.</p>');
                    return;
                }
                var html = '<p><strong>' + items.length + '</strong> borradores antiguos encontrados. Revisa la lista antes de enviarlos a la papelera.</p>' +
                    '<table class="widefat striped"><thead><tr><th>ID</th><th>Borrador antiguo</th><th>SKU</th><th>Última modificación</th><th>Producto del CSV</th></tr></thead><tbody>';
                items.forEach(function (it) {
                    html += '<tr><td><a href="post.php?post=' + it.id + '&action=edit" target="_blank">' + it.id + '</a></td><td>' + esc(it.title) +
                        '</td><td>' + esc(it.sku) + '</td><td>' + esc(it.modified) + '</td><td>' + esc(it.csv_name) + '</td></tr>';
                });
                html += '</tbody></table>';
                $result.html(html);
                $('#trash-old-drafts').prop('disabled', false);
            })
            .fail(function (x, s, err) { $result.text('❌ Error en la solicitud AJAX: ' + (err || s)); })
            .always(function () { $btn.prop('disabled', false); });
    });

    $('#trash-old-drafts').on('click', function (e) {
        e.preventDefault();
        if (!foundIds.length) { return; }
        if (!window.confirm('Se enviarán ' + foundIds.length + ' borradores antiguos a la papelera. ¿Continuar?')) { return; }

        var $btn = $(this).prop('disabled', true);
        var pending = foundIds.slice();
        var total = 0;
        var $status = $('<p>').prependTo($result);

        function next() {
            if (!pending.length) {
                $status.text('✅ ' + total + ' borradores enviados a la papelera.');
                foundIds = [];
                return;
            }
            var batch = pending.splice(0, 100);
            $status.text('Enviando a la papelera... ' + total + ' de ' + foundIds.length);
            $.post(ajaxurl, { action: 'ceco_trash_old_drafts', nonce: nonce, ids: batch })
                .done(function (r) {
                    if (!r.success) {
                        $status.text('❌ ' + ((r.data && r.data.message) || 'Error') + ' (' + total + ' enviados)');
                        $btn.prop('disabled', false);
                        return;
                    }
                    total += r.data.trashed;
                    next();
                })
                .fail(function (x, s, err) {
                    $status.text('❌ Error en la solicitud AJAX: ' + (err || s) + ' (' + total + ' enviados). Puedes volver a buscar y repetir.');
                    $btn.prop('disabled', false);
                });
        }
        next();
    });
});
