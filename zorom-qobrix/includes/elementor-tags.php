<?php
/**
 * Etiquetas dinámicas de Elementor. Se carga solo cuando Elementor está activo.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Controls_Manager;
use Elementor\Core\DynamicTags\Data_Tag;
use Elementor\Core\DynamicTags\Tag;
use Elementor\Modules\DynamicTags\Module;

/** Controles comunes: qué promoción usar. */
function zq_tag_project_control( $tag ) {
	$tag->add_control( 'proyecto', array(
		'label'       => 'Promoción',
		'type'        => Controls_Manager::TEXT,
		'placeholder' => 'Actual',
		'description' => 'Vacío = la promoción de la página, pestaña o listado. O bien nombre, slug o ID de Qobrix.',
	) );
}

class ZQ_Tag_Campo extends Tag {

	public function get_name() {
		return 'qobrix-campo';
	}

	public function get_title() {
		return 'Campo de la promoción';
	}

	public function get_group() {
		return 'qobrix';
	}

	public function get_categories() {
		return array( Module::TEXT_CATEGORY, Module::NUMBER_CATEGORY, Module::POST_META_CATEGORY );
	}

	protected function register_controls() {
		$this->add_control( 'campo', array(
			'label'   => 'Campo',
			'type'    => Controls_Manager::SELECT,
			'options' => zq_field_options() + array( 'otro' => 'Otro campo…' ),
			'default' => 'titulo',
		) );
		$this->add_control( 'campo_otro', array(
			'label'       => 'Nombre del campo en Qobrix',
			'type'        => Controls_Manager::TEXT,
			'condition'   => array( 'campo' => 'otro' ),
			'placeholder' => 'p. ej. city',
		) );
		$this->add_control( 'formato', array(
			'label'   => 'Formato',
			'type'    => Controls_Manager::SELECT,
			'options' => zq_formats(),
			'default' => 'auto',
		) );
		$this->add_control( 'vacio', array(
			'label'   => 'Si no hay dato, mostrar',
			'type'    => Controls_Manager::TEXT,
			'default' => '-',
		) );
		zq_tag_project_control( $this );
	}

	public function render() {
		$s       = $this->get_settings();
		$campo   = 'otro' === $s['campo'] ? $s['campo_otro'] : $s['campo'];
		$post_id = zq_resolve_project( isset( $s['proyecto'] ) ? $s['proyecto'] : '' );
		$out     = ( $post_id && $campo ) ? (string) zq_render_field( $post_id, $campo, $s['formato'] ) : '';
		if ( '' === trim( wp_strip_all_tags( $out ) ) ) {
			$out = esc_html( isset( $s['vacio'] ) ? $s['vacio'] : '-' );
		}
		echo $out; // phpcs:ignore WordPress.Security.EscapeOutput
	}
}

class ZQ_Tag_Imagen extends Data_Tag {

	public function get_name() {
		return 'qobrix-imagen';
	}

	public function get_title() {
		return 'Imagen de la promoción';
	}

	public function get_group() {
		return 'qobrix';
	}

	public function get_categories() {
		return array( Module::IMAGE_CATEGORY );
	}

	protected function register_controls() {
		$this->add_control( 'campo', array(
			'label'   => 'Imagen',
			'type'    => Controls_Manager::SELECT,
			'options' => zq_image_sources(),
			'default' => 'destacada',
		) );
		zq_tag_project_control( $this );
	}

	public function get_value( array $options = array() ) {
		$post_id = zq_resolve_project( (string) $this->get_settings( 'proyecto' ) );
		$thumb   = $post_id ? zq_image_id( $post_id, (string) $this->get_settings( 'campo' ) ) : 0;
		return $thumb
			? array( 'id' => $thumb, 'url' => (string) wp_get_attachment_image_url( $thumb, 'full' ) )
			: array( 'id' => null, 'url' => '' );
	}
}

class ZQ_Tag_Enlace extends Data_Tag {

	public function get_name() {
		return 'qobrix-enlace';
	}

	public function get_title() {
		return 'Enlace a la promoción';
	}

	public function get_group() {
		return 'qobrix';
	}

	public function get_categories() {
		return array( Module::URL_CATEGORY );
	}

	protected function register_controls() {
		zq_tag_project_control( $this );
	}

	public function get_value( array $options = array() ) {
		$post_id = zq_resolve_project( (string) $this->get_settings( 'proyecto' ) );
		return $post_id ? get_permalink( $post_id ) : '';
	}
}

class ZQ_Tag_Galeria extends Data_Tag {

	public function get_name() {
		return 'qobrix-galeria';
	}

	public function get_title() {
		return 'Galería de la promoción';
	}

	public function get_group() {
		return 'qobrix';
	}

	public function get_categories() {
		return array( Module::GALLERY_CATEGORY );
	}

	protected function register_controls() {
		zq_tag_project_control( $this );
	}

	public function get_value( array $options = array() ) {
		$post_id = zq_resolve_project( (string) $this->get_settings( 'proyecto' ) );
		$ids     = $post_id ? (array) get_post_meta( $post_id, '_zq_galeria', true ) : array();
		return array_map( function ( $id ) {
			return array( 'id' => (int) $id );
		}, array_values( $ids ) );
	}
}
