<?php
/**
 * Plugin Name: Zorom – Promociones Qobrix
 * Description: Sincroniza los proyectos (promociones) del CRM Qobrix y los muestra en pestañas con el shortcode [zorom_promociones].
 * Version:     1.0.0
 * Author:      Idital
 * Text Domain: zorom-qobrix
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ZQ_VERSION', '1.0.0' );
define( 'ZQ_FILE', __FILE__ );
define( 'ZQ_OPTION', 'zq_settings' );
define( 'ZQ_PROJECTS', 'zq_projects' );
define( 'ZQ_STATUS', 'zq_status' );
define( 'ZQ_IMAGES', 'zq_images' );
define( 'ZQ_CRON', 'zq_sync_event' );
define( 'ZQ_IMAGE_SIZE', 'zorom-promocion' );

/* -------------------------------------------------------------------------
 * Ajustes
 * ---------------------------------------------------------------------- */

function zq_defaults() {
	return array(
		'api_url'        => 'https://zorom8334.eu1.qobrix.com',
		'api_user'       => '',
		'api_key'        => '',
		'locale'         => 'es',
		'only_featured'  => 0,
		'project_ids'    => '',
		'link_pattern'   => '/promociones/{slug}/',
		'link_overrides' => '',
		'field_desc'     => 'short_description,description',
		'field_price'    => 'starting_price_from',
		'field_units'    => 'number_of_units',
		'field_date'     => 'completion_date',
		'image_variant'  => 'huge',
	);
}

function zq_options() {
	return wp_parse_args( (array) get_option( ZQ_OPTION, array() ), zq_defaults() );
}

add_action( 'init', function () {
	add_image_size( ZQ_IMAGE_SIZE, 880, 1140, true );
} );

/* -------------------------------------------------------------------------
 * Activación y cron
 * ---------------------------------------------------------------------- */

register_activation_hook( ZQ_FILE, function () {
	if ( ! wp_next_scheduled( ZQ_CRON ) ) {
		wp_schedule_event( time() + 60, 'hourly', ZQ_CRON );
	}
} );

register_deactivation_hook( ZQ_FILE, function () {
	wp_clear_scheduled_hook( ZQ_CRON );
} );

add_action( ZQ_CRON, 'zq_sync' );

/* -------------------------------------------------------------------------
 * Cliente de la API de Qobrix
 * ---------------------------------------------------------------------- */

function zq_api_get( $path, $params = array() ) {
	$o = zq_options();
	if ( empty( $o['api_url'] ) || empty( $o['api_user'] ) || empty( $o['api_key'] ) ) {
		return new WP_Error( 'zq_config', 'Faltan la URL, el usuario o la clave de la API en Ajustes → Qobrix.' );
	}

	$url     = add_query_arg( $params, rtrim( $o['api_url'], '/' ) . '/api/v2/' . ltrim( $path, '/' ) );
	$headers = array(
		'X-Api-User' => $o['api_user'],
		'X-Api-Key'  => $o['api_key'],
		'Accept'     => 'application/json',
	);
	if ( ! empty( $o['locale'] ) ) {
		$headers['X-Locale'] = $o['locale'];
	}

	$res = wp_remote_get( $url, array( 'headers' => $headers, 'timeout' => 30 ) );
	if ( is_wp_error( $res ) ) {
		return $res;
	}

	$code = wp_remote_retrieve_response_code( $res );
	$body = json_decode( wp_remote_retrieve_body( $res ), true );
	if ( 200 !== $code ) {
		$msg = isset( $body['errors'] ) ? implode( ' ', (array) $body['errors'] ) : wp_remote_retrieve_response_message( $res );
		return new WP_Error( 'zq_http', sprintf( 'Qobrix respondió %d: %s', $code, $msg ) );
	}

	return is_array( $body ) ? $body : new WP_Error( 'zq_json', 'Respuesta de Qobrix no válida.' );
}

