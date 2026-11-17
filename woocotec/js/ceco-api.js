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
                            $('#last-csv').text(m).attr('title', m);
                        }
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
                $('#stock-last-sync').text(response.success ? msg : '❌ ' + msg).attr('title', msg);
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

// Preparar fotos en lotes y regenerar el CSV al terminar
jQuery(document).ready(function ($) {
    var nonce = (window.cecoStock && cecoStock.nonce) || '';
    $('#process-images').on('click', function (e) {
        e.preventDefault();
        var $btn = $(this).prop('disabled', true);
        var $status = $('#images-status');

        function batch() {
            $.post(ajaxurl, { action: 'ceco_process_images', nonce: nonce })
                .done(function (r) {
                    if (!r.success) {
                        $status.text('❌ ' + ((r.data && r.data.message) || 'Error'));
                        $btn.prop('disabled', false);
                        return;
                    }
                    retries = 0;
                    $status.text(r.data.message);
                    if (r.data.remaining > 0 || r.data.busy) {
                        setTimeout(batch, r.data.busy ? 5000 : 200);
                        return;
                    }
                    $status.text(r.data.message + ' Regenerando el CSV...');
                    $.post(ajaxurl, { action: 'import_products' })
                        .done(function (c) {
                            var m = (c && c.data && c.data.message) || '';
                            $status.text(c.success ? '✅ Fotos preparadas y CSV regenerado. ' + m : '❌ ' + m);
                        })
                        .always(function () { $btn.prop('disabled', false); });
                })
                .fail(function (x, s, err) {
                    // Si el servidor corta un lote (p. ej. una foto enorme), el siguiente lote la descarta y sigue.
                    retries++;
                    var detail = 'HTTP ' + x.status + (err ? ' ' + err : '') +
                        (x.responseText ? ' — ' + $('<div>').html(x.responseText).text().replace(/\s+/g, ' ').substring(0, 200) : '');
                    if (retries <= 5) {
                        $status.text('⚠️ Un lote ha fallado (' + detail + '). Es normal si había una foto problemática: se descarta y se sigue. Reintentando en 10 s (' + retries + '/5)...');
                        setTimeout(batch, 10000);
                        return;
                    }
                    $status.text('❌ Error en la solicitud AJAX: ' + detail + '. Vuelve a pulsar para continuar.');
                    $btn.prop('disabled', false);
                });
        }
        var retries = 0;
        batch();
    });
});

// Corregir nombres con el EAN delante
jQuery(document).ready(function ($) {
    var nonce = (window.cecoStock && cecoStock.nonce) || '';
    var $res = $('#ean-names-result');
    function esc(t) { return $('<div>').text(t == null ? '' : String(t)).html(); }

    $('#find-ean-names').on('click', function (e) {
        e.preventDefault();
        var $btn = $(this).prop('disabled', true);
        $('#fix-ean-names').prop('disabled', true);
        $res.text('Buscando...');
        $.post(ajaxurl, { action: 'ceco_find_ean_names', nonce: nonce })
            .done(function (r) {
                if (!r.success) { $res.text('❌ ' + ((r.data && r.data.message) || 'Error')); return; }
                if (!r.data.count) { $res.html('<p>✅ No hay productos con el EAN delante del nombre.</p>'); return; }
                var html = '<p><strong>' + r.data.count + '</strong> productos con el EAN delante' + (r.data.count > 50 ? ' (se muestran 50)' : '') + ':</p>' +
                    '<table class="widefat striped"><thead><tr><th>ID</th><th>Nombre actual</th><th>Nombre corregido</th></tr></thead><tbody>';
                r.data.items.forEach(function (it) {
                    html += '<tr><td>' + it.id + '</td><td>' + esc(it.title) + '</td><td>' + esc(it.new) + '</td></tr>';
                });
                $res.html(html + '</tbody></table>');
                $('#fix-ean-names').prop('disabled', false).data('count', r.data.count);
            })
            .fail(function (x, s, err) { $res.text('❌ Error en la solicitud AJAX: ' + (err || s)); })
            .always(function () { $btn.prop('disabled', false); });
    });

    $('#fix-ean-names').on('click', function (e) {
        e.preventDefault();
        if (!window.confirm('Se corregirá el nombre de ' + $(this).data('count') + ' productos. ¿Continuar?')) { return; }
        var $btn = $(this).prop('disabled', true);
        var total = 0;
        var $st = $('<p>').prependTo($res);
        (function next() {
            $.post(ajaxurl, { action: 'ceco_fix_ean_names', nonce: nonce })
                .done(function (r) {
                    if (!r.success) { $st.text('❌ ' + ((r.data && r.data.message) || 'Error')); return; }
                    total += r.data.fixed;
                    if (r.data.remaining > 0 && r.data.fixed > 0) {
                        $st.text('Corrigiendo... ' + total + ' hechos, ' + r.data.remaining + ' pendientes');
                        next();
                    } else {
                        $st.text('✅ ' + total + ' nombres corregidos.' + (r.data.remaining ? ' (' + r.data.remaining + ' no se han podido corregir)' : ''));
                    }
                })
                .fail(function (x, s, err) { $st.text('❌ Error en la solicitud AJAX: ' + (err || s) + ' (' + total + ' corregidos). Vuelve a pulsar Buscar.'); });
        })();
    });
});

