<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Variables disponibles: $parte_id, $parte (WP_Post)
$proy_id      = get_post_meta( $parte_id, '_wmpro_proyecto_id', true );
$proyecto     = $proy_id ? get_post( $proy_id ) : null;
$tecnico_id   = get_post_meta( $parte_id, '_wmpro_tecnico', true );
$tecnico      = $tecnico_id ? get_user_by( 'ID', $tecnico_id ) : null;
$estado       = get_post_meta( $parte_id, '_wmpro_estado', true ) ?: 'pendiente';
$prioridad    = get_post_meta( $parte_id, '_wmpro_prioridad', true ) ?: 'normal';
$fecha_prog   = get_post_meta( $parte_id, '_wmpro_fecha_prog', true );
$fecha_ini    = get_post_meta( $parte_id, '_wmpro_fecha_inicio', true );
$fecha_fin    = get_post_meta( $parte_id, '_wmpro_fecha_fin', true );
$observ       = get_post_meta( $parte_id, '_wmpro_observaciones', true );
$tareas       = get_post_meta( $parte_id, '_wmpro_tareas', true ) ?: [];
$fotos        = get_post_meta( $parte_id, '_wmpro_fotos', true ) ?: [];
$firma_url    = get_post_meta( $parte_id, '_wmpro_firma_url', true );
$nom_firmante = get_post_meta( $parte_id, '_wmpro_nombre_firmante', true );
$firma_fecha  = get_post_meta( $parte_id, '_wmpro_firma_fecha', true );

$estados_map   = WMPRO_CPT_Parte::get_estados();
$prioridad_map = WMPRO_CPT_Parte::get_prioridades();
$ya_enviado    = ( $estado === 'enviado' );

$total_tareas  = count( $tareas );
$hechas_tareas = count( array_filter( $tareas, fn( $t ) => $t['completada'] ) );
$pct_tareas    = $total_tareas > 0 ? round( $hechas_tareas / $total_tareas * 100 ) : 0;
?>

