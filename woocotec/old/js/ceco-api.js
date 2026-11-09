jQuery(document).ready(function ($) {
    $('#start-import').on('click', function () {
        var offset = 0;
        var limit = 10; // Número de productos a importar por solicitud

        function importProducts() {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'import_products',
                    offset: offset,
                    limit: limit
                },
                success: function (response) {
                    if (response.success) {
                        offset += limit;
                        if (response.data.has_more) {
                            importProducts();
                        } else {
                            $('#start-import').prop('disabled', true);
                            $('#import-log').append('<p>Importación completada.</p>');
                        }
                    } else {
                        $('#import-log').append('<p>Error en la importación: ' + response.data.message + '</p>');
                    }
                },
                error: function (xhr, status, error) {
                    console.log(xhr.responseText);
                    $('#import-log').append('<p>Error en la solicitud AJAX: ' + error + '<br>Respuesta del servidor: ' + xhr.responseText + '</p>');
                }
            });
        }

        importProducts();
    });
});