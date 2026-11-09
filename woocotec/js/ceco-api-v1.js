/*
    jQuery(document).ready(function ($) {

        let offset = 0; // Comenzar desde el primer producto
        const logContainer = $('#import-log'); // Contenedor para mostrar el log

        function importProducts() {
            $.ajax({
                url: ajaxurl, // URL de WordPress para solicitudes AJAX
                type: 'POST',
                data: {
                    action: 'import_products', // Acción registrada en PHP
                    offset: offset // Enviar el offset actual
                },
                success: function (response) {
                    if (response.success) {
                        logContainer.append('<pre>' + response.data.log + '</pre>');
                        logContainer.append('<p>' + response.data.message + '</p>');

                        if (response.data.has_more) {
                            offset = response.data.next_offset;
                            importProducts(); // Continuar con el siguiente lote
                        } else {
                            logContainer.append('<p>Importación completada.</p>');
                        }
                    } else {
                        logContainer.append('<p>Error: ' + response.data.message + '</p>');
                    }
                }
            });
        }

        // Iniciar la importación al hacer clic en el botón
        $('#start-import').on('click', function () {
            logContainer.empty(); // Limpiar el log
            offset = 0; // Reiniciar el offset
            importProducts(); // Iniciar la importación
        });
    });
*/

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
            console.log("Respuesta del servidor:", response);
            if (response.success) {
                // Mostrar los mensajes en el log
                response.data.log.forEach(function (message) {
                    $('#import-log').append('<p>' + message + '</p>');
                });

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
            //console.log("Error AJAX:", xhr.responseText);
            $('#import-log').append('<p>Error en la solicitud AJAX: ' + error + '<br>Respuesta del servidor: ' + xhr.responseText + '</p>');
        }
    });
}