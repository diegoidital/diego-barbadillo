<?php
/**
 * Integración con el checkout por bloques de WooCommerce.
 *
 * @package IditalPagos
 */

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class Idital_Pagos_Blocks extends AbstractPaymentMethodType {

	private ?WC_Payment_Gateway $pasarela = null;

	public function __construct( string $id ) {
		$this->name = $id;
	}

	public function initialize() {
		$this->settings = get_option( 'woocommerce_' . $this->name . '_settings', array() );
		$pasarelas      = Idital_Pagos_Plugin::pasarelas();
		$this->pasarela = $pasarelas[ $this->name ] ?? null;
	}

	public function is_active() {
		return $this->pasarela && $this->pasarela->is_available();
	}

	public function get_payment_method_script_handles() {
		wp_register_script(
			'idital-pagos-bloques',
			IDITAL_PAGOS_URL . 'assets/js/bloques.js',
			array( 'wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities' ),
			IDITAL_PAGOS_VERSION,
			true
		);
		return array( 'idital-pagos-bloques' );
	}

	public function get_payment_method_data() {
		return array(
			'title'       => $this->pasarela ? $this->pasarela->get_title() : '',
			'description' => $this->pasarela ? $this->pasarela->get_description() : '',
			'supports'    => $this->pasarela ? array_values( $this->pasarela->supports ) : array( 'products' ),
		);
	}
}
