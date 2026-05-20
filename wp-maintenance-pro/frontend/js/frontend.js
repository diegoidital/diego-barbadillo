/* === WP Maintenance Pro — Frontend JS === */
(function ($) {
	'use strict';

	var PARTE_ID = wmproData.parte_id;
	var NONCE    = wmproData.nonce;
	var AJAX     = wmproData.ajax_url;
	var i18n     = wmproData.i18n;

	if (!PARTE_ID) return;

	/* ================================================================
	   FIRMA (Signature Pad)
	   ================================================================ */
	var SignaturePad = {
		canvas: null,
		ctx: null,
		drawing: false,
		hasContent: false,

		init: function (id) {
			this.canvas = document.getElementById(id);
			if (!this.canvas) return;
			this.ctx = this.canvas.getContext('2d');
			this.ctx.strokeStyle = '#000';
			this.ctx.lineWidth = 2.5;
			this.ctx.lineCap = 'round';
			this.ctx.lineJoin = 'round';
			this.resizeCanvas();

			this.canvas.addEventListener('mousedown', this.startDraw.bind(this));
			this.canvas.addEventListener('mousemove', this.draw.bind(this));
			this.canvas.addEventListener('mouseup', this.stopDraw.bind(this));
			this.canvas.addEventListener('mouseleave', this.stopDraw.bind(this));

			this.canvas.addEventListener('touchstart', function (e) { e.preventDefault(); this.startDraw(e.touches[0]); }.bind(this), { passive: false });
			this.canvas.addEventListener('touchmove', function (e) { e.preventDefault(); this.draw(e.touches[0]); }.bind(this), { passive: false });
			this.canvas.addEventListener('touchend', this.stopDraw.bind(this));
		},

		resizeCanvas: function () {
			var rect = this.canvas.getBoundingClientRect();
			var data = this.canvas.toDataURL();
			this.canvas.width  = rect.width  * window.devicePixelRatio;
			this.canvas.height = rect.height * window.devicePixelRatio;
			this.ctx.scale(window.devicePixelRatio, window.devicePixelRatio);
			this.ctx.strokeStyle = '#000';
			this.ctx.lineWidth = 2.5;
			this.ctx.lineCap = 'round';
			this.ctx.lineJoin = 'round';
		},

		getPos: function (e) {
			var rect = this.canvas.getBoundingClientRect();
			return {
				x: (e.clientX - rect.left),
				y: (e.clientY - rect.top)
			};
		},

		startDraw: function (e) {
			this.drawing = true;
			var pos = this.getPos(e);
			this.ctx.beginPath();
			this.ctx.moveTo(pos.x, pos.y);
		},

		draw: function (e) {
			if (!this.drawing) return;
			var pos = this.getPos(e);
			this.ctx.lineTo(pos.x, pos.y);
			this.ctx.stroke();
			this.hasContent = true;
		},

		stopDraw: function () { this.drawing = false; },

		clear: function () {
			this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
			this.hasContent = false;
		},

		isEmpty: function () { return !this.hasContent; },

		toDataURL: function () { return this.canvas.toDataURL('image/png'); }
	};

	SignaturePad.init('wmpro-signature-canvas');

	$('#wmpro-clear-signature').on('click', function () {
		SignaturePad.clear();
	});

	$('#wmpro-refirmar-btn').on('click', function () {
		$('#wmpro-firma-guardada').hide();
		$('#wmpro-firma-wrapper').show();
		SignaturePad.resizeCanvas();
	});

	$('#wmpro-save-signature-btn').on('click', function () {
		if (SignaturePad.isEmpty()) {
			showAlert(i18n.error_firma, 'warning');
			return;
		}
		var nombre = $('#wmpro-nombre-firmante').val().trim();
		if (!nombre) {
			showAlert(i18n.error_nombre_firma, 'warning');
			$('#wmpro-nombre-firmante').focus();
			return;
		}
		var $btn = $(this).prop('disabled', true).text('Guardando...');
		$.post(AJAX, {
			action:          'wmpro_save_signature',
			nonce:           NONCE,
			parte_id:        PARTE_ID,
			signature:       SignaturePad.toDataURL(),
			nombre_firmante: nombre,
		})
		.done(function (res) {
			if (res.success) {
				$('#wmpro-firma-wrapper').hide();
				var $saved = $('#wmpro-firma-guardada');
				if ($saved.length) {
					$saved.find('img').attr('src', res.data.url + '?' + Date.now());
					$saved.find('.wmpro-firmante').html('Firmado por: <strong>' + $('<div>').text(nombre).html() + '</strong>');
					$saved.show();
				} else {
					$('#wmpro-firma-wrapper').before(
						'<div id="wmpro-firma-guardada" class="wmpro-firma-guardada">' +
						'<img src="' + res.data.url + '" alt="Firma guardada" class="wmpro-firma-img">' +
						'<p class="wmpro-firmante">Firmado por: <strong>' + $('<div>').text(nombre).html() + '</strong></p>' +
						'<button type="button" id="wmpro-refirmar-btn" class="wmpro-btn wmpro-btn--outline">✏️ Volver a firmar</button>' +
						'</div>'
					);
					$(document).on('click', '#wmpro-refirmar-btn', function () {
						$('#wmpro-firma-guardada').hide();
						$('#wmpro-firma-wrapper').show();
						SignaturePad.resizeCanvas();
					});
				}
			} else {
				showAlert(res.data.msg || i18n.error_generico, 'error');
			}
		})
		.fail(function () { showAlert(i18n.error_generico, 'error'); })
		.always(function () { $btn.prop('disabled', false).text('💾 Guardar firma'); });
	});

	/* ================================================================
	   CHECKLIST
	   ================================================================ */
	$(document).on('change', '.wmpro-task-check', function () {
		var $item   = $(this).closest('.wmpro-checklist__item');
		var task_id = $(this).data('task-id');
		var $cb     = $(this);

		$cb.prop('disabled', true);
		$.post(AJAX, { action: 'wmpro_toggle_task', nonce: NONCE, parte_id: PARTE_ID, task_id: task_id })
			.done(function (res) {
				if (res.success) {
					if (res.data.completada) {
						$item.addClass('is-done');
						if (!$item.find('.wmpro-task-ts').length) {
							$item.append('<small class="wmpro-task-ts">✓ ahora</small>');
						}
					} else {
						$item.removeClass('is-done');
						$item.find('.wmpro-task-ts').remove();
					}
					updateCounter(res.data.hechas, res.data.total);
				} else {
					$cb.prop('checked', !$cb.prop('checked'));
				}
			})
			.fail(function () { $cb.prop('checked', !$cb.prop('checked')); })
			.always(function () { $cb.prop('disabled', false); });
	});

	function updateCounter(hechas, total) {
		var pct = total > 0 ? Math.round(hechas / total * 100) : 0;
		$('#wmpro-tasks-counter').text(hechas + '/' + total + ' completadas');
		$('#wmpro-progress-text').text(hechas + '/' + total);
		$('#wmpro-progress-fill').css('width', pct + '%');
	}

	/* Añadir tarea */
	$('#wmpro-add-task-btn, #wmpro-new-task-input').on('click keydown', function (e) {
		if (e.type === 'keydown' && e.key !== 'Enter') return;
		if (e.type === 'click' && $(this).attr('id') === 'wmpro-new-task-input') return;

		var texto = $('#wmpro-new-task-input').val().trim();
		if (!texto) { $('#wmpro-new-task-input').focus(); return; }

		$('#wmpro-add-task-btn').prop('disabled', true);
		$.post(AJAX, { action: 'wmpro_add_task', nonce: NONCE, parte_id: PARTE_ID, texto: texto })
			.done(function (res) {
				if (res.success) {
					var t = res.data.task;
					$('#wmpro-no-tasks').remove();
					var html = '<li class="wmpro-checklist__item" data-task-id="' + t.id + '">' +
						'<label class="wmpro-task-label">' +
						'<input type="checkbox" class="wmpro-task-check" data-task-id="' + t.id + '">' +
						'<span class="wmpro-task-text">' + $('<div>').text(t.texto).html() + '</span>' +
						'</label>' +
						'<button type="button" class="wmpro-btn-delete-task" data-task-id="' + t.id + '" title="Eliminar tarea">✕</button>' +
						'</li>';
					$('#wmpro-checklist').append(html);
					$('#wmpro-new-task-input').val('').focus();

					var current = parseInt($('#wmpro-tasks-counter').text()) || 0;
					var parts = $('#wmpro-tasks-counter').text().split('/');
					var total = parts.length > 1 ? parseInt(parts[1]) + 1 : 1;
					var hechas = parts.length > 1 ? parseInt(parts[0]) : 0;
					updateCounter(hechas, total);
				}
			})
			.always(function () { $('#wmpro-add-task-btn').prop('disabled', false); });
	});

	/* Eliminar tarea */
	$(document).on('click', '.wmpro-btn-delete-task', function () {
		if (!confirm(i18n.confirm_delete_tarea)) return;
		var task_id = $(this).data('task-id');
		var $item   = $(this).closest('.wmpro-checklist__item');

		$.post(AJAX, { action: 'wmpro_delete_task', nonce: NONCE, parte_id: PARTE_ID, task_id: task_id })
			.done(function (res) {
				if (res.success) {
					$item.fadeOut(200, function () {
						$(this).remove();
						var $items  = $('.wmpro-checklist__item');
						var total   = $items.length;
						var hechas  = $items.filter('.is-done').length;
						updateCounter(hechas, total);
						if (total === 0) $('#wmpro-checklist').append('<li class="wmpro-checklist__empty" id="wmpro-no-tasks">No hay tareas definidas.</li>');
					});
				}
			});
	});

	/* ================================================================
	   AUTOSAVE (observaciones, fechas)
	   ================================================================ */
	var autosaveTimer;
	$(document).on('change input', '.wmpro-autosave', function () {
		var $el    = $(this);
		var field  = $el.data('field');
		var value  = $el.val();
		clearTimeout(autosaveTimer);
		autosaveTimer = setTimeout(function () {
			$.post(AJAX, { action: 'wmpro_save_field', nonce: NONCE, parte_id: PARTE_ID, field: field, value: value });
		}, 800);
	});

	/* ================================================================
	   SUBIDA DE FOTOS
	   ================================================================ */
	var $uploadArea  = $('#wmpro-upload-area');
	var $fotoInput   = $('#wmpro-foto-input');
	var $photosGrid  = $('#wmpro-photos-grid');

	$uploadArea.on('dragover', function (e) { e.preventDefault(); $(this).addClass('dragover'); });
	$uploadArea.on('dragleave drop', function (e) { e.preventDefault(); $(this).removeClass('dragover'); });
	$uploadArea.on('drop', function (e) {
		var files = e.originalEvent.dataTransfer.files;
		uploadFiles(files);
	});

	$fotoInput.on('change', function () {
		uploadFiles(this.files);
		$(this).val('');
	});

	function uploadFiles(files) {
		if (!files || !files.length) return;
		Array.from(files).forEach(uploadSingleFile);
	}

	function uploadSingleFile(file) {
		var $progress = $('#wmpro-upload-progress').show();
		var $msg = $progress.find('.wmpro-upload-msg').text('Subiendo ' + file.name + '...');

		var fd = new FormData();
		fd.append('action',   'wmpro_upload_photo');
		fd.append('nonce',    NONCE);
		fd.append('parte_id', PARTE_ID);
		fd.append('foto',     file);

		$.ajax({
			url: AJAX, type: 'POST', data: fd,
			processData: false, contentType: false,
		})
		.done(function (res) {
			if (res.success) {
				var html = '<div class="wmpro-photo-item" data-attach-id="' + res.data.attach_id + '">' +
					'<a href="' + res.data.url + '" target="_blank" class="wmpro-photo-link">' +
					'<img src="' + res.data.thumb + '" alt="Foto del parte" loading="lazy">' +
					'</a>' +
					'<button type="button" class="wmpro-btn-delete-photo" data-attach-id="' + res.data.attach_id + '" title="Eliminar foto">✕</button>' +
					'</div>';
				$photosGrid.append(html);
			} else {
				showAlert(res.data.msg || 'Error al subir la imagen.', 'error');
			}
		})
		.fail(function () { showAlert('Error de red al subir la imagen.', 'error'); })
		.always(function () { $progress.hide(); });
	}

	/* Eliminar foto */
	$(document).on('click', '.wmpro-btn-delete-photo', function () {
		if (!confirm(i18n.confirm_delete_foto)) return;
		var attach_id = $(this).data('attach-id');
		var $item     = $(this).closest('.wmpro-photo-item');

		$.post(AJAX, { action: 'wmpro_delete_photo', nonce: NONCE, parte_id: PARTE_ID, attach_id: attach_id })
			.done(function (res) { if (res.success) $item.fadeOut(200, function () { $(this).remove(); }); });
	});

	/* ================================================================
	   ENVIAR PARTE
	   ================================================================ */
	$('#wmpro-submit-btn').on('click', function () {
		var firmaGuardada = $('#wmpro-firma-guardada').is(':visible') ||
			(window.getComputedStyle && document.getElementById('wmpro-firma-guardada') &&
			 document.getElementById('wmpro-firma-guardada').offsetParent !== null);

		var nombre = $('#wmpro-nombre-firmante').val().trim() ||
					 ($('#wmpro-firma-guardada').find('.wmpro-firmante strong').text()) || '';

		if (!firmaGuardada && SignaturePad.isEmpty()) {
			showAlert(i18n.error_firma, 'warning', '#wmpro-submit-msg');
			$('#wmpro-firma-wrapper')[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
			return;
		}

		if (!confirm(i18n.confirm_submit)) return;

		var $btn = $(this).prop('disabled', true).text(i18n.enviando);
		$('#wmpro-submit-msg').hide();

		// Guardar observaciones antes
		var obs = $('#wmpro-observaciones').val();
		$.post(AJAX, { action: 'wmpro_save_field', nonce: NONCE, parte_id: PARTE_ID, field: '_wmpro_observaciones', value: obs });

		$.post(AJAX, {
			action:          'wmpro_submit_parte',
			nonce:           NONCE,
			parte_id:        PARTE_ID,
			nombre_firmante: nombre,
		})
		.done(function (res) {
			if (res.success) {
				$('#wmpro-submit-msg')
					.removeClass('wmpro-alert--error wmpro-alert--warning')
					.addClass('wmpro-alert wmpro-alert--success')
					.html(i18n.enviado)
					.show();
				$btn.remove();
				$('.wmpro-parte__badges .wmpro-badge').first()
					.removeClass().addClass('wmpro-badge wmpro-badge--enviado').text('📧 Enviado');
				$('.wmpro-submit-info').hide();
			} else {
				showAlert(res.data.msg || i18n.error_generico, 'error', '#wmpro-submit-msg');
				$btn.prop('disabled', false).text('📧 Enviar parte');
			}
		})
		.fail(function () {
			showAlert(i18n.error_generico, 'error', '#wmpro-submit-msg');
			$btn.prop('disabled', false).text('📧 Enviar parte');
		});
	});

	/* ================================================================
	   UTILS
	   ================================================================ */
	function showAlert(msg, type, selector) {
		var $el = selector ? $(selector) : null;
		if ($el && $el.length) {
			$el.attr('class', 'wmpro-alert wmpro-alert--' + type).html(msg).show();
			$el[0].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
		} else {
			var $alert = $('<div class="wmpro-alert wmpro-alert--' + type + '">' + msg + '</div>');
			$('.wmpro-parte').prepend($alert);
			setTimeout(function () { $alert.fadeOut(400, function () { $(this).remove(); }); }, 5000);
		}
	}

	/* Resize signature canvas on orientation change */
	window.addEventListener('orientationchange', function () {
		setTimeout(function () {
			if (SignaturePad.canvas) SignaturePad.resizeCanvas();
		}, 300);
	});

})(jQuery);
