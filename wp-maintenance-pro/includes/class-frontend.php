<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WMPRO_Frontend {

	public static function init() {
		add_shortcode( 'wmpro_parte',      [ __CLASS__, 'shortcode_parte' ] );
		add_shortcode( 'wmpro_proyectos',  [ __CLASS__, 'shortcode_proyectos' ] );
		add_action( 'wp_enqueue_scripts',  [ __CLASS__, 'enqueue' ] );
	}

	public static function enqueue() {
		if ( ! is_page() ) return;
		global $post;
		if ( ! $post || ( ! has_shortcode( $post->post_content, 'wmpro_parte' ) && ! has_shortcode( $post->post_content, 'wmpro_proyectos' ) ) ) return;

		wp_enqueue_style( 'wmpro-frontend', WMPRO_URL . 'frontend/css/frontend.css', [], WMPRO_VERSION );
		wp_enqueue_script( 'wmpro-frontend', WMPRO_URL . 'frontend/js/frontend.js', [ 'jquery' ], WMPRO_VERSION, true );
		wp_localize_script( 'wmpro-frontend', 'wmproData', [
			'ajax_url'  => admin_url( 'admin-ajax.php' ),
			'nonce'     => wp_create_nonce( 'wmpro_frontend' ),
			'parte_id'  => intval( $_GET['parte_id'] ?? 0 ),
			'i18n'      => [
				'confirm_delete_foto'  => '¿Eliminar esta foto?',
				'confirm_delete_tarea' => '¿Eliminar esta tarea?',
				'confirm_submit'       => '¿Enviar el parte? Esta acción no se puede deshacer.',
				'error_firma'          => 'Por favor, añade la firma del cliente antes de enviar.',
				'error_nombre_firma'   => 'Por favor, indica el nombre del firmante.',
				'guardando'            => 'Guardando...',
				'guardado'             => '✓ Guardado',
				'enviando'             => 'Enviando parte...',
				'enviado'              => '✅ Parte enviado correctamente. Se ha enviado el informe por email.',
				'error_generico'       => 'Error al procesar la solicitud. Inténtalo de nuevo.',
			],
		] );
	}

	/* ---- Shortcode principal: [wmpro_parte] ---- */
	public static function shortcode_parte( $atts ) {
		if ( ! is_user_logged_in() ) {
			return '<div class="wmpro-alert wmpro-alert--error">Debes iniciar sesión para acceder a este parte.</div>';
		}

		$parte_id = intval( $_GET['parte_id'] ?? ( $atts['id'] ?? 0 ) );
		if ( ! $parte_id ) {
			return '<div class="wmpro-alert wmpro-alert--info">No se ha especificado ningún parte. Usa la URL: <code>?parte_id=XXX</code></div>';
		}

		$parte = get_post( $parte_id );
		if ( ! $parte || $parte->post_type !== 'wmpro_parte' ) {
			return '<div class="wmpro-alert wmpro-alert--error">Parte no encontrado.</div>';
		}

		$estado = get_post_meta( $parte_id, '_wmpro_estado', true ) ?: 'pendiente';

		// Marcar como en proceso al abrir si estaba pendiente
		if ( $estado === 'pendiente' ) {
			update_post_meta( $parte_id, '_wmpro_estado', 'en_proceso' );
			if ( ! get_post_meta( $parte_id, '_wmpro_fecha_inicio', true ) ) {
				update_post_meta( $parte_id, '_wmpro_fecha_inicio', current_time( 'Y-m-d H:i:s' ) );
			}
		}

		ob_start();
		include WMPRO_TEMPLATES . 'parte.php';
		return ob_get_clean();
	}

	/* ---- Shortcode listado: [wmpro_proyectos] ---- */
	public static function shortcode_proyectos( $atts ) {
		if ( ! is_user_logged_in() ) {
			return '<div class="wmpro-alert wmpro-alert--error">Debes iniciar sesión para ver los proyectos.</div>';
		}

		$proyectos = get_posts( [
			'post_type'      => 'wmpro_proyecto',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'meta_query'     => [ [ 'key' => '_wmpro_estado_proyecto', 'value' => 'activo' ] ],
		] );

		ob_start();
		?>
		<div class="wmpro-proyectos-list">
			<h2>Proyectos activos</h2>
			<?php if ( empty( $proyectos ) ) : ?>
				<p>No hay proyectos activos.</p>
			<?php else : foreach ( $proyectos as $proy ) :
				$partes = get_posts( [
					'post_type'      => 'wmpro_parte',
					'posts_per_page' => 5,
					'orderby'        => 'date',
					'order'          => 'DESC',
					'meta_query'     => [ [ 'key' => '_wmpro_proyecto_id', 'value' => $proy->ID ] ],
				] );
				$proxima = get_post_meta( $proy->ID, '_wmpro_fecha_proxima', true );
				?>
				<div class="wmpro-proyecto-card">
					<div class="wmpro-proyecto-header">
						<h3><?php echo esc_html( $proy->post_title ); ?></h3>
						<?php if ( $proxima ) : ?>
							<span class="wmpro-badge-date">📅 Próximo: <?php echo esc_html( date_i18n( 'd/m/Y', strtotime( $proxima ) ) ); ?></span>
						<?php endif; ?>
					</div>
					<?php if ( ! empty( $partes ) ) : ?>
						<ul class="wmpro-partes-list">
							<?php foreach ( $partes as $p ) :
								$estado = get_post_meta( $p->ID, '_wmpro_estado', true ) ?: 'pendiente';
								$estados = WMPRO_CPT_Parte::get_estados();
								$page_id = get_option( 'wmpro_parte_page_id' );
								$url     = add_query_arg( 'parte_id', $p->ID, get_permalink( $page_id ) );
								?>
								<li>
									<a href="<?php echo esc_url( $url ); ?>">
										<?php echo esc_html( $p->post_title ); ?>
									</a>
									<span class="wmpro-badge wmpro-badge--<?php echo esc_attr( $estado ); ?>">
										<?php echo esc_html( $estados[ $estado ] ?? $estado ); ?>
									</span>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php else : ?>
						<p class="wmpro-no-partes">Sin partes registrados.</p>
					<?php endif; ?>
				</div>
			<?php endforeach; endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}
}

WMPRO_Frontend::init();
