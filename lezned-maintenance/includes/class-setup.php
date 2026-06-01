<?php
/**
 * LZND_Setup — CPT/Taxonomy registration, activation, menu, seed data.
 *
 * @package Lezned_Maintenance
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LZND_Setup {

	// ─── Bootstrap ───────────────────────────────────────────────────────────

	public static function init() {
		add_action( 'init',       array( __CLASS__, 'register_all' ) );
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		// Rename auto-generated CPT submenu items at late priority
		add_action( 'admin_menu', array( __CLASS__, 'fix_submenu_labels' ), 9999 );
	}

	// ─── Register CPTs & Taxonomy ────────────────────────────────────────────

	public static function register_all() {
		self::register_cliente();
		self::register_proyecto();
		self::register_actuacion();
		self::register_tarea();
		self::register_tipo();
	}

	// ── Cliente ──────────────────────────────────────────────────────────────

	public static function register_cliente() {
		$labels = array(
			'name'               => __( 'Clientes',                   'lezned-maintenance' ),
			'singular_name'      => __( 'Cliente',                    'lezned-maintenance' ),
			'add_new'            => __( 'Añadir nuevo',               'lezned-maintenance' ),
			'add_new_item'       => __( 'Añadir nuevo cliente',       'lezned-maintenance' ),
			'edit_item'          => __( 'Editar cliente',             'lezned-maintenance' ),
			'new_item'           => __( 'Nuevo cliente',              'lezned-maintenance' ),
			'view_item'          => __( 'Ver cliente',                'lezned-maintenance' ),
			'search_items'       => __( 'Buscar clientes',            'lezned-maintenance' ),
			'not_found'          => __( 'No se encontraron clientes', 'lezned-maintenance' ),
			'not_found_in_trash' => __( 'Papelera vacía',            'lezned-maintenance' ),
			'menu_name'          => __( 'Clientes',                   'lezned-maintenance' ),
			'all_items'          => __( 'Clientes',                    'lezned-maintenance' ),
		);

		register_post_type( 'lznd_cliente', array(
			'labels'          => $labels,
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => 'lznd-menu',
			'supports'        => array( 'title' ),
			'rewrite'         => false,
			'capability_type' => 'post',
			'map_meta_cap'    => true,
		) );
	}

	// ── Proyecto ─────────────────────────────────────────────────────────────

	public static function register_proyecto() {
		$labels = array(
			'name'               => __( 'Proyectos',                    'lezned-maintenance' ),
			'singular_name'      => __( 'Proyecto',                    'lezned-maintenance' ),
			'add_new'            => __( 'Añadir nuevo',                'lezned-maintenance' ),
			'add_new_item'       => __( 'Añadir nuevo proyecto',       'lezned-maintenance' ),
			'edit_item'          => __( 'Editar proyecto',             'lezned-maintenance' ),
			'new_item'           => __( 'Nuevo proyecto',              'lezned-maintenance' ),
			'view_item'          => __( 'Ver proyecto',                'lezned-maintenance' ),
			'search_items'       => __( 'Buscar proyectos',            'lezned-maintenance' ),
			'not_found'          => __( 'No se encontraron proyectos', 'lezned-maintenance' ),
			'not_found_in_trash' => __( 'Papelera vacía',             'lezned-maintenance' ),
			'menu_name'          => __( 'Proyectos',                   'lezned-maintenance' ),
			'all_items'          => __( 'Proyectos',                    'lezned-maintenance' ),
		);

		register_post_type( 'lznd_proyecto', array(
			'labels'          => $labels,
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => 'lznd-menu',
			'supports'        => array( 'title', 'thumbnail' ),
			'rewrite'         => false,
			'capability_type' => 'post',
			'map_meta_cap'    => true,
		) );
	}

	// ── Actuación ────────────────────────────────────────────────────────────

	public static function register_actuacion() {
		$labels = array(
			'name'               => __( 'Actuaciones',                    'lezned-maintenance' ),
			'singular_name'      => __( 'Actuación',                     'lezned-maintenance' ),
			'add_new'            => __( 'Añadir nueva',                  'lezned-maintenance' ),
			'add_new_item'       => __( 'Añadir nueva actuación',        'lezned-maintenance' ),
			'edit_item'          => __( 'Editar actuación',              'lezned-maintenance' ),
			'new_item'           => __( 'Nueva actuación',               'lezned-maintenance' ),
			'view_item'          => __( 'Ver actuación',                 'lezned-maintenance' ),
			'search_items'       => __( 'Buscar actuaciones',            'lezned-maintenance' ),
			'not_found'          => __( 'No se encontraron actuaciones', 'lezned-maintenance' ),
			'not_found_in_trash' => __( 'Papelera vacía',               'lezned-maintenance' ),
			'menu_name'          => __( 'Actuaciones',                   'lezned-maintenance' ),
			'all_items'          => __( 'Actuaciones',                    'lezned-maintenance' ),
		);

		register_post_type( 'lznd_actuacion', array(
			'labels'          => $labels,
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => 'lznd-menu',
			'supports'        => array( 'title', 'author' ),
			'rewrite'         => false,
			'capability_type' => 'post',
			'map_meta_cap'    => true,
		) );
	}

	// ── Tarea ────────────────────────────────────────────────────────────────

	public static function register_tarea() {
		$labels = array(
			'name'               => __( 'Tareas',                    'lezned-maintenance' ),
			'singular_name'      => __( 'Tarea',                    'lezned-maintenance' ),
			'add_new'            => __( 'Añadir nueva',             'lezned-maintenance' ),
			'add_new_item'       => __( 'Añadir nueva tarea',       'lezned-maintenance' ),
			'edit_item'          => __( 'Editar tarea',             'lezned-maintenance' ),
			'new_item'           => __( 'Nueva tarea',              'lezned-maintenance' ),
			'view_item'          => __( 'Ver tarea',                'lezned-maintenance' ),
			'search_items'       => __( 'Buscar tareas',            'lezned-maintenance' ),
			'not_found'          => __( 'No se encontraron tareas', 'lezned-maintenance' ),
			'not_found_in_trash' => __( 'Papelera vacía',          'lezned-maintenance' ),
			'menu_name'          => __( 'Tareas',                   'lezned-maintenance' ),
			'all_items'          => __( 'Tareas',                    'lezned-maintenance' ),
		);

		register_post_type( 'lznd_tarea', array(
			'labels'          => $labels,
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => 'lznd-menu',
			'supports'        => array( 'title' ),
			'rewrite'         => false,
			'capability_type' => 'post',
			'map_meta_cap'    => true,
		) );
	}

	// ── Taxonomy: lznd_tipo ──────────────────────────────────────────────────

	public static function register_tipo() {
		$labels = array(
			'name'              => __( 'Tipos de trabajo',         'lezned-maintenance' ),
			'singular_name'     => __( 'Tipo de trabajo',         'lezned-maintenance' ),
			'search_items'      => __( 'Buscar tipos',            'lezned-maintenance' ),
			'all_items'         => __( 'Todos los tipos',         'lezned-maintenance' ),
			'parent_item'       => __( 'Tipo padre',              'lezned-maintenance' ),
			'parent_item_colon' => __( 'Tipo padre:',             'lezned-maintenance' ),
			'edit_item'         => __( 'Editar tipo',             'lezned-maintenance' ),
			'update_item'       => __( 'Actualizar tipo',         'lezned-maintenance' ),
			'add_new_item'      => __( 'Añadir nuevo tipo',       'lezned-maintenance' ),
			'new_item_name'     => __( 'Nombre del nuevo tipo',   'lezned-maintenance' ),
			'menu_name'         => __( 'Tipos de trabajo',        'lezned-maintenance' ),
			'not_found'         => __( 'No se encontraron tipos', 'lezned-maintenance' ),
		);

		register_taxonomy( 'lznd_tipo', array( 'lznd_actuacion', 'lznd_tarea' ), array(
			'labels'            => $labels,
			'hierarchical'      => true,
			'show_ui'           => true,
			'show_admin_column' => true,
			'query_var'         => false,
			'rewrite'           => false,
		) );
	}

	// ─── Admin Menu ──────────────────────────────────────────────────────────

	public static function add_menu() {
		// Main menu entry
		add_menu_page(
			__( 'Mantenimiento', 'lezned-maintenance' ),
			__( 'Mantenimiento', 'lezned-maintenance' ),
			'edit_posts',
			'lznd-menu',
			array( 'LZND_Dashboard', 'render' ),
			'dashicons-hammer',
			28
		);

		// Explicit "Dashboard" first submenu (same slug as parent replaces the
		// auto-generated copy that WordPress would otherwise add with the parent title).
		add_submenu_page(
			'lznd-menu',
			__( 'Dashboard', 'lezned-maintenance' ),
			__( 'Dashboard', 'lezned-maintenance' ),
			'edit_posts',
			'lznd-menu',
			array( 'LZND_Dashboard', 'render' )
		);

		// NOTE: Clientes, Proyectos, Actuaciones, Tareas are added automatically
		// by WordPress because each CPT has show_in_menu='lznd-menu'.

		// Tipos de trabajo — plugin-owned page (not the WP taxonomy editor)
		add_submenu_page(
			'lznd-menu',
			__( 'Tipos de trabajo', 'lezned-maintenance' ),
			__( 'Tipos de trabajo', 'lezned-maintenance' ),
			'manage_categories',
			'lznd-tipos',
			array( __CLASS__, 'render_tipos_page' )
		);

		// Project detail hidden page (no submenu entry)
		add_submenu_page(
			'',
			__( 'Detalle del proyecto', 'lezned-maintenance' ),
			__( 'Detalle del proyecto', 'lezned-maintenance' ),
			'edit_posts',
			'lznd-project-detail',
			array( 'LZND_Admin_UI', 'render_project_detail' )
		);
	}

	/**
	 * Fix Dashboard label and reorder submenu: Dashboard → Clientes → Proyectos →
	 * Actuaciones → Tipos de trabajo → Tareas.
	 * Runs at priority 9999 after all CPT submenus are registered.
	 */
	public static function fix_submenu_labels() {
		global $submenu;
		if ( ! isset( $submenu['lznd-menu'] ) ) {
			return;
		}

		// Fix Dashboard label
		foreach ( $submenu['lznd-menu'] as &$item ) {
			if ( isset( $item[2] ) && $item[2] === 'lznd-menu' ) {
				$item[0] = __( 'Dashboard', 'lezned-maintenance' );
				break;
			}
		}
		unset( $item );

		// Priority map for desired order
		$order = array(
			'lznd-menu'                              => 0,
			'edit.php?post_type=lznd_cliente'        => 10,
			'post-new.php?post_type=lznd_cliente'    => 11,
			'edit.php?post_type=lznd_proyecto'       => 20,
			'post-new.php?post_type=lznd_proyecto'   => 21,
			'edit.php?post_type=lznd_actuacion'      => 30,
			'post-new.php?post_type=lznd_actuacion'  => 31,
			'lznd-tipos'                             => 40,
			'edit.php?post_type=lznd_tarea'          => 50,
			'post-new.php?post_type=lznd_tarea'      => 51,
		);

		usort( $submenu['lznd-menu'], function ( $a, $b ) use ( $order ) {
			$pa = isset( $order[ $a[2] ] ) ? $order[ $a[2] ] : 99;
			$pb = isset( $order[ $b[2] ] ) ? $order[ $b[2] ] : 99;
			return $pa - $pb;
		} );
	}

	// ─── Tipos de trabajo admin page ─────────────────────────────────────────

	public static function render_tipos_page() {
		if ( ! current_user_can( 'manage_categories' ) ) {
			wp_die( esc_html__( 'No tienes permiso para acceder a esta página.', 'lezned-maintenance' ) );
		}

		$message   = '';
		$edit_term = null;

		// Handle delete (GET with nonce)
		if ( isset( $_GET['action'], $_GET['term_id'] ) && 'delete' === $_GET['action'] ) {
			$term_id = absint( $_GET['term_id'] );
			check_admin_referer( 'lznd_delete_tipo_' . $term_id );
			$result = wp_delete_term( $term_id, 'lznd_tipo' );
			if ( ! is_wp_error( $result ) ) {
				wp_safe_redirect( admin_url( 'admin.php?page=lznd-tipos&lznd_msg=deleted' ) );
				exit;
			}
		}

		// Handle add / edit (POST)
		if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['lznd_tipos_nonce'] ) ) {
			check_admin_referer( 'lznd_tipos_save', 'lznd_tipos_nonce' );
			$action = sanitize_key( isset( $_POST['lznd_tipos_action'] ) ? $_POST['lznd_tipos_action'] : 'add' );
			$name   = sanitize_text_field( isset( $_POST['term_name'] ) ? $_POST['term_name'] : '' );

			if ( 'edit' === $action ) {
				$term_id = absint( isset( $_POST['term_id'] ) ? $_POST['term_id'] : 0 );
				if ( $term_id && $name ) {
					wp_update_term( $term_id, 'lznd_tipo', array( 'name' => $name ) );
					wp_safe_redirect( admin_url( 'admin.php?page=lznd-tipos&lznd_msg=updated' ) );
					exit;
				}
			} else {
				if ( $name ) {
					wp_insert_term( $name, 'lznd_tipo' );
					wp_safe_redirect( admin_url( 'admin.php?page=lznd-tipos&lznd_msg=added' ) );
					exit;
				}
			}
		}

		// Inline edit view
		if ( isset( $_GET['action'], $_GET['term_id'] ) && 'edit' === $_GET['action'] ) {
			$term_id   = absint( $_GET['term_id'] );
			$candidate = get_term( $term_id, 'lznd_tipo' );
			if ( $candidate && ! is_wp_error( $candidate ) ) {
				$edit_term = $candidate;
			}
		}

		// Success notice from redirect
		if ( isset( $_GET['lznd_msg'] ) ) {
			$message = sanitize_key( $_GET['lznd_msg'] );
		}

		$terms     = get_terms( array( 'taxonomy' => 'lznd_tipo', 'hide_empty' => false ) );
		$page_url  = admin_url( 'admin.php?page=lznd-tipos' );
		?>
		<div class="wrap lznd-tipos-page">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Tipos de trabajo', 'lezned-maintenance' ); ?></h1>
			<hr class="wp-header-end">

			<?php if ( 'added' === $message ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Tipo de trabajo añadido correctamente.', 'lezned-maintenance' ); ?></p></div>
			<?php elseif ( 'updated' === $message ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Tipo de trabajo actualizado.', 'lezned-maintenance' ); ?></p></div>
			<?php elseif ( 'deleted' === $message ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Tipo de trabajo eliminado.', 'lezned-maintenance' ); ?></p></div>
			<?php endif; ?>

			<div class="lznd-tipos-layout">

				<!-- Terms table -->
				<div class="lznd-tipos-table-wrap">
					<table class="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Nombre', 'lezned-maintenance' ); ?></th>
								<th><?php esc_html_e( 'Slug', 'lezned-maintenance' ); ?></th>
								<th class="center"><?php esc_html_e( 'Actuaciones', 'lezned-maintenance' ); ?></th>
								<th><?php esc_html_e( 'Acciones', 'lezned-maintenance' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php if ( empty( $terms ) || is_wp_error( $terms ) ) : ?>
								<tr><td colspan="4"><?php esc_html_e( 'No hay tipos definidos.', 'lezned-maintenance' ); ?></td></tr>
							<?php else : ?>
								<?php foreach ( $terms as $term ) : ?>
									<tr>
										<td><strong><?php echo esc_html( $term->name ); ?></strong></td>
										<td><code><?php echo esc_html( $term->slug ); ?></code></td>
										<td class="center"><?php echo (int) $term->count; ?></td>
										<td>
											<a href="<?php echo esc_url( add_query_arg( array( 'action' => 'edit', 'term_id' => $term->term_id ), $page_url ) ); ?>">
												<?php esc_html_e( 'Editar', 'lezned-maintenance' ); ?>
											</a>
											&nbsp;|&nbsp;
											<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'delete', 'term_id' => $term->term_id ), $page_url ), 'lznd_delete_tipo_' . $term->term_id ) ); ?>"
											   onclick="return confirm('<?php esc_attr_e( '¿Eliminar este tipo de trabajo?', 'lezned-maintenance' ); ?>');"
											   style="color:#d63638;">
												<?php esc_html_e( 'Eliminar', 'lezned-maintenance' ); ?>
											</a>
										</td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div>

				<!-- Add / Edit form -->
				<div class="lznd-tipos-form-wrap">
					<div class="lznd-settings-section">
						<h2><?php echo $edit_term ? esc_html__( 'Editar tipo', 'lezned-maintenance' ) : esc_html__( 'Añadir tipo de trabajo', 'lezned-maintenance' ); ?></h2>
						<form method="post" action="<?php echo esc_url( $page_url ); ?>">
							<?php wp_nonce_field( 'lznd_tipos_save', 'lznd_tipos_nonce' ); ?>
							<input type="hidden" name="lznd_tipos_action" value="<?php echo $edit_term ? 'edit' : 'add'; ?>">
							<?php if ( $edit_term ) : ?>
								<input type="hidden" name="term_id" value="<?php echo (int) $edit_term->term_id; ?>">
							<?php endif; ?>
							<table class="form-table lznd-form">
								<tr>
									<th><label for="lznd_term_name"><?php esc_html_e( 'Nombre', 'lezned-maintenance' ); ?></label></th>
									<td>
										<input type="text" id="lznd_term_name" name="term_name"
											   value="<?php echo $edit_term ? esc_attr( $edit_term->name ) : ''; ?>"
											   class="regular-text" required>
									</td>
								</tr>
							</table>
							<p>
								<button type="submit" class="button button-primary">
									<?php echo $edit_term ? esc_html__( 'Actualizar', 'lezned-maintenance' ) : esc_html__( 'Añadir tipo', 'lezned-maintenance' ); ?>
								</button>
								<?php if ( $edit_term ) : ?>
									&nbsp;<a href="<?php echo esc_url( $page_url ); ?>" class="button"><?php esc_html_e( 'Cancelar', 'lezned-maintenance' ); ?></a>
								<?php endif; ?>
							</p>
						</form>
					</div>
				</div>

			</div><!-- .lznd-tipos-layout -->
		</div><!-- .wrap -->
		<?php
	}

	// ─── Activation ──────────────────────────────────────────────────────────

	public static function activate() {
		self::register_all();
		flush_rewrite_rules();
		self::seed_default_data();
		self::setup_page();
		LZND_Cron::setup_events();
	}

	// ─── Deactivation ────────────────────────────────────────────────────────

	public static function deactivate() {
		LZND_Cron::clear_events();
		flush_rewrite_rules();
	}

	// ─── Seed default data ───────────────────────────────────────────────────

	public static function seed_default_data() {
		// ── Default lznd_tipo terms ──────────────────────────────────────────
		$existing_terms = get_terms( array(
			'taxonomy'   => 'lznd_tipo',
			'hide_empty' => false,
			'fields'     => 'ids',
		) );

		if ( empty( $existing_terms ) || is_wp_error( $existing_terms ) ) {
			$default_tipos = array(
				'Mantenimiento',
				'Primera visita',
				'Urgencia',
				'Presupuesto',
			);
			foreach ( $default_tipos as $tipo ) {
				wp_insert_term( $tipo, 'lznd_tipo' );
			}
		}

		// ── Default lznd_tarea posts ─────────────────────────────────────────
		$existing_tareas = get_posts( array(
			'post_type'      => 'lznd_tarea',
			'posts_per_page' => 1,
			'post_status'    => 'any',
			'fields'         => 'ids',
		) );

		if ( empty( $existing_tareas ) ) {
			$default_tareas = array(
				'Revisión de juntas',
				'Revisión de llaves',
				'Revisión de grupo presión',
				'Conexiones de manguitos',
				'Estado de tuberías',
				'Pruebas de presión',
			);
			foreach ( $default_tareas as $tarea_name ) {
				wp_insert_post( array(
					'post_title'  => $tarea_name,
					'post_type'   => 'lznd_tarea',
					'post_status' => 'publish',
				) );
			}
		}
	}

	// ─── Setup frontend page ─────────────────────────────────────────────────

	public static function setup_page() {
		if ( get_option( 'lznd_page_id' ) ) {
			return;
		}

		$page_id = wp_insert_post( array(
			'post_title'   => __( 'Parte de Mantenimiento', 'lezned-maintenance' ),
			'post_content' => '[lznd_actuacion]',
			'post_status'  => 'publish',
			'post_type'    => 'page',
		) );

		if ( $page_id && ! is_wp_error( $page_id ) ) {
			update_option( 'lznd_page_id', $page_id );
		}
	}

}

LZND_Setup::init();
