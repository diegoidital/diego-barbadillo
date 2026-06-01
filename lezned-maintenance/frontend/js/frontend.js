/**
 * Lezned Maintenance Manager – Frontend JS
 * Requires: jQuery
 */

/* global lzndData, jQuery */

( function ( $ ) {
    'use strict';

    /* ========================================================================
       Configuration
       ======================================================================== */

    var ajax_url = ( window.lzndData && lzndData.ajax_url ) || '';
    var nonce    = ( window.lzndData && lzndData.nonce )    || '';
    var i18n     = ( window.lzndData && lzndData.i18n )     || {};

    /* ========================================================================
       State
       ======================================================================== */

    var actuacionId      = 0;
    var pendingChannel   = ''; // 'email' | 'whatsapp'
    var forceNew         = false; // set when user picks "start new" from pending popup
    var pendingActuacion = null; // in-progress actuacion data from dashboard response

    /* ========================================================================
       Signature pads
       ======================================================================== */

    var sigPads = {};

    /**
     * Initialize a signature canvas.
     * @param {string} canvasId  DOM id of the <canvas>
     */
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
            if ( ! w || ! h ) return; // canvas not visible yet, skip
            var savedData = null;
            if ( ! isEmpty && canvas.width > 0 && canvas.height > 0 ) {
                savedData = ctx.getImageData( 0, 0, canvas.width, canvas.height );
            }
            canvas.width  = w * ratio;
            canvas.height = h * ratio;
            ctx.scale( ratio, ratio );
            if ( savedData ) {
                ctx.putImageData( savedData, 0, 0 );
            }
            ctx.lineCap     = 'round';
            ctx.lineJoin    = 'round';
            ctx.lineWidth   = 2;
            ctx.strokeStyle = '#1a1a2e';
        }

        function getPos( e ) {
            var rect = canvas.getBoundingClientRect();
            return {
                x: e.clientX - rect.left,
                y: e.clientY - rect.top,
            };
        }

        function startDraw( e ) {
            e.preventDefault();
            drawing = true;
            canvas.setPointerCapture( e.pointerId );
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
            lastX  = pos.x;
            lastY  = pos.y;
            isEmpty = false;
        }

        function stopDraw() {
            drawing = false;
        }

        canvas.style.touchAction = 'none';
        canvas.style.userSelect  = 'none';

        canvas.addEventListener( 'pointerdown',   startDraw );
        canvas.addEventListener( 'pointermove',   draw );
        canvas.addEventListener( 'pointerup',     stopDraw );
        canvas.addEventListener( 'pointercancel', stopDraw );
        canvas.addEventListener( 'pointerleave',  stopDraw );

        sigPads[ canvasId ] = {
            canvas: canvas,
            ctx: ctx,
            isEmpty: function () { return isEmpty; },
            clear: function () {
                var ratio = Math.max( window.devicePixelRatio || 1, 1 );
                ctx.clearRect( 0, 0, canvas.width / ratio, canvas.height / ratio );
                isEmpty = true;
            },
            toDataURL: function () {
                return canvas.toDataURL( 'image/png' );
            },
            resize: resizeCanvas,
        };

        resizeCanvas();
    }

    /* ========================================================================
       Update progress bar
       ======================================================================== */

    function updateProgress( hechas, total ) {
        $( '#lznd-hechas' ).text( hechas );
        $( '#lznd-total' ).text( total );
        var pct = total > 0 ? Math.round( hechas / total * 100 ) : 0;
        $( '#lznd-progress-bar' ).css( 'width', pct + '%' );
    }

    /* ========================================================================
       Render tasks from array
       ======================================================================== */

    function renderTasks( tasks ) {
        var $list = $( '#lznd-task-list' );
        $list.empty();
        var hechas = 0;

        $.each( tasks, function ( i, task ) {
            if ( task.completada ) hechas++;
            $list.append( buildTaskItem( task ) );
        } );

        updateProgress( hechas, tasks.length );
    }

    function buildTaskItem( task ) {
        var done      = !! task.completada;
        var checkmark = done ? '&#10003;' : '';
        var tipoCampo = task.tipo_campo || '';

        var $li = $( '<li>' )
            .addClass( 'lznd-task-item' + ( done ? ' lznd-task-item--done' : '' ) )
            .attr( 'data-task-id', task.id )
            .attr( 'data-tipo-campo', tipoCampo );

        $li.append(
            $( '<button>' ).attr( 'type', 'button' ).addClass( 'lznd-task-toggle' )
                .append( $( '<span>' ).addClass( 'lznd-task-check' ).html( checkmark ) )
        );

        $li.append( $( '<span>' ).addClass( 'lznd-task-text' ).text( task.texto ) );
        $li.append( $( '<span>' ).addClass( 'lznd-task-time' ).html( done && task.completada_en ? formatTime( task.completada_en ) : '' ) );

        $li.append(
            $( '<button>' ).attr( 'type', 'button' ).addClass( 'lznd-task-delete' ).html( '&#10005;' )
        );

        // Field section for typed tasks
        if ( tipoCampo && tipoCampo !== 'casilla' ) {
            $li.append( buildTaskField( task ) );
        }

        return $li;
    }

    function buildTaskField( task ) {
        var tipoCampo = task.tipo_campo;
        var opciones  = task.opciones || [];
        var valor     = task.valor    || '';
        var $field    = $( '<div>' ).addClass( 'lznd-task-field' );

        if ( tipoCampo === 'texto' ) {
            $field.append(
                $( '<input>' )
                    .attr( { type: 'text', placeholder: 'Valor' } )
                    .addClass( 'lznd-task-input lznd-task-input--text' )
                    .attr( 'data-task-id', task.id )
                    .val( valor )
            );

        } else if ( tipoCampo === 'radio' ) {
            var $group = $( '<div>' ).addClass( 'lznd-radio-group' );
            $.each( opciones, function ( i, opt ) {
                var uid     = 'radio-' + task.id + '-' + i;
                var $label  = $( '<label>' ).addClass( 'lznd-radio-pill' ).attr( 'for', uid );
                var $radio  = $( '<input>' ).attr( {
                    type:  'radio',
                    name:  'task-' + task.id,
                    id:    uid,
                    value: opt,
                } ).addClass( 'lznd-task-radio' ).attr( 'data-task-id', task.id );
                if ( valor === opt ) {
                    $radio.prop( 'checked', true );
                    $label.addClass( 'lznd-radio-pill--selected lznd-radio-pill--' + opt.toLowerCase().replace( /\s+/g, '-' ) );
                }
                $label.append( $radio ).append( document.createTextNode( opt ) );
                $group.append( $label );
            } );
            $field.append( $group );

        } else if ( tipoCampo === 'checkboxes' ) {
            var selectedValues = [];
            var otroTexto      = '';
            try {
                if ( valor && valor.charAt(0) === '[' ) {
                    selectedValues = JSON.parse( valor );
                }
            } catch ( e ) { /* ignore */ }

            // Extract "Otro: ..." text
            $.each( selectedValues, function ( i, sv ) {
                if ( typeof sv === 'string' && sv.indexOf( 'Otro:' ) === 0 ) {
                    otroTexto = sv.substring(5).trim();
                }
            } );

            var $group = $( '<div>' ).addClass( 'lznd-checkbox-group' );
            $.each( opciones, function ( i, opt ) {
                var uid    = 'cbx-' + task.id + '-' + i;
                var isChecked = false;
                $.each( selectedValues, function ( j, sv ) {
                    if ( sv === opt || ( opt === 'Otro' && typeof sv === 'string' && sv.indexOf( 'Otro' ) === 0 ) ) {
                        isChecked = true;
                        return false;
                    }
                } );

                var $wrap  = $( '<div>' ).addClass( 'lznd-cbx-item' );
                var $label = $( '<label>' ).addClass( 'lznd-cbx-label' ).attr( 'for', uid );
                var $cbx   = $( '<input>' ).attr( { type: 'checkbox', id: uid, value: opt } )
                    .addClass( 'lznd-task-checkbox' )
                    .attr( 'data-task-id', task.id )
                    .prop( 'checked', isChecked );
                $label.append( $cbx ).append( document.createTextNode( ' ' + opt ) );
                $wrap.append( $label );

                if ( opt === 'Otro' ) {
                    var $otroWrap = $( '<div>' ).addClass( 'lznd-otro-wrap' )
                        .css( 'display', isChecked ? '' : 'none' );
                    $otroWrap.append(
                        $( '<input>' ).attr( { type: 'text', placeholder: 'Especificar fallo' } )
                            .addClass( 'lznd-task-input lznd-otro-input' )
                            .attr( 'data-task-id', task.id )
                            .val( otroTexto )
                    );
                    $wrap.append( $otroWrap );
                }

                $group.append( $wrap );
            } );
            $field.append( $group );
        }

        return $field;
    }

    /* ── Save a typed field value ──────────────────────────────────────── */

    function saveTaskValue( taskId, valor, $item, autoComplete ) {
        if ( ! actuacionId || ! taskId ) return;
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
                if ( autoComplete && $item && ! $item.hasClass( 'lznd-task-item--done' ) ) {
                    $item.find( '.lznd-task-toggle' ).trigger( 'click' );
                }
            },
        } );
    }

    function collectCheckboxValues( $item ) {
        var selected = [];
        $item.find( '.lznd-task-checkbox:checked' ).each( function () {
            var opt = $( this ).val();
            if ( opt === 'Otro' ) {
                var txt = $( this ).closest( '.lznd-cbx-item' ).find( '.lznd-otro-input' ).val().trim();
                selected.push( 'Otro' + ( txt ? ': ' + txt : '' ) );
            } else {
                selected.push( opt );
            }
        } );
        return JSON.stringify( selected );
    }

    function formatTime( datetimeStr ) {
        if ( ! datetimeStr ) return '';
        var d = new Date( datetimeStr.replace( ' ', 'T' ) );
        if ( isNaN( d ) ) return '';
        return ( '0' + d.getHours() ).slice( -2 ) + ':' + ( '0' + d.getMinutes() ).slice( -2 );
    }

    /* ========================================================================
       Render photos
       ======================================================================== */

    function renderFotos( fotos ) {
        var $grid = $( '#lznd-fotos-grid' );
        $grid.empty();
        $.each( fotos, function ( i, foto ) {
            $grid.append( buildFotoItem( foto ) );
        } );
    }

    function buildFotoItem( foto ) {
        return $( '<div>' )
            .addClass( 'lznd-foto-item' )
            .attr( 'data-attach-id', foto.attach_id )
            .append( $( '<img>' ).addClass( 'lznd-foto-thumb' ).attr( { src: foto.thumb, alt: '' } ) )
            .append( $( '<a>' ).addClass( 'lznd-foto-view' ).attr( { href: foto.url, target: '_blank' } ).html( '&#128065;' ) )
            .append( $( '<button>' ).attr( 'type', 'button' ).addClass( 'lznd-foto-delete' ).html( '&#10005;' ) );
    }

    /* ========================================================================
       Populate step 2 from API response
       ======================================================================== */

    function populateStep2( data ) {
        actuacionId = data.actuacion_id;

        // Update URL without reload
        if ( window.history && window.history.pushState ) {
            var url = new URL( window.location.href );
            url.searchParams.set( 'actuacion_id', actuacionId );
            window.history.pushState( { actuacion_id: actuacionId }, '', url.toString() );
        }

        // Update data-actuacion-id on wrapper
        $( '.lznd-app' ).attr( 'data-actuacion-id', actuacionId );

        // Tasks
        renderTasks( data.tasks || [] );

        // Fotos
        renderFotos( data.fotos || [] );

        // Observaciones
        $( '#lznd-observaciones' ).val( data.observaciones || '' );

        // Firmas: tecnico
        if ( data.has_firma_tecnico && data.firma_tecnico_url ) {
            $( '#lznd-firma-tec-saved' ).find( 'img' ).attr( 'src', data.firma_tecnico_url );
            $( '#lznd-firma-tec-saved' ).find( '.lznd-firma-name' ).text( data.nombre_firmante_tecnico || '' );
            $( '#lznd-firma-tec-saved' ).show();
            $( '#lznd-firma-tec-pad' ).hide();
        } else {
            $( '#lznd-firma-tec-saved' ).hide();
            $( '#lznd-firma-tec-pad' ).show();
        }

        // Firmas: cliente
        if ( data.has_firma_cliente && data.firma_cliente_url ) {
            $( '#lznd-firma-cli-saved' ).find( 'img' ).attr( 'src', data.firma_cliente_url );
            $( '#lznd-firma-cli-saved' ).find( '.lznd-firma-name' ).text( data.nombre_firmante_cliente || '' );
            $( '#lznd-firma-cli-saved' ).show();
            $( '#lznd-firma-cli-pad' ).hide();
        } else {
            $( '#lznd-firma-cli-saved' ).hide();
            $( '#lznd-firma-cli-pad' ).show();
        }

        // Header badges
        if ( data.estado ) {
            var estadoLabels = {
                pendiente: 'Pendiente',
                en_curso:  'En curso',
                enviado:   'Enviado',
                cerrado:   'Cerrado',
            };
            var $badge = $( '#lznd-badge-estado' );
            $badge.text( estadoLabels[ data.estado ] || data.estado );
            $badge.attr( 'class', 'lznd-badge lznd-badge--estado lznd-badge--estado-' + data.estado );
        }

    }

    /* ========================================================================
       Step 1: project selection → load dashboard
       ======================================================================== */

    $( '#lznd-proyecto-select' ).on( 'change', function () {
        var proyectoId = $( this ).val();

        // Reset state
        forceNew         = false;
        pendingActuacion = null;
        $( '#lznd-project-dashboard' ).hide();
        $( '#lznd-tipo-wrap' ).hide();
        $( '#lznd-tipo-select' ).val( '' );

        if ( ! proyectoId ) {
            return;
        }

        // Show loading state in dashboard area
        $( '#lznd-project-dashboard' ).show();
        $( '#lznd-dash-completadas' ).text( '…' );
        $( '#lznd-dash-pendientes-count' ).text( '…' );
        $( '#lznd-dash-proximas-count' ).text( '…' );
        $( '#lznd-dash-pendientes-section' ).hide();
        $( '#lznd-dash-proximas-section' ).hide();

        $.ajax( {
            url:    ajax_url,
            method: 'POST',
            data: {
                action:      'lznd_get_project_dashboard',
                nonce:       nonce,
                proyecto_id: proyectoId,
            },
            success: function ( res ) {
                if ( ! res.success ) {
                    showTipoWrap();
                    return;
                }

                renderDashboard( res.data );

                // If there's an in-progress actuacion, show popup before tipo selector
                if ( res.data.en_curso ) {
                    pendingActuacion = res.data.en_curso;
                    openPendingModal( pendingActuacion );
                } else {
                    var tipoFromProject = $( '#lznd-proyecto-select option:selected' ).data( 'tipo-term-id' );
                    if ( tipoFromProject ) {
                        startActuacion( proyectoId, tipoFromProject );
                    } else {
                        showTipoWrap();
                    }
                }
            },
            error: function () {
                showTipoWrap();
            },
        } );
    } );

    function showTipoWrap() {
        $( '#lznd-tipo-wrap' ).show();
    }

    function startActuacion( proyectoId, tipoTermId ) {
        var $errBox = $( '#lznd-step1-error' );
        $errBox.hide();

        var $btn = $( '#lznd-comenzar-btn' );
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
                force_new:    forceNew ? 1 : 0,
            },
            success: function ( res ) {
                $btn.prop( 'disabled', false ).find( '.lznd-btn__text' ).text( 'Comenzar' );
                $btn.find( '.lznd-btn__spinner' ).hide();

                if ( ! res.success ) {
                    $errBox.text( ( res.data && res.data.message ) || i18n.error ).show();
                    showTipoWrap();
                    return;
                }

                populateStep2( res.data );
                showStep2();
            },
            error: function () {
                $btn.prop( 'disabled', false ).find( '.lznd-btn__text' ).text( 'Comenzar' );
                $btn.find( '.lznd-btn__spinner' ).hide();
                $errBox.text( i18n.error || 'Error de conexión.' ).show();
                showTipoWrap();
            },
        } );
    }

    /* ========================================================================
       Dashboard rendering
       ======================================================================== */

    function renderDashboard( data ) {
        $( '#lznd-dash-completadas' ).text( data.completadas_count || 0 );
        $( '#lznd-dash-pendientes-count' ).text( data.pendientes_count || 0 );
        $( '#lznd-dash-proximas-count' ).text( data.proximas_count || 0 );

        // Pending list
        var $pendSection = $( '#lznd-dash-pendientes-section' );
        var $pendList    = $( '#lznd-dash-pendientes-list' ).empty();

        if ( data.pendientes && data.pendientes.length ) {
            $.each( data.pendientes, function ( i, act ) {
                $pendList.append( buildDashActuacion( act, true ) );
            } );
            $pendSection.show();
        } else {
            $pendSection.hide();
        }

        // Upcoming list
        var $proxSection = $( '#lznd-dash-proximas-section' );
        var $proxList    = $( '#lznd-dash-proximas-list' ).empty();

        if ( data.proximas && data.proximas.length ) {
            $.each( data.proximas, function ( i, act ) {
                $proxList.append( buildDashActuacion( act, false ) );
            } );
            $proxSection.show();
        } else {
            $proxSection.hide();
        }
    }

    function buildDashActuacion( act, showTasks ) {
        var fechaStr = '';
        if ( act.fecha_prog ) {
            var d = new Date( act.fecha_prog );
            if ( ! isNaN( d ) ) {
                fechaStr = ( '0' + d.getDate() ).slice( -2 ) + '/' +
                           ( '0' + ( d.getMonth() + 1 ) ).slice( -2 ) + '/' +
                           d.getFullYear();
            }
        }

        var progressStr = act.total > 0
            ? ( act.hechas + '/' + act.total + ' tareas' )
            : 'Sin tareas';

        var $item = $( '<div>' ).addClass( 'lznd-dash-item lznd-dash-item--' + ( act.estado || 'pendiente' ) );

        var $header = $( '<div>' ).addClass( 'lznd-dash-item__header' );
        $header.append( $( '<span>' ).addClass( 'lznd-dash-item__tipo' ).text( act.tipo ) );
        if ( fechaStr ) {
            $header.append( $( '<span>' ).addClass( 'lznd-dash-item__fecha' ).text( fechaStr ) );
        }
        $item.append( $header );

        var $meta = $( '<div>' ).addClass( 'lznd-dash-item__meta' );
        $meta.append( $( '<span>' ).addClass( 'lznd-dash-item__progress' ).text( progressStr ) );
        var estadoLabels = { pendiente: 'Pendiente', en_curso: 'En curso', enviado: 'Enviado', cerrado: 'Cerrado' };
        $meta.append(
            $( '<span>' )
                .addClass( 'lznd-badge lznd-badge--estado lznd-badge--estado-' + ( act.estado || 'pendiente' ) )
                .text( estadoLabels[ act.estado ] || act.estado )
        );
        $item.append( $meta );

        if ( showTasks && act.tareas && act.tareas.length ) {
            var $tasks = $( '<ul>' ).addClass( 'lznd-dash-item__tasks' );
            $.each( act.tareas, function ( i, t ) {
                $tasks.append(
                    $( '<li>' )
                        .addClass( t.completada ? 'lznd-dash-item__task--done' : '' )
                        .text( t.texto )
                );
            } );
            $item.append( $tasks );
        }

        return $item;
    }

    /* ========================================================================
       Pending work modal
       ======================================================================== */

    var $pendingModal = $( '#lznd-pending-modal' );

    function openPendingModal( act ) {
        // Show a summary of the pending actuacion
        var $summary = $( '#lznd-pending-summary' );
        if ( act && $summary.length ) {
            var fechaStr = '';
            if ( act.fecha_prog ) {
                var d = new Date( act.fecha_prog );
                if ( ! isNaN( d ) ) {
                    fechaStr = ( '0' + d.getDate() ).slice( -2 ) + '/' +
                               ( '0' + ( d.getMonth() + 1 ) ).slice( -2 ) + '/' +
                               d.getFullYear();
                }
            }
            var progressStr = act.total > 0
                ? ( act.hechas + ' de ' + act.total + ' tareas completadas' )
                : 'Sin tareas definidas';
            $summary.html(
                '<div class="lznd-pending-info">' +
                '<span class="lznd-pending-tipo">' + ( act.tipo || '' ) + '</span>' +
                ( fechaStr ? '<span class="lznd-pending-fecha">&#128197; ' + fechaStr + '</span>' : '' ) +
                '<span class="lznd-pending-progress">&#9745; ' + progressStr + '</span>' +
                '</div>'
            ).show();
        }
        $pendingModal.fadeIn( 150 );
        $( '#lznd-pending-recover' ).focus();
    }

    function closePendingModal() {
        $pendingModal.fadeOut( 130 );
    }

    $( '#lznd-pending-recover' ).on( 'click', function () {
        closePendingModal();
        if ( ! pendingActuacion ) return;

        // We need a tipo to load — find a tipo for the existing actuacion
        // Use the tipo from any available tipo term, or show tipo-wrap to let user pick
        // Best approach: load via tipo_term_id of the in-progress actuacion
        // For now we'll need to show tipo-wrap with a note, then when user hits Comenzar
        // the backend will find the existing in-progress actuacion regardless of tipo
        // because force_new=0 and any pending will be returned first.
        // Alternatively: redirect the page with actuacion_id in URL.
        var actId = pendingActuacion.id;
        if ( window.history && window.history.pushState ) {
            var url = new URL( window.location.href );
            url.searchParams.set( 'actuacion_id', actId );
            window.location.href = url.toString();
        }
    } );

    $( '#lznd-pending-new' ).on( 'click', function () {
        closePendingModal();
        forceNew = true;
        var proyectoId    = $( '#lznd-proyecto-select' ).val();
        var tipoFromProj  = $( '#lznd-proyecto-select option:selected' ).data( 'tipo-term-id' );
        if ( tipoFromProj ) {
            startActuacion( proyectoId, tipoFromProj );
        } else {
            showTipoWrap();
        }
    } );

    $pendingModal.find( '.lznd-modal__overlay' ).on( 'click', function () {
        closePendingModal();
        var proyectoId   = $( '#lznd-proyecto-select' ).val();
        var tipoFromProj = $( '#lznd-proyecto-select option:selected' ).data( 'tipo-term-id' );
        if ( tipoFromProj ) {
            startActuacion( proyectoId, tipoFromProj );
        } else {
            showTipoWrap();
        }
    } );

    /* ========================================================================
       Step 1 → Step 2: "Comenzar" button
       ======================================================================== */

    $( '#lznd-comenzar-btn' ).on( 'click', function () {
        var proyectoId = $( '#lznd-proyecto-select' ).val();
        var tipoTermId = $( '#lznd-tipo-select' ).val();
        var $errBox    = $( '#lznd-step1-error' );

        $errBox.hide();

        if ( ! proyectoId || ! tipoTermId ) {
            $errBox.text( i18n.error || 'Selecciona proyecto y tipo de trabajo.' ).show();
            return;
        }

        startActuacion( proyectoId, tipoTermId );
    } );

    function showStep2() {
        $( '#lznd-step-1' ).fadeOut( 180, function () {
            $( '#lznd-step-2' ).fadeIn( 220, function () {
                initSignaturePad( 'lznd-sig-tecnico' );
                initSignaturePad( 'lznd-sig-cliente' );
            } );
        } );
        $( '.lznd-step-dot[data-step="1"]' ).removeClass( 'lznd-step-dot--active' );
        $( '.lznd-step-dot[data-step="2"]' ).addClass( 'lznd-step-dot--active' );
    }

    /* ========================================================================
       Checklist events
       ======================================================================== */

    // Toggle task (delegated)
    $( '#lznd-task-list' ).on( 'click', '.lznd-task-toggle', function () {
        var $item   = $( this ).closest( '.lznd-task-item' );
        var taskId  = $item.data( 'task-id' );
        if ( ! actuacionId || ! taskId ) return;

        $item.css( 'opacity', 0.5 );

        $.ajax( {
            url:    ajax_url,
            method: 'POST',
            data: {
                action:       'lznd_toggle_task',
                nonce:        nonce,
                parte_id:     actuacionId,
                task_id:      taskId,
            },
            success: function ( res ) {
                $item.css( 'opacity', 1 );
                if ( ! res.success ) return;

                var d    = res.data;
                var done = !! d.completada;

                $item.toggleClass( 'lznd-task-item--done', done );
                var $check = $item.find( '.lznd-task-check' );
                $check.html( done ? '&#10003;' : '' );

                // Update time
                var $timeSpan = $item.find( '.lznd-task-time' );
                if ( done ) {
                    var now = new Date();
                    var hhmm = ( '0' + now.getHours() ).slice( -2 ) + ':' + ( '0' + now.getMinutes() ).slice( -2 );
                    $timeSpan.text( hhmm );
                } else {
                    $timeSpan.text( '' );
                }

                updateProgress( d.hechas, d.total );
            },
            error: function () {
                $item.css( 'opacity', 1 );
            },
        } );
    } );

    // ── Text field: save on blur ────────────────────────────────────────────
    $( '#lznd-task-list' ).on( 'blur', '.lznd-task-input--text', function () {
        var taskId = $( this ).attr( 'data-task-id' );
        var valor  = $( this ).val().trim();
        saveTaskValue( taskId, valor, $( this ).closest( '.lznd-task-item' ) );
    } );

    // ── Radio: save + auto-complete on pick ─────────────────────────────────
    $( '#lznd-task-list' ).on( 'change', '.lznd-task-radio', function () {
        var $radio = $( this );
        var taskId = $radio.attr( 'data-task-id' );
        var $item  = $radio.closest( '.lznd-task-item' );

        // Style pills
        $item.find( '.lznd-radio-pill' ).removeClass( 'lznd-radio-pill--selected lznd-radio-pill--correcto lznd-radio-pill--incidencia lznd-radio-pill--selected-any' );
        $radio.closest( '.lznd-radio-pill' )
            .addClass( 'lznd-radio-pill--selected lznd-radio-pill--' + $radio.val().toLowerCase().replace( /\s+/g, '-' ).replace( /[^a-z0-9-]/g, '' ) );

        saveTaskValue( taskId, $radio.val(), $item, true );
    } );

    // ── Checkboxes: save on change (+ toggle Otro text field) ──────────────
    $( '#lznd-task-list' ).on( 'change', '.lznd-task-checkbox', function () {
        var $cbx  = $( this );
        var $item = $cbx.closest( '.lznd-task-item' );
        var taskId = $cbx.attr( 'data-task-id' );

        if ( $cbx.val() === 'Otro' ) {
            $cbx.closest( '.lznd-cbx-item' ).find( '.lznd-otro-wrap' ).toggle( $cbx.prop( 'checked' ) );
        }

        saveTaskValue( taskId, collectCheckboxValues( $item ), $item );
    } );

    // ── "Otro" text: save on blur ───────────────────────────────────────────
    $( '#lznd-task-list' ).on( 'blur', '.lznd-otro-input', function () {
        var $item  = $( this ).closest( '.lznd-task-item' );
        var taskId = $( this ).attr( 'data-task-id' );
        saveTaskValue( taskId, collectCheckboxValues( $item ), $item );
    } );

    // Add task
    $( '#lznd-add-task-btn' ).on( 'click', function () {
        var $input = $( '#lznd-new-task-input' );
        var texto  = $input.val().trim();
        if ( ! texto ) {
            $input.focus();
            return;
        }
        if ( ! actuacionId ) return;

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

                var task = res.data.task;
                $( '#lznd-task-list' ).append( buildTaskItem( task ) );
                $input.val( '' ).focus();

                // Update total
                var total  = $( '#lznd-task-list .lznd-task-item' ).length;
                var hechas = $( '#lznd-task-list .lznd-task-item--done' ).length;
                updateProgress( hechas, total );
            },
            error: function () {
                $btn.prop( 'disabled', false );
            },
        } );
    } );

    // Add task on Enter key
    $( '#lznd-new-task-input' ).on( 'keydown', function ( e ) {
        if ( e.key === 'Enter' ) {
            e.preventDefault();
            $( '#lznd-add-task-btn' ).trigger( 'click' );
        }
    } );

    // Delete task (delegated)
    $( '#lznd-task-list' ).on( 'click', '.lznd-task-delete', function ( e ) {
        e.stopPropagation();
        if ( ! confirm( i18n.delete_task || '¿Eliminar esta tarea?' ) ) return;

        var $item  = $( this ).closest( '.lznd-task-item' );
        var taskId = $item.data( 'task-id' );
        if ( ! actuacionId || ! taskId ) return;

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

    /* ========================================================================
       Photo upload
       ======================================================================== */

    var $uploadArea  = $( '#lznd-upload-area' );
    var $uploadInput = $( '#lznd-foto-input' );

    // Click on area triggers file input
    $uploadArea.on( 'click', function ( e ) {
        if ( $( e.target ).is( 'input' ) ) return;
        $uploadInput.trigger( 'click' );
    } );

    // Drag-drop
    $uploadArea.on( 'dragover dragenter', function ( e ) {
        e.preventDefault();
        $uploadArea.addClass( 'lznd-dragover' );
    } );

    $uploadArea.on( 'dragleave drop', function ( e ) {
        $uploadArea.removeClass( 'lznd-dragover' );
    } );

    $uploadArea.on( 'drop', function ( e ) {
        e.preventDefault();
        var files = e.originalEvent.dataTransfer.files;
        uploadFiles( files );
    } );

    $uploadInput.on( 'change', function () {
        uploadFiles( this.files );
        this.value = ''; // Reset so same file can be re-selected
    } );

    function uploadFiles( files ) {
        if ( ! actuacionId ) return;
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

                $( '#lznd-fotos-grid' ).append( buildFotoItem( res.data ) );
            },
            error: function () {
                $progress.hide();
            },
        } );
    }

    // Delete photo (delegated)
    $( '#lznd-fotos-grid' ).on( 'click', '.lznd-foto-delete', function () {
        if ( ! confirm( i18n.delete_foto || '¿Eliminar esta foto?' ) ) return;

        var $item    = $( this ).closest( '.lznd-foto-item' );
        var attachId = $item.data( 'attach-id' );
        if ( ! actuacionId || ! attachId ) return;

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
                if ( res.success ) {
                    $item.remove();
                } else {
                    $item.css( 'opacity', 1 );
                }
            },
            error: function () {
                $item.css( 'opacity', 1 );
            },
        } );
    } );

    /* ========================================================================
       Observaciones autosave (debounced)
       ======================================================================== */

    var observTimer = null;

    $( '#lznd-observaciones' ).on( 'input', function () {
        if ( ! actuacionId ) return;
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

    /* ========================================================================
       Signature save
       ======================================================================== */

    // Tecnico: clear
    $( '#lznd-firma-tec-clear' ).on( 'click', function () {
        var pad = sigPads['lznd-sig-tecnico'];
        if ( pad ) pad.clear();
    } );

    // Tecnico: save
    $( '#lznd-firma-tec-save' ).on( 'click', function () {
        saveSignature( 'tecnico', 'lznd-sig-tecnico', '#lznd-nombre-firmante-tecnico', this );
    } );

    // Cliente: clear
    $( '#lznd-firma-cli-clear' ).on( 'click', function () {
        var pad = sigPads['lznd-sig-cliente'];
        if ( pad ) pad.clear();
    } );

    // Cliente: save
    $( '#lznd-firma-cli-save' ).on( 'click', function () {
        saveSignature( 'cliente', 'lznd-sig-cliente', '#lznd-nombre-firmante-cliente', this );
    } );

    // "Volver a firmar" buttons (both)
    $( document ).on( 'click', '.lznd-firma-redo', function () {
        var tipo     = $( this ).data( 'tipo' );
        var canvasId = tipo === 'tecnico' ? 'lznd-sig-tecnico' : 'lznd-sig-cliente';
        if ( tipo === 'tecnico' ) {
            $( '#lznd-firma-tec-saved' ).hide();
            $( '#lznd-firma-tec-pad' ).show();
        } else {
            $( '#lznd-firma-cli-saved' ).hide();
            $( '#lznd-firma-cli-pad' ).show();
        }
        // Wait for layout before resizing/initialising the now-visible canvas
        requestAnimationFrame( function () {
            if ( sigPads[ canvasId ] ) {
                sigPads[ canvasId ].resize();
            } else {
                initSignaturePad( canvasId );
            }
        } );
    } );

    function saveSignature( tipo, canvasId, nombreSelector, btnEl ) {
        if ( ! actuacionId ) return;

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
                action:           'lznd_save_signature',
                nonce:            nonce,
                actuacion_id:     actuacionId,
                tipo:             tipo,
                signature:        dataUrl,
                nombre_firmante:  nombre,
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
            error: function () {
                $btn.prop( 'disabled', false );
            },
        } );
    }

    /* ========================================================================
       Submit buttons
       ======================================================================== */

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

    /* ========================================================================
       Confirmation modal
       ======================================================================== */

    var $modal = $( '#lznd-confirm-modal' );

    function openModal( title, body ) {
        $( '#lznd-modal-title' ).text( title );
        $( '#lznd-modal-body' ).text( body );
        $modal.fadeIn( 150 );
        $( '#lznd-modal-confirm' ).focus();
    }

    function closeModal() {
        $modal.fadeOut( 130 );
    }

    $( '#lznd-modal-cancel' ).on( 'click', function () {
        closeModal();
        pendingChannel = '';
    } );

    $modal.find( '.lznd-modal__overlay' ).on( 'click', function () {
        closeModal();
        pendingChannel = '';
    } );

    $( document ).on( 'keydown', function ( e ) {
        if ( e.key === 'Escape' && $modal.is( ':visible' ) ) {
            closeModal();
            pendingChannel = '';
        }
    } );

    $( '#lznd-modal-confirm' ).on( 'click', function () {
        closeModal();
        if ( ! pendingChannel ) return;
        submitActuacion( pendingChannel );
        pendingChannel = '';
    } );

    function submitActuacion( canal ) {
        if ( ! actuacionId ) return;

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
                    $result
                        .text( ( res.data && res.data.message ) || i18n.error )
                        .removeClass( 'lznd-notice--success' )
                        .addClass( 'lznd-notice lznd-notice--error' )
                        .show();
                    return;
                }

                if ( canal === 'whatsapp' && res.data && res.data.whatsapp_url ) {
                    window.open( res.data.whatsapp_url, '_blank' );
                }

                // Show success then redirect to start page
                $result
                    .text( i18n.send_success || 'Parte enviado correctamente.' )
                    .removeClass( 'lznd-notice--error' )
                    .addClass( 'lznd-notice lznd-notice--success' )
                    .show();

                setTimeout( function () {
                    var url = new URL( window.location.href );
                    url.searchParams.delete( 'actuacion_id' );
                    window.location.href = url.toString();
                }, 2000 );
            },
            error: function () {
                $emailBtn.prop( 'disabled', false );
                $wpBtn.prop( 'disabled', false );
                $result
                    .text( i18n.error || 'Error de conexión.' )
                    .addClass( 'lznd-notice lznd-notice--error' )
                    .show();
            },
        } );
    }

    /* ========================================================================
       Init on page load: if actuacion_id already in URL, show step 2 directly
       ======================================================================== */

    var $app = $( '.lznd-app' );

    function initFromDOM() {
        var domActuacionId = parseInt( $app.attr( 'data-actuacion-id' ), 10 ) || 0;

        if ( domActuacionId > 0 ) {
            actuacionId = domActuacionId;
            // Step 2 is already shown via PHP, init signature pads
            $( '#lznd-steps-indicator .lznd-step-dot[data-step="1"]' ).removeClass( 'lznd-step-dot--active' );
            $( '#lznd-steps-indicator .lznd-step-dot[data-step="2"]' ).addClass( 'lznd-step-dot--active' );
            initSignaturePad( 'lznd-sig-tecnico' );
            initSignaturePad( 'lznd-sig-cliente' );
        }
    }

    initFromDOM();

    /* ========================================================================
       Orientation change: resize canvases
       ======================================================================== */

    $( window ).on( 'orientationchange resize', function () {
        Object.keys( sigPads ).forEach( function ( id ) {
            if ( sigPads[ id ] && sigPads[ id ].resize ) {
                sigPads[ id ].resize();
            }
        } );
    } );

} )( jQuery );
