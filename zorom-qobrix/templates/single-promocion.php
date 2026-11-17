<?php
/**
 * Página de una promoción pintada con la plantilla de Elementor elegida en Qobrix → Ajustes.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

while ( have_posts() ) :
	the_post();
	echo '<main id="content" class="zq-single">';
	echo zq_render_template( zq_single_template_id(), get_post() ); // phpcs:ignore WordPress.Security.EscapeOutput
	echo '</main>';
endwhile;

get_footer();
