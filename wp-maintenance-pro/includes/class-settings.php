<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WMPRO_Settings {

	public static function init() {
		add_action( 'admin_menu',    [ __CLASS__, 'add_page' ] );
		add_action( 'admin_post_wmpro_save_settings', [ __CLASS__, 'save' ] );
	}

	public static function add_page() {
		add_submenu_page(
			'wmpro-menu',
			'Ajustes',
			'⚙️ Ajustes',
			'manage_options',
			'wmpro-settings',
			[ __CLASS__, 'render' ]
		);
	}

	public static function render() {
		$s = get_option( 'wmpro_settings', [] );
		$page_id = get_option( 'wmpro_parte_page_id' );
		?>
		<div class="wrap">
			<h1>⚙️ Ajustes — WP Maintenance Pro</h1>
			<?php if ( isset( $_GET['saved'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p>Ajustes guardados correctamente.</p></div>
			<?php endif; ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'wmpro_settings_save', 'wmpro_settings_nonce' ); ?>
				<input type="hidden" name="action" value="wmpro_save_settings">

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="admin_email">Email del administrador</label></th>
						<td>
							<input type="email" id="admin_email" name="admin_email" class="regular-text"
								   value="<?php echo esc_attr( $s['admin_email'] ?? get_option( 'admin_email' ) ); ?>">
							<p class="description">Recibe los avisos de mantenimiento y copias de los partes.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="from_name">Nombre del remitente</label></th>
						<td>
							<input type="text" id="from_name" name="from_name" class="regular-text"
								   value="<?php echo esc_attr( $s['from_name'] ?? get_option( 'blogname' ) ); ?>">
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="aviso_dias">Días de antelación para avisos</label></th>
						<td>
							<input type="number" id="aviso_dias" name="aviso_dias" class="small-text"
								   value="<?php echo esc_attr( $s['aviso_dias'] ?? 7 ); ?>" min="1" max="30">
							<p class="description">Se enviará un email de aviso X días antes de la fecha programada.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="enviar_cliente">Enviar parte al cliente</label></th>
						<td>
							<label>
								<input type="checkbox" id="enviar_cliente" name="enviar_cliente" value="1"
									<?php checked( $s['enviar_cliente'] ?? 1, 1 ); ?>>
								Enviar copia del informe al email de contacto del proyecto
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row">Página frontend del parte</th>
						<td>
							<?php if ( $page_id ) : ?>
								<a href="<?php echo esc_url( get_permalink( $page_id ) ); ?>" target="_blank">
									<?php echo esc_html( get_the_title( $page_id ) ); ?>
								</a>
								— ID: <?php echo intval( $page_id ); ?>
								<p class="description">Página creada automáticamente con el shortcode <code>[wmpro_parte]</code>.</p>
							<?php else : ?>
								<p class="description">No configurada. Crea una página con el shortcode <code>[wmpro_parte]</code>.</p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row">Próximos cron jobs</th>
						<td>
							<p><strong>Check diario:</strong>
								<?php $ts = wp_next_scheduled( 'wmpro_daily_check' ); echo $ts ? date_i18n( 'd/m/Y H:i', $ts ) . ' (hora del servidor)' : 'No programado'; ?>
							</p>
							<p><strong>Resumen semanal:</strong>
								<?php $ts = wp_next_scheduled( 'wmpro_weekly_summary' ); echo $ts ? date_i18n( 'd/m/Y H:i', $ts ) . ' (hora del servidor)' : 'No programado'; ?>
							</p>
							<p class="description">ℹ️ Para garantizar la ejecución puntual, configura un cron del sistema apuntando a <code>wp-cron.php</code>.</p>
						</td>
					</tr>
				</table>

				<?php submit_button( 'Guardar ajustes' ); ?>
			</form>
		</div>
		<?php
	}

	public static function save() {
		check_admin_referer( 'wmpro_settings_save', 'wmpro_settings_nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'No autorizado' );

		update_option( 'wmpro_settings', [
			'admin_email'    => sanitize_email( $_POST['admin_email'] ?? '' ),
			'from_name'      => sanitize_text_field( $_POST['from_name'] ?? '' ),
			'aviso_dias'     => intval( $_POST['aviso_dias'] ?? 7 ),
			'enviar_cliente' => isset( $_POST['enviar_cliente'] ) ? 1 : 0,
		] );

		wp_safe_redirect( add_query_arg( 'saved', 1, admin_url( 'admin.php?page=wmpro-settings' ) ) );
		exit;
	}
}

WMPRO_Settings::init();
