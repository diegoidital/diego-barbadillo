jQuery(function ($) {
	'use strict';

	// Validación básica antes de guardar un parte
	$('#post').on('submit', function () {
		var postType = $('#post_type').val();
		if (postType !== 'wpmm_parte') return true;

		var loc = $('#wpmm_localizacion').val();
		var desc = $('#wpmm_descripcion').val().trim();

		if (!loc) {
			alert('Por favor, selecciona una localización antes de guardar el parte.');
			$('#wpmm_localizacion').focus();
			return false;
		}

		if (!desc) {
			alert('Por favor, añade una descripción del trabajo.');
			$('#wpmm_descripcion').focus();
			return false;
		}

		return true;
	});

	// Resaltar campos requeridos vacíos
	$('#wpmm_localizacion, #wpmm_descripcion').on('change blur', function () {
		var $el = $(this);
		if (!$el.val() || (typeof $el.val() === 'string' && !$el.val().trim())) {
			$el.css('border-color', '#d63638');
		} else {
			$el.css('border-color', '');
		}
	});

	// Validar que fecha fin >= fecha inicio
	$('#wpmm_fecha_fin').on('change', function () {
		var ini = $('#wpmm_fecha_inicio').val();
		var fin = $(this).val();
		if (ini && fin && fin < ini) {
			alert('La fecha de finalización no puede ser anterior a la fecha de inicio.');
			$(this).val('');
		}
	});
});
