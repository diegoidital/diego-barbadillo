<?php
/**
 * Página de un inmueble ([qobrix_vivienda]) con la cabecera y el pie del tema.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

while ( have_posts() ) :
	the_post();
	echo '<main id="content" class="zq-single zq-single--vivienda">';
	echo do_shortcode( '[qobrix_vivienda]' );
	echo '</main>';
endwhile;

get_footer();
