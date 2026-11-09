jQuery(document).ready(function ($) {
    $('#start-import').on('click', function () {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'import_products',
                offset: 0,
                limit: -1 // -1 para indicar que no hay límite
            },
            success: function (response) {
                if (response.success) {
                    $('#start-import').prop('disabled', true);
                    $('#import-log').append('<p>Importación completada.</p>');
                } else {
                    $('#import-log').append('<p>Error en la importación: ' + response.data.message + '</p>');
                }
            },
            error: function (xhr, status, error) {
                console.log(xhr.responseText);
                $('#import-log').append('<p>Error en la solicitud AJAX: ' + error + '<br>Respuesta del servidor: ' + xhr.responseText + '</p>');
            }
        });
    });
});