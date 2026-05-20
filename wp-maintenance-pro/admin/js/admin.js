jQuery(function ($) {
	'use strict';

	/* ---- Recurrencia toggle ---- */
	$('#wmpro-recurrencia').on('change', function () {
		$('#wmpro-recurrencia-opts').toggle($(this).val() !== 'ninguna');
	});

	/* ---- Plantilla de tareas (sortable + add/remove) ---- */
	var taskIndex = $('#wmpro-template-tasks .wmpro-task-item').length;

	if ($('#wmpro-template-tasks').length) {
		$('#wmpro-template-tasks').sortable({
			handle: '.wmpro-drag-handle',
			placeholder: 'wmpro-task-item wmpro-task-placeholder',
			update: function () { renumberTasks(); }
		});
	}

	$(document).on('click', '.wmpro-remove-task', function () {
		$(this).closest('.wmpro-task-item').fadeOut(200, function () {
			$(this).remove();
			renumberTasks();
		});
	});

	$('#wmpro-add-template-task').on('click', function () {
		var $list = $('#wmpro-template-tasks');
		var $empty = $list.find('.wmpro-task-empty');
		if ($empty.length) $empty.remove();

		var html = '<li class="wmpro-task-item" data-index="' + taskIndex + '">' +
			'<span class="wmpro-drag-handle dashicons dashicons-move"></span>' +
			'<input type="text" name="_wmpro_tareas_template[' + taskIndex + '][texto]" ' +
			'class="widefat wmpro-task-input" placeholder="Descripción de la tarea...">' +
			'<button type="button" class="button wmpro-remove-task" title="Eliminar">✕</button>' +
			'</li>';

		$list.append(html);
		$list.find('.wmpro-task-item:last .wmpro-task-input').focus();
		taskIndex++;
	});

	function renumberTasks() {
		$('#wmpro-template-tasks .wmpro-task-item').each(function (i) {
			$(this).attr('data-index', i);
			$(this).find('input').attr('name', '_wmpro_tareas_template[' + i + '][texto]');
		});
	}

	/* ---- Validación parte ---- */
	$('#post').on('submit', function () {
		if ($('#post_type').val() !== 'wmpro_parte') return true;
		if (!$('[name="_wmpro_proyecto_id"]').val()) {
			alert('Selecciona un proyecto antes de guardar.');
			return false;
		}
		return true;
	});
});
