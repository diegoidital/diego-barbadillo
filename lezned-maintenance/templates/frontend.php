<?php
/**
 * Frontend template: multi-step actuacion form
 *
 * Variables available from shortcode:
 *   $actuacion_id (int, may be 0)
 *
 * @package Lezned_Maintenance_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$current_user    = wp_get_current_user();
$current_user_id = get_current_user_id();

// Preload actuacion data if ID is provided
$pre_actuacion = null;
$pre_tareas    = [];
$pre_fotos     = [];
$pre_estado    = 'pendiente';
$pre_observ    = '';
$pre_proyecto  = null;
$pre_tipo_term = null;

if ( $actuacion_id > 0 ) {
    $pre_actuacion = get_post( $actuacion_id );

    if ( $pre_actuacion && $pre_actuacion->post_type === 'lznd_actuacion' ) {
        $pre_estado     = get_post_meta( $actuacion_id, '_lznd_estado', true ) ?: 'pendiente';
        $pre_observ     = get_post_meta( $actuacion_id, '_lznd_observaciones', true ) ?: '';
        $pre_proyecto_id = get_post_meta( $actuacion_id, '_lznd_proyecto_id', true );
        $pre_tipo_tid   = get_post_meta( $actuacion_id, '_lznd_tipo_term_id', true );

        if ( $pre_proyecto_id ) {
            $pre_proyecto = get_post( $pre_proyecto_id );
        }
        if ( $pre_tipo_tid ) {
            $pre_tipo_term = get_term( $pre_tipo_tid, 'lznd_tipo' );
        }

        $tareas_raw  = get_post_meta( $actuacion_id, '_lznd_tareas', true );
        $pre_tareas  = $tareas_raw ? json_decode( $tareas_raw, true ) : [];
        if ( ! is_array( $pre_tareas ) ) {
            $pre_tareas = [];
        }
        $pre_tareas = LZND_Ajax::fix_tareas_encoding( $pre_tareas );
        $pre_tareas = LZND_Ajax::enrich_tareas( $pre_tareas, intval( $pre_tipo_tid ), $actuacion_id );

        $fotos_raw  = get_post_meta( $actuacion_id, '_lznd_fotos', true );
        $fotos_arr  = is_array( $fotos_raw ) ? $fotos_raw : [];
        foreach ( $fotos_arr as $fid ) {
            $furl   = wp_get_attachment_url( $fid );
            $fthumb = wp_get_attachment_image_url( $fid, 'thumbnail' );
            if ( $furl ) {
                $pre_fotos[] = [ 'attach_id' => intval( $fid ), 'url' => $furl, 'thumb' => $fthumb ?: $furl ];
            }
        }
    } else {
        $actuacion_id = 0;
    }
}

// Get all proyectos the user can access
$proyectos_args = [
    'post_type'      => 'lznd_proyecto',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'orderby'        => 'title',
    'order'          => 'ASC',
];
// Administrators see all; others see their own
if ( ! current_user_can( 'manage_options' ) ) {
    $proyectos_args['author'] = $current_user_id;
}
$proyectos_list = get_posts( $proyectos_args );

// Get all tipo terms
$tipo_terms = get_terms( [
    'taxonomy'   => 'lznd_tipo',
    'hide_empty' => false,
] );
if ( is_wp_error( $tipo_terms ) ) {
    $tipo_terms = [];
}

// State helpers
$ya_enviado = in_array( $pre_estado, [ 'enviado', 'cerrado' ], true );
$total_tasks = count( $pre_tareas );
$hechas_tasks = count( array_filter( $pre_tareas, fn( $t ) => ! empty( $t['completada'] ) ) );

$firma_tec_url    = $actuacion_id ? get_post_meta( $actuacion_id, '_lznd_firma_tecnico_url', true ) : '';
$firma_cli_url    = $actuacion_id ? get_post_meta( $actuacion_id, '_lznd_firma_cliente_url', true ) : '';
$nombre_fir_tec   = $actuacion_id ? get_post_meta( $actuacion_id, '_lznd_nombre_firmante_tecnico', true ) : '';
$nombre_fir_cli   = $actuacion_id ? get_post_meta( $actuacion_id, '_lznd_nombre_firmante_cliente', true ) : '';
$fecha_prog       = $actuacion_id ? get_post_meta( $actuacion_id, '_lznd_fecha_prog', true ) : '';
$fecha_inicio     = $actuacion_id ? get_post_meta( $actuacion_id, '_lznd_fecha_inicio', true ) : '';
$fecha_fin_act    = $actuacion_id ? get_post_meta( $actuacion_id, '_lznd_fecha_fin', true ) : '';
$tecnico_name     = $actuacion_id ? get_post_meta( $actuacion_id, '_lznd_tecnico', true ) : $current_user->display_name;
$prioridad        = $actuacion_id ? ( get_post_meta( $actuacion_id, '_lznd_prioridad', true ) ?: 'normal' ) : 'normal';
?>
<div class="lznd-app" data-actuacion-id="<?php echo esc_attr( $actuacion_id ); ?>">

    <?php /* ---- Step indicator ---- */ ?>
    <div class="lznd-steps-indicator" id="lznd-steps-indicator">
        <span class="lznd-step-dot <?php echo $actuacion_id ? '' : 'lznd-step-dot--active'; ?>" data-step="1">1</span>
        <span class="lznd-step-connector"></span>
        <span class="lznd-step-dot <?php echo $actuacion_id ? 'lznd-step-dot--active' : ''; ?>" data-step="2">2</span>
    </div>

    <?php /* ============================================================
           STEP 1: Project selector + Dashboard + Type selector
           ============================================================ */ ?>
    <div class="lznd-step" id="lznd-step-1" <?php echo $actuacion_id ? 'style="display:none;"' : ''; ?>>
        <div class="lznd-card">
            <h2 class="lznd-card__title"><?php esc_html_e( 'Seleccionar trabajo', 'lezned-maintenance' ); ?></h2>
            <p class="lznd-card__subtitle"><?php esc_html_e( 'Selecciona el proyecto para comenzar.', 'lezned-maintenance' ); ?></p>

            <div class="lznd-field-group">
                <label for="lznd-proyecto-select" class="lznd-label">
                    <?php esc_html_e( 'Proyecto', 'lezned-maintenance' ); ?> <span class="lznd-required">*</span>
                </label>
                <select id="lznd-proyecto-select" class="lznd-select" required>
                    <option value=""><?php esc_html_e( '-- Selecciona un proyecto --', 'lezned-maintenance' ); ?></option>
                    <?php foreach ( $proyectos_list as $proy ) :
                        $proy_tipo_term_id = get_post_meta( $proy->ID, '_lznd_tipo_term_id', true );
                    ?>
                        <option value="<?php echo esc_attr( $proy->ID ); ?>" data-tipo-term-id="<?php echo esc_attr( $proy_tipo_term_id ?: '' ); ?>">
                            <?php echo esc_html( get_the_title( $proy->ID ) ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <?php /* ---- Project dashboard panel ---- */ ?>
        <div id="lznd-project-dashboard" class="lznd-project-dashboard" style="display:none;">
            <div class="lznd-dash-stats">
                <div class="lznd-dash-stat lznd-dash-stat--done">
                    <span class="lznd-dash-stat__num" id="lznd-dash-completadas">0</span>
                    <span class="lznd-dash-stat__label"><?php esc_html_e( 'Realizados', 'lezned-maintenance' ); ?></span>
                </div>
                <div class="lznd-dash-stat lznd-dash-stat--pending">
                    <span class="lznd-dash-stat__num" id="lznd-dash-pendientes-count">0</span>
                    <span class="lznd-dash-stat__label"><?php esc_html_e( 'Pendientes', 'lezned-maintenance' ); ?></span>
                </div>
                <div class="lznd-dash-stat lznd-dash-stat--upcoming">
                    <span class="lznd-dash-stat__num" id="lznd-dash-proximas-count">0</span>
                    <span class="lznd-dash-stat__label"><?php esc_html_e( 'Próximos', 'lezned-maintenance' ); ?></span>
                </div>
            </div>

            <div id="lznd-dash-pendientes-section" class="lznd-dash-section" style="display:none;">
                <h4 class="lznd-dash-section__title"><?php esc_html_e( 'Actuaciones pendientes', 'lezned-maintenance' ); ?></h4>
                <div id="lznd-dash-pendientes-list" class="lznd-dash-list"></div>
            </div>

            <div id="lznd-dash-proximas-section" class="lznd-dash-section" style="display:none;">
                <h4 class="lznd-dash-section__title"><?php esc_html_e( 'Próximos trabajos', 'lezned-maintenance' ); ?></h4>
                <div id="lznd-dash-proximas-list" class="lznd-dash-list"></div>
            </div>
        </div>

        <?php /* ---- Tipo fallback (shown only for projects without tipo configured) ---- */ ?>
        <div id="lznd-tipo-wrap" class="lznd-card" style="display:none;">
            <h3 class="lznd-card__section-title"><?php esc_html_e( 'Tipo de trabajo', 'lezned-maintenance' ); ?></h3>

            <div class="lznd-field-group">
                <label for="lznd-tipo-select" class="lznd-label">
                    <?php esc_html_e( 'Selecciona el tipo', 'lezned-maintenance' ); ?> <span class="lznd-required">*</span>
                </label>
                <select id="lznd-tipo-select" class="lznd-select">
                    <option value=""><?php esc_html_e( '-- Selecciona el tipo de trabajo --', 'lezned-maintenance' ); ?></option>
                    <?php foreach ( $tipo_terms as $term ) : ?>
                        <option value="<?php echo esc_attr( $term->term_id ); ?>">
                            <?php echo esc_html( $term->name ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div id="lznd-step1-error" class="lznd-notice lznd-notice--error" style="display:none;"></div>

            <button type="button" id="lznd-comenzar-btn" class="lznd-btn lznd-btn--primary lznd-btn--full">
                <span class="lznd-btn__text"><?php esc_html_e( 'Comenzar', 'lezned-maintenance' ); ?></span>
                <span class="lznd-btn__spinner" style="display:none;">&#9696;</span>
            </button>
        </div>
    </div>

    <?php /* ============================================================
           STEP 2: Actuacion form
           ============================================================ */ ?>
    <div class="lznd-step" id="lznd-step-2" <?php echo $actuacion_id ? '' : 'style="display:none;"'; ?>>

        <?php if ( $ya_enviado ) : ?>
            <div class="lznd-card lznd-card--success">
                <div class="lznd-success-icon">&#10003;</div>
                <h2 class="lznd-card__title"><?php esc_html_e( 'Parte enviado', 'lezned-maintenance' ); ?></h2>
                <p class="lznd-card__subtitle"><?php esc_html_e( 'Este parte ya ha sido enviado y está cerrado.', 'lezned-maintenance' ); ?></p>
                <?php if ( $pre_proyecto ) : ?>
                    <p><strong><?php esc_html_e( 'Proyecto:', 'lezned-maintenance' ); ?></strong>
                        <?php echo esc_html( get_the_title( $pre_proyecto->ID ) ); ?></p>
                <?php endif; ?>
                <?php if ( $fecha_inicio ) : ?>
                    <p><strong><?php esc_html_e( 'Fecha:', 'lezned-maintenance' ); ?></strong>
                        <?php echo esc_html( date_i18n( 'd/m/Y H:i', strtotime( $fecha_inicio ) ) ); ?></p>
                <?php endif; ?>
            </div>

        <?php else : ?>

        <?php /* -- Header -- */ ?>
        <div class="lznd-actuacion-header" id="lznd-actuacion-header">
            <div class="lznd-actuacion-header__main">
                <h2 class="lznd-actuacion-header__title" id="lznd-header-proyecto">
                    <?php echo $pre_proyecto ? esc_html( get_the_title( $pre_proyecto->ID ) ) : esc_html__( 'Proyecto', 'lezned-maintenance' ); ?>
                </h2>
                <div class="lznd-actuacion-header__badges">
                    <span class="lznd-badge lznd-badge--tipo" id="lznd-badge-tipo">
                        <?php echo ( $pre_tipo_term && ! is_wp_error( $pre_tipo_term ) ) ? esc_html( $pre_tipo_term->name ) : ''; ?>
                    </span>
                    <span class="lznd-badge lznd-badge--estado lznd-badge--estado-<?php echo esc_attr( $pre_estado ); ?>" id="lznd-badge-estado">
                        <?php
                        $estados_labels = [
                            'pendiente' => __( 'Pendiente', 'lezned-maintenance' ),
                            'en_curso'  => __( 'En curso', 'lezned-maintenance' ),
                            'enviado'   => __( 'Enviado', 'lezned-maintenance' ),
                            'cerrado'   => __( 'Cerrado', 'lezned-maintenance' ),
                        ];
                        echo esc_html( $estados_labels[ $pre_estado ] ?? ucfirst( $pre_estado ) );
                        ?>
                    </span>
                    <span class="lznd-badge lznd-badge--prioridad lznd-badge--prioridad-<?php echo esc_attr( $prioridad ); ?>">
                        <?php
                        $prio_labels = [
                            'baja'   => __( 'Baja', 'lezned-maintenance' ),
                            'normal' => __( 'Normal', 'lezned-maintenance' ),
                            'alta'   => __( 'Alta', 'lezned-maintenance' ),
                            'urgente'=> __( 'Urgente', 'lezned-maintenance' ),
                        ];
                        echo esc_html( $prio_labels[ $prioridad ] ?? $prioridad );
                        ?>
                    </span>
                </div>
            </div>
        </div>

        <?php /* -- Info grid -- */ ?>
        <div class="lznd-info-grid" id="lznd-info-grid">
            <div class="lznd-info-item">
                <span class="lznd-info-item__label"><?php esc_html_e( 'Técnico', 'lezned-maintenance' ); ?></span>
                <span class="lznd-info-item__value" id="lznd-info-tecnico"><?php echo esc_html( $tecnico_name ); ?></span>
            </div>
            <div class="lznd-info-item">
                <span class="lznd-info-item__label"><?php esc_html_e( 'Fecha programada', 'lezned-maintenance' ); ?></span>
                <span class="lznd-info-item__value" id="lznd-info-fecha-prog">
                    <?php echo $fecha_prog ? esc_html( date_i18n( 'd/m/Y', strtotime( $fecha_prog ) ) ) : '—'; ?>
                </span>
            </div>
            <div class="lznd-info-item">
                <span class="lznd-info-item__label"><?php esc_html_e( 'Inicio', 'lezned-maintenance' ); ?></span>
                <span class="lznd-info-item__value" id="lznd-info-fecha-inicio">
                    <?php echo $fecha_inicio ? esc_html( date_i18n( 'd/m/Y H:i', strtotime( $fecha_inicio ) ) ) : '—'; ?>
                </span>
            </div>
            <div class="lznd-info-item">
                <span class="lznd-info-item__label"><?php esc_html_e( 'Progreso', 'lezned-maintenance' ); ?></span>
                <span class="lznd-info-item__value" id="lznd-progress-text">
                    <span id="lznd-hechas"><?php echo esc_html( $hechas_tasks ); ?></span>/<span id="lznd-total"><?php echo esc_html( $total_tasks ); ?></span>
                </span>
                <div class="lznd-progress-bar-wrap">
                    <div class="lznd-progress-bar" id="lznd-progress-bar"
                         style="width:<?php echo $total_tasks > 0 ? esc_attr( round( $hechas_tasks / $total_tasks * 100 ) ) : 0; ?>%"></div>
                </div>
            </div>
        </div>

        <?php /* ---- Checklist ---- */ ?>
        <div class="lznd-card" id="lznd-checklist-card">
            <h3 class="lznd-card__section-title"><?php esc_html_e( 'Tareas', 'lezned-maintenance' ); ?></h3>

            <ul class="lznd-task-list" id="lznd-task-list">
            </ul>

            <div class="lznd-add-task-row">
                <input
                    type="text"
                    id="lznd-new-task-input"
                    class="lznd-input"
                    placeholder="<?php esc_attr_e( 'Nueva tarea...', 'lezned-maintenance' ); ?>"
                    maxlength="200"
                >
                <button type="button" id="lznd-add-task-btn" class="lznd-btn lznd-btn--secondary">
                    <?php esc_html_e( 'Añadir', 'lezned-maintenance' ); ?>
                </button>
            </div>
        </div>

        <?php /* ---- Observaciones ---- */ ?>
        <div class="lznd-card" id="lznd-observaciones-card">
            <h3 class="lznd-card__section-title"><?php esc_html_e( 'Observaciones', 'lezned-maintenance' ); ?></h3>
            <textarea
                id="lznd-observaciones"
                class="lznd-textarea"
                rows="4"
                placeholder="<?php esc_attr_e( 'Escribe tus observaciones aquí...', 'lezned-maintenance' ); ?>"
            ><?php echo esc_textarea( $pre_observ ); ?></textarea>
            <span class="lznd-autosave-status" id="lznd-observ-status"></span>
        </div>

        <?php /* ---- Fotos ---- */ ?>
        <div class="lznd-card" id="lznd-fotos-card">
            <h3 class="lznd-card__section-title"><?php esc_html_e( 'Fotos', 'lezned-maintenance' ); ?></h3>

            <div class="lznd-upload-area" id="lznd-upload-area">
                <div class="lznd-upload-area__inner">
                    <span class="lznd-upload-icon">&#128247;</span>
                    <p class="lznd-upload-label"><?php esc_html_e( 'Arrastra fotos aquí o haz clic para seleccionar', 'lezned-maintenance' ); ?></p>
                    <input type="file" id="lznd-foto-input" class="lznd-upload-input" accept="image/*" multiple>
                </div>
            </div>

            <div id="lznd-upload-progress" class="lznd-upload-progress" style="display:none;">
                <div class="lznd-upload-progress__bar"></div>
                <span class="lznd-upload-progress__label"></span>
            </div>

            <div class="lznd-fotos-grid" id="lznd-fotos-grid">
                <?php foreach ( $pre_fotos as $foto ) : ?>
                <div class="lznd-foto-item" data-attach-id="<?php echo esc_attr( $foto['attach_id'] ); ?>">
                    <img src="<?php echo esc_url( $foto['thumb'] ); ?>" alt="" loading="lazy" class="lznd-foto-thumb">
                    <a href="<?php echo esc_url( $foto['url'] ); ?>" target="_blank" class="lznd-foto-view">&#128065;</a>
                    <button type="button" class="lznd-foto-delete" aria-label="<?php esc_attr_e( 'Eliminar foto', 'lezned-maintenance' ); ?>">&#10005;</button>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <?php /* ---- Firma técnico ---- */ ?>
        <div class="lznd-card lznd-firma-card lznd-firma--tecnico" id="lznd-firma-tecnico-card">
            <h3 class="lznd-card__section-title lznd-firma-title lznd-firma-title--tecnico">
                <?php esc_html_e( 'Firma del técnico', 'lezned-maintenance' ); ?>
            </h3>

            <?php if ( $firma_tec_url ) : ?>
            <div class="lznd-firma-saved" id="lznd-firma-tec-saved">
                <img src="<?php echo esc_url( $firma_tec_url ); ?>" alt="<?php esc_attr_e( 'Firma técnico', 'lezned-maintenance' ); ?>" class="lznd-firma-preview">
                <p class="lznd-firma-name"><?php echo esc_html( $nombre_fir_tec ); ?></p>
                <button type="button" class="lznd-btn lznd-btn--ghost lznd-btn--sm lznd-firma-redo" data-tipo="tecnico">
                    <?php esc_html_e( 'Volver a firmar', 'lezned-maintenance' ); ?>
                </button>
            </div>
            <div class="lznd-firma-pad-wrap" id="lznd-firma-tec-pad" style="display:none;">
            <?php else : ?>
            <div class="lznd-firma-saved" id="lznd-firma-tec-saved" style="display:none;">
                <img src="" alt="" class="lznd-firma-preview">
                <p class="lznd-firma-name"></p>
                <button type="button" class="lznd-btn lznd-btn--ghost lznd-btn--sm lznd-firma-redo" data-tipo="tecnico">
                    <?php esc_html_e( 'Volver a firmar', 'lezned-maintenance' ); ?>
                </button>
            </div>
            <div class="lznd-firma-pad-wrap" id="lznd-firma-tec-pad">
            <?php endif; ?>
                <div class="lznd-firma-canvas-wrap">
                    <canvas id="lznd-sig-tecnico" class="lznd-sig-canvas"></canvas>
                </div>
                <div class="lznd-firma-actions">
                    <input
                        type="text"
                        id="lznd-nombre-firmante-tecnico"
                        class="lznd-input"
                        placeholder="<?php esc_attr_e( 'Nombre del técnico', 'lezned-maintenance' ); ?>"
                        value="<?php echo esc_attr( $nombre_fir_tec ?: $current_user->display_name ); ?>"
                    >
                    <button type="button" class="lznd-btn lznd-btn--ghost lznd-btn--sm" id="lznd-firma-tec-clear">
                        <?php esc_html_e( 'Borrar', 'lezned-maintenance' ); ?>
                    </button>
                    <button type="button" class="lznd-btn lznd-btn--primary lznd-btn--sm" id="lznd-firma-tec-save">
                        <?php esc_html_e( 'Guardar firma', 'lezned-maintenance' ); ?>
                    </button>
                </div>
            </div>
        </div>

        <?php /* ---- Firma cliente ---- */ ?>
        <div class="lznd-card lznd-firma-card lznd-firma--cliente" id="lznd-firma-cliente-card">
            <h3 class="lznd-card__section-title lznd-firma-title lznd-firma-title--cliente">
                <?php esc_html_e( 'Firma del cliente', 'lezned-maintenance' ); ?>
            </h3>

            <?php if ( $firma_cli_url ) : ?>
            <div class="lznd-firma-saved" id="lznd-firma-cli-saved">
                <img src="<?php echo esc_url( $firma_cli_url ); ?>" alt="<?php esc_attr_e( 'Firma cliente', 'lezned-maintenance' ); ?>" class="lznd-firma-preview">
                <p class="lznd-firma-name"><?php echo esc_html( $nombre_fir_cli ); ?></p>
                <button type="button" class="lznd-btn lznd-btn--ghost lznd-btn--sm lznd-firma-redo" data-tipo="cliente">
                    <?php esc_html_e( 'Volver a firmar', 'lezned-maintenance' ); ?>
                </button>
            </div>
            <div class="lznd-firma-pad-wrap" id="lznd-firma-cli-pad" style="display:none;">
            <?php else : ?>
            <div class="lznd-firma-saved" id="lznd-firma-cli-saved" style="display:none;">
                <img src="" alt="" class="lznd-firma-preview">
                <p class="lznd-firma-name"></p>
                <button type="button" class="lznd-btn lznd-btn--ghost lznd-btn--sm lznd-firma-redo" data-tipo="cliente">
                    <?php esc_html_e( 'Volver a firmar', 'lezned-maintenance' ); ?>
                </button>
            </div>
            <div class="lznd-firma-pad-wrap" id="lznd-firma-cli-pad">
            <?php endif; ?>
                <div class="lznd-firma-canvas-wrap">
                    <canvas id="lznd-sig-cliente" class="lznd-sig-canvas"></canvas>
                </div>
                <div class="lznd-firma-actions">
                    <input
                        type="text"
                        id="lznd-nombre-firmante-cliente"
                        class="lznd-input"
                        placeholder="<?php esc_attr_e( 'Nombre del cliente', 'lezned-maintenance' ); ?>"
                        value="<?php echo esc_attr( $nombre_fir_cli ); ?>"
                    >
                    <button type="button" class="lznd-btn lznd-btn--ghost lznd-btn--sm" id="lznd-firma-cli-clear">
                        <?php esc_html_e( 'Borrar', 'lezned-maintenance' ); ?>
                    </button>
                    <button type="button" class="lznd-btn lznd-btn--primary lznd-btn--sm" id="lznd-firma-cli-save">
                        <?php esc_html_e( 'Guardar firma', 'lezned-maintenance' ); ?>
                    </button>
                </div>
            </div>
        </div>

        <?php /* ---- Submit section ---- */ ?>
        <div class="lznd-card lznd-submit-card" id="lznd-submit-card">
            <h3 class="lznd-card__section-title"><?php esc_html_e( 'Enviar parte', 'lezned-maintenance' ); ?></h3>
            <p class="lznd-submit-hint"><?php esc_html_e( 'Una vez enviado, el parte quedará registrado como cerrado.', 'lezned-maintenance' ); ?></p>
            <div class="lznd-submit-buttons">
                <button type="button" id="lznd-send-email-btn" class="lznd-btn lznd-btn--email">
                    <span class="lznd-btn__icon">&#128231;</span>
                    <?php esc_html_e( 'Enviar por Email', 'lezned-maintenance' ); ?>
                </button>
                <button type="button" id="lznd-send-whatsapp-btn" class="lznd-btn lznd-btn--whatsapp">
                    <span class="lznd-btn__icon">&#128172;</span>
                    <?php esc_html_e( 'Enviar por WhatsApp', 'lezned-maintenance' ); ?>
                </button>
            </div>
            <div id="lznd-submit-result" class="lznd-notice" style="display:none;"></div>
        </div>

        <?php endif; // ya_enviado ?>

    </div><!-- #lznd-step-2 -->

</div><!-- .lznd-app -->

<?php if ( $actuacion_id > 0 && ! empty( $pre_tareas ) ) : ?>
<script>
window.lzndPreload = {
    actuacionId: <?php echo intval( $actuacion_id ); ?>,
    tareas: <?php echo wp_json_encode( $pre_tareas, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP ); ?>
};
</script>
<?php endif; ?>

<?php /* ---- Pending work modal ---- */ ?>
<div class="lznd-modal" id="lznd-pending-modal" role="dialog" aria-modal="true" style="display:none;">
    <div class="lznd-modal__overlay"></div>
    <div class="lznd-modal__box lznd-modal__box--pending">
        <div class="lznd-pending-modal__icon">&#9888;</div>
        <h3 class="lznd-modal__title"><?php esc_html_e( 'Trabajo pendiente', 'lezned-maintenance' ); ?></h3>
        <p class="lznd-modal__body">
            <?php esc_html_e( 'Hay un trabajo en curso para este proyecto. ¿Qué deseas hacer?', 'lezned-maintenance' ); ?>
        </p>
        <div id="lznd-pending-summary" class="lznd-pending-summary" style="display:none;"></div>
        <div class="lznd-modal__actions lznd-modal__actions--col">
            <button type="button" id="lznd-pending-recover" class="lznd-btn lznd-btn--primary lznd-btn--full">
                &#9654;&nbsp; <?php esc_html_e( 'Recuperar y completar trabajo pendiente', 'lezned-maintenance' ); ?>
            </button>
            <button type="button" id="lznd-pending-new" class="lznd-btn lznd-btn--ghost lznd-btn--full">
                &#43;&nbsp; <?php esc_html_e( 'Empezar un trabajo nuevo', 'lezned-maintenance' ); ?>
            </button>
        </div>
    </div>
</div>

<?php /* ---- Confirmation modal ---- */ ?>
<div class="lznd-modal" id="lznd-confirm-modal" role="dialog" aria-modal="true" style="display:none;">
    <div class="lznd-modal__overlay"></div>
    <div class="lznd-modal__box">
        <h3 class="lznd-modal__title" id="lznd-modal-title"></h3>
        <p class="lznd-modal__body" id="lznd-modal-body"></p>
        <div class="lznd-modal__actions">
            <button type="button" id="lznd-modal-cancel" class="lznd-btn lznd-btn--ghost">
                <?php esc_html_e( 'Cancelar', 'lezned-maintenance' ); ?>
            </button>
            <button type="button" id="lznd-modal-confirm" class="lznd-btn lznd-btn--primary">
                <?php esc_html_e( 'Confirmar', 'lezned-maintenance' ); ?>
            </button>
        </div>
    </div>
</div>
