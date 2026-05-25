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

    /* ========================================================================
       Tipos de trabajo – inline edit & delete
       ======================================================================== */

    var ajax_url  = ( window.lzndAdmin && lzndAdmin.ajax_url ) || ajaxurl;
    var nonce_val = ( window.lzndAdmin && lzndAdmin.nonce )    || '';

    function tiposNotice( msg, type ) {
        var $n = $( '#lznd-tipos-notice' );
        $n.removeClass( 'notice-success notice-error' )
          .addClass( 'notice notice-' + type + ' is-dismissible' )
          .html( '<p>' + msg + '</p>' )
          .show();
        setTimeout( function () { $n.fadeOut(); }, 3000 );
    }

    // Auto-generate slug from name in the "add" form
    $( '#lznd-tipo-name' ).on( 'input', function () {
        var $slug = $( '#lznd-tipo-slug' );
        if ( $slug.val() !== '' ) return; // user has manually typed a slug
        var raw  = $( this ).val().toLowerCase()
                       .normalize( 'NFD' ).replace( /[̀-ͯ]/g, '' )
                       .replace( /[^a-z0-9]+/g, '-' )
                       .replace( /^-+|-+$/g, '' );
        $slug.val( raw );
    } );

    // Clear auto-slug tracking when user edits slug manually
    $( '#lznd-tipo-slug' ).on( 'input', function () {
        $( this ).data( 'manual', true );
    } );

    // Edit button
    $( document ).on( 'click', '.lznd-tipo-edit-btn', function () {
        var $row = $( this ).closest( 'tr' );
        $row.find( '.lznd-view-mode' ).hide();
        $row.find( '.lznd-edit-mode' ).show().first().focus();
        $( this ).hide();
        $row.find( '.lznd-tipo-save-btn, .lznd-tipo-cancel-btn' ).show();
        $row.find( '.lznd-tipo-delete-btn' ).hide();
    } );

    // Cancel button
    $( document ).on( 'click', '.lznd-tipo-cancel-btn', function () {
        var $row = $( this ).closest( 'tr' );
        $row.find( '.lznd-tipo-name-input' ).val( $row.find( '.lznd-tipo-name-input' ).data( 'original' ) );
        $row.find( '.lznd-tipo-slug-input' ).val( $row.find( '.lznd-tipo-slug-input' ).data( 'original' ) );
        $row.find( '.lznd-view-mode' ).show();
        $row.find( '.lznd-edit-mode' ).hide();
        $row.find( '.lznd-tipo-save-btn, .lznd-tipo-cancel-btn' ).hide();
        $row.find( '.lznd-tipo-edit-btn, .lznd-tipo-delete-btn' ).show();
    } );

    // Save button
    $( document ).on( 'click', '.lznd-tipo-save-btn', function () {
        var $btn  = $( this ).prop( 'disabled', true );
        var $row  = $btn.closest( 'tr' );
        var termId = $row.data( 'term-id' );
        var name   = $row.find( '.lznd-tipo-name-input' ).val().trim();
        var slug   = $row.find( '.lznd-tipo-slug-input' ).val().trim();

        if ( ! name ) {
            tiposNotice( 'El nombre no puede estar vacío.', 'error' );
            $btn.prop( 'disabled', false );
            return;
        }

        $.ajax( {
            url:    ajax_url,
            method: 'POST',
            data: {
                action:   'lznd_save_tipo',
                nonce:    nonce_val,
                term_id:  termId,
                name:     name,
                slug:     slug,
            },
            success: function ( res ) {
                $btn.prop( 'disabled', false );
                if ( ! res.success ) {
                    tiposNotice( ( res.data && res.data.message ) || 'Error al guardar.', 'error' );
                    return;
                }
                $row.find( '.lznd-tipo-name-view' ).html( '<strong>' + $( '<span>' ).text( res.data.name ).html() + '</strong>' );
                $row.find( '.lznd-tipo-slug-view' ).html( '<code>' + $( '<span>' ).text( res.data.slug ).html() + '</code>' );
                $row.find( '.lznd-tipo-name-input' ).val( res.data.name ).data( 'original', res.data.name );
                $row.find( '.lznd-tipo-slug-input' ).val( res.data.slug ).data( 'original', res.data.slug );
                $row.find( '.lznd-view-mode' ).show();
                $row.find( '.lznd-edit-mode' ).hide();
                $row.find( '.lznd-tipo-save-btn, .lznd-tipo-cancel-btn' ).hide();
                $row.find( '.lznd-tipo-edit-btn, .lznd-tipo-delete-btn' ).show();
                tiposNotice( 'Tipo guardado correctamente.', 'success' );
            },
            error: function () {
                $btn.prop( 'disabled', false );
                tiposNotice( 'Error de conexión.', 'error' );
            },
        } );
    } );

    // Delete button
    $( document ).on( 'click', '.lznd-tipo-delete-btn', function () {
        var $btn   = $( this );
        var count  = parseInt( $btn.data( 'count' ), 10 ) || 0;
        var msg    = count > 0
            ? 'Este tipo se usa en ' + count + ' actuación(es). ¿Eliminar igualmente?'
            : '¿Eliminar este tipo de trabajo?';
        if ( ! confirm( msg ) ) return;

        var $row   = $btn.closest( 'tr' );
        var termId = $row.data( 'term-id' );

        $row.css( 'opacity', 0.5 );

        $.ajax( {
            url:    ajax_url,
            method: 'POST',
            data: {
                action:  'lznd_delete_tipo',
                nonce:   nonce_val,
                term_id: termId,
            },
            success: function ( res ) {
                if ( res.success ) {
                    $row.fadeOut( 300, function () {
                        $row.remove();
                        if ( $( '.lznd-tipos-table tbody tr' ).length === 0 ) {
                            $( '.lznd-tipos-table tbody' ).html(
                                '<tr><td colspan="4" style="text-align:center;padding:20px;color:#646970;">No hay tipos de trabajo todavía.</td></tr>'
                            );
                        }
                    } );
                    tiposNotice( 'Tipo eliminado.', 'success' );
                } else {
                    $row.css( 'opacity', 1 );
                    tiposNotice( ( res.data && res.data.message ) || 'Error al eliminar.', 'error' );
                }
            },
            error: function () {
                $row.css( 'opacity', 1 );
                tiposNotice( 'Error de conexión.', 'error' );
            },
        } );
    } );

} );
