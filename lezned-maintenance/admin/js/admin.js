/**
 * Lezned Maintenance Manager – Admin JS
 */

/* global lzndAdmin, jQuery */

jQuery( function ( $ ) {
    'use strict';

    /* ========================================================================
       Recurrence toggle
       ======================================================================== */

    var $recSelect = $( '#lznd-recurrencia' );
    var $recOpts   = $( '#lznd-rec-opts' );

    function toggleRecOpts() {
        var val = $recSelect.val();
        if ( val && val !== '' && val !== 'ninguna' ) {
            $recOpts.removeClass( 'lznd-hidden' ).show();
        } else {
            $recOpts.addClass( 'lznd-hidden' ).hide();
        }
    }

    if ( $recSelect.length ) {
        toggleRecOpts();
        $recSelect.on( 'change', toggleRecOpts );
    }

    /* ========================================================================
       Template task list – sortable + add/remove
       ======================================================================== */

    var $taskList = $( '#lznd-template-task-list' );

    if ( $taskList.length && $.fn.sortable ) {
        $taskList.sortable( {
            handle:      '.lznd-drag-handle',
            placeholder: 'lznd-task-item lznd-task-item--placeholder',
            tolerance:   'pointer',
            cursor:      'grabbing',
            update: function () {
                // Update hidden order field if exists
                $taskList.find( '.lznd-task-item' ).each( function ( idx ) {
                    $( this ).find( '[name$="[order]"]' ).val( idx );
                } );
            },
        } );
    }

    // Add task
    $( '#lznd-add-template-task' ).on( 'click', function () {
        var texto = $( '#lznd-new-template-task-text' ).val().trim();
        if ( ! texto ) {
            $( '#lznd-new-template-task-text' ).focus();
            return;
        }

        var idx = $taskList.find( '.lznd-task-item' ).length;
        var html = buildTaskRow( idx, texto );
        $taskList.append( html );
        $( '#lznd-new-template-task-text' ).val( '' ).focus();

        if ( $taskList.sortable ) {
            $taskList.sortable( 'refresh' );
        }
    } );

    // Allow Enter key in the add-task input
    $( '#lznd-new-template-task-text' ).on( 'keydown', function ( e ) {
        if ( e.key === 'Enter' ) {
            e.preventDefault();
            $( '#lznd-add-template-task' ).trigger( 'click' );
        }
    } );

    // Remove task (delegated)
    $taskList.on( 'click', '.lznd-remove-task', function () {
        var $item = $( this ).closest( '.lznd-task-item' );
        if ( confirm( ( window.lzndAdmin && lzndAdmin.i18n && lzndAdmin.i18n.delete_task ) || '¿Eliminar esta tarea?' ) ) {
            $item.remove();
            // Re-index
            $taskList.find( '.lznd-task-item' ).each( function ( idx ) {
                $( this ).find( 'input, textarea' ).each( function () {
                    var name = $( this ).attr( 'name' );
                    if ( name ) {
                        $( this ).attr( 'name', name.replace( /\[\d+\]/, '[' + idx + ']' ) );
                    }
                } );
            } );
        }
    } );

    function buildTaskRow( idx, texto ) {
        return '<li class="lznd-task-item">' +
            '<span class="lznd-drag-handle" title="Arrastrar">&#9776;</span>' +
            '<input type="text" name="lznd_tareas[' + idx + '][texto]" value="' + escHtml( texto ) + '" class="lznd-task-input" placeholder="Tarea...">' +
            '<input type="hidden" name="lznd_tareas[' + idx + '][order]" value="' + idx + '">' +
            '<button type="button" class="lznd-remove-task" title="Eliminar">&#10005;</button>' +
            '</li>';
    }

    function escHtml( str ) {
        return String( str )
            .replace( /&/g, '&amp;' )
            .replace( /</g, '&lt;' )
            .replace( />/g, '&gt;' )
            .replace( /"/g, '&quot;' );
    }

    /* ========================================================================
       Auto-fill client data
       ======================================================================== */

    var $clienteSelect = $( '#lznd-cliente-id' );

    $clienteSelect.on( 'change', function () {
        var clienteId = $( this ).val();
        if ( ! clienteId ) return;

        $.ajax( {
            url:    ( window.lzndAdmin && lzndAdmin.ajax_url ) || ajaxurl,
            method: 'POST',
            data: {
                action:     'lznd_get_client_data',
                nonce:      ( window.lzndAdmin && lzndAdmin.nonce ) || '',
                cliente_id: clienteId,
            },
            success: function ( res ) {
                if ( ! res.success ) return;
                var d = res.data;

                // Only auto-fill if empty
                if ( $( '#lznd-contacto-nombre' ).val() === '' ) {
                    $( '#lznd-contacto-nombre' ).val( d.nombre || '' );
                }
                if ( $( '#lznd-contacto-email' ).val() === '' ) {
                    $( '#lznd-contacto-email' ).val( d.email || '' );
                }
                if ( $( '#lznd-contacto-telefono' ).val() === '' ) {
                    $( '#lznd-contacto-telefono' ).val( d.telefono || '' );
                }
                if ( $( '#lznd-contacto-direccion' ).val() === '' ) {
                    $( '#lznd-contacto-direccion' ).val( d.direccion || '' );
                }
            },
        } );
    } );

    /* ========================================================================
       Calendar navigation
       ======================================================================== */

    var $calWrapper = $( '#lznd-calendar-wrapper' );

    $calWrapper.on( 'click', '#lznd-cal-prev, #lznd-cal-next', function () {
        var $btn       = $( this );
        var year       = $btn.data( 'year' );
        var month      = $btn.data( 'month' );
        var proyectoId = $calWrapper.find( '.lznd-calendar' ).data( 'proyecto' ) ||
                         $( '#lznd-proyecto-id-hidden' ).val() ||
                         0;

        if ( ! proyectoId ) return;

        $calWrapper.css( 'opacity', 0.5 );

        $.ajax( {
            url:    ( window.lzndAdmin && lzndAdmin.ajax_url ) || ajaxurl,
            method: 'POST',
            data: {
                action:      'lznd_calendar_nav',
                nonce:       ( window.lzndAdmin && lzndAdmin.nonce ) || '',
                proyecto_id: proyectoId,
                year:        year,
                month:       month,
            },
            success: function ( res ) {
                if ( res.success && res.data.html ) {
                    $calWrapper.html( res.data.html );
                }
                $calWrapper.css( 'opacity', 1 );
            },
            error: function () {
                $calWrapper.css( 'opacity', 1 );
            },
        } );
    } );

    /* ========================================================================
       Validate parte / actuacion form on submit
       ======================================================================== */

    var $parteForm = $( '#lznd-parte-form' );

    $parteForm.on( 'submit', function ( e ) {
        var errors = [];

        // Required: proyecto
        var $proy = $parteForm.find( '[name="_lznd_proyecto_id"]' );
        if ( $proy.length && ! $proy.val() ) {
            errors.push( 'El proyecto es obligatorio.' );
        }

        // Required: tipo
        var $tipo = $parteForm.find( '[name="_lznd_tipo_term_id"]' );
        if ( $tipo.length && ! $tipo.val() ) {
            errors.push( 'El tipo de trabajo es obligatorio.' );
        }

        if ( errors.length ) {
            e.preventDefault();
            var $errBox = $( '#lznd-parte-errors' );
            if ( ! $errBox.length ) {
                $errBox = $( '<div id="lznd-parte-errors" class="notice notice-error"></div>' );
                $parteForm.prepend( $errBox );
            }
            $errBox.html( '<ul><li>' + errors.join( '</li><li>' ) + '</li></ul>' );
            $( 'html, body' ).animate( { scrollTop: $errBox.offset().top - 40 }, 300 );
        }
    } );

    /* ========================================================================
       Admin columns: color-code state badges
       ======================================================================== */

    $( '.column-lznd_estado .lznd-badge' ).each( function () {
        // Already styled via CSS, nothing extra needed here.
    } );

} );
