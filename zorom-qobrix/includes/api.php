<?php
/**
 * Cliente de la API de Qobrix y sincronización de proyectos → entradas «Promoción».
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function zq_api_get( $path, $params = array() ) {
	$o = zq_options();
	if ( empty( $o['api_url'] ) || empty( $o['api_user'] ) || empty( $o['api_key'] ) ) {
		return new WP_Error( 'zq_config', 'Faltan la URL, el usuario o la clave de la API en Qobrix → Ajustes.' );
	}

	$url = add_query_arg( array_map( 'rawurlencode', $params ), rtrim( $o['api_url'], '/' ) . '/api/v2/' . ltrim( $path, '/' ) );
	$res = wp_remote_get( $url, array( 'headers' => zq_auth_headers() + array( 'Accept' => 'application/json' ), 'timeout' => 30 ) );
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

function zq_auth_headers() {
	$o       = zq_options();
	$headers = array(
		'X-Api-User' => $o['api_user'],
		'X-Api-Key'  => $o['api_key'],
	);
	if ( ! empty( $o['locale'] ) ) {
		$headers['X-Locale']        = $o['locale'];
		$headers['Accept-Language'] = $o['locale'];
	}
	return $headers;
}

/** Todos los registros de un recurso, recorriendo la paginación. */
function zq_fetch_all( $resource, $params = array() ) {
	$all  = array();
	$page = 1;
	do {
		$res = zq_api_get( $resource, array_merge( $params, array( 'limit' => 100, 'page' => $page ) ) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$all  = array_merge( $all, isset( $res['data'] ) ? (array) $res['data'] : array() );
		$more = ! empty( $res['pagination']['has_next_page'] );
		$page++;
	} while ( $more && $page <= 50 );

	return $all;
}

function zq_fetch_all_projects() {
	return zq_fetch_all( 'projects' );
}

/** Entrada «Promoción» vinculada a un ID de Qobrix (cualquier estado). */
function zq_find_post_by_qobrix_id( $qobrix_id ) {
	$ids = get_posts( array(
		'post_type'        => ZQ_CPT,
		'post_status'      => 'any',
		'meta_key'         => '_qobrix_id',
		'meta_value'       => $qobrix_id,
		'fields'           => 'ids',
		'posts_per_page'   => 1,
		'suppress_filters' => true,
	) );
	return $ids ? (int) $ids[0] : 0;
}

/**
 * Sincroniza todos los proyectos. Solo reescribe una promoción si ha cambiado en Qobrix
 * (campo «modified»), salvo que $force sea true.
 *
 * Qobrix es la fuente de: título, descripción, extracto, imagen destacada y campos qobrix_*.
 * WordPress conserva: slug (URL), orden, estado manual y el diseño de Elementor.
 */
function zq_sync( $force = false ) {
	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	}

	$o   = zq_options();
	$raw = zq_fetch_all_projects();

	if ( is_wp_error( $raw ) ) {
		update_option( ZQ_STATUS, array( 'time' => time(), 'error' => $raw->get_error_message() ), false );
		return $raw;
	}

	$seen         = array();
	$updated      = 0;
	$fields  = array();

	foreach ( $raw as $row ) {
		if ( empty( $row['id'] ) ) {
			continue;
		}
		$qid      = (string) $row['id'];
		$name     = isset( $row['name'] ) ? (string) $row['name'] : $qid;
		$modified = isset( $row['modified'] ) ? (string) $row['modified'] : '';
		$post_id  = zq_find_post_by_qobrix_id( $qid );

		foreach ( $row as $k => $v ) {
			$txt = ( is_scalar( $v ) || null === $v ) ? wp_strip_all_tags( (string) $v ) : wp_json_encode( $v, JSON_UNESCAPED_UNICODE );
			$fields[ $k ] = isset( $fields[ $k ] ) && '' !== $fields[ $k ] && '[]' !== $fields[ $k ] ? $fields[ $k ] : mb_substr( $txt, 0, 160 );
		}

		if ( $post_id && get_post_meta( $post_id, '_zq_hidden', true ) ) {
			wp_update_post( array( 'ID' => $post_id, 'post_status' => 'publish' ) );
			delete_post_meta( $post_id, '_zq_hidden' );
		}

		$changed = $force || ! $post_id || get_post_meta( $post_id, '_qobrix_modified', true ) !== $modified;
		if ( ! $changed ) {
			$seen[] = $post_id;
			continue;
		}

		$postarr = array(
			'post_type'    => ZQ_CPT,
			'post_title'   => $name,
			'post_content' => wp_kses_post( (string) zq_pick( $row, $o['field_desc'] ) ),
			'post_excerpt' => wp_kses_post( isset( $row['short_description'] ) ? (string) $row['short_description'] : '' ),
		);
		if ( $post_id ) {
			$postarr['ID'] = $post_id;
			$post_id       = wp_update_post( $postarr, true );
		} else {
			$postarr['post_status'] = 'publish';
			$postarr['post_name']   = sanitize_title( $name );
			$post_id                = wp_insert_post( $postarr, true );
		}
		if ( is_wp_error( $post_id ) || ! $post_id ) {
			continue;
		}

		foreach ( $row as $k => $v ) {
			$key = ZQ_META_PREFIX . sanitize_key( $k );
			if ( is_scalar( $v ) || null === $v ) {
				update_post_meta( $post_id, $key, is_bool( $v ) ? (int) $v : (string) $v );
			} else {
				// Listas y objetos (traducciones, ubicaciones…) se guardan como JSON.
				update_post_meta( $post_id, $key, wp_slash( wp_json_encode( $v ) ) );
			}
		}
		update_post_meta( $post_id, '_qobrix_id', $qid );
		update_post_meta( $post_id, '_qobrix_modified', $modified );

		// Las fotos se descargan después, por tandas (ver zq_sync_media).
		update_post_meta( $post_id, '_zq_media_pending', $force ? 'force' : 1 );

		$seen[] = $post_id;
		$updated++;
	}

	// Los proyectos que ya no están en Qobrix pasan a borrador (no se borran).
	$orphans = get_posts( array(
		'post_type'      => ZQ_CPT,
		'post_status'    => 'publish',
		'meta_key'       => '_qobrix_id',
		'fields'         => 'ids',
		'posts_per_page' => -1,
		'post__not_in'   => $seen ? $seen : array( 0 ),
	) );
	foreach ( $orphans as $id ) {
		wp_update_post( array( 'ID' => $id, 'post_status' => 'draft' ) );
		update_post_meta( $id, '_zq_hidden', 1 );
	}

	$units = zq_sync_units( $seen );
	$media = zq_sync_media( 20 );

	ksort( $fields );
	update_option( ZQ_STATUS, array(
		'time'    => time(),
		'error'   => '',
		'total'   => count( $raw ),
		'updated' => $updated,
		'hidden'  => count( $orphans ),
		'fields'  => $fields,
		'units'   => $units,
		'gallery' => $media['note'],
		'pending' => $media['pending'],
	), false );

	return true;
}