<div class="wmpro-parte" id="wmpro-parte-<?php echo esc_attr( $parte_id ); ?>">

	<!-- ====== CABECERA ====== -->
	<div class="wmpro-parte__header">
		<div class="wmpro-parte__logo">🔧</div>
		<div class="wmpro-parte__title-block">
			<h1 class="wmpro-parte__title"><?php echo esc_html( $parte->post_title ); ?></h1>
			<?php if ( $proyecto ) : ?>
				<p class="wmpro-parte__subtitle">
					📍 <?php echo esc_html( $proyecto->post_title ); ?>
					<?php
					$dir = get_post_meta( $proy_id, '_wmpro_direccion', true );
					$city = get_post_meta( $proy_id, '_wmpro_ciudad', true );
					if ( $dir || $city ) echo ' — ' . esc_html( implode( ', ', array_filter( [ $dir, $city ] ) ) );
					?>
				</p>
			<?php endif; ?>
		</div>
		<div class="wmpro-parte__badges">
			<span class="wmpro-badge wmpro-badge--<?php echo esc_attr( $estado ); ?>">
				<?php echo esc_html( $estados_map[ $estado ] ?? $estado ); ?>
			</span>
			<span class="wmpro-badge wmpro-badge--prio-<?php echo esc_attr( $prioridad ); ?>">
				<?php echo esc_html( $prioridad_map[ $prioridad ] ?? $prioridad ); ?>
			</span>
		</div>
	</div>

	<!-- ====== INFO RÁPIDA ====== -->
	<div class="wmpro-info-grid">
		<?php if ( $tecnico ) : ?>
			<div class="wmpro-info-item">
				<span class="wmpro-info-label">👷 Técnico</span>
				<span class="wmpro-info-value"><?php echo esc_html( $tecnico->display_name ); ?></span>
			</div>
		<?php endif; ?>
		<?php if ( $fecha_prog ) : ?>
			<div class="wmpro-info-item">
				<span class="wmpro-info-label">📅 Fecha programada</span>
				<span class="wmpro-info-value"><?php echo esc_html( date_i18n( 'd/m/Y', strtotime( $fecha_prog ) ) ); ?></span>
			</div>
		<?php endif; ?>
		<div class="wmpro-info-item">
			<span class="wmpro-info-label">🕐 Inicio</span>
			<span class="wmpro-info-value" id="wmpro-display-inicio">
				<?php echo $fecha_ini ? esc_html( date_i18n( 'd/m/Y H:i', strtotime( $fecha_ini ) ) ) : '<em>No registrado</em>'; ?>
			</span>
		</div>
		<div class="wmpro-info-item">
			<span class="wmpro-info-label">🏁 Fin</span>
			<span class="wmpro-info-value" id="wmpro-display-fin">
				<?php echo $fecha_fin ? esc_html( date_i18n( 'd/m/Y H:i', strtotime( $fecha_fin ) ) ) : '<em>No registrado</em>'; ?>
			</span>
		</div>
		<?php if ( $total_tareas > 0 ) : ?>
			<div class="wmpro-info-item">
				<span class="wmpro-info-label">✅ Progreso</span>
				<span class="wmpro-info-value">
					<span id="wmpro-progress-text"><?php echo esc_html( $hechas_tareas ); ?>/<?php echo esc_html( $total_tareas ); ?></span>
					<div class="wmpro-progress-bar">
						<div class="wmpro-progress-fill" id="wmpro-progress-fill" style="width:<?php echo esc_attr( $pct_tareas ); ?>%"></div>
					</div>
				</span>
			</div>
		<?php endif; ?>
	</div>

	<?php if ( $ya_enviado ) : ?>
		<div class="wmpro-alert wmpro-alert--success">
			✅ Este parte ha sido completado y el informe ha sido enviado por email.
			<?php if ( $firma_fecha ) : ?>
				<br><small>Firmado el <?php echo esc_html( date_i18n( 'd/m/Y \a \l\a\s H:i', strtotime( $firma_fecha ) ) ); ?> por <?php echo esc_html( $nom_firmante ); ?></small>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<!-- ====== SECCIÓN: CHECKLIST ====== -->
	<section class="wmpro-section">
		<div class="wmpro-section__header">
			<h2 class="wmpro-section__title">📋 Checklist de tareas</h2>
			<span class="wmpro-progress-label" id="wmpro-tasks-counter">
				<?php echo esc_html( $hechas_tareas ); ?>/<?php echo esc_html( $total_tareas ); ?> completadas
			</span>
		</div>

		<ul class="wmpro-checklist" id="wmpro-checklist">
			<?php if ( empty( $tareas ) ) : ?>
				<li class="wmpro-checklist__empty" id="wmpro-no-tasks">No hay tareas definidas.</li>
			<?php else : foreach ( $tareas as $tarea ) : ?>
				<li class="wmpro-checklist__item <?php echo $tarea['completada'] ? 'is-done' : ''; ?>"
					data-task-id="<?php echo esc_attr( $tarea['id'] ); ?>">
					<label class="wmpro-task-label">
						<input type="checkbox"
							   class="wmpro-task-check"
							   data-task-id="<?php echo esc_attr( $tarea['id'] ); ?>"
							   <?php checked( $tarea['completada'] ); ?>
							   <?php disabled( $ya_enviado ); ?>>
						<span class="wmpro-task-text"><?php echo esc_html( $tarea['texto'] ); ?></span>
					</label>
					<?php if ( $tarea['completada'] && $tarea['completada_en'] ) : ?>
						<small class="wmpro-task-ts">
							✓ <?php echo esc_html( date_i18n( 'd/m H:i', strtotime( $tarea['completada_en'] ) ) ); ?>
						</small>
					<?php endif; ?>
					<?php if ( ! $ya_enviado ) : ?>
						<button type="button" class="wmpro-btn-delete-task" data-task-id="<?php echo esc_attr( $tarea['id'] ); ?>" title="Eliminar tarea">✕</button>
					<?php endif; ?>
				</li>
			<?php endforeach; endif; ?>
		</ul>

		<?php if ( ! $ya_enviado ) : ?>
			<div class="wmpro-add-task">
				<input type="text" id="wmpro-new-task-input" class="wmpro-input"
					   placeholder="Escribe una nueva tarea..." maxlength="200">
				<button type="button" id="wmpro-add-task-btn" class="wmpro-btn wmpro-btn--secondary">
					+ Añadir tarea
				</button>
			</div>
		<?php endif; ?>
	</section>

	<!-- ====== SECCIÓN: OBSERVACIONES ====== -->
	<section class="wmpro-section">
		<h2 class="wmpro-section__title">📝 Observaciones</h2>
		<?php if ( $ya_enviado ) : ?>
			<p><?php echo nl2br( esc_html( $observ ) ) ?: '<em>Sin observaciones.</em>'; ?></p>
		<?php else : ?>
			<textarea id="wmpro-observaciones" class="wmpro-textarea wmpro-autosave"
					  data-field="_wmpro_observaciones"
					  placeholder="Describe el trabajo realizado, incidencias, materiales usados..."
					  rows="4"><?php echo esc_textarea( $observ ); ?></textarea>
		<?php endif; ?>
	</section>

	<!-- ====== SECCIÓN: FOTOS ====== -->
	<section class="wmpro-section">
		<h2 class="wmpro-section__title">📷 Fotos del parte</h2>

		<div class="wmpro-photos-grid" id="wmpro-photos-grid">
			<?php foreach ( $fotos as $attach_id ) :
				$url   = wp_get_attachment_url( $attach_id );
				$thumb = wp_get_attachment_image_url( $attach_id, 'medium' );
				if ( ! $url ) continue;
				?>
				<div class="wmpro-photo-item" data-attach-id="<?php echo esc_attr( $attach_id ); ?>">
					<a href="<?php echo esc_url( $url ); ?>" target="_blank" class="wmpro-photo-link">
						<img src="<?php echo esc_url( $thumb ); ?>" alt="Foto del parte" loading="lazy">
					</a>
					<?php if ( ! $ya_enviado ) : ?>
						<button type="button" class="wmpro-btn-delete-photo"
								data-attach-id="<?php echo esc_attr( $attach_id ); ?>"
								title="Eliminar foto">✕</button>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>

		<?php if ( ! $ya_enviado ) : ?>
			<div class="wmpro-upload-area" id="wmpro-upload-area">
				<input type="file" id="wmpro-foto-input" accept="image/*" multiple style="display:none">
				<label for="wmpro-foto-input" class="wmpro-upload-label">
					<span class="wmpro-upload-icon">📷</span>
					<span>Toca aquí para añadir fotos</span>
					<small>JPG, PNG, WebP · Máx. 8 MB por foto</small>
				</label>
				<div class="wmpro-upload-progress" id="wmpro-upload-progress" style="display:none">
					<div class="wmpro-upload-bar"></div>
					<span class="wmpro-upload-msg">Subiendo...</span>
				</div>
			</div>
		<?php endif; ?>
	</section>

	<!-- ====== SECCIÓN: FIRMA ====== -->
	<?php if ( ! $ya_enviado || $firma_url ) : ?>
	<section class="wmpro-section">
		<h2 class="wmpro-section__title">✍️ Firma del cliente</h2>

		<?php if ( $ya_enviado && $firma_url ) : ?>
			<div class="wmpro-firma-guardada">
				<img src="<?php echo esc_url( $firma_url ); ?>" alt="Firma del cliente" class="wmpro-firma-img">
				<?php if ( $nom_firmante ) : ?>
					<p class="wmpro-firmante">Firmado por: <strong><?php echo esc_html( $nom_firmante ); ?></strong></p>
				<?php endif; ?>
			</div>
		<?php elseif ( ! $ya_enviado ) : ?>
			<?php if ( $firma_url ) : ?>
				<div class="wmpro-firma-guardada" id="wmpro-firma-guardada">
					<img src="<?php echo esc_url( $firma_url ); ?>" alt="Firma guardada" class="wmpro-firma-img">
					<p class="wmpro-firmante" id="wmpro-firmante-display">
						<?php echo $nom_firmante ? 'Firmado por: <strong>' . esc_html( $nom_firmante ) . '</strong>' : ''; ?>
					</p>
					<button type="button" id="wmpro-refirmar-btn" class="wmpro-btn wmpro-btn--outline">
						✏️ Volver a firmar
					</button>
				</div>
			<?php endif; ?>
			<div class="wmpro-firma-wrapper" id="wmpro-firma-wrapper" <?php echo $firma_url ? 'style="display:none"' : ''; ?>>
				<div class="wmpro-firma-container">
					<canvas id="wmpro-signature-canvas" class="wmpro-canvas"></canvas>
					<div class="wmpro-firma-actions">
						<button type="button" id="wmpro-clear-signature" class="wmpro-btn wmpro-btn--outline wmpro-btn--sm">
							🗑️ Borrar
						</button>
						<span class="wmpro-firma-hint">Firma con el dedo en la zona de arriba</span>
					</div>
				</div>
				<div class="wmpro-firma-nombre">
					<input type="text" id="wmpro-nombre-firmante" class="wmpro-input"
						   placeholder="Nombre del firmante (obligatorio)"
						   value="<?php echo esc_attr( $nom_firmante ); ?>">
				</div>
				<button type="button" id="wmpro-save-signature-btn" class="wmpro-btn wmpro-btn--primary">
					💾 Guardar firma
				</button>
			</div>
		<?php endif; ?>
	</section>
	<?php endif; ?>

	<!-- ====== SECCIÓN: ENVIAR PARTE ====== -->
	<?php if ( ! $ya_enviado ) : ?>
	<section class="wmpro-section wmpro-section--submit">
		<div class="wmpro-submit-info">
			<p>Al enviar el parte, se generará un informe con toda la información y se enviará por email.</p>
		</div>
		<div id="wmpro-submit-msg" class="wmpro-alert" style="display:none"></div>
		<button type="button" id="wmpro-submit-btn" class="wmpro-btn wmpro-btn--send" data-parte-id="<?php echo esc_attr( $parte_id ); ?>">
			📧 Enviar parte
		</button>
	</section>
	<?php endif; ?>

</div><!-- .wmpro-parte -->
