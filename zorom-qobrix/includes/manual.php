<?php
/**
 * Campos manuales de cada promoción (caja «Contenido de la página» en el editor).
 * La sincronización con Qobrix nunca los toca.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Definición de los campos manuales: clave => [etiqueta, tipo, ayuda]. */
function zq_manual_fields() {
	return apply_filters( 'zq_manual_fields', array(
		'Portada'                       => array(
			'ubicacion' => array( 'Ubicación', 'text', 'Ej.: Calpe, Alicante. Vacío = ciudad de Qobrix.' ),
			'subtitulo' => array( 'Frase de portada', 'text', 'Ej.: Adosados de lujo en Calpe' ),
		),
		'Zona (primer bloque)'          => array(
			'zona_titulo'    => array( 'Título', 'text', 'Ej.: Calpe, un paraíso natural' ),
			'zona_subtitulo' => array( 'Subtítulo', 'text', '' ),
			'zona_texto'     => array( 'Texto', 'html', '' ),
			'zona_imagen'    => array( 'Imagen', 'image', '' ),
		),
		'Descripción de la promoción'   => array(
			'descripcion_titulo' => array( 'Título', 'text', 'Ej.: Urbanización de lujo en Calpe. El texto viene de Qobrix.' ),
		),
		'Localización'                  => array(
			'localizacion_titulo' => array( 'Título', 'text', 'Ej.: Localización exclusiva' ),
			'localizacion_texto'  => array( 'Texto', 'html', '' ),
		),
		'Zona (segundo bloque)'         => array(
			'zona2_titulo' => array( 'Título', 'text', 'Ej.: Calpe, mucho más que playa y montaña' ),
			'zona2_texto'  => array( 'Texto', 'html', '' ),
			'zona2_imagen' => array( 'Imagen', 'image', '' ),
		),
	) );
}

/** Lista plana clave => [etiqueta, tipo]. */
function zq_manual_keys() {
	$out = array();
	foreach ( zq_manual_fields() as $group => $fields ) {
		foreach ( $fields as $k => $f ) {
			$out[ $k ] = array( $group . ' – ' . $f[0], $f[1] );
		}
	}
	return $out;
}

function zq_get_manual( $post_id, $key ) {
	return get_post_meta( $post_id, 'zq_' . $key, true );
}

add_action( 'add_meta_boxes', function () {
	add_meta_box( 'zq_manual', 'Contenido de la página (manual)', 'zq_manual_metabox', ZQ_CPT, 'normal', 'high' );
	add_meta_box( 'zq_qobrix', 'Datos de Qobrix (solo lectura)', 'zq_qobrix_metabox', ZQ_CPT, 'side', 'default' );
} );

add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( in_array( $hook, array( 'post.php', 'post-new.php' ), true ) && ZQ_CPT === get_post_type() ) {
		wp_enqueue_media();
	}
} );