/**
 * Descarga las fotos pendientes (destacada + galería) durante como mucho $seconds segundos.
 * Lo que no da tiempo queda pendiente y se programa una continuación en segundo plano,
 * para no superar el tiempo máximo de una petición del servidor.
 */
function zq_sync_media( $seconds = 20 ) {
	$GLOBALS['zq_deadline'] = time() + $seconds;
	$note    = '';
	$pending = 0;

	$ids = get_posts( array(
		'post_type'      => ZQ_CPT,
		'post_status'    => 'any',
		'meta_key'       => '_zq_media_pending',
		'fields'         => 'ids',
		'posts_per_page' => -1,
	) );
	foreach ( $ids as $post_id ) {
		if ( zq_out_of_time() ) {
			$pending++;
			continue;
		}
		$qid  = get_post_meta( $post_id, '_qobrix_id', true );
		$name = get_the_title( $post_id );
		$flag = get_post_meta( $post_id, '_zq_media_pending', true );

		if ( 'gallery' !== $flag ) { // La destacada solo una vez por cambio.
			zq_sync_image( $post_id, $qid, $name );
			update_post_meta( $post_id, '_zq_media_pending', 'gallery' );
		}
		$r = zq_sync_gallery( $post_id, $qid, $name );
		if ( 'partial' === $r ) {
			$pending++;
			continue;
		}
		$note = $r ? $r : $note;
		delete_post_meta( $post_id, '_zq_media_pending' );
	}

	if ( $pending && ! wp_next_scheduled( 'zq_media_event' ) ) {
		wp_schedule_single_event( time() + 60, 'zq_media_event' );
	}
	return array( 'note' => $note, 'pending' => $pending );
}

