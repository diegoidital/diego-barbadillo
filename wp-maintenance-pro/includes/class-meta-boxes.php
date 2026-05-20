<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WMPRO_Meta_Boxes {

	public static function init() {
		add_action( 'add_meta_boxes',         [ __CLASS__, 'register' ] );
		add_action( 'save_post_wmpro_proyecto', [ __CLASS__, 'save_proyecto' ] );
		add_action( 'save_post_wmpro_parte',    [ __CLASS__, 'save_parte' ] );
	}

	public static function register() {
		add_meta_box( 'wmpro_proyecto_info',        'Información de la Localización', [ __CLASS__, 'box_proyecto_info' ],      'wmpro_proyecto', 'normal', 'high' );
		add_meta_box( 'wmpro_proyecto_recurrencia', 'Recurrencia y Programación',     [ __CLASS__, 'box_proyecto_recurrencia' ], 'wmpro_proyecto', 'side',   'high' );
		add_meta_box( 'wmpro_proyecto_tareas',      'Plantilla de Tareas (Checklist)',[ __CLASS__, 'box_proyecto_tareas' ],     'wmpro_proyecto', 'normal', 'default' );
		add_meta_box( 'wmpro_parte_datos',          'Datos del Parte',                [ __CLASS__, 'box_parte_datos' ],         'wmpro_parte',    'normal', 'high' );
		add_meta_box( 'wmpro_parte_acceso',         'Acceso Frontend',                [ __CLASS__, 'box_parte_acceso' ],        'wmpro_parte',    'side',   'high' );
	}

	/* ------------------------------------------------------------------ */
	/* PROYECTO: Información                                                */
	/* ------------------------------------------------------------------ */
	public static function box_proyecto_info( $post ) {
		wp_nonce_field( 'wmpro_save_proyecto', 'wmpro_proyecto_nonce' );

		$f = function( $key ) use ( $post ) {
			return get_post_meta( $post->ID, $key, true );
		};
		?>
		<div class="wmpro-meta-form">
			<div class="wmpro-row">
				<div class="wmpro-col">
					<label>Dirección</label>
					<input type="text" name="_wmpro_direccion" value="<?php echo esc_attr( $f( '_wmpro_direccion' ) ); ?>" class="widefat" placeholder="Calle, número...">
				</div>
				<div class="wmpro-col">
					<label>Ciudad</label>
					<input type="text" name="_wmpro_ciudad" value="<?php echo esc_attr( $f( '_wmpro_ciudad' ) ); ?>" class="widefat">
				</div>
				<div class="wmpro-col wmpro-col--xs">
					<label>CP</label>
					<input type="text" name="_wmpro_cp" value="<?php echo esc_attr( $f( '_wmpro_cp' ) ); ?>" class="widefat" maxlength="5">
				</div>
			</div>
			<div class="wmpro-row">
				<div class="wmpro-col">
					<label>Contacto</label>
					<input type="text" name="_wmpro_contacto" value="<?php echo esc_attr( $f( '_wmpro_contacto' ) ); ?>" class="widefat">
				</div>
				<div class="wmpro-col">
					<label>Teléfono</label>
					<input type="tel" name="_wmpro_telefono" value="<?php echo esc_attr( $f( '_wmpro_telefono' ) ); ?>" class="widefat">
				</div>
				<div class="wmpro-col">
					<label>Email contacto</label>
					<input type="email" name="_wmpro_email_contacto" value="<?php echo esc_attr( $f( '_wmpro_email_contacto' ) ); ?>" class="widefat" placeholder="Para enviar el parte firmado">
				</div>
			</div>
			<div class="wmpro-row">
				<div class="wmpro-col">
					<label>Prioridad por defecto</label>
					<select name="_wmpro_prioridad_defecto" class="widefat">
						<?php foreach ( WMPRO_CPT_Parte::get_prioridades() as $k => $v ) : ?>
							<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $f( '_wmpro_prioridad_defecto' ), $k ); ?>><?php echo esc_html( $v ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="wmpro-col">
					<label>Estado del proyecto</label>
					<select name="_wmpro_estado_proyecto" class="widefat">
						<option value="activo"   <?php selected( $f( '_wmpro_estado_proyecto' ), 'activo' ); ?>>✅ Activo</option>
						<option value="inactivo" <?php selected( $f( '_wmpro_estado_proyecto' ), 'inactivo' ); ?>>⏸ Inactivo</option>
					</select>
				</div>
			</div>
			<div class="wmpro-row">
				<div class="wmpro-col wmpro-col--full">
					<label>Notas del proyecto</label>
					<textarea name="_wmpro_notas" class="widefat" rows="3" placeholder="Instrucciones de acceso, observaciones generales..."><?php echo esc_textarea( $f( '_wmpro_notas' ) ); ?></textarea>
				</div>
			</div>
		</div>
		<?php
	}

	/* ------------------------------------------------------------------ */
	/* PROYECTO: Recurrencia                                                */
	/* ------------------------------------------------------------------ */
	public static function box_proyecto_recurrencia( $post ) {
		$recurrencia   = get_post_meta( $post->ID, '_wmpro_recurrencia', true )   ?: 'ninguna';
		$fecha_proxima = get_post_meta( $post->ID, '_wmpro_fecha_proxima', true );
		$aviso_email   = get_post_meta( $post->ID, '_wmpro_aviso_email', true )   ?: get_option( 'admin_email' );
		$auto_crear    = get_post_meta( $post->ID, '_wmpro_auto_crear', true );
		?>
		<div class="wmpro-meta-form">
			<p>
				<label><strong>Frecuencia</strong></label>
				<select name="_wmpro_recurrencia" class="widefat" id="wmpro-recurrencia">
					<option value="ninguna"   <?php selected( $recurrencia, 'ninguna' ); ?>>Sin recurrencia</option>
					<option value="semanal"   <?php selected( $recurrencia, 'semanal' ); ?>>Semanal</option>
					<option value="quincenal" <?php selected( $recurrencia, 'quincenal' ); ?>>Quincenal</option>
					<option value="mensual"   <?php selected( $recurrencia, 'mensual' ); ?>>Mensual</option>
					<option value="trimestral"<?php selected( $recurrencia, 'trimestral' ); ?>>Trimestral</option>
					<option value="semestral" <?php selected( $recurrencia, 'semestral' ); ?>>Semestral</option>
					<option value="anual"     <?php selected( $recurrencia, 'anual' ); ?>>Anual</option>
				</select>
			</p>
			<div id="wmpro-recurrencia-opts" <?php echo $recurrencia === 'ninguna' ? 'style="display:none"' : ''; ?>>
				<p>
					<label><strong>Próxima fecha programada</strong></label>
					<input type="date" name="_wmpro_fecha_proxima" class="widefat" value="<?php echo esc_attr( $fecha_proxima ); ?>">
				</p>
				<p>
					<label><strong>Email para avisos</strong></label>
					<input type="email" name="_wmpro_aviso_email" class="widefat" value="<?php echo esc_attr( $aviso_email ); ?>">
					<small>Aviso 7 días antes de la fecha programada.</small>
				</p>
				<p>
					<label>
						<input type="checkbox" name="_wmpro_auto_crear" value="1" <?php checked( $auto_crear, '1' ); ?>>
						Crear parte automáticamente al llegar la fecha
					</label>
				</p>
			</div>
		</div>
		<?php
	}

	/* ------------------------------------------------------------------ */
	/* PROYECTO: Plantilla de tareas                                        */
	/* ------------------------------------------------------------------ */
	public static function box_proyecto_tareas( $post ) {
		$tareas = get_post_meta( $post->ID, '_wmpro_tareas_template', true ) ?: [];
		?>
		<div class="wmpro-meta-form">
			<p class="description">Define las tareas que se copiarán automáticamente a cada nuevo parte de este proyecto.</p>
			<ul id="wmpro-template-tasks" class="wmpro-task-list wmpro-sortable">
				<?php foreach ( $tareas as $i => $tarea ) : ?>
					<li class="wmpro-task-item" data-index="<?php echo $i; ?>">
						<span class="wmpro-drag-handle dashicons dashicons-move"></span>
						<input type="text" name="_wmpro_tareas_template[<?php echo $i; ?>][texto]"
							   value="<?php echo esc_attr( $tarea['texto'] ); ?>"
							   class="widefat wmpro-task-input" placeholder="Descripción de la tarea...">
						<button type="button" class="button wmpro-remove-task" title="Eliminar tarea">✕</button>
					</li>
				<?php endforeach; ?>
			</ul>
			<button type="button" id="wmpro-add-template-task" class="button button-secondary" style="margin-top:8px">
				+ Añadir tarea a la plantilla
			</button>
		</div>
		<?php
	}

	/* ------------------------------------------------------------------ */
	/* PARTE: Datos                                                         */
	/* ------------------------------------------------------------------ */
	public static function box_parte_datos( $post ) {
		wp_nonce_field( 'wmpro_save_parte', 'wmpro_parte_nonce' );

		$f = fn( $k ) => get_post_meta( $post->ID, $k, true );

		$proyectos = get_posts( [ 'post_type' => 'wmpro_proyecto', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC', 'post_status' => 'publish' ] );
		$users     = get_users( [ 'orderby' => 'display_name' ] );
		?>
		<div class="wmpro-meta-form">
			<div class="wmpro-row">
				<div class="wmpro-col">
					<label>Proyecto / Localización *</label>
					<select name="_wmpro_proyecto_id" class="widefat" required>
						<option value="">— Selecciona proyecto —</option>
						<?php foreach ( $proyectos as $p ) : ?>
							<option value="<?php echo $p->ID; ?>" <?php selected( $f( '_wmpro_proyecto_id' ), $p->ID ); ?>><?php echo esc_html( $p->post_title ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="wmpro-col">
					<label>Técnico asignado</label>
					<select name="_wmpro_tecnico" class="widefat">
						<option value="">— Sin asignar —</option>
						<?php foreach ( $users as $u ) : ?>
							<option value="<?php echo $u->ID; ?>" <?php selected( $f( '_wmpro_tecnico' ), $u->ID ); ?>><?php echo esc_html( $u->display_name ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>
			<div class="wmpro-row">
				<div class="wmpro-col">
					<label>Estado</label>
					<select name="_wmpro_estado" class="widefat">
						<?php foreach ( WMPRO_CPT_Parte::get_estados() as $k => $v ) : ?>
							<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $f( '_wmpro_estado' ) ?: 'pendiente', $k ); ?>><?php echo esc_html( $v ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="wmpro-col">
					<label>Prioridad</label>
					<select name="_wmpro_prioridad" class="widefat">
						<?php foreach ( WMPRO_CPT_Parte::get_prioridades() as $k => $v ) : ?>
							<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $f( '_wmpro_prioridad' ) ?: 'normal', $k ); ?>><?php echo esc_html( $v ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>
			<div class="wmpro-row">
				<div class="wmpro-col">
					<label>Fecha programada</label>
					<input type="date" name="_wmpro_fecha_prog" class="widefat" value="<?php echo esc_attr( $f( '_wmpro_fecha_prog' ) ); ?>">
				</div>
				<div class="wmpro-col">
					<label>Fecha inicio real</label>
					<input type="datetime-local" name="_wmpro_fecha_inicio" class="widefat" value="<?php echo esc_attr( $f( '_wmpro_fecha_inicio' ) ); ?>">
				</div>
				<div class="wmpro-col">
					<label>Fecha fin real</label>
					<input type="datetime-local" name="_wmpro_fecha_fin" class="widefat" value="<?php echo esc_attr( $f( '_wmpro_fecha_fin' ) ); ?>">
				</div>
			</div>
			<div class="wmpro-row">
				<div class="wmpro-col wmpro-col--full">
					<label>Observaciones del parte</label>
					<textarea name="_wmpro_observaciones" class="widefat" rows="3"><?php echo esc_textarea( $f( '_wmpro_observaciones' ) ); ?></textarea>
				</div>
			</div>
		</div>
		<?php
	}

	/* ------------------------------------------------------------------ */
	/* PARTE: Acceso frontend                                               */
	/* ------------------------------------------------------------------ */
	public static function box_parte_acceso( $post ) {
		if ( $post->post_status !== 'publish' ) {
			echo '<p class="description">Guarda el parte para obtener el enlace frontend.</p>';
			return;
		}
		$page_id = get_option( 'wmpro_parte_page_id' );
		$url     = add_query_arg( 'parte_id', $post->ID, get_permalink( $page_id ) );
		$estado  = get_post_meta( $post->ID, '_wmpro_estado', true ) ?: 'pendiente';
		$estados = WMPRO_CPT_Parte::get_estados();
		?>
		<p><strong>Estado:</strong> <?php echo esc_html( $estados[ $estado ] ?? $estado ); ?></p>
		<p>
			<strong>Enlace para el técnico:</strong><br>
			<a href="<?php echo esc_url( $url ); ?>" target="_blank" class="button button-primary" style="margin-top:5px">
				Abrir parte →
			</a>
		</p>
		<p class="description" style="margin-top:8px">Envía este enlace al técnico para que rellene el parte desde su dispositivo.</p>
		<?php
	}

	/* ------------------------------------------------------------------ */
	/* Save: Proyecto                                                        */
	/* ------------------------------------------------------------------ */
	public static function save_proyecto( $post_id ) {
		if ( ! isset( $_POST['wmpro_proyecto_nonce'] ) || ! wp_verify_nonce( $_POST['wmpro_proyecto_nonce'], 'wmpro_save_proyecto' ) ) return;
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
		if ( ! current_user_can( 'edit_post', $post_id ) ) return;

		$text_fields = [ '_wmpro_direccion', '_wmpro_ciudad', '_wmpro_cp', '_wmpro_contacto', '_wmpro_telefono', '_wmpro_aviso_email', '_wmpro_notas', '_wmpro_estado_proyecto', '_wmpro_prioridad_defecto' ];
		foreach ( $text_fields as $key ) {
			$val = sanitize_text_field( $_POST[ $key ] ?? '' );
			update_post_meta( $post_id, $key, $val );
		}

		update_post_meta( $post_id, '_wmpro_email_contacto', sanitize_email( $_POST['_wmpro_email_contacto'] ?? '' ) );
		update_post_meta( $post_id, '_wmpro_recurrencia',    sanitize_key( $_POST['_wmpro_recurrencia'] ?? 'ninguna' ) );
		update_post_meta( $post_id, '_wmpro_fecha_proxima',  sanitize_text_field( $_POST['_wmpro_fecha_proxima'] ?? '' ) );
		update_post_meta( $post_id, '_wmpro_auto_crear',     isset( $_POST['_wmpro_auto_crear'] ) ? '1' : '0' );

		// Plantilla de tareas
		$raw_tareas = $_POST['_wmpro_tareas_template'] ?? [];
		$tareas = [];
		foreach ( $raw_tareas as $t ) {
			$texto = sanitize_text_field( $t['texto'] ?? '' );
			if ( $texto ) {
				$tareas[] = [ 'texto' => $texto ];
			}
		}
		update_post_meta( $post_id, '_wmpro_tareas_template', $tareas );

		// Reset aviso enviado si cambia la fecha
		update_post_meta( $post_id, '_wmpro_aviso_enviado', '0' );
	}

	/* ------------------------------------------------------------------ */
	/* Save: Parte                                                           */
	/* ------------------------------------------------------------------ */
	public static function save_parte( $post_id ) {
		if ( ! isset( $_POST['wmpro_parte_nonce'] ) || ! wp_verify_nonce( $_POST['wmpro_parte_nonce'], 'wmpro_save_parte' ) ) return;
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
		if ( ! current_user_can( 'edit_post', $post_id ) ) return;

		$int_fields  = [ '_wmpro_proyecto_id', '_wmpro_tecnico' ];
		$text_fields = [ '_wmpro_estado', '_wmpro_prioridad', '_wmpro_fecha_prog', '_wmpro_fecha_inicio', '_wmpro_fecha_fin' ];

		foreach ( $int_fields  as $k ) update_post_meta( $post_id, $k, absint( $_POST[ $k ] ?? 0 ) );
		foreach ( $text_fields as $k ) update_post_meta( $post_id, $k, sanitize_text_field( $_POST[ $k ] ?? '' ) );

		update_post_meta( $post_id, '_wmpro_observaciones', sanitize_textarea_field( $_POST['_wmpro_observaciones'] ?? '' ) );

		// Si aún no tiene tareas, copiar del proyecto
		$tareas_existentes = get_post_meta( $post_id, '_wmpro_tareas', true );
		if ( empty( $tareas_existentes ) ) {
			$proyecto_id = absint( $_POST['_wmpro_proyecto_id'] ?? 0 );
			if ( $proyecto_id ) {
				$template = get_post_meta( $proyecto_id, '_wmpro_tareas_template', true ) ?: [];
				$tareas   = [];
				foreach ( $template as $t ) {
					$tareas[] = [ 'id' => uniqid( 'task_' ), 'texto' => $t['texto'], 'completada' => false, 'completada_en' => null ];
				}
				update_post_meta( $post_id, '_wmpro_tareas', $tareas );
			}
		}
	}
}

WMPRO_Meta_Boxes::init();
