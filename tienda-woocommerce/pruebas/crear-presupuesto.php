<?php
/**
 * Crea un pedido pendiente de pago (como un presupuesto aceptado) y muestra su URL de pago.
 * Uso: docker compose run --rm wpcli wp eval-file /ruta/crear-presupuesto.php [importe]
 */
$importe = isset( $args[0] ) ? (float) $args[0] : 1250.50;
$pedido  = wc_create_order();
$item    = new WC_Order_Item_Fee();
$item->set_name( 'Presupuesto de prueba' );
$item->set_total( $importe );
$item->set_tax_status( 'none' );
$pedido->add_item( $item );
$pedido->set_billing_first_name( 'Cliente' );
$pedido->set_billing_last_name( 'Prueba' );
$pedido->set_billing_email( 'cliente@example.com' );
$pedido->set_currency( 'EUR' );
$pedido->calculate_totals( false );
$pedido->set_status( 'pending' );
$pedido->save();
echo wp_json_encode(
	array(
		'id'  => $pedido->get_id(),
		'pay' => $pedido->get_checkout_payment_url(),
	)
) . "\n";