add_action( 'zq_media_event', function () {
	$r      = zq_sync_media( 25 );
	$status = (array) get_option( ZQ_STATUS, array() );
	$status['pending'] = $r['pending'];
	if ( $r['note'] ) {
		$status['gallery'] = $r['note'];
	}
	update_option( ZQ_STATUS, $status, false );
} );

function zq_out_of_time() {
	return isset( $GLOBALS['zq_deadline'] ) && time() >= $GLOBALS['zq_deadline'];
}

/** Primer campo no vacío de una lista separada por comas. */
function zq_pick( array $row, $fields ) {
	foreach ( array_map( 'trim', explode( ',', $fields ) ) as $f ) {
		if ( '' !== $f && isset( $row[ $f ] ) && '' !== $row[ $f ] && null !== $row[ $f ] ) {
			return $row[ $f ];
		}
	}
	return '';
}

function zq_remote_image_url( $qobrix_id ) {
	$o = zq_options();
	return rtrim( $o['api_url'], '/' ) . '/api/v2/media/icon/projects/' . rawurlencode( $qobrix_id ) . '/featured_photo/' . rawurlencode( $o['image_variant'] );
}

/**
 * Descarga la foto destacada a la Biblioteca de medios y la asigna como imagen destacada,
 * para que WordPress genere los tamaños recortados. Si falla, conserva la anterior.
 */
function zq_sync_image( $post_id, $qobrix_id, $name ) {
	$att = zq_sideload( zq_remote_image_url( $qobrix_id ), $post_id, $name );
	if ( ! $att ) {
		return 0;
	}
	update_post_meta( $att, '_zq_project_id', $qobrix_id );

	// Sustituir la imagen anterior solo si la puso este plugin.
	$old = (int) get_post_thumbnail_id( $post_id );
	set_post_thumbnail( $post_id, $att );
	if ( $old && $old !== $att && get_post_meta( $old, '_zq_project_id', true ) ) {
		wp_delete_attachment( $old, true );
	}
	return $att;
}

/* -------------------------------------------------------------------------
 * Inmuebles de cada proyecto («Lista de propiedades»)
 * ---------------------------------------------------------------------- */

/**
 * Descarga todos los inmuebles asociados a algún proyecto y los guarda, agrupados,
 * en el meta «_zq_unidades» de cada promoción. Se hace en cada sincronización porque
 * el estado de una vivienda (vendida/disponible) cambia sin que cambie el proyecto.
 * Devuelve un resumen para el panel o el mensaje de error.
 */
function zq_sync_units( array $post_ids ) {
	$rows = zq_fetch_all( 'properties', array( 'search' => 'project != null' ) );
	if ( is_wp_error( $rows ) ) {
		return array( 'error' => $rows->get_error_message() );
	}

	$by_project = array();
	$sample     = array();
	foreach ( $rows as $row ) {
		$project = isset( $row['project'] ) ? $row['project'] : '';
		if ( is_array( $project ) ) { // Por si llega expandido.
			$project = isset( $project['id'] ) ? $project['id'] : '';
		}
		if ( ! $project ) {
			continue;
		}
		$unit = array();
		foreach ( $row as $k => $v ) {
			if ( is_scalar( $v ) || null === $v ) {
				$unit[ $k ] = is_bool( $v ) ? (int) $v : $v;
			}
		}
		$by_project[ $project ][] = $unit;
		if ( ! $sample ) {
			$sample = array_map( function ( $v ) {
				return mb_substr( wp_strip_all_tags( (string) $v ), 0, 60 );
			}, $unit );
		}
	}

	foreach ( $post_ids as $post_id ) {
		$qid   = get_post_meta( $post_id, '_qobrix_id', true );
		$units = isset( $by_project[ $qid ] ) ? $by_project[ $qid ] : array();
		usort( $units, function ( $a, $b ) {
			return strnatcmp( (string) ( isset( $a['ref'] ) ? $a['ref'] : '' ), (string) ( isset( $b['ref'] ) ? $b['ref'] : '' ) );
		} );
		update_post_meta( $post_id, '_zq_unidades', wp_slash( wp_json_encode( $units ) ) );
	}

	ksort( $sample );
	return array( 'total' => count( $rows ), 'fields' => $sample );
}

function zq_get_units( $post_id ) {
	$units = json_decode( (string) get_post_meta( $post_id, '_zq_unidades', true ), true );
	return is_array( $units ) ? $units : array();
}

/* -------------------------------------------------------------------------
 * Galería de fotos del proyecto
 * ---------------------------------------------------------------------- */

