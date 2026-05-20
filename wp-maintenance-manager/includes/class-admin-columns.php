<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WPMM_Admin_Columns {

	public static function init() {
		// Columnas para partes
		add_filter( 'manage_wpmm_parte_posts_columns', [ __CLASS__, 'parte_columns' ] );
		add_action( 'manage_wpmm_parte_posts_custom_column', [ __CLASS__, 'parte_column_content' ], 10, 2 );
		add_filter( 'manage_edit-wpmm_parte_sortable_columns', [ __CLASS__, 'parte_sortable_columns' ] );

		// Columnas para localizaciones
		add_filter( 'manage_wpmm_localizacion_posts_columns', [ __CLASS__, 'loc_columns' ] );
		add_action( 'manage_wpmm_localizacion_posts_custom_column', [ __CLASS__, 'loc_column_content' ], 10, 2 );
	}

	public static function parte_columns( $columns ) {
		return [
			'cb'               => $columns['cb'],
			'title'            => 'Nº / Título del Parte',
			'wpmm_loc'         => 'Localización',
			'wpmm_estado'      => 'Estado',
			'wpmm_prioridad'   => 'Prioridad',
			'wpmm_tecnico'     => 'Técnico',
			'wpmm_fecha_ini'   => 'Fecha inicio',
			'wpmm_horas'       => 'Horas',
			'date'             => 'Creado',
		];
	}

	public static function parte_column_content( $column, $post_id ) {
		switch ( $column ) {
			case 'wpmm_loc':
				$loc_id = get_post_meta( $post_id, '_wpmm_localizacion', true );
				if ( $loc_id ) {
					$loc = get_post( $loc_id );
					if ( $loc ) {
						printf(
							'<a href="%s">%s</a>',
							esc_url( add_query_arg( [ 'post_type' => 'wpmm_parte', 'wpmm_loc' => $loc_id ], admin_url( 'edit.php' ) ) ),
							esc_html( $loc->post_title )
						);
					}
				} else {
					echo '<span class="wpmm-empty">—</span>';
				}
				break;

			case 'wpmm_estado':
				$estado   = get_post_meta( $post_id, '_wpmm_estado', true ) ?: 'pendiente';
				$estados  = WPMM_Meta_Boxes::get_estados();
				$label    = $estados[ $estado ] ?? $estado;
				printf( '<span class="wpmm-badge wpmm-badge--%s">%s</span>', esc_attr( $estado ), esc_html( $label ) );
				break;

			case 'wpmm_prioridad':
				$prio      = get_post_meta( $post_id, '_wpmm_prioridad', true ) ?: 'normal';
				$prios     = WPMM_Meta_Boxes::get_prioridades();
				$label     = $prios[ $prio ] ?? $prio;
				printf( '<span class="wpmm-badge wpmm-badge--prio-%s">%s</span>', esc_attr( $prio ), esc_html( $label ) );
				break;

			case 'wpmm_tecnico':
				$tecnico_id = get_post_meta( $post_id, '_wpmm_tecnico', true );
				if ( $tecnico_id ) {
					$user = get_user_by( 'ID', $tecnico_id );
					echo $user ? esc_html( $user->display_name ) : '—';
				} else {
					echo '<span class="wpmm-empty">Sin asignar</span>';
				}
				break;

			case 'wpmm_fecha_ini':
				$fecha = get_post_meta( $post_id, '_wpmm_fecha_inicio', true );
				echo $fecha ? esc_html( date_i18n( get_option( 'date_format' ), strtotime( $fecha ) ) ) : '<span class="wpmm-empty">—</span>';
				break;

			case 'wpmm_horas':
				$horas = get_post_meta( $post_id, '_wpmm_horas', true );
				echo $horas ? esc_html( $horas ) . ' h' : '<span class="wpmm-empty">—</span>';
				break;
		}
	}

	public static function parte_sortable_columns( $columns ) {
		$columns['wpmm_estado']    = 'wpmm_estado';
		$columns['wpmm_prioridad'] = 'wpmm_prioridad';
		$columns['wpmm_fecha_ini'] = 'wpmm_fecha_ini';
		return $columns;
	}

	public static function loc_columns( $columns ) {
		return [
			'cb'              => $columns['cb'],
			'title'           => 'Nombre de la localización',
			'wpmm_direccion'  => 'Dirección',
			'wpmm_ciudad'     => 'Ciudad',
			'wpmm_contacto'   => 'Contacto',
			'wpmm_partes'     => 'Partes',
			'date'            => 'Creado',
		];
	}

	public static function loc_column_content( $column, $post_id ) {
		switch ( $column ) {
			case 'wpmm_direccion':
				$dir = get_post_meta( $post_id, '_wpmm_loc_direccion', true );
				echo $dir ? esc_html( $dir ) : '<span class="wpmm-empty">—</span>';
				break;

			case 'wpmm_ciudad':
				$ciudad = get_post_meta( $post_id, '_wpmm_loc_ciudad', true );
				$cp     = get_post_meta( $post_id, '_wpmm_loc_cp', true );
				$out    = array_filter( [ $cp, $ciudad ] );
				echo $out ? esc_html( implode( ' ', $out ) ) : '<span class="wpmm-empty">—</span>';
				break;

			case 'wpmm_contacto':
				$contacto = get_post_meta( $post_id, '_wpmm_loc_contacto', true );
				$tel      = get_post_meta( $post_id, '_wpmm_loc_telefono', true );
				if ( $contacto ) echo esc_html( $contacto );
				if ( $tel ) printf( '<br><small>%s</small>', esc_html( $tel ) );
				if ( ! $contacto && ! $tel ) echo '<span class="wpmm-empty">—</span>';
				break;

			case 'wpmm_partes':
				$count = get_posts( [
					'post_type'      => 'wpmm_parte',
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'meta_query'     => [ [ 'key' => '_wpmm_localizacion', 'value' => $post_id ] ],
				] );
				$n = count( $count );
				printf(
					'<a href="%s"><strong>%d</strong></a>',
					esc_url( add_query_arg( [ 'post_type' => 'wpmm_parte', 'wpmm_loc' => $post_id ], admin_url( 'edit.php' ) ) ),
					$n
				);
				break;
		}
	}
}

WPMM_Admin_Columns::init();