// Poner foto a los productos publicados que no tienen
jQuery(document).ready(function ($) {
    var nonce = (window.cecoStock && cecoStock.nonce) || '';
    var $res = $('#missing-photos-result');
    function esc(t) { return $('<div>').text(t == null ? '' : String(t)).html(); }

    $('#find-missing-photos').on('click', function (e) {
        e.preventDefault();
        var $btn = $(this).prop('disabled', true);
        $('#fix-missing-photos').prop('disabled', true);
        $res.text('Buscando...');
        $.post(ajaxurl, { action: 'ceco_find_missing_photos', nonce: nonce })
            .done(function (r) {
                if (!r.success) { $res.text('❌ ' + ((r.data && r.data.message) || 'Error')); return; }
                if (!r.data.count) { $res.html('<p>✅ No hay productos publicados sin foto que tengan fotos en el CSV.</p>'); return; }
                var html = '<p><strong>' + r.data.count + '</strong> productos publicados sin foto' + (r.data.count > 50 ? ' (se muestran 50)' : '') + ':</p>' +
                    '<table class="widefat striped"><thead><tr><th>ID</th><th>Producto</th><th>SKU</th><th>Fotos en el CSV</th></tr></thead><tbody>';
                r.data.items.forEach(function (it) {
                    html += '<tr><td><a href="post.php?post=' + it.id + '&action=edit" target="_blank">' + it.id + '</a></td><td>' + esc(it.title) + '</td><td>' + esc(it.sku) + '</td><td>' + it.n + '</td></tr>';
                });
                $res.html(html + '</tbody></table>');
                $('#fix-missing-photos').prop('disabled', false).data('count', r.data.count);
            })
            .fail(function (x, s, err) { $res.text('❌ Error en la solicitud AJAX: ' + (err || s)); })
            .always(function () { $btn.prop('disabled', false); });
    });

    $('#fix-missing-photos').on('click', function (e) {
        e.preventDefault();
        if (!window.confirm('Se pondrán fotos a ' + $(this).data('count') + ' productos. Puede tardar; deja la pestaña abierta. ¿Continuar?')) { return; }
        $(this).prop('disabled', true);
        var total = 0, errors = 0, retries = 0;
        var $st = $('<p>').prependTo($res);
        (function next() {
            $.post(ajaxurl, { action: 'ceco_fix_missing_photos', nonce: nonce })
                .done(function (r) {
                    if (!r.success) { $st.text('❌ ' + ((r.data && r.data.message) || 'Error')); return; }
                    retries = 0;
                    total += r.data.done; errors += r.data.errors;
                    if (r.data.remaining > 0 && (r.data.done > 0 || r.data.errors > 0)) {
                        $st.text('Poniendo fotos... ' + total + ' productos hechos, ' + r.data.remaining + ' pendientes' + (errors ? ', ' + errors + ' sin foto disponible' : ''));
                        next();
                    } else {
                        $st.text('✅ Fotos puestas a ' + total + ' productos.' + (errors ? ' ' + errors + ' no se han podido completar (fotos no descargables).' : ''));
                    }
                })
                .fail(function (x, s, err) {
                    retries++;
                    if (retries <= 5) {
                        $st.text('⚠️ Un lote ha fallado (HTTP ' + x.status + '). Se descarta el producto problemático y se sigue en 10 s (' + retries + '/5)...');
                        setTimeout(next, 10000);
                        return;
                    }
                    $st.text('❌ Error en la solicitud AJAX: HTTP ' + x.status + ' ' + (err || s) + ' (' + total + ' hechos). Vuelve a pulsar Buscar.');
                });
        })();
    });
});
