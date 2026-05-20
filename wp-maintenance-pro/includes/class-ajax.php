<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WMPRO_Ajax {

	public static function init() {
		$actions = [
			'wmpro_toggle_task',
			'wmpro_add_task',
			'wmpro_delete_task',
			'wmpro_upload_photo',
			'wmpro_delete_photo',
			'wmpro_save_signature',
			'wmpro_submit_parte',
			'wmpro_save_field',
		];
		foreach ( $actions as $action ) {
			add_action( "wp_ajax_{$action}", [ __CLASS__, str_replace( 'wmpro_', 'handle_', $action ) ] );
		}
	}

	private static function verify( $nonce_action = 'wmpro_frontend' ) {
		if ( ! is_user_logged_in() ) wp_send_json_error( [ 'msg' => 'No autenticado' ], 401 );
		check_ajax_referer( $nonce_action, 'nonce' );
	}

	private static function get_parte( $parte_id ) {
		$parte = get_post( $parte_id );
		if ( ! $parte || $parte->post_type !== 'wmpro_parte' ) {
			wp_send_json_error( [ 'msg' => 'Parte no encontrado' ], 404 );
		}
		return $parte;
	}

	/* ---- Toggle tarea ---- */
	public static function handle_toggle_task() {
		self::verify();
		$parte_id = intval( $_POST['parte_id'] );
		$task_id  = sanitize_text_field( $_POST['task_id'] );
		self::get_parte( $parte_id );

		$tareas = get_post_meta( $parte_id, '_wmpro_tareas', true ) ?: [];
		$found  = false;
		foreach ( $tareas as &$t ) {
			if ( $t['id'] === $task_id ) {
				$t['completada']    = ! $t['completada'];
				$t['completada_en'] = $t['completada'] ? current_time( 'Y-m-d H:i:s' ) : null;
				$found = true;
				break;
			}
		}
		unset( $t );

		if ( ! $found ) wp_send_json_error( [ 'msg' => 'Tarea no encontrada' ] );

		update_post_meta( $parte_id, '_wmpro_tareas', $tareas );

		// Actualizar estado si todas completadas
		$total  = count( $tareas );
		$hechas = count( array_filter( $tareas, fn( $x ) => $x['completada'] ) );
		$estado_actual = get_post_meta( $parte_id, '_wmpro_estado', true );
		if ( $total > 0 && $hechas === $total && $estado_actual === 'en_proceso' ) {
			update_post_meta( $parte_id, '_wmpro_estado', 'completado' );
		}

		wp_send_json_success( [ 'completada' => $tareas[ array_search( $task_id, array_column( $tareas, 'id' ) ) ]['completada'] ?? false, 'total' => $total, 'hechas' => $hechas ] );
	}

	/* ---- Añadir tarea ---- */
	public static function handle_add_task() {
		self::verify();
		$parte_id = intval( $_POST['parte_id'] );
		$texto    = sanitize_text_field( $_POST['texto'] );
		self::get_parte( $parte_id );

		if ( empty( $texto ) ) wp_send_json_error( [ 'msg' => 'El texto no puede estar vacío' ] );

		$tareas = get_post_meta( $parte_id, '_wmpro_tareas', true ) ?: [];
		$nueva  = [ 'id' => uniqid( 'task_' ), 'texto' => $texto, 'completada' => false, 'completada_en' => null ];
		$tareas[] = $nueva;
		update_post_meta( $parte_id, '_wmpro_tareas', $tareas );

		wp_send_json_success( [ 'task' => $nueva ] );
	}

	/* ---- Eliminar tarea ---- */
	public static function handle_delete_task() {
		self::verify();
		$parte_id = intval( $_POST['parte_id'] );
		$task_id  = sanitize_text_field( $_POST['task_id'] );
		self::get_parte( $parte_id );

		$tareas = get_post_meta( $parte_id, '_wmpro_tareas', true ) ?: [];
		$tareas = array_values( array_filter( $tareas, fn( $t ) => $t['id'] !== $task_id ) );
		update_post_meta( $parte_id, '_wmpro_tareas', $tareas );

		wp_send_json_success();
	}

	/* ---- Subir foto ---- */
	public static function handle_upload_photo() {
		self::verify();
		$parte_id = intval( $_POST['parte_id'] );
		self::get_parte( $parte_id );

		if ( empty( $_FILES['foto'] ) ) wp_send_json_error( [ 'msg' => 'No se recibió ninguna imagen' ] );

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$allowed_types = [ 'image/jpeg', 'image/png', 'image/webp', 'image/gif' ];
		if ( ! in_array( $_FILES['foto']['type'], $allowed_types, true ) ) {
			wp_send_json_error( [ 'msg' => 'Tipo de archivo no permitido. Solo se aceptan imágenes.' ] );
		}

		if ( $_FILES['foto']['size'] > 8 * 1024 * 1024 ) {
			wp_send_json_error( [ 'msg' => 'La imagen supera el límite de 8 MB.' ] );
		}

		$attach_id = media_handle_upload( 'foto', $parte_id );
		if ( is_wp_error( $attach_id ) ) {
			wp_send_json_error( [ 'msg' => $attach_id->get_error_message() ] );
		}

		$fotos = get_post_meta( $parte_id, '_wmpro_fotos', true ) ?: [];
		$fotos[] = $attach_id;
		update_post_meta( $parte_id, '_wmpro_fotos', $fotos );

		wp_send_json_success( [
			'attach_id' => $attach_id,
			'url'       => wp_get_attachment_url( $attach_id ),
			'thumb'     => wp_get_attachment_image_url( $attach_id, 'thumbnail' ),
		] );
	}

	/* ---- Eliminar foto ---- */
	public static function handle_delete_photo() {
		self::verify();
		$parte_id  = intval( $_POST['parte_id'] );
		$attach_id = intval( $_POST['attach_id'] );
		self::get_parte( $parte_id );

		$fotos = get_post_meta( $parte_id, '_wmpro_fotos', true ) ?: [];
		$fotos = array_values( array_filter( $fotos, fn( $f ) => intval( $f ) !== $attach_id ) );
		update_post_meta( $parte_id, '_wmpro_fotos', $fotos );

		wp_send_json_success();
	}

	/* ---- Guardar firma ---- */
	public static function handle_save_signature() {
		self::verify();
		$parte_id   = intval( $_POST['parte_id'] );
		$data_url   = $_POST['signature'] ?? '';
		$nombre     = sanitize_text_field( $_POST['nombre_firmante'] ?? '' );
		self::get_parte( $parte_id );

		if ( empty( $data_url ) || strpos( $data_url, 'data:image/png;base64,' ) !== 0 ) {
			wp_send_json_error( [ 'msg' => 'Firma inválida' ] );
		}

		// Guardar como archivo en uploads
		$upload_dir = wp_upload_dir();
		$filename   = 'firma-parte-' . $parte_id . '-' . time() . '.png';
		$file_path  = trailingslashit( $upload_dir['path'] ) . $filename;
		$file_url   = trailingslashit( $upload_dir['url'] ) . $filename;

		$img_data = base64_decode( str_replace( 'data:image/png;base64,', '', $data_url ) );
		if ( ! $img_data ) wp_send_json_error( [ 'msg' => 'Error procesando la firma' ] );

		file_put_contents( $file_path, $img_data );

		update_post_meta( $parte_id, '_wmpro_firma_url',    $file_url );
		update_post_meta( $parte_id, '_wmpro_firma_path',   $file_path );
		update_post_meta( $parte_id, '_wmpro_nombre_firmante', $nombre );
		update_post_meta( $parte_id, '_wmpro_firma_fecha',  current_time( 'Y-m-d H:i:s' ) );

		wp_send_json_success( [ 'url' => $file_url ] );
	}

	/* ---- Guardar campo suelto (inicio/fin, observaciones) ---- */
	public static function handle_save_field() {
		self::verify();
		$parte_id = intval( $_POST['parte_id'] );
		$field    = sanitize_key( $_POST['field'] );
		$value    = sanitize_textarea_field( $_POST['value'] );
		self::get_parte( $parte_id );

		$allowed = [ '_wmpro_fecha_inicio', '_wmpro_fecha_fin', '_wmpro_observaciones', '_wmpro_estado' ];
		if ( ! in_array( $field, $allowed, true ) ) wp_send_json_error( [ 'msg' => 'Campo no permitido' ] );

		update_post_meta( $parte_id, $field, $value );
		wp_send_json_success();
	}

	/* ---- Enviar parte (submit final) ---- */
	public static function handle_submit_parte() {
		self::verify();
		$parte_id    = intval( $_POST['parte_id'] );
		$nombre_firm = sanitize_text_field( $_POST['nombre_firmante'] ?? '' );
		self::get_parte( $parte_id );

		// Marcar como completado y enviado
		update_post_meta( $parte_id, '_wmpro_estado',       'enviado' );
		update_post_meta( $parte_id, '_wmpro_fecha_fin',    current_time( 'Y-m-d H:i:s' ) );
		update_post_meta( $parte_id, '_wmpro_nombre_firmante', $nombre_firm );

		// Enviar email
		$result = WMPRO_Cron::send_report_email( $parte_id );

		// Avanzar recurrencia del proyecto
		$proyecto_id = get_post_meta( $parte_id, '_wmpro_proyecto_id', true );
		if ( $proyecto_id ) {
			WMPRO_Cron::advance_recurrence( $proyecto_id );
		}

		wp_send_json_success( [ 'email_sent' => $result ] );
	}
}

WMPRO_Ajax::init();
