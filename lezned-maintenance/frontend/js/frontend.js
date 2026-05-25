/**
 * Lezned Maintenance Manager – Frontend JS
 * Requires: jQuery
 */

/* global lzndData, jQuery */

( function ( $ ) {
    'use strict';

    var ajax_url   = ( window.lzndData && lzndData.ajax_url ) || '';
    var nonce      = ( window.lzndData && lzndData.nonce )    || '';
    var i18n       = ( window.lzndData && lzndData.i18n )     || {};
    var $app       = $( '.lznd-app' );
    var actuacionId = parseInt( $app.attr( 'data-actuacion-id' ), 10 ) || 0;

    /* ========================================================================
       Signature pads
       ======================================================================== */

    var sigPads = {};
    var pendingChannel = '';

    function initSignaturePad( canvasId ) {
        var canvas = document.getElementById( canvasId );
        if ( ! canvas ) return;

        var ctx      = canvas.getContext( '2d' );
        var drawing  = false;
        var lastX    = 0;
        var lastY    = 0;
        var isEmpty  = true;

        function resizeCanvas() {
            var ratio = Math.max( window.devicePixelRatio || 1, 1 );
            var w = canvas.offsetWidth;
            var h = canvas.offsetHeight;
            var data = ctx.getImageData( 0, 0, canvas.width, canvas.height );
            canvas.width  = w * ratio;
            canvas.height = h * ratio;
            ctx.scale( ratio, ratio );
            if ( ! isEmpty ) {
                ctx.putImageData( data, 0, 0 );
            }
            ctx.lineCap   = 'round';
            ctx.lineJoin  = 'round';
            ctx.lineWidth = 2;
            ctx.strokeStyle = '#1a1a2e';
        }

        function getPos( e ) {
            var rect = canvas.getBoundingClientRect();
            if ( e.touches && e.touches[0] ) {
                return { x: e.touches[0].clientX - rect.left, y: e.touches[0].clientY - rect.top };
            }
            return { x: e.clientX - rect.left, y: e.clientY - rect.top };
        }

        function startDraw( e ) {
            e.preventDefault();
            drawing = true;
            var pos = getPos( e );
            lastX = pos.x;
            lastY = pos.y;
            ctx.beginPath();
            ctx.moveTo( lastX, lastY );
        }

        function draw( e ) {
            if ( ! drawing ) return;
            e.preventDefault();
            var pos = getPos( e );
            ctx.lineWidth   = 2.5;
            ctx.lineCap     = 'round';
            ctx.lineJoin    = 'round';
            ctx.strokeStyle = '#1a1a2e';
            ctx.beginPath();
            ctx.moveTo( lastX, lastY );
            ctx.lineTo( pos.x, pos.y );
            ctx.stroke();
            lastX   = pos.x;
            lastY   = pos.y;
            isEmpty = false;
        }

        function stopDraw() { drawing = false; }

        canvas.addEventListener( 'mousedown',  startDraw );
        canvas.addEventListener( 'mousemove',  draw );
        canvas.addEventListener( 'mouseup',    stopDraw );
        canvas.addEventListener( 'mouseleave', stopDraw );
        canvas.addEventListener( 'touchstart', startDraw, { passive: false } );
        canvas.addEventListener( 'touchmove',  draw,      { passive: false } );
        canvas.addEventListener( 'touchend',   stopDraw );

        sigPads[ canvasId ] = {
            canvas: canvas,
            ctx: ctx,
            isEmpty: function () { return isEmpty; },
            clear: function () {
                var ratio = Math.max( window.devicePixelRatio || 1, 1 );
                ctx.clearRect( 0, 0, canvas.width / ratio, canvas.height / ratio );
                isEmpty = true;
            },
            toDataURL: function () { return canvas.toDataURL( 'image/png' ); },
            resize: resizeCanvas,
        };

        resizeCanvas();
    }

    /* ========================================================================
       Progress bar helper
       ======================================================================== */

    function updateProgress( hechas, total ) {
        $( '#lznd-hechas' ).text( hechas );
        $( '#lznd-total' ).text( total );
        var pct = total > 0 ? Math.round( hechas / total * 100 ) : 0;
        $( '#lznd-progress-bar' ).css( 'width', pct + '%' );
    }

    /* ========================================================================
       Task builder
       ======================================================================== */

    function buildTaskItem( task ) {
        var done      = !! task.completada;
        var checkmark = done ? '&#10003;' : '';
        var timeText  = ( done && task.completada_en ) ? formatTime( task.completada_en ) : '';

        return $( '<li>' )
            .addClass( 'lznd-task-item' + ( done ? ' lznd-task-item--done' : '' ) )
            .attr( 'data-task-id', task.id )
            .append(
                $( '<button>' ).attr( 'type', 'button' ).addClass( 'lznd-task-toggle' )
                    .append( $( '<span>' ).addClass( 'lznd-task-check' ).html( checkmark ) )
            )
            .append( $( '<span>' ).addClass( 'lznd-task-text' ).text( task.texto ) )
            .append( $( '<span>' ).addClass( 'lznd-task-time' ).text( timeText ) )
            .append(
                $( '<button>' ).attr( 'type', 'button' ).addClass( 'lznd-task-delete' ).html( '&#10005;' )
            );
    }

    function formatTime( datetimeStr ) {
        if ( ! datetimeStr ) return '';
        var d = new Date( datetimeStr.replace( ' ', 'T' ) );
        if ( isNaN( d ) ) return '';
        return ( '0' + d.getHours() ).slice( -2 ) + ':' + ( '0' + d.getMinutes() ).slice( -2 );
    }

    /* ========================================================================
       DASHBOARD handlers (shown when actuacionId === 0)
       ======================================================================== */

    $( '#lznd-toggle-new-job' ).on( 'click', function () {
        $( '#lznd-new-job-panel' ).slideDown( 220 );
        $( this ).hide();
    } );

    $( '#lznd-cancel-new-job' ).on( 'click', function () {
        $( '#lznd-new-job-panel' ).slideUp( 180 );
        $( '#lznd-toggle-new-job' ).show();
    } );

    $( '#lznd-comenzar-btn' ).on( 'click', function () {
        var proyectoId = $( '#lznd-proyecto-select' ).val();
        var tipoTermId = $( '#lznd-tipo-select' ).val();
        var $errBox    = $( '#lznd-step1-error' );
        var $btn       = $( this );

        $errBox.hide();

        if ( ! proyectoId || ! tipoTermId ) {
            $errBox.text( i18n.error || 'Selecciona proyecto y tipo de trabajo.' ).show();
            return;
        }

        $btn.prop( 'disabled', true ).find( '.lznd-btn__text' ).text( i18n.loading || 'Cargando...' );
        $btn.find( '.lznd-btn__spinner' ).show();

        $.ajax( {
            url:    ajax_url,
            method: 'POST',
            data: {
                action:       'lznd_get_project_tasks',
                nonce:        nonce,
                proyecto_id:  proyectoId,
                tipo_term_id: tipoTermId,
            },
            success: function ( res ) {
                if ( ! res.success ) {
                    $btn.prop( 'disabled', false ).find( '.lznd-btn__text' ).text( 'Comenzar' );
                    $btn.find( '.lznd-btn__spinner' ).hide();
                    $errBox.text( ( res.data && res.data.message ) || i18n.error ).show();
                    return;
                }
                var url = new URL( window.location.href );
                url.searchParams.set( 'actuacion_id', res.data.actuacion_id );
                window.location.href = url.toString();
            },
            error: function () {
                $btn.prop( 'disabled', false ).find( '.lznd-btn__text' ).text( 'Comenzar' );
                $btn.find( '.lznd-btn__spinner' ).hide();
                $errBox.text( i18n.error || 'Error de conexión.' ).show();
            },
        } );
    } );

    /* ========================================================================
       FORM handlers (shown when actuacionId > 0)
       ======================================================================== */

    if ( actuacionId > 0 ) {

        // Init signature pads
        initSignaturePad( 'lznd-sig-tecnico' );
        initSignaturePad( 'lznd-sig-cliente' );

        // ── Task toggle ──────────────────────────────────────────────────────
        $( '#lznd-task-list' ).on( 'click', '.lznd-task-toggle', function () {
            var $item  = $( this ).closest( '.lznd-task-item' );
            var taskId = $item.data( 'task-id' );
            if ( ! taskId ) return;

            $item.css( 'opacity', 0.5 );

            $.ajax( {
                url:    ajax_url,
                method: 'POST',
                data: {
                    action:   'lznd_toggle_task',
                    nonce:    nonce,
                    parte_id: actuacionId,
                    task_id:  taskId,
                },
                success: function ( res ) {
                    $item.css( 'opacity', 1 );
                    if ( ! res.success ) return;

                    var done = !! res.data.completada;
                    $item.toggleClass( 'lznd-task-item--done', done );
                    $item.find( '.lznd-task-check' ).html( done ? '&#10003;' : '' );

                    var $timeSpan = $item.find( '.lznd-task-time' );
                    if ( done ) {
                        var now = new Date();
                        $timeSpan.text( ( '0' + now.getHours() ).slice( -2 ) + ':' + ( '0' + now.getMinutes() ).slice( -2 ) );
                    } else {
                        $timeSpan.text( '' );
                    }

                    updateProgress( res.data.hechas, res.data.total );
                },
                error: function () { $item.css( 'opacity', 1 ); },
            } );
        } );

        // ── Add task ─────────────────────────────────────────────────────────
        $( '#lznd-add-task-btn' ).on( 'click', function () {
            var $input = $( '#lznd-new-task-input' );
            var texto  = $input.val().trim();
            if ( ! texto ) { $input.focus(); return; }

            var $btn = $( this ).prop( 'disabled', true );

            $.ajax( {
                url:    ajax_url,
                method: 'POST',
                data: {
                    action:       'lznd_add_task',
                    nonce:        nonce,
                    actuacion_id: actuacionId,
                    texto:        texto,
                },
                success: function ( res ) {
                    $btn.prop( 'disabled', false );
                    if ( ! res.success ) return;

                    $( '#lznd-task-list' ).append( buildTaskItem( res.data.task ) );
                    $input.val( '' ).focus();

                    var total  = $( '#lznd-task-list .lznd-task-item' ).length;
                    var hechas = $( '#lznd-task-list .lznd-task-item--done' ).length;
                    updateProgress( hechas, total );
                },
                error: function () { $btn.prop( 'disabled', false ); },
            } );
        } );

        $( '#lznd-new-task-input' ).on( 'keydown', function ( e ) {
            if ( e.key === 'Enter' ) { e.preventDefault(); $( '#lznd-add-task-btn' ).trigger( 'click' ); }
        } );

        // ── Task value: seleccionar / radio ───────────────────────────────────
        $( '#lznd-task-list' ).on( 'change', '.lznd-task-select, .lznd-task-field--radio input[type="radio"]', function () {
            var $item  = $( this ).closest( '.lznd-task-item' );
            var taskId = $item.data( 'task-id' );
            var valor  = $( this ).val();

            $.ajax( {
                url:    ajax_url,
                method: 'POST',
                data: {
                    action:       'lznd_save_task_value',
                    nonce:        nonce,
                    actuacion_id: actuacionId,
                    task_id:      taskId,
                    valor:        valor,
                },
                success: function ( res ) {
                    if ( ! res.success ) return;
                    var done = valor !== '';
                    $item.toggleClass( 'lznd-task-item--done', done );
                    $item.find( '.lznd-task-check' ).html( done ? '&#10003;' : '' );
                    if ( done ) {
                        var now = new Date();
                        $item.find( '.lznd-task-time' ).text( ( '0' + now.getHours() ).slice( -2 ) + ':' + ( '0' + now.getMinutes() ).slice( -2 ) );
                    } else {
                        $item.find( '.lznd-task-time' ).text( '' );
                    }
                    updateProgress( res.data.hechas, res.data.total );
                },
            } );
        } );

        // ── Delete task ───────────────────────────────────────────────────────
        $( '#lznd-task-list' ).on( 'click', '.lznd-task-delete', function ( e ) {
            e.stopPropagation();
            if ( ! confirm( i18n.delete_task || '¿Eliminar esta tarea?' ) ) return;

            var $item  = $( this ).closest( '.lznd-task-item' );
            var taskId = $item.data( 'task-id' );
            if ( ! taskId ) return;

            $.ajax( {
                url:    ajax_url,
                method: 'POST',
                data: {
                    action:       'lznd_delete_task',
                    nonce:        nonce,
                    actuacion_id: actuacionId,
                    task_id:      taskId,
                },
                success: function ( res ) {
                    if ( res.success ) {
                        $item.remove();
                        var total  = $( '#lznd-task-list .lznd-task-item' ).length;
                        var hechas = $( '#lznd-task-list .lznd-task-item--done' ).length;
                        updateProgress( hechas, total );
                    }
                },
            } );
        } );

        // ── Photo upload ──────────────────────────────────────────────────────
        var $uploadArea  = $( '#lznd-upload-area' );
        var $uploadInput = $( '#lznd-foto-input' );

        $uploadArea.on( 'click', function ( e ) {
            if ( $( e.target ).is( 'input' ) ) return;
            $uploadInput.trigger( 'click' );
        } );

        $uploadArea.on( 'dragover dragenter', function ( e ) {
            e.preventDefault();
            $uploadArea.addClass( 'lznd-dragover' );
        } );

        $uploadArea.on( 'dragleave drop', function () {
            $uploadArea.removeClass( 'lznd-dragover' );
        } );

        $uploadArea.on( 'drop', function ( e ) {
            e.preventDefault();
            uploadFiles( e.originalEvent.dataTransfer.files );
        } );

        $uploadInput.on( 'change', function () {
            uploadFiles( this.files );
            this.value = '';
        } );

        function uploadFiles( files ) {
            for ( var i = 0; i < files.length; i++ ) {
                uploadSingleFile( files[i] );
            }
        }

        function uploadSingleFile( file ) {
            if ( ! file.type.match( /^image\// ) ) return;

            var $progress = $( '#lznd-upload-progress' ).show();
            $progress.find( '.lznd-upload-progress__label' ).text( i18n.uploading || 'Subiendo...' );

            var formData = new FormData();
            formData.append( 'action', 'lznd_upload_foto' );
            formData.append( 'nonce', nonce );
            formData.append( 'actuacion_id', actuacionId );
            formData.append( 'foto', file, file.name );

            $.ajax( {
                url:         ajax_url,
                method:      'POST',
                data:        formData,
                processData: false,
                contentType: false,
                success: function ( res ) {
                    $progress.hide();
                    if ( ! res.success ) return;
                    $( '#lznd-fotos-grid' ).append(
                        $( '<div>' ).addClass( 'lznd-foto-item' ).attr( 'data-attach-id', res.data.attach_id )
                            .append( $( '<img>' ).addClass( 'lznd-foto-thumb' ).attr( { src: res.data.thumb, alt: '' } ) )
                            .append( $( '<a>' ).addClass( 'lznd-foto-view' ).attr( { href: res.data.url, target: '_blank' } ).html( '&#128065;' ) )
                            .append( $( '<button>' ).attr( 'type', 'button' ).addClass( 'lznd-foto-delete' ).html( '&#10005;' ) )
                    );
                },
                error: function () { $progress.hide(); },
            } );
        }

        // ── Delete photo ──────────────────────────────────────────────────────
        $( '#lznd-fotos-grid' ).on( 'click', '.lznd-foto-delete', function () {
            if ( ! confirm( i18n.delete_foto || '¿Eliminar esta foto?' ) ) return;

            var $item    = $( this ).closest( '.lznd-foto-item' );
            var attachId = $item.data( 'attach-id' );
            if ( ! attachId ) return;

            $item.css( 'opacity', 0.5 );

            $.ajax( {
                url:    ajax_url,
                method: 'POST',
                data: {
                    action:       'lznd_delete_foto',
                    nonce:        nonce,
                    actuacion_id: actuacionId,
                    attach_id:    attachId,
                },
                success: function ( res ) {
                    if ( res.success ) { $item.remove(); } else { $item.css( 'opacity', 1 ); }
                },
                error: function () { $item.css( 'opacity', 1 ); },
            } );
        } );

        // ── Observations autosave ─────────────────────────────────────────────
        var observTimer = null;

        $( '#lznd-observaciones' ).on( 'input', function () {
            clearTimeout( observTimer );
            var $status = $( '#lznd-observ-status' ).text( i18n.saving || 'Guardando...' ).addClass( 'lznd-saving' ).removeClass( 'lznd-saved' );
            var value   = $( this ).val();

            observTimer = setTimeout( function () {
                $.ajax( {
                    url:    ajax_url,
                    method: 'POST',
                    data: {
                        action:       'lznd_save_field',
                        nonce:        nonce,
                        actuacion_id: actuacionId,
                        field:        '_lznd_observaciones',
                        value:        value,
                    },
                    success: function ( res ) {
                        if ( res.success ) {
                            $status.text( i18n.saved || 'Guardado' ).removeClass( 'lznd-saving' ).addClass( 'lznd-saved' );
                            setTimeout( function () { $status.text( '' ).removeClass( 'lznd-saved' ); }, 2000 );
                        }
                    },
                } );
            }, 800 );
        } );

        // ── Signatures ────────────────────────────────────────────────────────
        $( '#lznd-firma-tec-clear' ).on( 'click', function () {
            if ( sigPads['lznd-sig-tecnico'] ) sigPads['lznd-sig-tecnico'].clear();
        } );

        $( '#lznd-firma-tec-save' ).on( 'click', function () {
            saveSignature( 'tecnico', 'lznd-sig-tecnico', '#lznd-nombre-firmante-tecnico', this );
        } );

        $( '#lznd-firma-cli-clear' ).on( 'click', function () {
            if ( sigPads['lznd-sig-cliente'] ) sigPads['lznd-sig-cliente'].clear();
        } );

        $( '#lznd-firma-cli-save' ).on( 'click', function () {
            saveSignature( 'cliente', 'lznd-sig-cliente', '#lznd-nombre-firmante-cliente', this );
        } );

        $( document ).on( 'click', '.lznd-firma-redo', function () {
            var tipo = $( this ).data( 'tipo' );
            if ( tipo === 'tecnico' ) {
                $( '#lznd-firma-tec-saved' ).hide();
                $( '#lznd-firma-tec-pad' ).show();
                if ( sigPads['lznd-sig-tecnico'] ) sigPads['lznd-sig-tecnico'].resize();
            } else {
                $( '#lznd-firma-cli-saved' ).hide();
                $( '#lznd-firma-cli-pad' ).show();
                if ( sigPads['lznd-sig-cliente'] ) sigPads['lznd-sig-cliente'].resize();
            }
        } );

        function saveSignature( tipo, canvasId, nombreSelector, btnEl ) {
            var pad = sigPads[ canvasId ];
            if ( ! pad || pad.isEmpty() ) {
                alert( i18n.sign_empty || 'Por favor, firma antes de guardar.' );
                return;
            }

            var dataUrl = pad.toDataURL();
            var nombre  = $( nombreSelector ).val().trim();
            var $btn    = $( btnEl ).prop( 'disabled', true );

            $.ajax( {
                url:    ajax_url,
                method: 'POST',
                data: {
                    action:          'lznd_save_signature',
                    nonce:           nonce,
                    actuacion_id:    actuacionId,
                    tipo:            tipo,
                    signature:       dataUrl,
                    nombre_firmante: nombre,
                },
                success: function ( res ) {
                    $btn.prop( 'disabled', false );
                    if ( ! res.success ) return;

                    var url = res.data.url;
                    if ( tipo === 'tecnico' ) {
                        $( '#lznd-firma-tec-saved' ).find( 'img' ).attr( 'src', url );
                        $( '#lznd-firma-tec-saved' ).find( '.lznd-firma-name' ).text( nombre );
                        $( '#lznd-firma-tec-pad' ).hide();
                        $( '#lznd-firma-tec-saved' ).show();
                    } else {
                        $( '#lznd-firma-cli-saved' ).find( 'img' ).attr( 'src', url );
                        $( '#lznd-firma-cli-saved' ).find( '.lznd-firma-name' ).text( nombre );
                        $( '#lznd-firma-cli-pad' ).hide();
                        $( '#lznd-firma-cli-saved' ).show();
                    }
                },
                error: function () { $btn.prop( 'disabled', false ); },
            } );
        }

        // ── Submit ────────────────────────────────────────────────────────────
        $( '#lznd-send-email-btn' ).on( 'click', function () {
            pendingChannel = 'email';
            openModal(
                i18n.confirm_send_email || '¿Enviar el parte por email?',
                'Se enviará una copia al administrador y al contacto del proyecto.'
            );
        } );

        $( '#lznd-send-whatsapp-btn' ).on( 'click', function () {
            pendingChannel = 'whatsapp';
            openModal(
                i18n.confirm_send_wp || '¿Enviar el parte por WhatsApp?',
                'Se abrirá WhatsApp con el mensaje precompuesto listo para enviar.'
            );
        } );

        // ── Modal ─────────────────────────────────────────────────────────────
        var $modal = $( '#lznd-confirm-modal' );

        function openModal( title, body ) {
            $( '#lznd-modal-title' ).text( title );
            $( '#lznd-modal-body' ).text( body );
            $modal.fadeIn( 150 );
            $( '#lznd-modal-confirm' ).focus();
        }

        function closeModal() { $modal.fadeOut( 130 ); }

        $( '#lznd-modal-cancel' ).on( 'click', function () { closeModal(); pendingChannel = ''; } );

        $modal.find( '.lznd-modal__overlay' ).on( 'click', function () { closeModal(); pendingChannel = ''; } );

        $( document ).on( 'keydown', function ( e ) {
            if ( e.key === 'Escape' && $modal.is( ':visible' ) ) { closeModal(); pendingChannel = ''; }
        } );

        $( '#lznd-modal-confirm' ).on( 'click', function () {
            closeModal();
            if ( ! pendingChannel ) return;
            submitActuacion( pendingChannel );
            pendingChannel = '';
        } );

        function submitActuacion( canal ) {
            var $emailBtn = $( '#lznd-send-email-btn' );
            var $wpBtn    = $( '#lznd-send-whatsapp-btn' );
            var $result   = $( '#lznd-submit-result' );

            $emailBtn.prop( 'disabled', true );
            $wpBtn.prop( 'disabled', true );
            $result.hide();

            $.ajax( {
                url:    ajax_url,
                method: 'POST',
                data: {
                    action:       'lznd_submit_actuacion',
                    nonce:        nonce,
                    actuacion_id: actuacionId,
                    canal:        canal,
                },
                success: function ( res ) {
                    $emailBtn.prop( 'disabled', false );
                    $wpBtn.prop( 'disabled', false );

                    if ( ! res.success ) {
                        $result.text( ( res.data && res.data.message ) || i18n.error )
                               .removeClass( 'lznd-notice--success' )
                               .addClass( 'lznd-notice lznd-notice--error' )
                               .show();
                        return;
                    }

                    if ( canal === 'whatsapp' && res.data && res.data.whatsapp_url ) {
                        window.open( res.data.whatsapp_url, '_blank' );
                    }

                    $result.text( i18n.send_success || 'Parte enviado correctamente.' )
                           .removeClass( 'lznd-notice--error' )
                           .addClass( 'lznd-notice lznd-notice--success' )
                           .show();

                    setTimeout( function () {
                        $( '#lznd-submit-card' ).fadeOut();
                        $( '#lznd-badge-estado' )
                            .text( 'Enviado' )
                            .attr( 'class', 'lznd-badge lznd-badge--estado lznd-badge--estado-enviado' );
                    }, 2000 );
                },
                error: function () {
                    $emailBtn.prop( 'disabled', false );
                    $wpBtn.prop( 'disabled', false );
                    $result.text( i18n.error || 'Error de conexión.' )
                           .addClass( 'lznd-notice lznd-notice--error' )
                           .show();
                },
            } );
        }

    } // end if ( actuacionId > 0 )

    /* ========================================================================
       Orientation/resize: resize signature canvases
       ======================================================================== */

    $( window ).on( 'orientationchange resize', function () {
        Object.keys( sigPads ).forEach( function ( id ) {
            if ( sigPads[ id ] && sigPads[ id ].resize ) sigPads[ id ].resize();
        } );
    } );

} )( jQuery );
