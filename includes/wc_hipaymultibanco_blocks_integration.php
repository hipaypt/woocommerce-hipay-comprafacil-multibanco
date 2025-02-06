<?php

class WC_HiPayMultibanco_Blocks_Integration extends Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType {

    protected $name = 'hipaymultibanco'; // Deve corresponder ao ID do método de pagamento
	
    public function initialize() {
        $this->settings = get_option('woocommerce_hipaymultibanco_settings', array());
    }

    public function is_active() {
        return !empty($this->settings['enabled']) && 'yes' === $this->settings['enabled'];
		
    }

    public function get_payment_method_script_handles() {
        wp_register_script(
            'wc-hipaymultibanco-blocks-integration',
            plugins_url('../assets/js/hipaymultibanco-blocks.js', __FILE__),
            array('wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-i18n'),
            '1.0.0',
            true
        );
        return array('wc-hipaymultibanco-blocks-integration');
    }

    public function get_payment_method_data() {
        return array(
            'title'       => $this->settings['title'] ?? 'Multibanco',
            'description' => $this->settings['description'] ?? 'Pague com Multibanco.',
            'supports'    => array('products'),
        );
    }
}