/**
 * Descarga a la Biblioteca de medios las fotos del proyecto (hasta el máximo de Ajustes)
 * y guarda sus IDs, en orden, en «_zq_galeria». Solo se ejecuta cuando el proyecto cambia.
 */
function zq_sync_gallery( $post_id, $qobrix_id, $name ) {
	$o     = zq_options();
	$max   = max( 0, (int) $o['gallery_max'] );
	$known = (array) get_post_meta( $post_id, '_zq_galeria_map', true ); // id media Qobrix => id adjunto.
	if ( ! $max ) {
		return '';
	}

	$res = zq_api_get( 'media', array( 'related_model' => 'Projects', 'related_id' => $qobrix_id, 'limit' => 100 ) );
	if ( is_wp_error( $res ) ) {
		return $res->get_error_message();
	}
	$items = isset( $res['data'] ) ? (array) $res['data'] : array();

	// Solo fotos: fuera documentos, folletos, planos y vídeos.
	$items = array_values( array_filter( $items, function ( $m ) {
		$cat  = isset( $m['category'] ) ? $m['category'] : '';
		$cat  = is_array( $cat ) ? ( isset( $cat['name'] ) ? $cat['name'] : '' ) : (string) $cat;
		$mime = isset( $m['file']['mime_type'] ) ? (string) $m['file']['mime_type'] : '';
		if ( empty( $m['id'] ) || ( $mime && 0 !== strpos( $mime, 'image/' ) ) ) {
			return false;
		}
		$type = isset( $m['media_type'] ) && is_string( $m['media_type'] ) ? $m['media_type'] : '';
		return ! preg_match( '/document|brochure|pdf|floor|plan|video/', strtolower( $type . ' ' . $cat ) );
	} ) );
	usort( $items, function ( $a, $b ) {
		return (int) ( isset( $a['display_order'] ) ? $a['display_order'] : 0 ) - (int) ( isset( $b['display_order'] ) ? $b['display_order'] : 0 );
	} );
	$items = array_slice( $items, 0, $max );

	$map  = array();
	$ids  = array();
	$tried = array();
	foreach ( $items as $i => $m ) {
		$mid = (string) $m['id'];
		$att = isset( $known[ $mid ] ) && get_post( $known[ $mid ] ) ? (int) $known[ $mid ] : 0;
		if ( ! $att && zq_out_of_time() ) {
			// Sin tiempo: guardar lo hecho y seguir en la próxima tanda.
			update_post_meta( $post_id, '_zq_galeria_map', $map + $known );
			return 'partial';
		}
		foreach ( $att ? array() : zq_media_candidates( $m ) as $url ) {
			$att = zq_sideload( $url, $post_id, sprintf( '%s %d', $name, $i + 1 ), zq_is_api_url( $url ) );
			if ( ! $att && 0 === $i ) {
				$tried[] = preg_replace( '#^https?://[^/]+#', '', $url ) . ' → ' . ( isset( $GLOBALS['zq_last_dl'] ) ? $GLOBALS['zq_last_dl'] : '?' );
			}
			if ( $att ) {
				break;
			}
		}
		if ( $att ) {
			update_post_meta( $att, '_zq_project_id', $qobrix_id );
			$map[ $mid ] = $att;
			$ids[]       = $att;
		}
	}

	// Borrar las fotos que ya no están en Qobrix.
	foreach ( $known as $mid => $att ) {
		if ( ! isset( $map[ $mid ] ) && get_post_meta( $att, '_zq_project_id', true ) ) {
			wp_delete_attachment( $att, true );
		}
	}

	update_post_meta( $post_id, '_zq_galeria_map', $map );
	update_post_meta( $post_id, '_zq_galeria', $ids );

	if ( count( $items ) && ! $ids ) {
		// Diagnóstico para el panel: qué devuelve Qobrix para la primera foto y qué se intentó.
		$sample = array();
		foreach ( $items[0] as $k => $v ) {
			$sample[] = $k . '=' . ( is_scalar( $v ) ? mb_substr( (string) $v, 0, 90 ) : wp_json_encode( $v ) );
		}
		return 'No se pudo descargar ninguna foto de la galería. Primera foto de Qobrix: ' . implode( ' · ', $sample ) . ' | Intentos: ' . implode( ' ; ', $tried );
	}
	return '';
}

