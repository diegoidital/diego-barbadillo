<?php
/**
 * Vídeo de portada de las promociones.
 *
 * - Campo «Vídeo de portada (URL .mp4)» en cada promoción (Portada).
 * - Amiral lleva incluido su vídeo (assets/video/amiral-portada.mp4).
 * - Sustituye a la imagen de la portada (la de la plantilla de Elementor o la del plugin),
 *   sin sonido, en bucle, con una capa negra suave para que se lea el texto.
 * - Si la portada de la plantilla no se detecta sola, añade la clase CSS «zq-hero» al contenedor de la portada.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** URL del vídeo de portada de una promoción ('' si no tiene). */
function zq_hero_video_url( $post_id ) {
	$v = trim( (string) zq_get_manual( $post_id, 'video_portada' ) );
	if ( '' !== $v ) {
		return esc_url_raw( $v );
	}
	if ( 'amiral' === get_post_field( 'post_name', $post_id ) ) {
		return plugins_url( 'assets/video/amiral-portada.mp4', ZQ_FILE );
	}
	return '';
}

function zq_hero_video_poster( $post_id ) {
	return ( 'amiral' === get_post_field( 'post_name', $post_id ) && ! trim( (string) zq_get_manual( $post_id, 'video_portada' ) ) )
		? plugins_url( 'assets/video/amiral-portada.jpg', ZQ_FILE )
		: '';
}

add_action( 'wp_footer', function () {
	if ( ! is_singular( ZQ_CPT ) ) {
		return;
	}
	$id  = get_queried_object_id();
	$url = zq_hero_video_url( $id );
	if ( ! $url ) {
		return;
	}
	$poster = zq_hero_video_poster( $id );
	?>
	<style id="zq-hero-video-css">
		.zq-hero-video-on { position: relative; overflow: hidden; background-image: none !important; }
		.zq-hero-video-on::before { display: none !important; }
		.zq-hero-video-on > .zq-hero-video, .zq-hero-video-on > .zq-hero-video-ov { position: absolute; inset: 0; width: 100%; height: 100%; }
		.zq-hero-video-on > .zq-hero-video { object-fit: cover; z-index: 0; }
		.zq-hero-video-on > .zq-hero-video-ov { background: rgba(0, 0, 0, .4); z-index: 1; pointer-events: none; }
		.zq-hero-video-on > :not(.zq-hero-video):not(.zq-hero-video-ov) { position: relative; z-index: 2; }
		@media (prefers-reduced-motion: reduce) { .zq-hero-video { display: none; } }
	</style>
	<script>
	(function () {
		var src = <?php echo wp_json_encode( $url ); ?>, poster = <?php echo wp_json_encode( $poster ); ?>;
		function find() {
			var el = document.querySelector('.zq-hero, .zq-f-hero');
			if (el) { return el; }
			var cand = document.querySelectorAll('main > .e-con, main .elementor > .e-con, .zq-single .e-con, .elementor-location-single .e-con, .e-con');
			for (var i = 0; i < cand.length && i < 12; i++) {
				var cs = getComputedStyle(cand[i]);
				if (cs.backgroundImage && cs.backgroundImage !== 'none' && cand[i].offsetHeight >= 400) { return cand[i]; }
			}
			return null;
		}
		function init() {
			var hero = find();
			if (!hero || hero.querySelector('.zq-hero-video')) { return; }
			var v = document.createElement('video');
			v.className = 'zq-hero-video';
			v.src = src; v.muted = true; v.loop = true; v.autoplay = true; v.playsInline = true;
			v.setAttribute('muted', ''); v.setAttribute('playsinline', ''); v.setAttribute('aria-hidden', 'true');
			v.preload = 'auto';
			if (poster) { v.poster = poster; }
			var ov = document.createElement('div');
			ov.className = 'zq-hero-video-ov';
			hero.classList.add('zq-hero-video-on');
			hero.insertBefore(ov, hero.firstChild);
			hero.insertBefore(v, hero.firstChild);
			var p = v.play(); if (p && p.catch) { p.catch(function () {}); }
		}
		if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { init(); }
	})();
	</script>
	<?php
}, 30 );
