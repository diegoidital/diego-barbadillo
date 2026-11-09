jQuery(document).ready(function ($) {
    $('#start-import').on('click', function () {
        $('#import-log').html('<p>Iniciando creación del CSV...</p>');

        // Primera llamada AJAX: Crear y rellenar el CSV
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'import_products' // Acción PHP para crear el CSV
            },
            success: function (response) {
                if (response.success) {
                    $('#import-log').append('<p>' + response.data.message + '</p>');
                    //$('#import-log').append('<p>Iniciando importación de productos desde el CSV...</p>');

                    // Segunda llamada AJAX: Crear productos desde el CSV
                    processProducts(0, 10); // Iniciar desde el offset 0 con un límite de 10 productos por lote
                } else {
                    $('#import-log').append('<p>Error al crear el CSV: ' + response.data.message + '</p>');
                }
            },
            error: function (xhr, status, error) {
                $('#import-log').append('<p>Error en la solicitud AJAX: ' + error + '</p>');
            }
        });

        // Función para procesar productos desde el CSV
           /* function processProducts(offset, limit) {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'process_products_from_csv', // Acción PHP para procesar productos
                        offset: offset,
                        limit: limit
                    },
                    success: function (response) {
                        if (response.success) {
                            $('#import-log').append('<p>' + response.data.message + '</p>');

                            if (response.data.has_more) {
                                processProducts(response.data.next_offset, limit); // Procesar el siguiente lote
                            } else {
                                $('#import-log').append('<p>Importación completada.</p>');
                            }
                        } else {
                            $('#import-log').append('<p>Error al procesar productos: ' + response.data.message + '</p>');
                        }
                    },
                    error: function (xhr, status, error) {
                        $('#import-log').append('<p>Error en la solicitud AJAX: ' + error + '</p>');
                    }
                });
            }*/
    });
});