/** Devuelve todos los proyectos recorriendo la paginación. */
function zq_fetch_all_projects() {
	$all  = array();
	$page = 1;
	do {
		$res = zq_api_get( 'projects', array( 'limit' => 100, 'page' => $page ) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$all  = array_merge( $all, isset( $res['data'] ) ? (array) $res['data'] : array() );
		$more = ! empty( $res['pagination']['has_next_page'] );
		$page++;
	} while ( $more && $page <= 20 );

	return $all;
}

/* -------------------------------------------------------------------------
 * Sincronización
 * ---------------------------------------------------------------------- */

/** Primer campo no vacío de una lista separada por comas. */
function zq_pick( array $row, $fields ) {
	foreach ( array_map( 'trim', explode( ',', $fields ) ) as $f ) {
		if ( '' !== $f && isset( $row[ $f ] ) && '' !== $row[ $f ] && null !== $row[ $f ] ) {
			return $row[ $f ];
		}
	}
	return '';
}

function zq_sync() {
	$o   = zq_options();
	$raw = zq_fetch_all_projects();

	if ( is_wp_error( $raw ) ) {
		update_option( ZQ_STATUS, array( 'time' => time(), 'error' => $raw->get_error_message() ), false );
		return $raw;
	}

	$ids = array_filter( array_map( 'trim', preg_split( '/[\s,]+/', $o['project_ids'] ) ) );

	$projects = array();
	foreach ( $raw as $row ) {
		if ( empty( $row['id'] ) ) {
			continue;
		}
		if ( $ids && ! in_array( $row['id'], $ids, true ) ) {
			continue;
		}
		if ( ! $ids && $o['only_featured'] && empty( $row['featured'] ) ) {
			continue;
		}
		$name = isset( $row['name'] ) ? (string) $row['name'] : '';

		$projects[ $row['id'] ] = array(
			'id'          => $row['id'],
			'ref'         => isset( $row['ref'] ) ? (string) $row['ref'] : '',
			'name'        => $name,
			'slug'        => sanitize_title( $name ),
			'description' => (string) zq_pick( $row, $o['field_desc'] ),
			'price'       => zq_pick( $row, $o['field_price'] ),
			'units'       => zq_pick( $row, $o['field_units'] ),
			'date'        => (string) zq_pick( $row, $o['field_date'] ),
			'modified'    => isset( $row['modified'] ) ? (string) $row['modified'] : '',
		);
	}

	// Orden manual si se indicaron IDs; si no, alfabético.
	if ( $ids ) {
		$ordered = array();
		foreach ( $ids as $id ) {
			if ( isset( $projects[ $id ] ) ) {
				$ordered[ $id ] = $projects[ $id ];
			}
		}
		$projects = $ordered;
	} else {
		uasort( $projects, function ( $a, $b ) {
			return strcasecmp( $a['name'], $b['name'] );
		} );
	}

	foreach ( $projects as $id => $p ) {
		$projects[ $id ]['image_id'] = zq_sync_image( $p );
	}

	update_option( ZQ_PROJECTS, $projects, false );
	update_option( ZQ_STATUS, array(
		'time'   => time(),
		'error'  => '',
		'count'  => count( $projects ),
		'total'  => count( $raw ),
		'sample' => $raw ? zq_sample_fields( reset( $raw ) ) : array(),
	), false );

	return $projects;
}

/** Campos del primer proyecto, para comprobar el mapeo desde el panel. */
function zq_sample_fields( $row ) {
	$out = array();
	foreach ( (array) $row as $k => $v ) {
		if ( is_scalar( $v ) || null === $v ) {
			$out[ $k ] = mb_substr( wp_strip_all_tags( (string) $v ), 0, 80 );
		}
	}
	return $out;
}

/**
 * Descarga la foto destacada a la biblioteca de medios (una vez por versión del proyecto)
 * para que WordPress genere el tamaño recortado. Devuelve el ID del adjunto o 0.
 */
function zq_sync_image( array $p ) {
	$map  = (array) get_option( ZQ_IMAGES, array() );
	$prev = isset( $map[ $p['id'] ] ) ? $map[ $p['id'] ] : null;

	if ( $prev && $prev['modified'] === $p['modified'] && get_post( $prev['attachment'] ) ) {
		return (int) $prev['attachment'];
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$tmp = download_url( zq_remote_image_url( $p['id'] ), 60 );
	if ( is_wp_error( $tmp ) ) {
		return $prev ? (int) $prev['attachment'] : 0;
	}

	$mime = wp_get_image_mime( $tmp );
	$ext  = array( 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif' );
	if ( ! isset( $ext[ $mime ] ) ) {
		wp_delete_file( $tmp );
		return $prev ? (int) $prev['attachment'] : 0;
	}

	$file = array(
		'name'     => ( $p['slug'] ? $p['slug'] : $p['id'] ) . '.' . $ext[ $mime ],
		'tmp_name' => $tmp,
	);
	$att  = media_handle_sideload( $file, 0, $p['name'] );
	if ( is_wp_error( $att ) ) {
		wp_delete_file( $tmp );
		return $prev ? (int) $prev['attachment'] : 0;
	}
	update_post_meta( $att, '_wp_attachment_image_alt', $p['name'] );
	update_post_meta( $att, '_zq_project_id', $p['id'] );

	if ( $prev && get_post( $prev['attachment'] ) ) {
		wp_delete_attachment( $prev['attachment'], true );
	}

	$map[ $p['id'] ] = array( 'attachment' => $att, 'modified' => $p['modified'] );
	update_option( ZQ_IMAGES, $map, false );

	return (int) $att;
}

function zq_remote_image_url( $project_id ) {
	$o = zq_options();
	return rtrim( $o['api_url'], '/' ) . '/api/v2/media/icon/projects/' . rawurlencode( $project_id ) . '/featured_photo/' . rawurlencode( $o['image_variant'] );
}

/* -------------------------------------------------------------------------
 * Formato
 * ---------------------------------------------------------------------- */

function zq_project_link( array $p ) {
	$o = zq_options();

	foreach ( preg_split( '/\r\n|\r|\n/', $o['link_overrides'] ) as $line ) {
		$parts = array_map( 'trim', explode( '|', $line, 2 ) );
		if ( 2 === count( $parts ) && '' !== $parts[0]
			&& ( $parts[0] === $p['id'] || 0 === strcasecmp( $parts[0], $p['name'] ) || ( $p['ref'] && $parts[0] === $p['ref'] ) ) ) {
			return zq_absolute_url( $parts[1] );
		}
	}

	return zq_absolute_url( strtr( $o['link_pattern'], array(
		'{slug}' => $p['slug'],
		'{id}'   => $p['id'],
		'{ref}'  => $p['ref'],
	) ) );
}

function zq_absolute_url( $path ) {
	return preg_match( '#^https?://#', $path ) ? $path : home_url( '/' . ltrim( $path, '/' ) );
}

function zq_format_price( $value ) {
	if ( '' === $value || null === $value || ! is_numeric( $value ) ) {
		return '';
	}
	return number_format( (float) $value, 0, ',', '.' ) . ' €';
}

function zq_format_date( $value ) {
	if ( '' === $value ) {
		return '';
	}
	$ts = strtotime( $value );
	return $ts ? gmdate( 'd/m/Y', $ts ) : $value;
}

/* -------------------------------------------------------------------------
 * Shortcode [zorom_promociones]
 * ---------------------------------------------------------------------- */

add_action( 'wp_enqueue_scripts', function () {
	$url = plugin_dir_url( ZQ_FILE );
	wp_register_style( 'zorom-qobrix', $url . 'assets/promociones.css', array(), ZQ_VERSION );
	wp_register_script( 'zorom-qobrix', $url . 'assets/promociones.js', array(), ZQ_VERSION, true );
} );

add_shortcode( 'zorom_promociones', function ( $atts ) {
	static $instance = 0;
	$instance++;

	$a = shortcode_atts( array(
		'limite'        => 4,
		'texto_enlace'  => 'Ver promoción',
		'etiq_precio'   => 'Precio desde',
		'etiq_unidades' => 'Unidades',
		'etiq_fecha'    => 'Fecha',
	), $atts, 'zorom_promociones' );

	$projects = array_slice( array_values( (array) get_option( ZQ_PROJECTS, array() ) ), 0, max( 1, (int) $a['limite'] ) );
	if ( ! $projects ) {
		return current_user_can( 'manage_options' )
			? '<p><em>Promociones Qobrix: no hay proyectos sincronizados. Revisa Ajustes → Qobrix.</em></p>'
			: '';
	}

	wp_enqueue_style( 'zorom-qobrix' );
	wp_enqueue_script( 'zorom-qobrix' );

	$uid = 'zq' . $instance;
	ob_start();
	?>
	<div class="zq-promos" data-zq-tabs>
		<div class="zq-tabs" role="tablist">
			<?php foreach ( $projects as $i => $p ) : ?>
				<button type="button" role="tab" class="zq-tab<?php echo 0 === $i ? ' is-active' : ''; ?>"
					id="<?php echo esc_attr( "$uid-tab-$i" ); ?>"
					aria-controls="<?php echo esc_attr( "$uid-panel-$i" ); ?>"
					aria-selected="<?php echo 0 === $i ? 'true' : 'false'; ?>"
					tabindex="<?php echo 0 === $i ? '0' : '-1'; ?>"><?php echo esc_html( $p['name'] ); ?></button>
			<?php endforeach; ?>
		</div>

		<?php foreach ( $projects as $i => $p ) : ?>
			<div class="zq-panel" role="tabpanel"
				id="<?php echo esc_attr( "$uid-panel-$i" ); ?>"
				aria-labelledby="<?php echo esc_attr( "$uid-tab-$i" ); ?>"
				<?php echo 0 === $i ? '' : 'hidden'; ?>>
				<div class="zq-panel__info">
					<h3 class="zq-title"><?php echo esc_html( $p['name'] ); ?></h3>
					<?php if ( $p['description'] ) : ?>
						<div class="zq-desc"><?php echo wpautop( wp_kses_post( $p['description'] ) ); ?></div>
					<?php endif; ?>
					<dl class="zq-datos">
						<?php
						$datos = array(
							$a['etiq_precio']   => zq_format_price( $p['price'] ),
							$a['etiq_unidades'] => '' !== (string) $p['units'] ? (string) $p['units'] : '',
							$a['etiq_fecha']    => zq_format_date( $p['date'] ),
						);
						foreach ( $datos as $label => $value ) :
							if ( '' === $value ) {
								continue;
							}
							?>
							<div class="zq-dato"><dt><?php echo esc_html( $label ); ?></dt><dd><?php echo esc_html( $value ); ?></dd></div>
						<?php endforeach; ?>
					</dl>
					<a class="zq-link" href="<?php echo esc_url( zq_project_link( $p ) ); ?>"><?php echo esc_html( $a['texto_enlace'] ); ?> <span aria-hidden="true">&rarr;</span></a>
				</div>
				<div class="zq-panel__img">
					<?php
					if ( ! empty( $p['image_id'] ) && wp_attachment_is_image( $p['image_id'] ) ) {
						echo wp_get_attachment_image( $p['image_id'], ZQ_IMAGE_SIZE, false, array(
							'alt'     => $p['name'],
							'loading' => 0 === $i ? 'eager' : 'lazy',
						) );
					} else {
						printf( '<img src="%s" alt="%s" loading="lazy">', esc_url( zq_remote_image_url( $p['id'] ) ), esc_attr( $p['name'] ) );
					}
					?>
				</div>
			</div>
		<?php endforeach; ?>
	</div>
	<?php
	return ob_get_clean();
} );

/* -------------------------------------------------------------------------
 * Panel de administración: Ajustes → Qobrix
 * ---------------------------------------------------------------------- */

add_action( 'admin_menu', function () {
	add_options_page( 'Qobrix', 'Qobrix', 'manage_options', 'zorom-qobrix', 'zq_settings_page' );
} );

add_action( 'admin_init', function () {
	register_setting( 'zq', ZQ_OPTION, array( 'sanitize_callback' => 'zq_sanitize' ) );
} );

function zq_sanitize( $in ) {
	$old = zq_options();
	$out = zq_defaults();

	$out['api_url']        = esc_url_raw( rtrim( trim( isset( $in['api_url'] ) ? $in['api_url'] : '' ), '/' ) );
	$out['api_user']       = sanitize_text_field( isset( $in['api_user'] ) ? $in['api_user'] : '' );
	// La clave no se muestra en el formulario: si llega vacía se conserva la anterior.
	$out['api_key']        = ! empty( $in['api_key'] ) ? sanitize_text_field( $in['api_key'] ) : $old['api_key'];
	$out['locale']         = sanitize_text_field( isset( $in['locale'] ) ? $in['locale'] : '' );
	$out['only_featured']  = empty( $in['only_featured'] ) ? 0 : 1;
	$out['project_ids']    = sanitize_textarea_field( isset( $in['project_ids'] ) ? $in['project_ids'] : '' );
	$out['link_pattern']   = sanitize_text_field( isset( $in['link_pattern'] ) ? $in['link_pattern'] : '' );
	$out['link_overrides'] = sanitize_textarea_field( isset( $in['link_overrides'] ) ? $in['link_overrides'] : '' );
	foreach ( array( 'field_desc', 'field_price', 'field_units', 'field_date' ) as $k ) {
		$out[ $k ] = preg_replace( '/[^a-z0-9_,]/i', '', isset( $in[ $k ] ) ? $in[ $k ] : '' );
	}
	$out['image_variant'] = in_array( isset( $in['image_variant'] ) ? $in['image_variant'] : '', array( 'huge', 'large', 'medium' ), true ) ? $in['image_variant'] : 'huge';

	return $out;
}

add_action( 'admin_post_zq_sync_now', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Sin permiso.' );
	}
	check_admin_referer( 'zq_sync_now' );
	zq_sync();
	wp_safe_redirect( admin_url( 'options-general.php?page=zorom-qobrix&zq_synced=1' ) );
	exit;
} );

function zq_settings_page() {
	$o      = zq_options();
	$status = (array) get_option( ZQ_STATUS, array() );
	$list   = (array) get_option( ZQ_PROJECTS, array() );
	$name   = ZQ_OPTION;
	?>
	<div class="wrap">
		<h1>Promociones Qobrix</h1>

		<?php if ( ! empty( $status['time'] ) ) : ?>
			<div class="notice <?php echo empty( $status['error'] ) ? 'notice-success' : 'notice-error'; ?>"><p>
				Última sincronización: <?php echo esc_html( wp_date( 'd/m/Y H:i', $status['time'] ) ); ?> —
				<?php
				echo empty( $status['error'] )
					? esc_html( sprintf( '%d promociones mostradas (de %d proyectos en Qobrix).', $status['count'], $status['total'] ) )
					: esc_html( 'Error: ' . $status['error'] );
				?>
			</p></div>
		<?php endif; ?>

		<form method="post" action="options.php">
			<?php settings_fields( 'zq' ); ?>
			<h2>Conexión</h2>
			<table class="form-table" role="presentation">
				<tr><th><label for="zq-url">URL del CRM</label></th>
					<td><input id="zq-url" class="regular-text" type="url" name="<?php echo esc_attr( $name ); ?>[api_url]" value="<?php echo esc_attr( $o['api_url'] ); ?>"></td></tr>
				<tr><th><label for="zq-user">Usuario API</label></th>
					<td><input id="zq-user" class="regular-text" type="text" autocomplete="off" name="<?php echo esc_attr( $name ); ?>[api_user]" value="<?php echo esc_attr( $o['api_user'] ); ?>"></td></tr>
				<tr><th><label for="zq-key">Clave API</label></th>
					<td><input id="zq-key" class="regular-text" type="password" autocomplete="new-password" name="<?php echo esc_attr( $name ); ?>[api_key]" value="" placeholder="<?php echo $o['api_key'] ? '•••••••• (guardada; déjalo vacío para mantenerla)' : ''; ?>"></td></tr>
				<tr><th><label for="zq-locale">Idioma (X-Locale)</label></th>
					<td><input id="zq-locale" class="small-text" type="text" name="<?php echo esc_attr( $name ); ?>[locale]" value="<?php echo esc_attr( $o['locale'] ); ?>"> <span class="description">Ej.: es, en. Vacío = idioma por defecto del CRM.</span></td></tr>
			</table>

			<h2>Qué promociones mostrar</h2>
			<table class="form-table" role="presentation">
				<tr><th>Solo destacados</th>
					<td><label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[only_featured]" value="1" <?php checked( $o['only_featured'] ); ?>> Mostrar solo los proyectos marcados como «destacado» en Qobrix</label></td></tr>
				<tr><th><label for="zq-ids">IDs y orden manual</label></th>
					<td><textarea id="zq-ids" class="large-text code" rows="4" name="<?php echo esc_attr( $name ); ?>[project_ids]"><?php echo esc_textarea( $o['project_ids'] ); ?></textarea>
						<p class="description">Opcional. Un ID de proyecto de Qobrix por línea, en el orden de las pestañas. Si lo rellenas, se ignora «Solo destacados».</p></td></tr>
			</table>

			<h2>Enlaces «Ver promoción»</h2>
			<table class="form-table" role="presentation">
				<tr><th><label for="zq-link">Patrón de URL</label></th>
					<td><input id="zq-link" class="regular-text code" type="text" name="<?php echo esc_attr( $name ); ?>[link_pattern]" value="<?php echo esc_attr( $o['link_pattern'] ); ?>">
						<p class="description">Variables: <code>{slug}</code> (nombre en minúsculas con guiones), <code>{id}</code>, <code>{ref}</code>. Relativo a la web o URL completa.</p></td></tr>
				<tr><th><label for="zq-over">Enlaces concretos</label></th>
					<td><textarea id="zq-over" class="large-text code" rows="4" name="<?php echo esc_attr( $name ); ?>[link_overrides]"><?php echo esc_textarea( $o['link_overrides'] ); ?></textarea>
						<p class="description">Una línea por proyecto: <code>Nombre, ID o ref | URL</code>. Ej.: <code>Amiral | /promociones/amiral-la-nucia/</code></p></td></tr>
			</table>

			<h2>Campos de Qobrix</h2>
			<table class="form-table" role="presentation">
				<?php
				$fields = array(
					'field_desc'  => 'Descripción',
					'field_price' => 'Precio desde',
					'field_units' => 'Unidades',
					'field_date'  => 'Fecha',
				);
				foreach ( $fields as $k => $label ) :
					?>
					<tr><th><label for="zq-<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $label ); ?></label></th>
						<td><input id="zq-<?php echo esc_attr( $k ); ?>" class="regular-text code" type="text" name="<?php echo esc_attr( $name . '[' . $k . ']' ); ?>" value="<?php echo esc_attr( $o[ $k ] ); ?>"></td></tr>
				<?php endforeach; ?>
				<tr><th><label for="zq-img">Tamaño de la foto en Qobrix</label></th>
					<td><select id="zq-img" name="<?php echo esc_attr( $name ); ?>[image_variant]">
						<?php foreach ( array( 'huge', 'large', 'medium' ) as $v ) : ?>
							<option value="<?php echo esc_attr( $v ); ?>" <?php selected( $o['image_variant'], $v ); ?>><?php echo esc_html( $v ); ?></option>
						<?php endforeach; ?>
					</select> <span class="description">Se descarga a la Biblioteca de medios y WordPress la recorta a 880×1140.</span></td></tr>
			</table>
			<p class="description">Si un campo tiene otro nombre en vuestro Qobrix, cámbialo aquí. Puedes poner varios separados por comas: se usa el primero que tenga valor.</p>

			<?php submit_button( 'Guardar ajustes' ); ?>
		</form>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="zq_sync_now">
			<?php wp_nonce_field( 'zq_sync_now' ); ?>
			<?php submit_button( 'Sincronizar ahora', 'secondary', 'submit', false ); ?>
			<span class="description">&nbsp;Se sincroniza sola cada hora. Shortcode: <code>[zorom_promociones limite="4"]</code></span>
		</form>

		<?php if ( $list ) : ?>
			<h2>Promociones sincronizadas</h2>
			<table class="widefat striped" style="max-width:1100px">
				<thead><tr><th>Nombre</th><th>ID Qobrix</th><th>Precio</th><th>Unidades</th><th>Fecha</th><th>Imagen</th><th>Enlace</th></tr></thead>
				<tbody>
				<?php foreach ( $list as $p ) : ?>
					<tr>
						<td><?php echo esc_html( $p['name'] ); ?></td>
						<td><code><?php echo esc_html( $p['id'] ); ?></code></td>
						<td><?php echo esc_html( zq_format_price( $p['price'] ) ); ?></td>
						<td><?php echo esc_html( (string) $p['units'] ); ?></td>
						<td><?php echo esc_html( zq_format_date( $p['date'] ) ); ?></td>
						<td><?php echo ! empty( $p['image_id'] ) ? wp_get_attachment_image( $p['image_id'], array( 60, 60 ) ) : '—'; ?></td>
						<td><a href="<?php echo esc_url( zq_project_link( $p ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( zq_project_link( $p ) ); ?></a></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<?php if ( ! empty( $status['sample'] ) ) : ?>
			<h2>Campos disponibles en Qobrix (primer proyecto)</h2>
			<details><summary>Ver campos para ajustar el mapeo</summary>
				<table class="widefat striped" style="max-width:900px"><tbody>
				<?php foreach ( $status['sample'] as $k => $v ) : ?>
					<tr><td><code><?php echo esc_html( $k ); ?></code></td><td><?php echo esc_html( $v ); ?></td></tr>
				<?php endforeach; ?>
				</tbody></table>
			</details>
		<?php endif; ?>
	</div>
	<?php
}
