<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WMPRO_Admin_Columns {

	public static function init() {
		add_filter( 'manage_wmpro_parte_posts_columns',           [ __CLASS__, 'parte_cols' ] );
		add_action( 'manage_wmpro_parte_posts_custom_column',     [ __CLASS__, 'parte_col_content' ], 10, 2 );
		add_filter( 'manage_edit-wmpro_parte_sortable_columns',   [ __CLASS__, 'parte_sortable' ] );
		add_filter( 'manage_wmpro_proyecto_posts_columns',        [ __CLASS__, 'proyecto_cols' ] );
		add_action( 'manage_wmpro_proyecto_posts_custom_column',  [ __CLASS__, 'proyecto_col_content' ], 10, 2 );
	}

	/* ---- Partes ---- */
	public static function parte_cols( $cols ) {
		return [
			'cb'              => $cols['cb'],
			'title'           => 'Nº Parte / Título',
			'wmpro_proyecto'  => 'Proyecto',
			'wmpro_estado'    => 'Estado',
			'wmpro_prioridad' => 'Prioridad',
			'wmpro_tecnico'   => 'Técnico',
			'wmpro_tareas'    => 'Tareas',
			'wmpro_fecha'     => 'Fecha programada',
			'date'            => 'Creado',
		];
	}

	public static function parte_col_content( $col, $id ) {
		switch ( $col ) {
			case 'wmpro_proyecto':
				$pid = get_post_meta( $id, '_wmpro_proyecto_id', true );
				if ( $pid ) {
					$p = get_post( $pid );
					if ( $p ) printf( '<a href="%s">%s</a>', esc_url( add_query_arg( [ 'post_type' => 'wmpro_parte', 'wmpro_proyecto' => $pid ], admin_url( 'edit.php' ) ) ), esc_html( $p->post_title ) );
				} else echo '—';
				break;

			case 'wmpro_estado':
				$e = get_post_meta( $id, '_wmpro_estado', true ) ?: 'pendiente';
				$estados = WMPRO_CPT_Parte::get_estados();
				printf( '<span class="wmpro-badge wmpro-badge--%s">%s</span>', esc_attr( $e ), esc_html( $estados[ $e ] ?? $e ) );
				break;

			case 'wmpro_prioridad':
				$p = get_post_meta( $id, '_wmpro_prioridad', true ) ?: 'normal';
				$prios = WMPRO_CPT_Parte::get_prioridades();
				printf( '<span class="wmpro-badge wmpro-badge--prio-%s">%s</span>', esc_attr( $p ), esc_html( $prios[ $p ] ?? $p ) );
				break;

			case 'wmpro_tecnico':
				$uid = get_post_meta( $id, '_wmpro_tecnico', true );
				if ( $uid ) {
					$u = get_user_by( 'ID', $uid );
					echo $u ? esc_html( $u->display_name ) : '—';
				} else echo '<span class="wmpro-na">Sin asignar</span>';
				break;

			case 'wmpro_tareas':
				$tareas = get_post_meta( $id, '_wmpro_tareas', true ) ?: [];
				if ( empty( $tareas ) ) { echo '—'; break; }
				$total    = count( $tareas );
				$hechas   = count( array_filter( $tareas, fn( $t ) => $t['completada'] ) );
				$pct      = $total ? round( $hechas / $total * 100 ) : 0;
				$color    = $pct >= 100 ? '#00a32a' : ( $pct > 0 ? '#f0a953' : '#8c8f94' );
				printf(
					'<span title="%d/%d completadas"><strong style="color:%s">%d%%</strong> <small>(%d/%d)</small></span>',
					$hechas, $total, $color, $pct, $hechas, $total
				);
				break;

			case 'wmpro_fecha':
				$f = get_post_meta( $id, '_wmpro_fecha_prog', true );
				if ( $f ) {
					$ts   = strtotime( $f );
					$hoy  = strtotime( current_time( 'Y-m-d' ) );
					$diff = ( $ts - $hoy ) / DAY_IN_SECONDS;
					$cls  = $diff < 0 ? ' style="color:#d63638;font-weight:600"' : ( $diff <= 3 ? ' style="color:#f0a953;font-weight:600"' : '' );
					printf( '<span%s>%s</span>', $cls, esc_html( date_i18n( 'd/m/Y', $ts ) ) );
				} else echo '—';
				break;
		}
	}

	public static function parte_sortable( $cols ) {
		$cols['wmpro_estado'] = 'wmpro_estado';
		$cols['wmpro_fecha']  = '_wmpro_fecha_prog';
		return $cols;
	}

	/* ---- Proyectos ---- */
	public static function proyecto_cols( $cols ) {
		return [
			'cb'               => $cols['cb'],
			'title'            => 'Nombre / Localización',
			'wmpro_direccion'  => 'Dirección',
			'wmpro_recurrencia'=> 'Recurrencia',
			'wmpro_proxima'    => 'Próxima fecha',
			'wmpro_partes'     => 'Partes',
			'wmpro_estado_proy'=> 'Estado',
			'date'             => 'Creado',
		];
	}

	public static function proyecto_col_content( $col, $id ) {
		switch ( $col ) {
			case 'wmpro_direccion':
				$dir  = get_post_meta( $id, '_wmpro_direccion', true );
				$city = get_post_meta( $id, '_wmpro_ciudad', true );
				$out  = array_filter( [ $dir, $city ] );
				echo $out ? esc_html( implode( ', ', $out ) ) : '—';
				break;

			case 'wmpro_recurrencia':
				$r = get_post_meta( $id, '_wmpro_recurrencia', true ) ?: 'ninguna';
				$labels = [ 'ninguna' => '—', 'semanal' => 'Semanal', 'quincenal' => 'Quincenal', 'mensual' => 'Mensual', 'trimestral' => 'Trimestral', 'semestral' => 'Semestral', 'anual' => 'Anual' ];
				echo esc_html( $labels[ $r ] ?? $r );
				break;

			case 'wmpro_proxima':
				$f = get_post_meta( $id, '_wmpro_fecha_proxima', true );
				if ( $f ) {
					$ts   = strtotime( $f );
					$hoy  = strtotime( current_time( 'Y-m-d' ) );
					$diff = ceil( ( $ts - $hoy ) / DAY_IN_SECONDS );
					$cls  = $diff < 0 ? 'color:#d63638' : ( $diff <= 7 ? 'color:#f0a953' : '' );
					$suf  = $diff < 0 ? " (hace {$diff} días)" : ( $diff <= 7 ? " ({$diff} días)" : '' );
					printf( '<span style="%s">%s%s</span>', $cls, esc_html( date_i18n( 'd/m/Y', $ts ) ), esc_html( $suf ) );
				} else echo '—';
				break;

			case 'wmpro_partes':
				$n = count( get_posts( [ 'post_type' => 'wmpro_parte', 'posts_per_page' => -1, 'fields' => 'ids', 'meta_query' => [ [ 'key' => '_wmpro_proyecto_id', 'value' => $id ] ] ] ) );
				printf( '<a href="%s"><strong>%d</strong></a>', esc_url( add_query_arg( [ 'post_type' => 'wmpro_parte', 'wmpro_proyecto' => $id ], admin_url( 'edit.php' ) ) ), $n );
				break;

			case 'wmpro_estado_proy':
				$e = get_post_meta( $id, '_wmpro_estado_proyecto', true ) ?: 'activo';
				echo $e === 'activo' ? '<span class="wmpro-badge wmpro-badge--completado">✅ Activo</span>' : '<span class="wmpro-badge wmpro-badge--cancelado">⏸ Inactivo</span>';
				break;
		}
	}
}

WMPRO_Admin_Columns::init();