/** URLs posibles de una foto, en orden: las que vengan en la respuesta y las rutas conocidas de la API. */
function zq_media_candidates( array $m ) {
	$base = rtrim( zq_options()['api_url'], '/' );
	$out  = array();

	// Formato de Qobrix: file.thumbnails.{xlarge,large,huge…} y file.href (original, puede pesar varios MB).
	$file = isset( $m['file'] ) && is_array( $m['file'] ) ? $m['file'] : array();
	$thumbs = isset( $file['thumbnails'] ) && is_array( $file['thumbnails'] ) ? $file['thumbnails'] : array();
	foreach ( array( 'xlarge', 'large', 'huge', 'medium' ) as $size ) {
		if ( ! empty( $thumbs[ $size ] ) && is_string( $thumbs[ $size ] ) ) {
			$out[] = preg_match( '#^https?://#', $thumbs[ $size ] ) ? $thumbs[ $size ] : $base . '/' . ltrim( $thumbs[ $size ], '/' );
		}
	}
	if ( ! empty( $file['href'] ) && is_string( $file['href'] ) ) {
		$out[] = preg_match( '#^https?://#', $file['href'] ) ? $file['href'] : $base . '/' . ltrim( $file['href'], '/' );
	}

	foreach ( $m as $k => $v ) {
		if ( ! is_string( $v ) ) {
			continue;
		}
		if ( preg_match( '#^https?://#', $v ) ) {
			$out[] = $v;
		} elseif ( preg_match( '#^/[^\s]+\.(jpe?g|png|webp|gif)#i', $v ) || 0 === strpos( $v, '/api/' ) ) {
			$out[] = $base . $v;
		}
	}
	$id = rawurlencode( (string) $m['id'] );
	foreach ( array( 'large', 'huge', 'medium', '' ) as $size ) {
		$out[] = $base . '/api/v2/media/' . $id . ( $size ? '/' . $size : '' );
	}
	return array_values( array_unique( $out ) );
}

function zq_is_api_url( $url ) {
	return wp_parse_url( $url, PHP_URL_HOST ) === wp_parse_url( zq_options()['api_url'], PHP_URL_HOST );
}

/**
 * Descarga una imagen y la añade a la Biblioteca de medios. Con $auth, la petición a Qobrix
 * lleva las credenciales, pero la redirección al almacenamiento de ficheros no (para no
 * enviar la clave a otro servidor).
 */
function zq_sideload( $url, $post_id, $title, $auth = false ) {
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	if ( $auth ) {
		$head = wp_remote_get( $url, array( 'headers' => zq_auth_headers(), 'redirection' => 0, 'timeout' => 30 ) );
		if ( is_wp_error( $head ) ) {
			$GLOBALS['zq_last_dl'] = $head->get_error_message();
			return 0;
		}
		$location = wp_remote_retrieve_header( $head, 'location' );
		$GLOBALS['zq_last_dl'] = 'HTTP ' . wp_remote_retrieve_response_code( $head ) . ' ' . wp_remote_retrieve_header( $head, 'content-type' );
		if ( $location ) {
			$url = $location;
		} elseif ( 200 !== wp_remote_retrieve_response_code( $head ) ) {
			return 0;
		}
		// Si respondió 200 sin redirigir, la imagen viene en la propia respuesta.
		if ( ! $location ) {
			$tmp = wp_tempnam( $url );
			file_put_contents( $tmp, wp_remote_retrieve_body( $head ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}
	}
	if ( empty( $tmp ) ) {
		$tmp = download_url( $url, 60 );
		if ( is_wp_error( $tmp ) ) {
			$GLOBALS['zq_last_dl'] = $tmp->get_error_message();
			return 0;
		}
	}

	$mime = wp_get_image_mime( $tmp );
	$ext  = array( 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif' );
	if ( ! isset( $ext[ $mime ] ) ) {
		$GLOBALS['zq_last_dl'] = ( isset( $GLOBALS['zq_last_dl'] ) ? $GLOBALS['zq_last_dl'] : '' ) . ' (no es imagen)';
		wp_delete_file( $tmp );
		return 0;
	}

	$att = media_handle_sideload( array( 'name' => sanitize_title( $title ) . '.' . $ext[ $mime ], 'tmp_name' => $tmp ), $post_id, $title );
	if ( is_wp_error( $att ) ) {
		wp_delete_file( $tmp );
		return 0;
	}
	update_post_meta( $att, '_wp_attachment_image_alt', $title );
	return (int) $att;
}
