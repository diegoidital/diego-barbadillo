<?php
/**
 * LZND_Meta_Boxes — Meta box registration and save handlers for all CPTs.
 *
 * @package Lezned_Maintenance
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LZND_Meta_Boxes {

	// ─── Bootstrap ───────────────────────────────────────────────────────────

	public static function init() {
		add_action( 'add_meta_boxes',             array( __CLASS__, 'register' ) );
		add_action( 'save_post_lznd_cliente',     array( __CLASS__, 'save_cliente' ),   10, 2 );
		add_action( 'save_post_lznd_proyecto',    array( __CLASS__, 'save_proyecto' ),  10, 2 );
		add_action( 'save_post_lznd_actuacion',   array( __CLASS__, 'save_actuacion' ), 10, 2 );
		add_action( 'save_post_lznd_tarea',       array( __CLASS__, 'save_tarea' ),     10, 2 );
	}

	// ─── Register All Meta Boxes ─────────────────────────────────────────────

	public static function register() {
		// ── lznd_cliente ────────────────────────────────────────────────────
		add_meta_box(
			'lznd_box_cliente',
			__( 'Datos del cliente', 'lezned-maintenance' ),
			array( __CLASS__, 'box_cliente' ),
			'lznd_cliente',
			'normal',
			'high'
		);

		// ── lznd_proyecto ───────────────────────────────────────────────────
		add_meta_box(
			'lznd_box_proyecto_info',
			__( 'Información del proyecto', 'lezned-maintenance' ),
			array( __CLASS__, 'box_proyecto_info' ),
			'lznd_proyecto',
			'normal',
			'high'
		);

		add_meta_box(
			'lznd_box_proyecto_recurrencia',
			__( 'Recurrencia', 'lezned-maintenance' ),
			array( __CLASS__, 'box_proyecto_recurrencia' ),
			'lznd_proyecto',
			'side',
			'high'
		);

		add_meta_box(
			'lznd_box_proyecto_link',
			__( 'Ver proyecto →', 'lezned-maintenance' ),
			array( __CLASS__, 'box_proyecto_link' ),
			'lznd_proyecto',
			'side',
			'default'
		);

		// ── lznd_actuacion ──────────────────────────────────────────────────
		add_meta_box(
			'lznd_box_actuacion',
			__( 'Datos de la actuación', 'lezned-maintenance' ),
			array( __CLASS__, 'box_actuacion' ),
			'lznd_actuacion',
			'normal',
			'high'
		);

		add_meta_box(
			'lznd_box_actuacion_link',
			__( 'Acceso frontend', 'lezned-maintenance' ),
			array( __CLASS__, 'box_actuacion_link' ),
			'lznd_actuacion',
			'side',
			'high'
		);

		// ── lznd_tarea ──────────────────────────────────────────────────────
		add_meta_box(
			'lznd_box_tarea',
			__( 'Configuración de tarea', 'lezned-maintenance' ),
			array( __CLASS__, 'box_tarea' ),
			'lznd_tarea',
			'normal',
			'high'
		);
	}

	// ─── Helper: render a text field row ─────────────────────────────────────

	private static function text_row( $label, $name, $value, $type = 'text', $placeholder = '' ) {
		$id = esc_attr( $name );
		printf(
			'<div class="lznd-row">
				<div class="lznd-col lznd-col-label"><label for="%1$s">%2$s</label></div>
				<div class="lznd-col lznd-col-field">
					<input type="%3$s" id="%1$s" name="%1$s" value="%4$s" placeholder="%5$s" class="widefat" />
				</div>
			</div>',
			$id,
			esc_html( $label ),
			esc_attr( $type ),
			esc_attr( $value ),
			esc_attr( $placeholder )
		);
	}

	// ─── Helper: render a textarea row ───────────────────────────────────────

	private static function textarea_row( $label, $name, $value, $rows = 4 ) {
		$id = esc_attr( $name );
		printf(
			'<div class="lznd-row">
				<div class="lznd-col lznd-col-label"><label for="%1$s">%2$s</label></div>
				<div class="lznd-col lznd-col-field">
					<textarea id="%1$s" name="%1$s" rows="%3$d" class="widefat">%4$s</textarea>
				</div>
			</div>',
			$id,
			esc_html( $label ),
			(int) $rows,
			esc_textarea( $value )
		);
	}

	// ─── Helper: render a select row ─────────────────────────────────────────

	private static function select_row( $label, $name, $options, $current, $extra_class = '' ) {
		$id = esc_attr( $name );
		$out  = '<div class="lznd-row">';
		$out .= '<div class="lznd-col lznd-col-label"><label for="' . $id . '">' . esc_html( $label ) . '</label></div>';
		$out .= '<div class="lznd-col lznd-col-field">';
		$out .= '<select id="' . $id . '" name="' . $id . '" class="widefat ' . esc_attr( $extra_class ) . '">';
		foreach ( $options as $val => $text ) {
			$out .= '<option value="' . esc_attr( $val ) . '"' . selected( $current, $val, false ) . '>' . esc_html( $text ) . '</option>';
		}
		$out .= '</select>';
		$out .= '</div></div>';
		echo $out; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	// ─── Box: Cliente ────────────────────────────────────────────────────────

	public static function box_cliente( $post ) {
		wp_nonce_field( 'lznd_save_cliente', 'lznd_cliente_nonce' );

		$nombre   = get_post_meta( $post->ID, '_lznd_nombre_contacto', true );
		$telefono = get_post_meta( $post->ID, '_lznd_telefono',         true );
		$email    = get_post_meta( $post->ID, '_lznd_email',            true );
		$notas    = get_post_meta( $post->ID, '_lznd_notas',            true );

		echo '<div class="lznd-form">';
		self::text_row(     __( 'Nombre de contacto', 'lezned-maintenance' ), '_lznd_nombre_contacto', $nombre );
		self::text_row(     __( 'Teléfono',           'lezned-maintenance' ), '_lznd_telefono',         $telefono, 'tel' );
		self::text_row(     __( 'Email',              'lezned-maintenance' ), '_lznd_email',            $email,    'email' );
		self::textarea_row( __( 'Notas',              'lezned-maintenance' ), '_lznd_notas',            $notas );
		echo '</div>';
	}

	// ─── Box: Proyecto — Info ─────────────────────────────────────────────────

	public static function box_proyecto_info( $post ) {
		wp_nonce_field( 'lznd_save_proyecto', 'lznd_proyecto_nonce' );

		$cliente_id       = get_post_meta( $post->ID, '_lznd_cliente_id',       true );
		$contacto_nombre  = get_post_meta( $post->ID, '_lznd_contacto_nombre',  true );
		$contacto_tel     = get_post_meta( $post->ID, '_lznd_contacto_telefono', true );
		$contacto_email   = get_post_meta( $post->ID, '_lznd_contacto_email',   true );
		$notas            = get_post_meta( $post->ID, '_lznd_notas',            true );

		// Build clients list
		$clientes = get_posts( array(
			'post_type'      => 'lznd_cliente',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'orderby'        => 'title',
			'order'          => 'ASC',
		) );

		echo '<div class="lznd-form">';

		// Client selector
		echo '<div class="lznd-row">';
		echo '<div class="lznd-col lznd-col-label"><label for="_lznd_cliente_id">' . esc_html__( 'Cliente', 'lezned-maintenance' ) . '</label></div>';
		echo '<div class="lznd-col lznd-col-field">';
		echo '<select id="_lznd_cliente_id" name="_lznd_cliente_id" class="widefat">';
		echo '<option value="">' . esc_html__( '— Seleccionar cliente —', 'lezned-maintenance' ) . '</option>';
		foreach ( $clientes as $c ) {
			echo '<option value="' . esc_attr( $c->ID ) . '"' . selected( $cliente_id, $c->ID, false ) . '>' . esc_html( $c->post_title ) . '</option>';
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Auto-rellenar desde cliente: los datos de contacto se rellenarán automáticamente al seleccionar un cliente.', 'lezned-maintenance' ) . '</p>';
		echo '</div></div>';

		// Contact override fields
		echo '<div class="lznd-section-title"><strong>' . esc_html__( 'Datos de contacto del proyecto', 'lezned-maintenance' ) . '</strong></div>';
		self::text_row( __( 'Nombre contacto',    'lezned-maintenance' ), '_lznd_contacto_nombre',   $contacto_nombre );
		self::text_row( __( 'Teléfono contacto',  'lezned-maintenance' ), '_lznd_contacto_telefono', $contacto_tel, 'tel' );
		self::text_row( __( 'Email contacto',     'lezned-maintenance' ), '_lznd_contacto_email',    $contacto_email, 'email' );

		// Notes
		self::textarea_row( __( 'Notas', 'lezned-maintenance' ), '_lznd_notas', $notas );

		echo '</div>';
	}

	// ─── Box: Proyecto — Recurrencia ─────────────────────────────────────────

	public static function box_proyecto_recurrencia( $post ) {
		$recurrencia  = get_post_meta( $post->ID, '_lznd_recurrencia',   true );
		$fecha_prox   = get_post_meta( $post->ID, '_lznd_fecha_proxima', true );
		$auto_crear   = get_post_meta( $post->ID, '_lznd_auto_crear',    true );
		$aviso_email  = get_post_meta( $post->ID, '_lznd_aviso_email',   true );

		if ( ! $recurrencia ) {
			$recurrencia = 'ninguna';
		}

		$options = array(
			'ninguna'   => __( 'Sin recurrencia',  'lezned-maintenance' ),
			'semestral' => __( 'Cada 6 meses',     'lezned-maintenance' ),
			'anual'     => __( 'Anual',             'lezned-maintenance' ),
		);

		$show_rec_opts = ( 'ninguna' !== $recurrencia ) ? '' : 'display:none;';

		echo '<div class="lznd-form">';

		// Recurrence select
		echo '<div class="lznd-row">';
		echo '<label for="_lznd_recurrencia"><strong>' . esc_html__( 'Recurrencia', 'lezned-maintenance' ) . '</strong></label>';
		echo '<select id="_lznd_recurrencia" name="_lznd_recurrencia" class="widefat" style="margin-top:6px;">';
		foreach ( $options as $val => $text ) {
			echo '<option value="' . esc_attr( $val ) . '"' . selected( $recurrencia, $val, false ) . '>' . esc_html( $text ) . '</option>';
		}
		echo '</select>';
		echo '</div>';

		// Conditional fields (hidden when ninguna)
		echo '<div id="lznd-rec-opts" style="' . esc_attr( $show_rec_opts ) . '">';

		echo '<div class="lznd-row" style="margin-top:10px;">';
		echo '<label for="_lznd_fecha_proxima">' . esc_html__( 'Próxima fecha', 'lezned-maintenance' ) . '</label>';
		echo '<input type="date" id="_lznd_fecha_proxima" name="_lznd_fecha_proxima" value="' . esc_attr( $fecha_prox ) . '" class="widefat" style="margin-top:4px;" />';
		echo '</div>';

		echo '<div class="lznd-row" style="margin-top:10px;">';
		echo '<label>';
		echo '<input type="checkbox" name="_lznd_auto_crear" value="1"' . checked( $auto_crear, '1', false ) . ' />';
		echo ' ' . esc_html__( 'Crear actuación automáticamente', 'lezned-maintenance' );
		echo '</label>';
		echo '</div>';

		echo '<div class="lznd-row" style="margin-top:10px;">';
		echo '<label for="_lznd_aviso_email">' . esc_html__( 'Email para avisos', 'lezned-maintenance' ) . '</label>';
		echo '<input type="email" id="_lznd_aviso_email" name="_lznd_aviso_email" value="' . esc_attr( $aviso_email ) . '" class="widefat" style="margin-top:4px;" />';
		echo '</div>';

		echo '</div>'; // #lznd-rec-opts

		echo '</div>'; // .lznd-form

		// Inline JS to toggle rec opts
		?>
		<script>
		(function($){
			$('#_lznd_recurrencia').on('change', function(){
				if ( $(this).val() !== 'ninguna' ) {
					$('#lznd-rec-opts').slideDown();
				} else {
					$('#lznd-rec-opts').slideUp();
				}
			});
		})(jQuery);
		</script>
		<?php
	}

	// ─── Box: Proyecto — Link ────────────────────────────────────────────────

	public static function box_proyecto_link( $post ) {
		if ( 'publish' !== $post->post_status ) {
			echo '<p class="description">' . esc_html__( 'Guarda el proyecto para ver el detalle.', 'lezned-maintenance' ) . '</p>';
			return;
		}

		$url = admin_url( 'admin.php?page=lznd-project-detail&id=' . $post->ID );
		echo '<p>';
		printf(
			'<a href="%s" class="button button-primary" target="_blank">%s</a>',
			esc_url( $url ),
			esc_html__( 'Ver detalle del proyecto', 'lezned-maintenance' )
		);
		echo '</p>';
	}

	// ─── Box: Actuación ──────────────────────────────────────────────────────

	public static function box_actuacion( $post ) {
		wp_nonce_field( 'lznd_save_actuacion', 'lznd_actuacion_nonce' );

		$proyecto_id   = get_post_meta( $post->ID, '_lznd_proyecto_id',  true );
		$tipo_term_id  = get_post_meta( $post->ID, '_lznd_tipo',          true );
		$tecnico_id    = get_post_meta( $post->ID, '_lznd_tecnico',       true );
		$estado        = get_post_meta( $post->ID, '_lznd_estado',        true );
		$prioridad     = get_post_meta( $post->ID, '_lznd_prioridad',     true );
		$fecha_prog    = get_post_meta( $post->ID, '_lznd_fecha_prog',    true );
		$fecha_inicio  = get_post_meta( $post->ID, '_lznd_fecha_inicio',  true );
		$fecha_fin     = get_post_meta( $post->ID, '_lznd_fecha_fin',     true );
		$observaciones = get_post_meta( $post->ID, '_lznd_observaciones', true );

		if ( ! $estado ) {
			$estado = 'pendiente';
		}
		if ( ! $prioridad ) {
			$prioridad = 'normal';
		}

		// Build proyecto options
		$proyectos = get_posts( array(
			'post_type'      => 'lznd_proyecto',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'orderby'        => 'title',
			'order'          => 'ASC',
		) );

		// Build tipo options
		$tipos = get_terms( array(
			'taxonomy'   => 'lznd_tipo',
			'hide_empty' => false,
		) );

		// Build user options
		$users = get_users( array(
			'orderby' => 'display_name',
			'order'   => 'ASC',
		) );

		$estado_options = array(
			'pendiente'   => __( '⏳ Pendiente',    'lezned-maintenance' ),
			'en_proceso'  => __( '🔧 En proceso',   'lezned-maintenance' ),
			'completado'  => __( '✅ Completado',   'lezned-maintenance' ),
			'enviado'     => __( '📤 Enviado',      'lezned-maintenance' ),
			'cancelado'   => __( '❌ Cancelado',    'lezned-maintenance' ),
		);

		$prioridad_options = array(
			'baja'    => __( 'Baja',    'lezned-maintenance' ),
			'normal'  => __( 'Normal',  'lezned-maintenance' ),
			'alta'    => __( 'Alta',    'lezned-maintenance' ),
			'urgente' => __( 'Urgente', 'lezned-maintenance' ),
		);

		echo '<div class="lznd-form">';

		// Proyecto
		echo '<div class="lznd-row">';
		echo '<div class="lznd-col lznd-col-label"><label for="_lznd_proyecto_id">' . esc_html__( 'Proyecto', 'lezned-maintenance' ) . '</label></div>';
		echo '<div class="lznd-col lznd-col-field">';
		echo '<select id="_lznd_proyecto_id" name="_lznd_proyecto_id" class="widefat">';
		echo '<option value="">' . esc_html__( '— Seleccionar proyecto —', 'lezned-maintenance' ) . '</option>';
		foreach ( $proyectos as $p ) {
			echo '<option value="' . esc_attr( $p->ID ) . '"' . selected( $proyecto_id, $p->ID, false ) . '>' . esc_html( $p->post_title ) . '</option>';
		}
		echo '</select>';
		echo '</div></div>';

		// Tipo
		echo '<div class="lznd-row">';
		echo '<div class="lznd-col lznd-col-label"><label for="_lznd_tipo">' . esc_html__( 'Tipo de trabajo', 'lezned-maintenance' ) . '</label></div>';
		echo '<div class="lznd-col lznd-col-field">';
		echo '<select id="_lznd_tipo" name="_lznd_tipo" class="widefat">';
		echo '<option value="">' . esc_html__( '— Seleccionar tipo —', 'lezned-maintenance' ) . '</option>';
		if ( ! is_wp_error( $tipos ) ) {
			foreach ( $tipos as $term ) {
				echo '<option value="' . esc_attr( $term->term_id ) . '"' . selected( $tipo_term_id, $term->term_id, false ) . '>' . esc_html( $term->name ) . '</option>';
			}
		}
		echo '</select>';
		echo '</div></div>';

		// Técnico
		echo '<div class="lznd-row">';
		echo '<div class="lznd-col lznd-col-label"><label for="_lznd_tecnico">' . esc_html__( 'Técnico', 'lezned-maintenance' ) . '</label></div>';
		echo '<div class="lznd-col lznd-col-field">';
		echo '<select id="_lznd_tecnico" name="_lznd_tecnico" class="widefat">';
		echo '<option value="">' . esc_html__( '— Seleccionar técnico —', 'lezned-maintenance' ) . '</option>';
		foreach ( $users as $user ) {
			echo '<option value="' . esc_attr( $user->ID ) . '"' . selected( $tecnico_id, $user->ID, false ) . '>' . esc_html( $user->display_name ) . '</option>';
		}
		echo '</select>';
		echo '</div></div>';

		// Estado
		self::select_row( __( 'Estado', 'lezned-maintenance' ), '_lznd_estado', $estado_options, $estado );

		// Prioridad
		self::select_row( __( 'Prioridad', 'lezned-maintenance' ), '_lznd_prioridad', $prioridad_options, $prioridad );

		// Fecha programada
		echo '<div class="lznd-row">';
		echo '<div class="lznd-col lznd-col-label"><label for="_lznd_fecha_prog">' . esc_html__( 'Fecha programada', 'lezned-maintenance' ) . '</label></div>';
		echo '<div class="lznd-col lznd-col-field"><input type="date" id="_lznd_fecha_prog" name="_lznd_fecha_prog" value="' . esc_attr( $fecha_prog ) . '" class="widefat" /></div>';
		echo '</div>';

		// Fecha inicio
		echo '<div class="lznd-row">';
		echo '<div class="lznd-col lznd-col-label"><label for="_lznd_fecha_inicio">' . esc_html__( 'Inicio real', 'lezned-maintenance' ) . '</label></div>';
		echo '<div class="lznd-col lznd-col-field"><input type="datetime-local" id="_lznd_fecha_inicio" name="_lznd_fecha_inicio" value="' . esc_attr( $fecha_inicio ) . '" class="widefat" /></div>';
		echo '</div>';

		// Fecha fin
		echo '<div class="lznd-row">';
		echo '<div class="lznd-col lznd-col-label"><label for="_lznd_fecha_fin">' . esc_html__( 'Fin real', 'lezned-maintenance' ) . '</label></div>';
		echo '<div class="lznd-col lznd-col-field"><input type="datetime-local" id="_lznd_fecha_fin" name="_lznd_fecha_fin" value="' . esc_attr( $fecha_fin ) . '" class="widefat" /></div>';
		echo '</div>';

		// Observaciones
		self::textarea_row( __( 'Observaciones', 'lezned-maintenance' ), '_lznd_observaciones', $observaciones, 5 );

		echo '<p class="description">' . esc_html__( 'Las tareas y el checklist se gestionan desde el frontend.', 'lezned-maintenance' ) . '</p>';

		echo '</div>'; // .lznd-form
	}

	// ─── Box: Actuación — Frontend Link ──────────────────────────────────────

	public static function box_actuacion_link( $post ) {
		if ( 'publish' !== $post->post_status ) {
			echo '<p class="description">' . esc_html__( 'Guarda la actuación para obtener el enlace frontend.', 'lezned-maintenance' ) . '</p>';
			return;
		}

		$page_id = get_option( 'lznd_page_id' );
		if ( ! $page_id ) {
			echo '<p class="description">' . esc_html__( 'No se encontró la página frontend.', 'lezned-maintenance' ) . '</p>';
			return;
		}

		$url = add_query_arg( 'actuacion_id', $post->ID, get_permalink( $page_id ) );
		echo '<p>';
		printf(
			'<a href="%s" class="button" target="_blank">%s</a>',
			esc_url( $url ),
			esc_html__( 'Abrir parte frontend', 'lezned-maintenance' )
		);
		echo '</p>';
		echo '<p class="description">' . esc_html__( 'Enlace directo al parte de mantenimiento.', 'lezned-maintenance' ) . '</p>';
		echo '<p><input type="text" value="' . esc_url( $url ) . '" class="widefat" readonly onclick="this.select();" /></p>';
	}

	// ─── Box: Tarea — Field type + taxonomy ─────────────────────────────────

	public static function box_tarea( $post ) {
		wp_nonce_field( 'lznd_save_tarea', 'lznd_tarea_nonce' );

		$tipo_campo   = get_post_meta( $post->ID, '_lznd_tipo_campo', true ) ?: 'texto';
		$opciones_raw = get_post_meta( $post->ID, '_lznd_opciones',   true ) ?: '';
		$opciones     = $opciones_raw ? json_decode( $opciones_raw, true ) : [];
		if ( ! is_array( $opciones ) ) {
			$opciones = [];
		}

		$terms         = get_terms( array( 'taxonomy' => 'lznd_tipo', 'hide_empty' => false ) );
		$current_terms = wp_get_post_terms( $post->ID, 'lznd_tipo', array( 'fields' => 'ids' ) );

		echo '<div class="lznd-form">';

		// ── Tipo de campo ────────────────────────────────────────────────────
		echo '<div class="lznd-row">';
		echo '<div class="lznd-col lznd-col-label"><strong>' . esc_html__( 'Tipo de campo', 'lezned-maintenance' ) . '</strong></div>';
		echo '<div class="lznd-col lznd-col-field">';
		echo '<div class="lznd-tipo-campo-opts" style="display:flex;gap:16px;margin-top:4px;">';
		$tipo_campo_options = array(
			'texto'       => __( 'Texto',       'lezned-maintenance' ),
			'seleccionar' => __( 'Seleccionar', 'lezned-maintenance' ),
			'radio'       => __( 'Radio',       'lezned-maintenance' ),
		);
		foreach ( $tipo_campo_options as $val => $label ) {
			$checked = checked( $tipo_campo, $val, false );
			printf(
				'<label><input type="radio" name="_lznd_tipo_campo" value="%s"%s /> %s</label>',
				esc_attr( $val ),
				$checked,
				esc_html( $label )
			);
		}
		echo '</div>';
		echo '</div></div>';

		// ── Options builder (shown for seleccionar/radio) ────────────────────
		$show_opts = in_array( $tipo_campo, array( 'seleccionar', 'radio' ), true ) ? '' : 'display:none;';
		echo '<div id="lznd-tarea-opciones-wrap" style="' . esc_attr( $show_opts ) . '">';
		echo '<div class="lznd-row" style="margin-top:10px;">';
		echo '<div class="lznd-col lznd-col-label"><strong>' . esc_html__( 'Opciones', 'lezned-maintenance' ) . '</strong></div>';
		echo '<div class="lznd-col lznd-col-field">';
		echo '<ul id="lznd-tarea-opciones-list" style="margin:0 0 6px 0;padding:0;list-style:none;">';
		foreach ( $opciones as $opt ) {
			printf(
				'<li class="lznd-opcion-item" style="display:flex;gap:4px;margin-bottom:4px;">
					<input type="text" name="_lznd_opciones[]" value="%s" class="widefat" style="flex:1;" />
					<button type="button" class="button lznd-remove-opcion" title="%s">&#10005;</button>
				</li>',
				esc_attr( $opt ),
				esc_attr__( 'Eliminar opción', 'lezned-maintenance' )
			);
		}
		echo '</ul>';
		echo '<button type="button" class="button" id="lznd-add-opcion">' . esc_html__( '+ Añadir opción', 'lezned-maintenance' ) . '</button>';
		echo '</div></div>';
		echo '</div>'; // #lznd-tarea-opciones-wrap

		// ── Taxonomy assignment ──────────────────────────────────────────────
		echo '<div class="lznd-row" style="margin-top:14px;">';
		echo '<div class="lznd-col lznd-col-label"><strong>' . esc_html__( 'Tipos de trabajo', 'lezned-maintenance' ) . '</strong></div>';
		echo '<div class="lznd-col lznd-col-field">';
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			echo '<p class="description">' . esc_html__( 'No hay tipos de trabajo disponibles.', 'lezned-maintenance' ) . '</p>';
		} else {
			echo '<p class="description" style="margin-bottom:6px;">' . esc_html__( 'Asignar esta tarea a uno o más tipos de trabajo:', 'lezned-maintenance' ) . '</p>';
			echo '<div class="lznd-checkboxes">';
			foreach ( $terms as $term ) {
				$checked = in_array( $term->term_id, (array) $current_terms, true ) ? ' checked="checked"' : '';
				printf(
					'<label class="lznd-checkbox-label"><input type="checkbox" name="_lznd_tipo_terms[]" value="%d"%s /> %s</label>',
					(int) $term->term_id,
					$checked,
					esc_html( $term->name )
				);
			}
			echo '</div>';
		}
		echo '</div></div>';

		echo '</div>'; // .lznd-form
	}

	// ─── Save: Cliente ────────────────────────────────────────────────────────

	public static function save_cliente( $post_id, $post ) {
		if ( ! isset( $_POST['lznd_cliente_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( $_POST['lznd_cliente_nonce'], 'lznd_save_cliente' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$fields = array(
			'_lznd_nombre_contacto' => 'text',
			'_lznd_telefono'        => 'text',
			'_lznd_email'           => 'email',
			'_lznd_notas'           => 'textarea',
		);

		foreach ( $fields as $field => $type ) {
			if ( ! isset( $_POST[ $field ] ) ) {
				continue;
			}
			switch ( $type ) {
				case 'email':
					$value = sanitize_email( $_POST[ $field ] );
					break;
				case 'textarea':
					$value = sanitize_textarea_field( $_POST[ $field ] );
					break;
				default:
					$value = sanitize_text_field( $_POST[ $field ] );
					break;
			}
			update_post_meta( $post_id, $field, $value );
		}
	}

	// ─── Save: Proyecto ───────────────────────────────────────────────────────

	public static function save_proyecto( $post_id, $post ) {
		if ( ! isset( $_POST['lznd_proyecto_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( $_POST['lznd_proyecto_nonce'], 'lznd_save_proyecto' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Text fields
		$text_fields = array(
			'_lznd_contacto_nombre',
			'_lznd_contacto_telefono',
			'_lznd_recurrencia',
			'_lznd_fecha_proxima',
			'_lznd_aviso_email',
			'_lznd_notas',
		);

		foreach ( $text_fields as $field ) {
			if ( isset( $_POST[ $field ] ) ) {
				if ( '_lznd_aviso_email' === $field ) {
					update_post_meta( $post_id, $field, sanitize_email( $_POST[ $field ] ) );
				} elseif ( '_lznd_notas' === $field ) {
					update_post_meta( $post_id, $field, sanitize_textarea_field( $_POST[ $field ] ) );
				} else {
					update_post_meta( $post_id, $field, sanitize_text_field( $_POST[ $field ] ) );
				}
			}
		}

		// Email override
		if ( isset( $_POST['_lznd_contacto_email'] ) ) {
			update_post_meta( $post_id, '_lznd_contacto_email', sanitize_email( $_POST['_lznd_contacto_email'] ) );
		}

		// Integer: cliente_id
		if ( isset( $_POST['_lznd_cliente_id'] ) ) {
			update_post_meta( $post_id, '_lznd_cliente_id', absint( $_POST['_lznd_cliente_id'] ) );
		}

		// Checkbox: auto_crear
		$auto_crear = isset( $_POST['_lznd_auto_crear'] ) ? '1' : '0';
		update_post_meta( $post_id, '_lznd_auto_crear', $auto_crear );

		// Validate recurrencia
		$valid_recurrencias = array( 'ninguna', 'semestral', 'anual' );
		if ( isset( $_POST['_lznd_recurrencia'] ) && in_array( $_POST['_lznd_recurrencia'], $valid_recurrencias, true ) ) {
			update_post_meta( $post_id, '_lznd_recurrencia', $_POST['_lznd_recurrencia'] );
		}
	}

	// ─── Save: Actuación ─────────────────────────────────────────────────────

	public static function save_actuacion( $post_id, $post ) {
		if ( ! isset( $_POST['lznd_actuacion_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( $_POST['lznd_actuacion_nonce'], 'lznd_save_actuacion' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Integer fields
		$int_fields = array( '_lznd_proyecto_id', '_lznd_tipo', '_lznd_tecnico' );
		foreach ( $int_fields as $field ) {
			if ( isset( $_POST[ $field ] ) ) {
				update_post_meta( $post_id, $field, absint( $_POST[ $field ] ) );
			}
		}

		// Validated estado
		$valid_estados = array( 'pendiente', 'en_proceso', 'completado', 'enviado', 'cancelado' );
		if ( isset( $_POST['_lznd_estado'] ) && in_array( $_POST['_lznd_estado'], $valid_estados, true ) ) {
			update_post_meta( $post_id, '_lznd_estado', $_POST['_lznd_estado'] );
		}

		// Validated prioridad
		$valid_prioridades = array( 'baja', 'normal', 'alta', 'urgente' );
		if ( isset( $_POST['_lznd_prioridad'] ) && in_array( $_POST['_lznd_prioridad'], $valid_prioridades, true ) ) {
			update_post_meta( $post_id, '_lznd_prioridad', $_POST['_lznd_prioridad'] );
		}

		// Date/datetime fields
		$date_fields = array( '_lznd_fecha_prog', '_lznd_fecha_inicio', '_lznd_fecha_fin' );
		foreach ( $date_fields as $field ) {
			if ( isset( $_POST[ $field ] ) ) {
				update_post_meta( $post_id, $field, sanitize_text_field( $_POST[ $field ] ) );
			}
		}

		// Textarea
		if ( isset( $_POST['_lznd_observaciones'] ) ) {
			update_post_meta( $post_id, '_lznd_observaciones', sanitize_textarea_field( $_POST['_lznd_observaciones'] ) );
		}

		// Sync tipo as taxonomy term
		$tipo_id = isset( $_POST['_lznd_tipo'] ) ? absint( $_POST['_lznd_tipo'] ) : 0;
		if ( $tipo_id ) {
			wp_set_object_terms( $post_id, $tipo_id, 'lznd_tipo' );
		} else {
			wp_set_object_terms( $post_id, array(), 'lznd_tipo' );
		}
	}

	// ─── Save: Tarea ──────────────────────────────────────────────────────────

	public static function save_tarea( $post_id, $post ) {
		if ( ! isset( $_POST['lznd_tarea_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( $_POST['lznd_tarea_nonce'], 'lznd_save_tarea' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Tipo de campo
		$valid_tipos = array( 'texto', 'seleccionar', 'radio' );
		$tipo_campo  = ( isset( $_POST['_lznd_tipo_campo'] ) && in_array( $_POST['_lznd_tipo_campo'], $valid_tipos, true ) )
			? $_POST['_lznd_tipo_campo'] : 'texto';
		update_post_meta( $post_id, '_lznd_tipo_campo', $tipo_campo );

		// Opciones (only relevant for seleccionar/radio)
		$opciones = array();
		if ( in_array( $tipo_campo, array( 'seleccionar', 'radio' ), true ) ) {
			if ( isset( $_POST['_lznd_opciones'] ) && is_array( $_POST['_lznd_opciones'] ) ) {
				foreach ( $_POST['_lznd_opciones'] as $opt ) {
					$clean = sanitize_text_field( $opt );
					if ( $clean !== '' ) {
						$opciones[] = $clean;
					}
				}
			}
		}
		update_post_meta( $post_id, '_lznd_opciones', wp_json_encode( $opciones, JSON_UNESCAPED_UNICODE ) );

		// Taxonomy terms
		$term_ids = array();
		if ( isset( $_POST['_lznd_tipo_terms'] ) && is_array( $_POST['_lznd_tipo_terms'] ) ) {
			$term_ids = array_map( 'absint', $_POST['_lznd_tipo_terms'] );
			$term_ids = array_filter( $term_ids );
		}

		wp_set_object_terms( $post_id, $term_ids, 'lznd_tipo' );
	}
}

LZND_Meta_Boxes::init();
