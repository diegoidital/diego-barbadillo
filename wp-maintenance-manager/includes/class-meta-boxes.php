<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WPMM_Meta_Boxes {

	public static function init() {
		add_action( 'add_meta_boxes', [ __CLASS__, 'register' ] );
		add_action( 'save_post_wpmm_parte', [ __CLASS__, 'save' ] );
		add_action( 'save_post_wpmm_localizacion', [ __CLASS__, 'save_location' ] );
	}

	public static function get_estados() {
		return [
			'pendiente'    => '⏳ Pendiente',
			'en_curso'     => '🔧 En curso',
			'completado'   => '✅ Completado',
			'cancelado'    => '❌ Cancelado',
			'en_espera'    => '⏸ En espera',
		];
	}

	public static function get_prioridades() {
		return [
			'baja'    => '🟢 Baja',
			'normal'  => '🔵 Normal',
			'alta'    => '🟠 Alta',
			'urgente' => '🔴 Urgente',
		];
	}

	public static function register() {
		add_meta_box(
			'wpmm_datos_parte',
			'Datos del Parte',
			[ __CLASS__, 'render_parte' ],
			'wpmm_parte',
			'normal',
			'high'
		);

		add_meta_box(
			'wpmm_datos_localizacion',
			'Información de la Localización',
			[ __CLASS__, 'render_location' ],
			'wpmm_localizacion',
			'normal',
			'high'
		);
	}

	public static function render_parte( $post ) {
		wp_nonce_field( 'wpmm_save_parte', 'wpmm_nonce' );

		$loc_id      = get_post_meta( $post->ID, '_wpmm_localizacion', true );
		$estado      = get_post_meta( $post->ID, '_wpmm_estado', true ) ?: 'pendiente';
		$prioridad   = get_post_meta( $post->ID, '_wpmm_prioridad', true ) ?: 'normal';
		$fecha_ini   = get_post_meta( $post->ID, '_wpmm_fecha_inicio', true );
		$fecha_fin   = get_post_meta( $post->ID, '_wpmm_fecha_fin', true );
		$tecnico     = get_post_meta( $post->ID, '_wpmm_tecnico', true );
		$descripcion = get_post_meta( $post->ID, '_wpmm_descripcion', true );
		$materiales  = get_post_meta( $post->ID, '_wpmm_materiales', true );
		$horas       = get_post_meta( $post->ID, '_wpmm_horas', true );
		$observ      = get_post_meta( $post->ID, '_wpmm_observaciones', true );

		$locations = get_posts( [
			'post_type'      => 'wpmm_localizacion',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'orderby'        => 'title',
			'order'          => 'ASC',
		] );

		$users = get_users( [ 'orderby' => 'display_name' ] );
		?>
		<div class="wpmm-form">

			<div class="wpmm-row">
				<div class="wpmm-col">
					<label class="wpmm-label" for="wpmm_localizacion">Localización / Proyecto <span class="required">*</span></label>
					<select id="wpmm_localizacion" name="wpmm_localizacion" class="wpmm-select widefat" required>
						<option value="">— Selecciona una localización —</option>
						<?php foreach ( $locations as $loc ) : ?>
							<option value="<?php echo esc_attr( $loc->ID ); ?>" <?php selected( $loc_id, $loc->ID ); ?>>
								<?php echo esc_html( $loc->post_title ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<?php if ( empty( $locations ) ) : ?>
						<p class="wpmm-notice">No hay localizaciones creadas. <a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=wpmm_localizacion' ) ); ?>">Crear una ahora</a>.</p>
					<?php endif; ?>
				</div>

				<div class="wpmm-col">
					<label class="wpmm-label" for="wpmm_tecnico">Técnico responsable</label>
					<select id="wpmm_tecnico" name="wpmm_tecnico" class="wpmm-select widefat">
						<option value="">— Sin asignar —</option>
						<?php foreach ( $users as $user ) : ?>
							<option value="<?php echo esc_attr( $user->ID ); ?>" <?php selected( $tecnico, $user->ID ); ?>>
								<?php echo esc_html( $user->display_name ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>

			<div class="wpmm-row">
				<div class="wpmm-col">
					<label class="wpmm-label" for="wpmm_estado">Estado</label>
					<select id="wpmm_estado" name="wpmm_estado" class="wpmm-select widefat">
						<?php foreach ( self::get_estados() as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $estado, $key ); ?>>
								<?php echo esc_html( $label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="wpmm-col">
					<label class="wpmm-label" for="wpmm_prioridad">Prioridad</label>
					<select id="wpmm_prioridad" name="wpmm_prioridad" class="wpmm-select widefat">
						<?php foreach ( self::get_prioridades() as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $prioridad, $key ); ?>>
								<?php echo esc_html( $label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>

			<div class="wpmm-row">
				<div class="wpmm-col">
					<label class="wpmm-label" for="wpmm_fecha_inicio">Fecha de inicio</label>
					<input type="date" id="wpmm_fecha_inicio" name="wpmm_fecha_inicio" class="wpmm-input widefat" value="<?php echo esc_attr( $fecha_ini ); ?>">
				</div>
				<div class="wpmm-col">
					<label class="wpmm-label" for="wpmm_fecha_fin">Fecha de finalización</label>
					<input type="date" id="wpmm_fecha_fin" name="wpmm_fecha_fin" class="wpmm-input widefat" value="<?php echo esc_attr( $fecha_fin ); ?>">
				</div>
				<div class="wpmm-col wpmm-col--small">
					<label class="wpmm-label" for="wpmm_horas">Horas empleadas</label>
					<input type="number" id="wpmm_horas" name="wpmm_horas" class="wpmm-input widefat" value="<?php echo esc_attr( $horas ); ?>" min="0" step="0.5" placeholder="0">
				</div>
			</div>

			<div class="wpmm-row">
				<div class="wpmm-col wpmm-col--full">
					<label class="wpmm-label" for="wpmm_descripcion">Descripción del trabajo <span class="required">*</span></label>
					<textarea id="wpmm_descripcion" name="wpmm_descripcion" class="wpmm-textarea widefat" rows="4" placeholder="Describe el trabajo realizado o a realizar..."><?php echo esc_textarea( $descripcion ); ?></textarea>
				</div>
			</div>

			<div class="wpmm-row">
				<div class="wpmm-col wpmm-col--full">
					<label class="wpmm-label" for="wpmm_materiales">Materiales utilizados</label>
					<textarea id="wpmm_materiales" name="wpmm_materiales" class="wpmm-textarea widefat" rows="3" placeholder="Lista los materiales o repuestos utilizados..."><?php echo esc_textarea( $materiales ); ?></textarea>
				</div>
			</div>

			<div class="wpmm-row">
				<div class="wpmm-col wpmm-col--full">
					<label class="wpmm-label" for="wpmm_observaciones">Observaciones</label>
					<textarea id="wpmm_observaciones" name="wpmm_observaciones" class="wpmm-textarea widefat" rows="3" placeholder="Observaciones adicionales, incidencias, próximas revisiones..."><?php echo esc_textarea( $observ ); ?></textarea>
				</div>
			</div>

		</div>
		<?php
	}

	public static function render_location( $post ) {
		wp_nonce_field( 'wpmm_save_location', 'wpmm_loc_nonce' );

		$direccion   = get_post_meta( $post->ID, '_wpmm_loc_direccion', true );
		$ciudad      = get_post_meta( $post->ID, '_wpmm_loc_ciudad', true );
		$cp          = get_post_meta( $post->ID, '_wpmm_loc_cp', true );
		$contacto    = get_post_meta( $post->ID, '_wpmm_loc_contacto', true );
		$telefono    = get_post_meta( $post->ID, '_wpmm_loc_telefono', true );
		$email       = get_post_meta( $post->ID, '_wpmm_loc_email', true );
		$notas       = get_post_meta( $post->ID, '_wpmm_loc_notas', true );
		?>
		<div class="wpmm-form">
			<div class="wpmm-row">
				<div class="wpmm-col">
					<label class="wpmm-label" for="wpmm_loc_direccion">Dirección</label>
					<input type="text" id="wpmm_loc_direccion" name="wpmm_loc_direccion" class="wpmm-input widefat" value="<?php echo esc_attr( $direccion ); ?>" placeholder="Calle, número...">
				</div>
				<div class="wpmm-col">
					<label class="wpmm-label" for="wpmm_loc_ciudad">Ciudad / Municipio</label>
					<input type="text" id="wpmm_loc_ciudad" name="wpmm_loc_ciudad" class="wpmm-input widefat" value="<?php echo esc_attr( $ciudad ); ?>">
				</div>
				<div class="wpmm-col wpmm-col--small">
					<label class="wpmm-label" for="wpmm_loc_cp">Código Postal</label>
					<input type="text" id="wpmm_loc_cp" name="wpmm_loc_cp" class="wpmm-input widefat" value="<?php echo esc_attr( $cp ); ?>" maxlength="5">
				</div>
			</div>

			<div class="wpmm-row">
				<div class="wpmm-col">
					<label class="wpmm-label" for="wpmm_loc_contacto">Persona de contacto</label>
					<input type="text" id="wpmm_loc_contacto" name="wpmm_loc_contacto" class="wpmm-input widefat" value="<?php echo esc_attr( $contacto ); ?>">
				</div>
				<div class="wpmm-col">
					<label class="wpmm-label" for="wpmm_loc_telefono">Teléfono</label>
					<input type="tel" id="wpmm_loc_telefono" name="wpmm_loc_telefono" class="wpmm-input widefat" value="<?php echo esc_attr( $telefono ); ?>">
				</div>
				<div class="wpmm-col">
					<label class="wpmm-label" for="wpmm_loc_email">Email</label>
					<input type="email" id="wpmm_loc_email" name="wpmm_loc_email" class="wpmm-input widefat" value="<?php echo esc_attr( $email ); ?>">
				</div>
			</div>

			<div class="wpmm-row">
				<div class="wpmm-col wpmm-col--full">
					<label class="wpmm-label" for="wpmm_loc_notas">Notas de la localización</label>
					<textarea id="wpmm_loc_notas" name="wpmm_loc_notas" class="wpmm-textarea widefat" rows="3" placeholder="Accesos, horarios, instrucciones especiales..."><?php echo esc_textarea( $notas ); ?></textarea>
				</div>
			</div>
		</div>
		<?php
	}

	public static function save( $post_id ) {
		if ( ! isset( $_POST['wpmm_nonce'] ) || ! wp_verify_nonce( $_POST['wpmm_nonce'], 'wpmm_save_parte' ) ) return;
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
		if ( ! current_user_can( 'edit_post', $post_id ) ) return;

		$fields = [
			'_wpmm_localizacion'  => [ 'key' => 'wpmm_localizacion', 'type' => 'int' ],
			'_wpmm_tecnico'       => [ 'key' => 'wpmm_tecnico', 'type' => 'int' ],
			'_wpmm_estado'        => [ 'key' => 'wpmm_estado', 'type' => 'key' ],
			'_wpmm_prioridad'     => [ 'key' => 'wpmm_prioridad', 'type' => 'key' ],
			'_wpmm_fecha_inicio'  => [ 'key' => 'wpmm_fecha_inicio', 'type' => 'date' ],
			'_wpmm_fecha_fin'     => [ 'key' => 'wpmm_fecha_fin', 'type' => 'date' ],
			'_wpmm_horas'         => [ 'key' => 'wpmm_horas', 'type' => 'float' ],
			'_wpmm_descripcion'   => [ 'key' => 'wpmm_descripcion', 'type' => 'textarea' ],
			'_wpmm_materiales'    => [ 'key' => 'wpmm_materiales', 'type' => 'textarea' ],
			'_wpmm_observaciones' => [ 'key' => 'wpmm_observaciones', 'type' => 'textarea' ],
		];

		foreach ( $fields as $meta_key => $config ) {
			$raw = $_POST[ $config['key'] ] ?? '';
			switch ( $config['type'] ) {
				case 'int':
					$value = absint( $raw );
					break;
				case 'float':
					$value = (float) $raw;
					break;
				case 'key':
					$value = sanitize_key( $raw );
					break;
				case 'date':
					$value = sanitize_text_field( $raw );
					break;
				case 'textarea':
					$value = sanitize_textarea_field( $raw );
					break;
				default:
					$value = sanitize_text_field( $raw );
			}
			update_post_meta( $post_id, $meta_key, $value );
		}
	}

	public static function save_location( $post_id ) {
		if ( ! isset( $_POST['wpmm_loc_nonce'] ) || ! wp_verify_nonce( $_POST['wpmm_loc_nonce'], 'wpmm_save_location' ) ) return;
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
		if ( ! current_user_can( 'edit_post', $post_id ) ) return;

		$fields = [
			'_wpmm_loc_direccion' => 'wpmm_loc_direccion',
			'_wpmm_loc_ciudad'    => 'wpmm_loc_ciudad',
			'_wpmm_loc_cp'        => 'wpmm_loc_cp',
			'_wpmm_loc_contacto'  => 'wpmm_loc_contacto',
			'_wpmm_loc_telefono'  => 'wpmm_loc_telefono',
			'_wpmm_loc_email'     => 'wpmm_loc_email',
			'_wpmm_loc_notas'     => 'wpmm_loc_notas',
		];

		foreach ( $fields as $meta_key => $post_key ) {
			$raw = $_POST[ $post_key ] ?? '';
			if ( $post_key === '_wpmm_loc_notas' ) {
				$value = sanitize_textarea_field( $raw );
			} elseif ( $post_key === 'wpmm_loc_email' ) {
				$value = sanitize_email( $raw );
			} else {
				$value = sanitize_text_field( $raw );
			}
			update_post_meta( $post_id, $meta_key, $value );
		}
	}
}

WPMM_Meta_Boxes::init();
