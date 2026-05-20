<?php
/**
 * Frontend template: dashboard + actuacion form
 *
 * @package Lezned_Maintenance_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$current_user    = wp_get_current_user();
$current_user_id = get_current_user_id();

// ── Actuacion preload ────────────────────────────────────────────────────────
$actuacion_id  = intval( $_GET['actuacion_id'] ?? 0 );
$pre_actuacion = null;
$pre_tareas    = [];
$pre_fotos     = [];
$pre_estado    = 'pendiente';
$pre_observ    = '';
$pre_proyecto  = null;
$pre_tipo_term = null;
$ya_enviado    = false;

if ( $actuacion_id > 0 ) {
    $pre_actuacion = get_post( $actuacion_id );

    if ( $pre_actuacion && $pre_actuacion->post_type === 'lznd_actuacion' ) {
        $pre_estado      = get_post_meta( $actuacion_id, '_lznd_estado', true ) ?: 'pendiente';
        $ya_enviado      = in_array( $pre_estado, [ 'enviado', 'cerrado' ], true );
        $pre_observ      = get_post_meta( $actuacion_id, '_lznd_observaciones', true ) ?: '';
        $pre_proyecto_id = get_post_meta( $actuacion_id, '_lznd_proyecto_id', true );
        $pre_tipo_tid    = get_post_meta( $actuacion_id, '_lznd_tipo_term_id', true );

        if ( $pre_proyecto_id ) {
            $pre_proyecto = get_post( $pre_proyecto_id );
        }
        if ( $pre_tipo_tid ) {
            $pre_tipo_term = get_term( $pre_tipo_tid, 'lznd_tipo' );
        }

        $tareas_raw = get_post_meta( $actuacion_id, '_lznd_tareas', true );
        $pre_tareas = $tareas_raw ? json_decode( $tareas_raw, true ) : [];
        if ( ! is_array( $pre_tareas ) ) {
            $pre_tareas = [];
        }

        $fotos_raw = get_post_meta( $actuacion_id, '_lznd_fotos', true );
        $fotos_arr = is_array( $fotos_raw ) ? $fotos_raw : [];
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

// ── Common: projects + types ─────────────────────────────────────────────────
$proyectos_args = [
    'post_type'      => 'lznd_proyecto',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'orderby'        => 'title',
    'order'          => 'ASC',
];
if ( ! current_user_can( 'manage_options' ) ) {
    $proyectos_args['author'] = $current_user_id;
}
$proyectos_list = get_posts( $proyectos_args );

$tipo_terms = get_terms( [ 'taxonomy' => 'lznd_tipo', 'hide_empty' => false ] );
if ( is_wp_error( $tipo_terms ) ) {
    $tipo_terms = [];
}

// ── Form-view helpers ────────────────────────────────────────────────────────
if ( $actuacion_id > 0 ) {
    $total_tasks  = count( $pre_tareas );
    $hechas_tasks = count( array_filter( $pre_tareas, fn( $t ) => ! empty( $t['completada'] ) ) );

    $firma_tec_url  = get_post_meta( $actuacion_id, '_lznd_firma_tecnico_url', true );
    $firma_cli_url  = get_post_meta( $actuacion_id, '_lznd_firma_cliente_url', true );
    $nombre_fir_tec = get_post_meta( $actuacion_id, '_lznd_nombre_firmante_tecnico', true );
    $nombre_fir_cli = get_post_meta( $actuacion_id, '_lznd_nombre_firmante_cliente', true );
    $fecha_prog     = get_post_meta( $actuacion_id, '_lznd_fecha_prog', true );
    $fecha_inicio   = get_post_meta( $actuacion_id, '_lznd_fecha_inicio', true );
    $prioridad      = get_post_meta( $actuacion_id, '_lznd_prioridad', true ) ?: 'normal';
    $tecnico_name   = get_post_meta( $actuacion_id, '_lznd_tecnico', true ) ?: $current_user->display_name;
}

// ── Dashboard data ───────────────────────────────────────────────────────────
if ( ! $actuacion_id ) {
    $dash_args = [
        'post_type'      => 'lznd_actuacion',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'modified',
        'order'          => 'DESC',
        'meta_query'     => [
            [
                'key'     => '_lznd_estado',
                'value'   => [ 'pendiente', 'en_curso' ],
                'compare' => 'IN',
            ],
        ],
    ];
    if ( ! current_user_can( 'manage_options' ) ) {
        $dash_args['author'] = $current_user_id;
    }
    $active_jobs = get_posts( $dash_args );

    $dash_pending     = 0;
    $dash_in_progress = 0;
    foreach ( $active_jobs as $j ) {
        $es = get_post_meta( $j->ID, '_lznd_estado', true );
        if ( $es === 'pendiente' ) $dash_pending++;
        if ( $es === 'en_curso' )  $dash_in_progress++;
    }

    $completed_args = [
        'post_type'      => 'lznd_actuacion',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'meta_query'     => [
            [
                'key'     => '_lznd_estado',
                'value'   => [ 'enviado', 'cerrado' ],
                'compare' => 'IN',
            ],
        ],
    ];
    if ( ! current_user_can( 'manage_options' ) ) {
        $completed_args['author'] = $current_user_id;
    }
    $dash_completed = count( get_posts( $completed_args ) );
}
?>
<div class="lznd-app" data-actuacion-id="<?php echo esc_attr( $actuacion_id ); ?>">

<?php if ( ! $actuacion_id ) : ?>

<!-- ============================================================
     DASHBOARD VIEW
     ============================================================ -->
<div class="lznd-dash" id="lznd-dashboard">

    <div class="lznd-dash__header">
        <div class="lznd-dash__header-info">
            <h2 class="lznd-dash__title"><?php esc_html_e( 'Panel de Mantenimiento', 'lezned-maintenance' ); ?></h2>
            <p class="lznd-dash__subtitle"><?php printf( esc_html__( 'Bienvenido, %s', 'lezned-maintenance' ), esc_html( $current_user->display_name ) ); ?></p>
        </div>
        <button type="button" id="lznd-toggle-new-job" class="lznd-btn lznd-btn--primary">
            <?php esc_html_e( '+ Nueva actuación', 'lezned-maintenance' ); ?>
        </button>
    </div>

    <!-- KPI cards -->
    <div class="lznd-kpis">
        <div class="lznd-kpi">
            <div class="lznd-kpi__value"><?php echo esc_html( count( $proyectos_list ) ); ?></div>
            <div class="lznd-kpi__label"><?php esc_html_e( 'Proyectos', 'lezned-maintenance' ); ?></div>
        </div>
        <div class="lznd-kpi lznd-kpi--warn">
            <div class="lznd-kpi__value"><?php echo esc_html( $dash_pending ); ?></div>
            <div class="lznd-kpi__label"><?php esc_html_e( 'Pendientes', 'lezned-maintenance' ); ?></div>
        </div>
        <div class="lznd-kpi lznd-kpi--blue">
            <div class="lznd-kpi__value"><?php echo esc_html( $dash_in_progress ); ?></div>
            <div class="lznd-kpi__label"><?php esc_html_e( 'En curso', 'lezned-maintenance' ); ?></div>
        </div>
        <div class="lznd-kpi lznd-kpi--green">
            <div class="lznd-kpi__value"><?php echo esc_html( $dash_completed ); ?></div>
            <div class="lznd-kpi__label"><?php esc_html_e( 'Completados', 'lezned-maintenance' ); ?></div>
        </div>
    </div>

    <!-- Nueva actuación panel (hidden) -->
    <div class="lznd-card lznd-new-job-panel" id="lznd-new-job-panel" style="display:none;">
        <h3 class="lznd-card__section-title"><?php esc_html_e( 'Nueva actuación', 'lezned-maintenance' ); ?></h3>
        <p class="lznd-card__subtitle"><?php esc_html_e( 'Selecciona proyecto y tipo de trabajo para comenzar o retomar.', 'lezned-maintenance' ); ?></p>

        <div class="lznd-field-group">
            <label for="lznd-proyecto-select" class="lznd-label">
                <?php esc_html_e( 'Proyecto', 'lezned-maintenance' ); ?> <span class="lznd-required">*</span>
            </label>
            <select id="lznd-proyecto-select" class="lznd-select">
                <option value=""><?php esc_html_e( '-- Selecciona un proyecto --', 'lezned-maintenance' ); ?></option>
                <?php foreach ( $proyectos_list as $proy ) : ?>
                    <option value="<?php echo esc_attr( $proy->ID ); ?>"><?php echo esc_html( get_the_title( $proy->ID ) ); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="lznd-field-group">
            <label for="lznd-tipo-select" class="lznd-label">
                <?php esc_html_e( 'Tipo de trabajo', 'lezned-maintenance' ); ?> <span class="lznd-required">*</span>
            </label>
            <select id="lznd-tipo-select" class="lznd-select">
                <option value=""><?php esc_html_e( '-- Selecciona el tipo de trabajo --', 'lezned-maintenance' ); ?></option>
                <?php foreach ( $tipo_terms as $term ) : ?>
                    <option value="<?php echo esc_attr( $term->term_id ); ?>"><?php echo esc_html( $term->name ); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div id="lznd-step1-error" class="lznd-notice lznd-notice--error" style="display:none;"></div>

        <div class="lznd-new-job-actions">
            <button type="button" id="lznd-comenzar-btn" class="lznd-btn lznd-btn--primary">
                <span class="lznd-btn__text"><?php esc_html_e( 'Comenzar', 'lezned-maintenance' ); ?></span>
                <span class="lznd-btn__spinner" style="display:none;">&#9696;</span>
            </button>
            <button type="button" id="lznd-cancel-new-job" class="lznd-btn lznd-btn--ghost">
                <?php esc_html_e( 'Cancelar', 'lezned-maintenance' ); ?>
            </button>
        </div>
    </div>

    <!-- Trabajos activos -->
    <div class="lznd-dash__jobs">
        <h3 class="lznd-dash__section-title"><?php esc_html_e( 'Trabajos activos', 'lezned-maintenance' ); ?></h3>

        <?php if ( empty( $active_jobs ) ) : ?>
            <div class="lznd-empty-state">
                <div class="lznd-empty-state__icon">📋</div>
                <p class="lznd-empty-state__text"><?php esc_html_e( 'No hay trabajos activos.', 'lezned-maintenance' ); ?></p>
                <p class="lznd-empty-state__hint"><?php esc_html_e( 'Pulsa "+ Nueva actuación" para iniciar.', 'lezned-maintenance' ); ?></p>
            </div>
        <?php else : ?>
        <div class="lznd-jobs-list">

            <?php
            $estado_labels = [
                'pendiente' => __( 'Pendiente', 'lezned-maintenance' ),
                'en_curso'  => __( 'En curso',  'lezned-maintenance' ),
            ];
            foreach ( $active_jobs as $job ) :
                $j_id       = $job->ID;
                $j_proy_id  = get_post_meta( $j_id, '_lznd_proyecto_id', true );
                $j_tipo_tid = get_post_meta( $j_id, '_lznd_tipo_term_id', true );
                $j_estado   = get_post_meta( $j_id, '_lznd_estado', true ) ?: 'pendiente';

                $j_tareas_raw = get_post_meta( $j_id, '_lznd_tareas', true );
                $j_tareas     = $j_tareas_raw ? json_decode( $j_tareas_raw, true ) : [];
                $j_tareas     = is_array( $j_tareas ) ? $j_tareas : [];
                $j_total      = count( $j_tareas );
                $j_hechas     = count( array_filter( $j_tareas, fn( $t ) => ! empty( $t['completada'] ) ) );
                $j_pct        = $j_total > 0 ? round( $j_hechas / $j_total * 100 ) : 0;
                $j_proy_name  = $j_proy_id ? get_the_title( $j_proy_id ) : '—';

                $j_tipo_name = '';
                if ( $j_tipo_tid ) {
                    $j_term = get_term( $j_tipo_tid, 'lznd_tipo' );
                    if ( $j_term && ! is_wp_error( $j_term ) ) {
                        $j_tipo_name = $j_term->name;
                    }
                }

                $j_fecha   = get_post_meta( $j_id, '_lznd_fecha_prog', true );
                $j_tecnico = get_post_meta( $j_id, '_lznd_tecnico', true );
            ?>
            <div class="lznd-job-row">
                <div class="lznd-job-row__info">
                    <div class="lznd-job-row__title"><?php echo esc_html( $j_proy_name ); ?></div>
                    <div class="lznd-job-row__meta">
                        <?php if ( $j_tipo_name ) : ?>
                            <span class="lznd-badge lznd-badge--tipo"><?php echo esc_html( $j_tipo_name ); ?></span>
                        <?php endif; ?>
                        <?php if ( $j_tecnico ) : ?>
                            <span class="lznd-job-row__tech"><?php echo esc_html( $j_tecnico ); ?></span>
                        <?php endif; ?>
                        <?php if ( $j_fecha ) : ?>
                            <span class="lznd-job-row__date"><?php echo esc_html( date_i18n( 'd/m/Y', strtotime( $j_fecha ) ) ); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="lznd-job-row__progress">
                    <div class="lznd-progress-bar-wrap">
                        <div class="lznd-progress-bar" style="width:<?php echo esc_attr( $j_pct ); ?>%"></div>
                    </div>
                    <span class="lznd-job-row__prog-text"><?php echo esc_html( "{$j_hechas}/{$j_total}" ); ?> tareas</span>
                </div>
                <div class="lznd-job-row__status">
                    <span class="lznd-badge lznd-badge--estado lznd-badge--estado-<?php echo esc_attr( $j_estado ); ?>">
                        <?php echo esc_html( $estado_labels[ $j_estado ] ?? ucfirst( $j_estado ) ); ?>
                    </span>
                </div>
                <div class="lznd-job-row__action">
                    <a href="<?php echo esc_url( add_query_arg( 'actuacion_id', $j_id, get_permalink() ) ); ?>"
                       class="lznd-btn lznd-btn--sm lznd-btn--primary">
                        <?php esc_html_e( 'Continuar', 'lezned-maintenance' ); ?>
                    </a>
                </div>
            </div>
            <?php endforeach; ?>

        </div>
        <?php endif; ?>
    </div>

</div><!-- .lznd-dash -->

<?php else : ?>

<!-- ============================================================
     FORM VIEW
     ============================================================ -->

<a href="<?php echo esc_url( remove_query_arg( 'actuacion_id' ) ); ?>" class="lznd-back-link">
    &#8592; <?php esc_html_e( 'Volver al panel', 'lezned-maintenance' ); ?>
</a>

<?php if ( $ya_enviado ) : ?>

    <div class="lznd-card lznd-card--success">
        <div class="lznd-success-icon">&#10003;</div>
        <h2 class="lznd-card__title"><?php esc_html_e( 'Parte enviado', 'lezned-maintenance' ); ?></h2>
        <p class="lznd-card__subtitle"><?php esc_html_e( 'Este parte ya ha sido enviado y está cerrado.', 'lezned-maintenance' ); ?></p>
        <?php if ( $pre_proyecto ) : ?>
            <p><strong><?php esc_html_e( 'Proyecto:', 'lezned-maintenance' ); ?></strong>
                <?php echo esc_html( get_the_title( $pre_proyecto->ID ) ); ?></p>
        <?php endif; ?>
        <?php if ( ! empty( $fecha_inicio ) ) : ?>
            <p><strong><?php esc_html_e( 'Fecha:', 'lezned-maintenance' ); ?></strong>
                <?php echo esc_html( date_i18n( 'd/m/Y H:i', strtotime( $fecha_inicio ) ) ); ?></p>
        <?php endif; ?>
    </div>

<?php else : ?>

    <!-- Header -->
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
                        'en_curso'  => __( 'En curso',  'lezned-maintenance' ),
                        'enviado'   => __( 'Enviado',   'lezned-maintenance' ),
                        'cerrado'   => __( 'Cerrado',   'lezned-maintenance' ),
                    ];
                    echo esc_html( $estados_labels[ $pre_estado ] ?? ucfirst( $pre_estado ) );
                    ?>
                </span>
                <span class="lznd-badge lznd-badge--prioridad lznd-badge--prioridad-<?php echo esc_attr( $prioridad ); ?>">
                    <?php
                    $prio_labels = [
                        'baja'    => __( 'Baja',    'lezned-maintenance' ),
                        'normal'  => __( 'Normal',  'lezned-maintenance' ),
                        'alta'    => __( 'Alta',    'lezned-maintenance' ),
                        'urgente' => __( 'Urgente', 'lezned-maintenance' ),
                    ];
                    echo esc_html( $prio_labels[ $prioridad ] ?? $prioridad );
                    ?>
                </span>
            </div>
        </div>
    </div>

    <!-- Info grid -->
    <div class="lznd-info-grid" id="lznd-info-grid">
        <div class="lznd-info-item">
            <span class="lznd-info-item__label"><?php esc_html_e( 'Técnico', 'lezned-maintenance' ); ?></span>
            <span class="lznd-info-item__value" id="lznd-info-tecnico"><?php echo esc_html( $tecnico_name ); ?></span>
        </div>
        <div class="lznd-info-item">
            <span class="lznd-info-item__label"><?php esc_html_e( 'Fecha programada', 'lezned-maintenance' ); ?></span>
            <span class="lznd-info-item__value" id="lznd-info-fecha-prog">
                <?php echo ! empty( $fecha_prog ) ? esc_html( date_i18n( 'd/m/Y', strtotime( $fecha_prog ) ) ) : '—'; ?>
            </span>
        </div>
        <div class="lznd-info-item">
            <span class="lznd-info-item__label"><?php esc_html_e( 'Inicio', 'lezned-maintenance' ); ?></span>
            <span class="lznd-info-item__value" id="lznd-info-fecha-inicio">
                <?php echo ! empty( $fecha_inicio ) ? esc_html( date_i18n( 'd/m/Y H:i', strtotime( $fecha_inicio ) ) ) : '—'; ?>
            </span>
        </div>
        <div class="lznd-info-item">
            <span class="lznd-info-item__label"><?php esc_html_e( 'Progreso', 'lezned-maintenance' ); ?></span>
            <span class="lznd-info-item__value">
                <span id="lznd-hechas"><?php echo esc_html( $hechas_tasks ); ?></span>/<span id="lznd-total"><?php echo esc_html( $total_tasks ); ?></span>
            </span>
            <div class="lznd-progress-bar-wrap">
                <div class="lznd-progress-bar" id="lznd-progress-bar"
                     style="width:<?php echo $total_tasks > 0 ? esc_attr( round( $hechas_tasks / $total_tasks * 100 ) ) : 0; ?>%"></div>
            </div>
        </div>
    </div>

    <!-- Checklist -->
    <div class="lznd-card" id="lznd-checklist-card">
        <h3 class="lznd-card__section-title"><?php esc_html_e( 'Tareas', 'lezned-maintenance' ); ?></h3>
        <ul class="lznd-task-list" id="lznd-task-list">
            <?php foreach ( $pre_tareas as $tarea ) :
                $is_done = ! empty( $tarea['completada'] );
            ?>
            <li class="lznd-task-item <?php echo $is_done ? 'lznd-task-item--done' : ''; ?>"
                data-task-id="<?php echo esc_attr( $tarea['id'] ); ?>">
                <button type="button" class="lznd-task-toggle" aria-label="<?php esc_attr_e( 'Marcar tarea', 'lezned-maintenance' ); ?>">
                    <span class="lznd-task-check"><?php echo $is_done ? '&#10003;' : ''; ?></span>
                </button>
                <span class="lznd-task-text"><?php echo esc_html( $tarea['texto'] ); ?></span>
                <span class="lznd-task-time"><?php echo ( $is_done && ! empty( $tarea['completada_en'] ) ) ? esc_html( date_i18n( 'H:i', strtotime( $tarea['completada_en'] ) ) ) : ''; ?></span>
                <button type="button" class="lznd-task-delete" aria-label="<?php esc_attr_e( 'Eliminar tarea', 'lezned-maintenance' ); ?>">&#10005;</button>
            </li>
            <?php endforeach; ?>
        </ul>
        <div class="lznd-add-task-row">
            <input type="text" id="lznd-new-task-input" class="lznd-input"
                   placeholder="<?php esc_attr_e( 'Nueva tarea...', 'lezned-maintenance' ); ?>" maxlength="200">
            <button type="button" id="lznd-add-task-btn" class="lznd-btn lznd-btn--secondary">
                <?php esc_html_e( 'Añadir', 'lezned-maintenance' ); ?>
            </button>
        </div>
    </div>

    <!-- Observaciones -->
    <div class="lznd-card" id="lznd-observaciones-card">
        <h3 class="lznd-card__section-title"><?php esc_html_e( 'Observaciones', 'lezned-maintenance' ); ?></h3>
        <textarea id="lznd-observaciones" class="lznd-textarea" rows="4"
                  placeholder="<?php esc_attr_e( 'Escribe tus observaciones aquí...', 'lezned-maintenance' ); ?>"><?php echo esc_textarea( $pre_observ ); ?></textarea>
        <span class="lznd-autosave-status" id="lznd-observ-status"></span>
    </div>

    <!-- Fotos -->
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

    <!-- Firma técnico -->
    <div class="lznd-card lznd-firma-card lznd-firma--tecnico" id="lznd-firma-tecnico-card">
        <h3 class="lznd-card__section-title lznd-firma-title lznd-firma-title--tecnico">
            <?php esc_html_e( 'Firma del técnico', 'lezned-maintenance' ); ?>
        </h3>
        <?php if ( ! empty( $firma_tec_url ) ) : ?>
        <div class="lznd-firma-saved" id="lznd-firma-tec-saved">
            <img src="<?php echo esc_url( $firma_tec_url ); ?>" alt="" class="lznd-firma-preview">
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
                <input type="text" id="lznd-nombre-firmante-tecnico" class="lznd-input"
                       placeholder="<?php esc_attr_e( 'Nombre del técnico', 'lezned-maintenance' ); ?>"
                       value="<?php echo esc_attr( ! empty( $nombre_fir_tec ) ? $nombre_fir_tec : $current_user->display_name ); ?>">
                <button type="button" class="lznd-btn lznd-btn--ghost lznd-btn--sm" id="lznd-firma-tec-clear">
                    <?php esc_html_e( 'Borrar', 'lezned-maintenance' ); ?>
                </button>
                <button type="button" class="lznd-btn lznd-btn--primary lznd-btn--sm" id="lznd-firma-tec-save">
                    <?php esc_html_e( 'Guardar firma', 'lezned-maintenance' ); ?>
                </button>
            </div>
        </div>
    </div>

    <!-- Firma cliente -->
    <div class="lznd-card lznd-firma-card lznd-firma--cliente" id="lznd-firma-cliente-card">
        <h3 class="lznd-card__section-title lznd-firma-title lznd-firma-title--cliente">
            <?php esc_html_e( 'Firma del cliente', 'lezned-maintenance' ); ?>
        </h3>
        <?php if ( ! empty( $firma_cli_url ) ) : ?>
        <div class="lznd-firma-saved" id="lznd-firma-cli-saved">
            <img src="<?php echo esc_url( $firma_cli_url ); ?>" alt="" class="lznd-firma-preview">
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
                <input type="text" id="lznd-nombre-firmante-cliente" class="lznd-input"
                       placeholder="<?php esc_attr_e( 'Nombre del cliente', 'lezned-maintenance' ); ?>"
                       value="<?php echo esc_attr( $nombre_fir_cli ?? '' ); ?>">
                <button type="button" class="lznd-btn lznd-btn--ghost lznd-btn--sm" id="lznd-firma-cli-clear">
                    <?php esc_html_e( 'Borrar', 'lezned-maintenance' ); ?>
                </button>
                <button type="button" class="lznd-btn lznd-btn--primary lznd-btn--sm" id="lznd-firma-cli-save">
                    <?php esc_html_e( 'Guardar firma', 'lezned-maintenance' ); ?>
                </button>
            </div>
        </div>
    </div>

    <!-- Enviar -->
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

<?php endif; // $ya_enviado ?>

<?php endif; // $actuacion_id ?>

</div><!-- .lznd-app -->

<!-- Confirmation modal (only used in form view) -->
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
