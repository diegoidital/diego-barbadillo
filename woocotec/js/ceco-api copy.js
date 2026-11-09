        jQuery(document).ready(function ($) {
            $('#start-import').on('click', function () {
                var offset = 0;
                var limit = 5; // Reducir el tamaño del lote a 5 productos

                function importProducts(offset, limit) {
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
                                $('#import-log').append('<p>' + response.data.message + '</p>');
                                if (response.data.has_more) {
                                    importProducts(response.data.next_offset, limit);
                                } else {
                                    $('#import-log').append('<p>Importación completada.</p>');
                                }
                            } else {
                                $('#import-log').append('<p>Error en la importación: ' + response.data.message + '</p>');
                            }
                        },
                        error: function (xhr, status, error) {
                            $('#import-log').append('<p>Error en la solicitud AJAX: ' + error + '<br>Respuesta del servidor: ' + xhr.responseText + '</p>');
                        }
                    });
                }

                // Iniciar la importación
                $('#import-log').html('<p>Iniciando importación...</p>');
                importProducts(0, limit);
            });
        });