function zq_manual_metabox( $post ) {
	wp_nonce_field( 'zq_manual', 'zq_manual_nonce' );
	echo '<p class="description">Estos textos no vienen de Qobrix y la sincronización no los cambia. En Elementor: <code>[qobrix campo="clave"]</code> o etiqueta dinámica Qobrix → Campo.</p>';

	foreach ( zq_manual_fields() as $group => $fields ) {
		echo '<h3 style="margin:1.5em 0 .5em">' . esc_html( $group ) . '</h3><table class="form-table" role="presentation">';
		foreach ( $fields as $key => $f ) {
			list( $label, $type, $help ) = $f;
			$name  = 'zq_manual[' . $key . ']';
			$value = zq_get_manual( $post->ID, $key );
			echo '<tr><th><label for="zq_' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label><br><code style="font-weight:normal">' . esc_html( $key ) . '</code></th><td>';

			if ( 'html' === $type ) {
				wp_editor( (string) $value, 'zq_' . $key, array(
					'textarea_name' => $name,
					'textarea_rows' => 8,
					'media_buttons' => false,
				) );
			} elseif ( 'image' === $type ) {
				$img = $value ? wp_get_attachment_image( (int) $value, 'medium', false, array( 'style' => 'max-width:240px;height:auto;display:block;margin-bottom:6px' ) ) : '';
				echo '<div class="zq-img-field"><div class="zq-img-preview">' . $img . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
				echo '<input type="hidden" id="zq_' . esc_attr( $key ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '">';
				echo '<button type="button" class="button zq-img-pick">Elegir imagen</button> <button type="button" class="button-link zq-img-remove"' . ( $value ? '' : ' style="display:none"' ) . '>Quitar</button></div>';
			} else {
				echo '<input type="text" class="large-text" id="zq_' . esc_attr( $key ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '">';
			}
			if ( $help ) {
				echo '<p class="description">' . esc_html( $help ) . '</p>';
			}
			echo '</td></tr>';
		}
		echo '</table>';
	}
	?>
	<script>
	jQuery( function ( $ ) {
		$( document ).on( 'click', '.zq-img-pick', function ( e ) {
			e.preventDefault();
			var box = $( this ).closest( '.zq-img-field' );
			var frame = wp.media( { title: 'Elegir imagen', multiple: false, library: { type: 'image' } } );
			frame.on( 'select', function () {
				var a = frame.state().get( 'selection' ).first().toJSON();
				var url = ( a.sizes && a.sizes.medium ? a.sizes.medium.url : a.url );
				box.find( 'input' ).val( a.id );
				box.find( '.zq-img-preview' ).html( '<img src="' + url + '" style="max-width:240px;height:auto;display:block;margin-bottom:6px">' );
				box.find( '.zq-img-remove' ).show();
			} );
			frame.open();
		} );
		$( document ).on( 'click', '.zq-img-remove', function ( e ) {
			e.preventDefault();
			var box = $( this ).closest( '.zq-img-field' );
			box.find( 'input' ).val( '' );
			box.find( '.zq-img-preview' ).empty();
			$( this ).hide();
		} );
	} );
	</script>
	<?php
}

function zq_qobrix_metabox( $post ) {
	$qid = get_post_meta( $post->ID, '_qobrix_id', true );
	echo '<p>ID Qobrix: <code>' . esc_html( $qid ) . '</code></p>';
	$rows = array(
		'Precio desde' => zq_render_field( $post->ID, 'precio' ),
		'Unidades'     => zq_render_field( $post->ID, 'unidades' ),
		'Fecha'        => zq_render_field( $post->ID, 'fecha' ),
		'Inmuebles'    => (string) count( zq_get_units( $post->ID ) ),
		'Fotos'        => (string) count( (array) get_post_meta( $post->ID, '_zq_galeria', true ) ),
	);
	echo '<table class="widefat striped"><tbody>';
	foreach ( $rows as $k => $v ) {
		echo '<tr><td>' . esc_html( $k ) . '</td><td>' . $v . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo '</tbody></table><p class="description">Título, descripción, imagen destacada, inmuebles y fotos se actualizan desde Qobrix. Para cambiarlos, edítalos en Qobrix.</p>';
}

add_action( 'save_post_' . ZQ_CPT, function ( $post_id ) {
	if ( ! isset( $_POST['zq_manual_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['zq_manual_nonce'] ), 'zq_manual' ) ) {
		return;
	}
	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	$in = isset( $_POST['zq_manual'] ) ? (array) wp_unslash( $_POST['zq_manual'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	foreach ( zq_manual_keys() as $key => $f ) {
		$v = isset( $in[ $key ] ) ? $in[ $key ] : '';
		if ( 'html' === $f[1] ) {
			$v = wp_kses_post( $v );
		} elseif ( 'image' === $f[1] ) {
			$v = absint( $v ) ? absint( $v ) : '';
		} else {
			$v = sanitize_text_field( $v );
		}
		if ( '' === $v ) {
			delete_post_meta( $post_id, 'zq_' . $key );
		} else {
			update_post_meta( $post_id, 'zq_' . $key, wp_slash( $v ) );
		}
	}
} );
