<?php
/**
 * Panel Qobrix → Ajustes y columnas del listado de promociones.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', function () {
	add_menu_page( 'Qobrix', 'Qobrix', 'manage_options', 'zorom-qobrix', 'zq_settings_page', 'dashicons-building', 26 );
	add_submenu_page( 'zorom-qobrix', 'Ajustes de Qobrix', 'Ajustes y sincronización', 'manage_options', 'zorom-qobrix', 'zq_settings_page' );
} );

add_action( 'admin_init', function () {
	register_setting( 'zq', ZQ_OPTION, array( 'sanitize_callback' => 'zq_sanitize' ) );
} );

function zq_sanitize( $in ) {
	$old = zq_options();
	$out = zq_defaults();
	$in  = (array) $in;

	$out['api_url']  = esc_url_raw( rtrim( trim( isset( $in['api_url'] ) ? $in['api_url'] : '' ), '/' ) );
	$out['api_user'] = sanitize_text_field( isset( $in['api_user'] ) ? $in['api_user'] : '' );
	// La clave no se muestra en el formulario: si llega vacía se conserva la anterior.
	$out['api_key'] = ! empty( $in['api_key'] ) ? sanitize_text_field( $in['api_key'] ) : $old['api_key'];
	$out['locale']  = sanitize_text_field( isset( $in['locale'] ) ? $in['locale'] : '' );
	$out['slug']    = sanitize_title( isset( $in['slug'] ) ? $in['slug'] : '' );
	$out['slug']    = $out['slug'] ? $out['slug'] : 'promociones';
	$out['single_tpl']  = absint( isset( $in['single_tpl'] ) ? $in['single_tpl'] : 0 );
	$out['form_shortcode'] = isset( $in['form_shortcode'] ) ? sanitize_text_field( wp_unslash( $in['form_shortcode'] ) ) : '';
	$out['form_tpl']       = absint( isset( $in['form_tpl'] ) ? $in['form_tpl'] : 0 );
	$out['menu_height']    = min( 300, absint( isset( $in['menu_height'] ) ? $in['menu_height'] : 80 ) );
	$out['menu_transparente'] = empty( $in['menu_transparente'] ) ? 0 : 1;
	$out['load_fonts']     = empty( $in['load_fonts'] ) ? 0 : 1;
	$out['gallery_max'] = min( 50, absint( isset( $in['gallery_max'] ) ? $in['gallery_max'] : 12 ) );
	foreach ( array( 'field_desc', 'field_price', 'field_units', 'field_date', 'field_energy' ) as $k ) {
		$out[ $k ] = preg_replace( '/[^a-z0-9_,]/i', '', isset( $in[ $k ] ) ? $in[ $k ] : '' );
	}
	$variant              = isset( $in['image_variant'] ) ? $in['image_variant'] : '';
	$out['image_variant'] = in_array( $variant, array( 'huge', 'large', 'medium' ), true ) ? $variant : 'huge';

	if ( $out['slug'] !== $old['slug'] ) {
		update_option( 'zq_flush', 1 );
	}
	return $out;
}

add_action( 'admin_post_zq_sync_now', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Sin permiso.' );
	}
	check_admin_referer( 'zq_sync_now' );
	zq_sync( ! empty( $_POST['zq_force'] ) );
	wp_safe_redirect( admin_url( 'admin.php?page=zorom-qobrix' ) );
	exit;
} );

function zq_settings_page() {
	$o      = zq_options();
	$status = (array) get_option( ZQ_STATUS, array() );
	$name   = ZQ_OPTION;
	?>
	<div class="wrap">
		<h1>Promociones Qobrix</h1>

		<?php if ( ! empty( $status['time'] ) ) : ?>
			<div class="notice <?php echo empty( $status['error'] ) ? 'notice-success' : 'notice-error'; ?>"><p>
				Última sincronización: <?php echo esc_html( wp_date( 'd/m/Y H:i', $status['time'] ) ); ?> —
				<?php
				echo empty( $status['error'] )
					? esc_html( sprintf( '%d proyectos en Qobrix, %d actualizados, %d pasados a borrador.', $status['total'], $status['updated'], $status['hidden'] ) )
					: esc_html( 'Error: ' . $status['error'] );
				?>
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . ZQ_CPT ) ); ?>">Ver promociones</a>
			</p>
			<?php if ( ! empty( $status['units']['error'] ) ) : ?>
				<p><strong>Inmuebles:</strong> <?php echo esc_html( $status['units']['error'] ); ?></p>
			<?php elseif ( isset( $status['units']['total'] ) ) : ?>
				<p>Inmuebles vinculados a proyectos: <?php echo (int) $status['units']['total']; ?>.</p>
			<?php endif; ?>
			<?php if ( ! empty( $status['pending'] ) ) : ?>
				<p><strong>Fotos:</strong> descargando en segundo plano (<?php echo (int) $status['pending']; ?> promociones pendientes). Se completan solas en unos minutos; recarga esta página para ver el avance.</p>
			<?php endif; ?>
			<?php if ( ! empty( $status['gallery'] ) ) : ?>
				<p><strong>Galería:</strong> <?php echo esc_html( $status['gallery'] ); ?></p>
			<?php endif; ?>
			</div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:1em 0">
			<input type="hidden" name="action" value="zq_sync_now">
			<?php wp_nonce_field( 'zq_sync_now' ); ?>
			<?php submit_button( 'Sincronizar ahora', 'primary', 'submit', false ); ?>
			<label style="margin-left:1em"><input type="checkbox" name="zq_force" value="1"> Forzar (reescribir todas y volver a descargar imágenes)</label>
			<p class="description">Se sincroniza sola cada hora. Solo se reescriben las promociones que han cambiado en Qobrix.</p>
		</form>

		<form method="post" action="options.php">
			<?php settings_fields( 'zq' ); ?>
			<h2>Conexión</h2>
			<table class="form-table" role="presentation">
				<tr><th><label for="zq-url">URL del CRM</label></th>
					<td><input id="zq-url" class="regular-text" type="url" name="<?php echo esc_attr( $name ); ?>[api_url]" value="<?php echo esc_attr( $o['api_url'] ); ?>"></td></tr>
				<tr><th><label for="zq-user">Usuario API</label></th>
					<td><input id="zq-user" class="regular-text" type="text" autocomplete="off" name="<?php echo esc_attr( $name ); ?>[api_user]" value="<?php echo esc_attr( $o['api_user'] ); ?>"></td></tr>
				<tr><th><label for="zq-key">Clave API</label></th>
					<td><input id="zq-key" class="regular-text" type="password" autocomplete="new-password" name="<?php echo esc_attr( $name ); ?>[api_key]" value="" placeholder="<?php echo esc_attr( $o['api_key'] ? '•••••••• (guardada; déjalo vacío para mantenerla)' : '' ); ?>"></td></tr>
				<tr><th><label for="zq-locale">Idioma (X-Locale)</label></th>
					<td><input id="zq-locale" class="small-text" type="text" name="<?php echo esc_attr( $name ); ?>[locale]" value="<?php echo esc_attr( $o['locale'] ); ?>"> <span class="description">Ej.: es, en. Vacío = idioma por defecto del CRM.</span></td></tr>
			</table>

			<h2>Páginas de promoción</h2>
			<table class="form-table" role="presentation">
				<tr><th><label for="zq-slug">Base de la URL</label></th>
					<td><code><?php echo esc_html( home_url( '/' ) ); ?></code><input id="zq-slug" class="regular-text code" type="text" name="<?php echo esc_attr( $name ); ?>[slug]" value="<?php echo esc_attr( $o['slug'] ); ?>"><code>/amiral/</code>
						<p class="description">El final de cada URL se puede cambiar en la propia promoción y la sincronización no lo toca.</p></td></tr>
				<tr><th><label for="zq-ftpl">Formulario (Elementor)</label></th>
					<td><select id="zq-ftpl" name="<?php echo esc_attr( $name ); ?>[form_tpl]">
						<option value="0">— Automático: el global «Form_contacto» —</option>
						<?php foreach ( get_posts( array( 'post_type' => 'elementor_library', 'post_status' => 'publish', 'posts_per_page' => 200, 'orderby' => 'title', 'order' => 'ASC' ) ) as $t ) : ?>
							<option value="<?php echo (int) $t->ID; ?>" <?php selected( (int) $o['form_tpl'], $t->ID ); ?>><?php echo esc_html( $t->post_title . ' (#' . $t->ID . ')' ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="description">Global o plantilla de Elementor con el formulario de la sección «Contacta». Actual: <?php echo zq_form_template_id() ? esc_html( get_the_title( zq_form_template_id() ) ) : 'ninguno encontrado'; ?>.</p></td></tr>
				<tr><th><label for="zq-menu">Menú sobre la portada</label></th>
					<td><input id="zq-menu" class="small-text" type="number" min="0" max="300" name="<?php echo esc_attr( $name ); ?>[menu_height]" value="<?php echo (int) $o['menu_height']; ?>"> px de alto del menú (la portada sube esa medida para quedar debajo)<br>
					<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[menu_transparente]" value="1" <?php checked( $o['menu_transparente'] ); ?>> Fondo del menú transparente en las páginas de promoción</label></td></tr>
				<tr><th><label for="zq-form">Formulario de contacto (shortcode)</label></th>
					<td><input id="zq-form" class="regular-text code" type="text" name="<?php echo esc_attr( $name ); ?>[form_shortcode]" value="<?php echo esc_attr( $o['form_shortcode'] ); ?>" placeholder='[contact-form-7 id="123"]'>
						<p class="description">Shortcode del formulario que se muestra en la sección «Contacta» de cada promoción (Contact Form 7, Elementor Pro…). Se le aplica el estilo del diseño.</p>
						<details><summary>Formulario de Contact Form 7 con la maqueta del diseño (copiar en el formulario)</summary>
<pre style="white-space:pre-wrap;background:#f6f7f7;padding:10px">&lt;div class="zq-campos"&gt;
[text* nombre placeholder "Nombre *"]
[text* apellidos placeholder "Apellidos *"]
[email* email placeholder "Email *"]
[tel telefono placeholder "Teléfono"]
&lt;div class="zq-campo--ancho"&gt;[textarea mensaje placeholder "Mensaje"]&lt;/div&gt;
&lt;/div&gt;
[hidden promocion default:get]
[submit "Enviar"]</pre></details></td></tr>
				<tr><th>Tipografías</th>
					<td><label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[load_fonts]" value="1" <?php checked( $o['load_fonts'] ); ?>> Cargar Geist e Inter (Google Fonts) en las páginas de promoción</label>
						<p class="description">Desmárcalo si el tema ya las carga.</p></td></tr>
				<tr><th><label for="zq-tpl">Plantilla de Elementor</label></th>
					<td><select id="zq-tpl" name="<?php echo esc_attr( $name ); ?>[single_tpl]">
						<option value="0">— Diseño del tema —</option>
						<?php
						$tpls = get_posts( array( 'post_type' => 'elementor_library', 'post_status' => 'publish', 'posts_per_page' => 200, 'orderby' => 'title', 'order' => 'ASC' ) );
						foreach ( $tpls as $t ) :
							?>
							<option value="<?php echo (int) $t->ID; ?>" <?php selected( (int) $o['single_tpl'], $t->ID ); ?>><?php echo esc_html( $t->post_title . ' (#' . $t->ID . ')' ); ?></option>
						<?php endforeach; ?>
					</select>
						<p class="description">Todas las páginas de promoción se pintan con esta plantilla (Plantillas → Plantillas guardadas). Dentro, los shortcodes y etiquetas Qobrix muestran los datos de cada promoción.<br>
						<?php if ( class_exists( '\Elementor\Plugin' ) ) : ?>
							<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=zq_create_template' ), 'zq_create_template' ) ); ?>">Crear plantilla de ejemplo (diseño Figma)</a><br>
						<?php endif; ?>
						Con Elementor Pro también puedes usar Theme Builder → Entrada individual → condición «Promociones»; en ese caso este ajuste se ignora.</p></td></tr>
			</table>

			<h2>Campos con nombre propio</h2>
			<table class="form-table" role="presentation">
				<?php
				$map = array(
					'field_desc'  => 'descripcion',
					'field_price' => 'precio',
					'field_units' => 'unidades',
					'field_date'   => 'fecha',
					'field_energy' => 'eficiencia',
				);
				foreach ( $map as $k => $alias ) :
					?>
					<tr><th><label for="zq-<?php echo esc_attr( $k ); ?>"><code><?php echo esc_html( $alias ); ?></code></label></th>
						<td><input id="zq-<?php echo esc_attr( $k ); ?>" class="regular-text code" type="text" name="<?php echo esc_attr( $name . '[' . $k . ']' ); ?>" value="<?php echo esc_attr( $o[ $k ] ); ?>"></td></tr>
				<?php endforeach; ?>
				<tr><th><label for="zq-img">Foto de Qobrix</label></th>
					<td><select id="zq-img" name="<?php echo esc_attr( $name ); ?>[image_variant]">
						<?php foreach ( array( 'huge', 'large', 'medium' ) as $v ) : ?>
							<option value="<?php echo esc_attr( $v ); ?>" <?php selected( $o['image_variant'], $v ); ?>><?php echo esc_html( $v ); ?></option>
						<?php endforeach; ?>
					</select> <span class="description">Se guarda en la Biblioteca de medios y se recorta a 880×1140 (tamaño <code><?php echo esc_html( ZQ_IMAGE_SIZE ); ?></code>).</span></td></tr>
				<tr><th><label for="zq-gal">Fotos de galería</label></th>
					<td><input id="zq-gal" class="small-text" type="number" min="0" max="50" name="<?php echo esc_attr( $name ); ?>[gallery_max]" value="<?php echo (int) $o['gallery_max']; ?>"> <span class="description">Máximo de fotos por promoción que se descargan de Qobrix (0 = ninguna).</span></td></tr>
			</table>
			<p class="description">Campo de Qobrix del que sale cada nombre propio. Varios separados por comas: se usa el primero con valor.</p>

			<?php submit_button( 'Guardar ajustes' ); ?>
		</form>

		<h2>Cómo usar los campos en Elementor</h2>
		<p>Con <strong>Elementor Pro</strong>: en cualquier campo pulsa el icono de etiquetas dinámicas → grupo <strong>Qobrix</strong> (Campo, Imagen o Enlace de la promoción).<br>
		Sin Pro, o donde prefieras: widget <em>Shortcode</em> o <em>Editor de texto</em> con estos códigos.</p>
		<table class="widefat striped" style="max-width:1100px">
			<thead><tr><th>Shortcode</th><th>Qué muestra</th><th>Ejemplo (primer proyecto)</th></tr></thead>
			<tbody>
				<?php
				$fields = isset( $status['fields'] ) ? (array) $status['fields'] : array();
				foreach ( zq_field_aliases() as $k => $a ) :
					?>
					<tr><td><code>[qobrix campo="<?php echo esc_html( $k ); ?>"]</code></td><td><?php echo esc_html( $a['label'] ); ?></td><td></td></tr>
				<?php endforeach; ?>
				<?php foreach ( $fields as $k => $v ) : ?>
					<tr><td><code>[qobrix campo="<?php echo esc_html( $k ); ?>"]</code></td><td>Campo de Qobrix</td><td><?php echo esc_html( $v ); ?></td></tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php if ( ! $fields ) : ?>
			<p><em>Sincroniza para ver aquí todos los campos que envía Qobrix.</em></p>
		<?php endif; ?>

		<?php if ( ! empty( $status['units']['fields'] ) ) : ?>
			<h3>Campos de los inmuebles (para <code>[qobrix_propiedades columnas="…"]</code>)</h3>
			<details><summary>Ver campos</summary>
			<table class="widefat striped" style="max-width:900px"><tbody>
				<?php foreach ( $status['units']['fields'] as $k => $v ) : ?>
					<tr><td><code><?php echo esc_html( $k ); ?></code></td><td><?php echo esc_html( $v ); ?></td></tr>
				<?php endforeach; ?>
			</tbody></table></details>
		<?php endif; ?>

		<h3>Bloques completos</h3>
		<ul style="list-style:disc;padding-left:20px">
			<li><code>[zorom_promociones limite="4" plantilla="ID"]</code> — Pestañas. Con <code>plantilla</code> cada pestaña pinta esa plantilla de Elementor (Plantillas → Plantillas guardadas, tipo «Sección» o «Contenedor»); sin ella, el diseño por defecto.</li>
			<li><code>[qobrix_listado columnas="3" plantilla="ID"]</code> — Rejilla con todas las promociones (página «Ver todas»).</li>
			<li><code>[qobrix_imagen campo="destacada|zona_imagen|zona2_imagen|galeria:1"]</code>, <code>[qobrix_enlace texto="Ver promoción"]</code> — Imagen y enlace.</li>
			<li><code>[qobrix_hero]</code> — Portada: imagen destacada de fondo, título, ubicación, frase y botón (<code>boton</code>, <code>enlace</code>, <code>altura</code>).</li>
			<li><code>[qobrix_datos campos="fecha:Fecha,precio:Precio,eficiencia:Eficiencia,unidades:Unidades"]</code> — Fichas con icono. Cada elemento: <code>campo:Etiqueta:icono:formato</code>. Iconos: fecha, precio, eficiencia, unidades, aeropuerto, playa, centro, hospital, escuela, tiendas.</li>
			<li><code>[qobrix_propiedades]</code> — Tabla de inmuebles. Columnas con <code>columnas="ref:Referencia,bedrooms:Dormitorios,list_selling_price_amount:Precio:precio,status:Estado:estado"</code> (formatos: precio, m2, estado).</li>
			<li><code>[qobrix_galeria desde="1" limite="2" columnas="2"]</code> y <code>[qobrix_mapa zoom="14"]</code>.</li>
			<li>Opciones comunes: <code>destacados="si"</code>, <code>ids="Amiral, Gran Sol Villas"</code>, <code>orden="menu|titulo|precio|fecha"</code>, <code>proyecto="Amiral"</code> (en campos sueltos).</li>
			<li>Opciones de <code>[qobrix]</code>: <code>formato="precio|numero|fecha|si_no|texto|html"</code>, <code>antes="Desde "</code>, <code>despues=" m²"</code>, <code>vacio="Consultar"</code>.</li>
		</ul>
	</div>
	<?php
}

/* Columnas en Promociones → Todas las promociones. */
add_filter( 'manage_' . ZQ_CPT . '_posts_columns', function ( $cols ) {
	$new = array();
	foreach ( $cols as $k => $v ) {
		$new[ $k ] = $v;
		if ( 'title' === $k ) {
			$new['zq_precio'] = 'Precio desde';
			$new['zq_id']     = 'ID Qobrix';
		}
	}
	return $new;
} );

add_action( 'manage_' . ZQ_CPT . '_posts_custom_column', function ( $col, $post_id ) {
	if ( 'zq_precio' === $col ) {
		echo zq_render_field( $post_id, 'precio' ); // phpcs:ignore WordPress.Security.EscapeOutput
	} elseif ( 'zq_id' === $col ) {
		echo '<code>' . esc_html( get_post_meta( $post_id, '_qobrix_id', true ) ) . '</code>';
	}
}, 10, 2 );
