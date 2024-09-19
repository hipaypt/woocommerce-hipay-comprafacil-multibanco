<?php
/*
Plugin Name: WooCommerce HiPay Comprafacil Multibanco
Plugin URI: https://github.com/hipaypt/woocommerce-hipay-comprafacil-multibanco
Description: WooCommerce plugin for Multibanco payments with HiPay. HiPay Professional account is mandatory.
Version: 1.5.0
Author: Hi-Pay Portugal
Author URI: https://www.hipay.com
*/

add_action('plugins_loaded', 'woocommerce_hipaymultibanco_init', 0);

function woocommerce_hipaymultibanco_init()
{

	if (!class_exists('WC_Payment_Gateway')) {
		return;
	}

	class WC_HipayMultibanco extends WC_Payment_Gateway
	{

		const HIPAY_MULTIBANCO_CURRENCY = 'EUR';
		const HIPAY_LOG_INFO = 'INFO';
		const HIPAY_LOG_ERROR = 'ERROR';

		protected $logger;

		public function __construct()
		{

			global $woocommerce;

			$this->id = 'hipaymultibanco';
			$this->icon = WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__)) . '/images/mb_choose.png';
			$this->has_fields = false;
			$this->method_title = __('HiPay Wallet Multibanco', $this->id);
			$this->init_form_fields();
			$this->init_settings();
			$this->title = $this->get_option('title');
			$this->description = $this->get_option('description');
			$this->entidade = $this->get_option('entidade');
			$this->sandbox = $this->get_option('sandbox');
			$this->username = $this->get_option('hw_username');
			$this->max_amount = $this->get_option('hw_max_amount');
			$this->password = $this->get_option('hw_password');
			$this->salt = $this->get_option('salt');
			$this->stockonpayment = $this->get_option('stockonpayment');
			$this->reference = "";
			$this->description_ref = $this->get_option('description_ref');
			$this->timeLimitDays = $this->get_option('timeLimitDays');
			if ($this->max_amount == "")
				$this->max_amount = 0;
			$this->logActions = $this->get_option('logActions');

			$this->logger = wc_get_logger();

			add_action('woocommerce_api_wc_hipaymultibanco', array($this, 'check_callback_response'));
			add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
			add_action('woocommerce_thankyou_hipaymultibanco', array($this, 'thanks_page'));
			add_action('woocommerce_email_before_order_table', array($this, 'email_instructions'), 9, 3);
			add_action('woocommerce_view_order', array($this, 'thanks_page'));

		}

		/**
		 * init form fields for admin configuration
		 */
		function init_form_fields()
		{

			global $wpdb;
			$table_name = $wpdb->prefix . 'woocommerce_hipay_mb';
			if ($wpdb->get_var("show tables like '$table_name'") != $table_name) {
				$charset_collate = $wpdb->get_charset_collate();
				$sql = "CREATE TABLE $table_name (
				  `id` bigint(20) NOT NULL AUTO_INCREMENT,
				  `create_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
				  `reference` varchar(20) NOT NULL,
				  `processed` tinyint(4) NOT NULL DEFAULT '0',
				  `timeLimit` smallint(2) NOT NULL,
				  `order_id` bigint(20) NOT NULL,
				  `expire_date` datetime NOT NULL,
				  `processed_date` datetime NOT NULL,
				  `entity` varchar(7) NOT NULL,
				UNIQUE KEY id (id)
				) $charset_collate;";

				require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
				dbDelta($sql);
			}

			$this->form_fields = array(
				'enabled' => array(
					'title' => __('Enable/Disable', $this->id),
					'type' => 'checkbox',
					'label' => __('Ativar Pagamento via Multibanco', $this->id),
					'default' => 'yes'
				),

				'sandbox' => array(
					'title' => __('Usar Sandbox', $this->id),
					'type' => 'checkbox',
					'label' => __('Plataforma de Testes', $this->id),
					'default' => 'no'
				),

				'title' => array(
					'title' => __('Titulo Checkout', $this->id),
					'type' => 'text',
					'description' => __('T&iacute;tulo a visualizar durante o checkout.', $this->id),
					'default' => __('Multibanco', $this->id)
				),
				'description' => array(
					'title' => __('Mensagem Checkout.', $this->id),
					'type' => 'textarea',
					'description' => __('Mensagem a visualizar durante o checkout e que antecede a entidade, refer&ecirc;ncia e valor.', $this->id),
					'default' => __('Efetue o pagamento num terminal Multibanco ou atrav&eacute;s de do seu HomeBanking.', $this->id)
				),
				'description_ref' => array(
					'title' => __('Mensagem para Refer&ecirc;ncia.', $this->id),
					'type' => 'textarea',
					'description' => __('Mensagem que acompanha a entidade, refer&ecirc;ncia e valor.', $this->id),
					'default' => __('Efetue o pagamento da seguinte Refer&ecirc;ncia num terminal Multibanco ou atrav&eacute;s de do seu HomeBanking.', $this->id)
				),
				'entidade' => array(
					'title' => __('Entidade', $this->id),
					'type' => 'select',
					'description' => __('Entidade contratada', $this->id),
					'options' => array(
						'11249' => __('11 249', $this->id),
						'10241' => __('10 241', $this->id)
					)
				),
				'timeLimitDays' => array(
					'title' => __('Tempo limite de pagamento', $this->id),
					'type' => 'select',
					'description' => __('em dias (para 0 ou 1 dia contacte o suporte)', $this->id),
					'options' => array(
						'3' => __('3', $this->id),
						'30' => __('30', $this->id),
						'90' => __('90', $this->id),
						'1' => __('1', $this->id),
						'0' => __('0', $this->id)
					)
				),
				'hw_username' => array(
					'title' => __('Username', $this->id),
					'type' => 'text',
					'description' => __('Password para webservice Multibanco.', $this->id),
					'required' => true
				),
				'hw_password' => array(
					'title' => __('Password', $this->id),
					'type' => 'text',
					'description' => __('Password para webservice Multibanco.', $this->id),
					'required' => true
				),
				'hw_max_amount' => array(
					'title' => __('Valor máximo permitido', $this->id),
					'type' => 'text',
					'description' => __('0 para sem limite.', $this->id),
					'required' => true,
					'default' => '0'
				),
				'stockonpayment' => array(
					'title' => __('Reduzir stock no pagamento', $this->id),
					'type' => 'checkbox',
					'description' => __('Reduz apenas o stock depois do ato do pagamento.', $this->id),
					'default' => 'no'

				),
				'logActions' => array(
					'title' => __('Ativar Log', $this->id),
					'type' => 'checkbox',
					'description' => '',
					'default' => 'no'

				),
				'salt' => array(
					'title' => __('Chave de Encripta&ccedil;&atilde;o', $this->id),
					'type' => 'text',
					'description' => __('Preencher sempre.', $this->id),
					'required' => true,
					'default' => uniqid()
				),
			);

		}

		/**
		 * check configuration status
		 */
		public function admin_options()
		{

			global $wpdb;

			$soap_active = false;
			$has_webservice_access = false;
			$has_webservice_access_config = false;
			if (extension_loaded('soap')) {
				$soap_active = true;

				$wsURL = "https://hm.comprafacil.pt/SIBSClick2/webservice/comprafacilWS.asmx?wsdl";
				if ($this->sandbox == "yes")
					$wsURL = "https://hm.comprafacil.pt/SIBSClick2Teste/webservice/comprafacilWS.asmx?wsdl";
				if ($this->entidade == "10241") {
					$wsURL = "https://hm.comprafacil.pt/SIBSClick/webservice/comprafacilWS.asmx?wsdl";
					if ($this->sandbox == "yes")
						$wsURL = "https://hm.comprafacil.pt/SIBSClickTeste/webservice/comprafacilWS.asmx?wsdl";
				}
				try {
					$client = new SoapClient($wsURL);
					$has_webservice_access = true;

					if ($this->username != "" && $this->password != "") {

						$dateStartStr = date("d-m-Y H:i:s");
						$dataEndStr = $dateStartStr;
						$type = "P";
						$parameters = array(
							"username" => $this->username,
							"password" => $this->password,
							"dateStartStr" => $dateStartStr,
							"dataEndStr" => $dataEndStr,
							"type" => $type,
						);

						$res = $client->getInfo($parameters);
						if ($res->GetReferencesInfoResult)
							$has_webservice_access = true;
						else
							$has_webservice_access = false;
						$has_webservice_access_error = $res->error;
					}
				} catch (Exception $e) {
					$has_webservice_access_error = $e->getMessage();
				}

			}


			$has_entity_column = false;
			$table_name = $wpdb->prefix . 'woocommerce_hipay_mb';
			$fivesdrafts = $wpdb->get_results("DESCRIBE $table_name");
			foreach ($fivesdrafts as $fivesdraft) {
				if ($fivesdraft->Field == "entity") {
					$has_entity_column = true;
					break;
				}
			}

			if (!$has_entity_column) {
				$t = $wpdb->get_results("ALTER TABLE $table_name ADD COLUMN entity varchar(7) NOT NULL;");
			}
			?>
			<h3><?php _e('Pagamento por Multibanco via HiPay Wallet', $this->id); ?></h3>
			<p><?php _e('Emita Refer&ecirc;ncias Multibanco na sua loja online, pagas na rede Multibanco ou atrav&eacute;s de HomeBanking.', $this->id); ?>
			</p>

			<table class="wc_emails widefat" cellspacing="0">
				<tbody>
					<tr>
						<td class="wc-email-settings-table-status">
							<?php
							if ($soap_active) { ?>
								<span class="status-enabled"></span>
								<?php
							} else { ?>
								<span class="status-disabled"></span>
								<?php
							} ?>
						</td>
						<td class="wc-email-settings-table-name"><?php echo __('SOAP LIB', $this->id); ?></td>
						<td>
							<?php
							if (!$soap_active)
								echo __('Instale ou ative a livraria SOAP para funcionamento correto.', $this->id); ?>
						</td>
					</tr>

					<tr>
						<td class="wc-email-settings-table-status">
							<?php
							if ($has_entity_column) { ?>
								<span class="status-enabled"></span>
								<?php
							} else { ?>
								<span class="status-disabled"></span>
								<?php
							} ?>
						</td>
						<td class="wc-email-settings-table-name"><?php echo __('Estrutura da Tabela de Registo', $this->id); ?>
						</td>
						<td><?php if (!$has_entity_column)
							echo __("Faça reload para verificar se o problema foi resolvido.", $this->id); ?>
						</td>
					</tr>

					<tr>
						<td class="wc-email-settings-table-status">
							<?php
							if ($soap_active && $has_webservice_access) { ?>
								<span class="status-enabled"></span>
								<?php
							} else { ?>
								<span class="status-disabled"></span>
								<?php
							} ?>
						</td>
						<td class="wc-email-settings-table-name"><?php echo __('Acesso Webservice', $this->id); ?></td>
						<td><?php if (!$has_webservice_access) {
							echo __('Verifique se a falha de acesso do servidor ao Webservice é temporária ou de autenticação.', $this->id);
							echo "<br>" . __('ERRO: ', $this->id) . $has_webservice_access_error;
						} elseif (!$soap_active) {
							echo __('Instale ou ative a livraria SOAP para funcionamento correto.', $this->id);
						}
						?></td>
					</tr>


				</tbody>
			</table>

			<table class="form-table">

				<?php
				$this->generate_settings_html();
				?>
			</table>

			<p>
				<?php _e('CERTIFIQUE-SE QUE:<br>1. tem a livraria SOAP para PHP ativa<br>2. tem a API REST do Woocommerce ativa<br><br>', $this->id); ?>
			</p>
			<p><?php _e('CANCELAMENTO DE REFERÊNCIAS MULTIBANCO<br>Para cancelar as ordens com referências expiradas configurar um cronjob para correr todos os dias (por exemplo às 12:00)<br><br>Endereço<br>http://link_da_loja/wc-api/WC_HipayMultibanco/?order=cancel<br><br>Alterar "http://link_da_loja" para o link da loja.<br><br>Os stocks são atualizados (incrementados com a quantidade reservada) caso não tenha optado pela atualização após confirmação de pagamento.', $this->id); ?>
			</p>
			<?php
		}


		/**
		 * log action
		 * 
		 * @param string 	$messsage_
		 * @param string 	$type_ 
		 */
		private function logActionMessage($message, $type)
		{

			if ($this->logActions == "yes") {

				if (is_array($message) || is_object($message)) {
					$message = wc_print_r($message, true);
				}

				if ($type === self::HIPAY_LOG_INFO) {
					$this->logger->info($message, array('source' => $this->id));
				} else {
					$this->logger->error($message, array('source' => $this->id));
				}
			}
		}

		/**
		 * multibanco details for display template
		 * 
		 * @param int 	$order_id_
		 */
		function thanks_page($order_id)
		{

			global $woocommerce;
			global $myref;
			global $wpdb;

			$order = new WC_Order($order_id);
			$order_total = $order->get_total();

			$fivesdrafts = $wpdb->get_results("SELECT ID, entity,reference, order_id, processed FROM " . $wpdb->prefix . "woocommerce_hipay_mb WHERE order_id = '" . $order_id . "'");
			foreach ($fivesdrafts as $fivesdraft) {
				echo '<table cellpadding="6" cellspacing="2" style="width: 350px; height: 55px; margin: 10px 0 2px 0;border: 1px solid #ddd"><tr>
							<td style="background-color: #ccc;color:#313131;text-align:center;" colspan="3">' . $this->description_ref . '</td>
						</tr>
						<tr>
							<td rowspan="3" style="width:110px;padding: 0px 5px 0px 5px;vertical-align: middle;"><img src="' . WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__)) . '/images/mb_payment.jpg" style="margin-bottom: 0px; margin-right: 0px;"/></td>
							<td style="width:100px;">ENTIDADE:</td>
							<td style="font-weight:bold;width:245px;">' . $fivesdraft->entity . '</td>
						</tr>
						<tr>
							<td>REFER&Ecirc;NCIA:</td>
							<td style="font-weight:bold;">' . $fivesdraft->reference . '</td>
						</tr>
						<tr>
							<td>VALOR:</td>
							<td style="font-weight:bold;">' . $order_total . ' &euro;</td>
						</tr>
					</table>';
			}
			if (isset($_GET["ref"]))
				$woocommerce->cart->empty_cart();

		}


		/**
		 * process multibanco request
		 * 
		 * @param int 	$order_id
		 * 
		 * @return array
		 */
		function process_payment($order_id)
		{

			global $woocommerce;
			global $wpdb;

			$order = new WC_Order($order_id);
			$order_total = $order->get_total();

			$myref = $this->GenerateReference($order_id, $order_total);

			$this->logActionMessage("Order id: " . $order_id, self::HIPAY_LOG_INFO);
			if ($myref->error !== "") {
				$this->logActionMessage($myref, self::HIPAY_LOG_ERROR);
				throw new Exception($myref->error);
			} else {
				$this->logActionMessage($myref, self::HIPAY_LOG_INFO);
			}

			$table_name = $wpdb->prefix . 'woocommerce_hipay_mb';
			$timeLimitDays = $this->timeLimitDays + 1;
			$expire_date = date('Y-m-d', strtotime("+" . $timeLimitDays . " days"));
			$expire_date .= " 00:00:00";
			$wpdb->insert($table_name, array('entity' => $myref->entity, 'reference' => $myref->reference, 'timeLimit' => $this->timeLimitDays, 'expire_date' => $expire_date, 'order_id' => $order_id));

			$order->update_status('on-hold', __('Aguardar Pagamento por Multibanco.', $this->id));
			if ($this->stockonpayment != "yes") {
				wc_reduce_stock_levels($order_id);
			}

			$order->add_order_note('Entidade: ' . $myref->entity . ' Ref. Multibanco: ' . $myref->reference);

			return array(
				'result' => 'success',
				'redirect' => add_query_arg('ent', $myref->entity, add_query_arg('ref', $myref->reference, add_query_arg('order-received', $order_id, add_query_arg('key', $order->get_order_key(), $order->get_checkout_order_received_url()))))
			);
		}


		/**
		 * multibanco details for email template
		 * 
		 * @param WC_Order 	$order_
		 * @param bool 		$sent_to_admin
		 * @param bool 		$plain_text
		 */

		function email_instructions($order, $sent_to_admin, $plain_text = false)
		{

			global $woocommerce;
			global $wpdb;

			$order_total = $order->get_total();
			$order_status = $order->get_status();
			$order_payment_method = get_post_meta($order->get_id(), '_payment_method', true);
			$order_id = $order->get_id();

			if ($order_status !== 'on-hold' || $order_payment_method !== 'hipaymultibanco')
				return;

			$table_name = $wpdb->prefix . 'woocommerce_hipay_mb';
			$fivesdrafts = $wpdb->get_results("SELECT ID, reference, order_id,entity FROM $table_name WHERE order_id = '" . $order_id . "' LIMIT 1");

			foreach ($fivesdrafts as $fivesdraft) {

				echo '<table cellpadding="6" cellspacing="2" style="width: 390px; height: 55px; margin: 10px 2px;border: 1px solid #ddd"><tr>
						<td style="background-color: #ccc;color:#313131;text-align:center;" colspan="3">' . $this->description_ref . '</td>
					</tr>
					<tr>
						<td rowspan="3" style="width:200px;padding: 0px 5px 0px 5px;vertical-align: middle;"><img src="' . WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__)) . '/images/mb_payment.jpg" style="margin-bottom: 0px; margin-right: 0px;"/></td>
						<td style="width:100px;">ENTIDADE:</td>
						<td style="font-weight:bold;width:245px;">' . $fivesdraft->entity . '</td>
					</tr>
					<tr>
						<td>REFER&Ecirc;NCIA:</td>
						<td style="font-weight:bold;">' . $fivesdraft->reference . '</td>
					</tr>
					<tr>
						<td>VALOR:</td>
						<td style="font-weight:bold;">' . $order_total . ' &euro;</td>
					</tr>
				</table>';
			}
		}


		/**
		 * process payment notification
		 * 
		 * @return bool
		 */
		function check_callback_response()
		{

			global $woocommerce;
			global $wpdb;

			$order_id = filter_var($_GET["order"], FILTER_SANITIZE_STRING);
			$ch = filter_var($_GET["ch"], FILTER_SANITIZE_STRING);

			if ($order_id == "cancel") {

				//get all pending orders
				$table_name = $wpdb->prefix . 'woocommerce_hipay_mb';
				$expire_date = date('Y-m-d');
				$expire_date .= " 00:00:00";

				$fivesdrafts = $wpdb->get_results("SELECT ID, reference, order_id, processed FROM $table_name WHERE processed = 0 and expire_date <= '" . $expire_date . "'");

				foreach ($fivesdrafts as $fivesdraft) {
					//get order
					$orderCheck = wc_get_order($fivesdraft->order_id);
					if (!empty($orderCheck)) {
						$order = new WC_Order($fivesdraft->order_id);

						//get status
						$order_c_id = $order->get_id();

						$cur_payment_method = get_post_meta($order_c_id, '_payment_method', true);
						//update order if! wc-pending or wc-on-hold
						if ($cur_payment_method == 'hipaymultibanco' && ($order->post->post_status == "wc-pending" || $order->post->post_status == "wc-on-hold")) {
							$order->update_status('cancelled', __("Ref. MULTIBANCO Expirada: ", "woothemes"), 0);
							$this->logActionMessage("Cron cancelled Order id: " . $fivesdraft->order_id . " Reference expired", self::HIPAY_LOG_INFO);

							if ($this->stockonpayment != "yes") {

								$products = $order->get_items();
								foreach ($products as $product) {
									$qt = $product['item_meta']['_qty'][0];
									$product_id = $product['item_meta']['_product_id'][0];
									$variation_id = (int) $product['item_meta']['_variation_id'][0];

									if ($variation_id > 0) {
										$pv = new WC_Product_Variation($variation_id);
										if ($pv->managing_stock()) {
											$pv->increase_stock($qt);
											$order->add_order_note('#' . $product_id . ' variation #' . $variation_id . ' stock +' . $qt);
										} else {
											$p = new WC_Product($product_id);
											$p->increase_stock($qt);
											$order->add_order_note('#' . $product_id . ' stock +' . $qt);
										}
									} else {
										$p = new WC_Product($product_id);
										$p->increase_stock($qt);
										$order->add_order_note('#' . $product_id . ' stock +' . $qt);
									}
								}
							}
						}
					} else {
						$this->logActionMessage("Cron tried to cancel Order id: " . $fivesdraft->order_id . " but order does not exist", self::HIPAY_LOG_INFO);
					}
					//update mb table processed
					$wpdb->update($table_name, array('processed' => 1, 'processed_date' => date('Y-m-d H:i:s')), array('ID' => $fivesdraft->ID));

				}

				return;
			}

			if ($ch == sha1($this->salt . $order_id)) {

				try {
					$wsURL = "https://hm.comprafacil.pt/SIBSClick2/webservice/comprafacilWS.asmx?wsdl";
					if ($this->sandbox == "yes")
						$wsURL = "https://hm.comprafacil.pt/SIBSClick2Teste/webservice/comprafacilWS.asmx?wsdl";
					if ($this->entidade == "10241") {
						$wsURL = "https://hm.comprafacil.pt/SIBSClick/webservice/comprafacilWS.asmx?wsdl";
						if ($this->sandbox == "yes")
							$wsURL = "https://hm.comprafacil.pt/SIBSClickTeste/webservice/comprafacilWS.asmx?wsdl";
					}

					$ref = filter_var($_GET['ref'], FILTER_SANITIZE_STRING);
					$ent = filter_var($_GET['ent'], FILTER_SANITIZE_STRING);
					$transaction_id = preg_replace('/\s+/', '', $ent . $ref);

					$parameters = array(
						"reference" => $ref,
						"username" => $this->username,
						"password" => $this->password
					);

					$client = new SoapClient($wsURL);

					$paid = false;
					$res = $client->getInfoReference($parameters);
					if ($res->getInfoReferenceResult) {
						$paid = $res->paid;
						if ($paid) {
							$this->logActionMessage("Check Order id: " . $order_id . " with Reference: " . $ref . " is Paid", self::HIPAY_LOG_INFO);
							$order = new WC_Order($order_id);
							if ($this->stockonpayment == "yes") {
								wc_reduce_stock_levels($order_id);
								$order->add_order_note('Stock atualizado depois de pagamento');
							}

							$order->payment_complete($transaction_id);
							$this->logActionMessage("Check Order id: " . $order_id . " - Set completed", self::HIPAY_LOG_INFO);

						} else {
							$this->logActionMessage("Check Order id: " . $order_id . " with Reference: " . $ref . " is not paid", self::HIPAY_LOG_INFO);
							$order = new WC_Order($order_id);
							$order->add_order_note('Tentativa de pagamento falhada: ref. MB n&atilde;o paga.');
						}

					} else {
						$this->logActionMessage("Check Order id: " . $order_id . " unable to check", self::HIPAY_LOG_ERROR);
						header('HTTP/1.1 402 Payment Required');
						echo "payent required";
						exit;
					}

				} catch (Exception $e) {
					$this->logActionMessage("Check Order id: " . $order_id . " " . $e->getMessage(), self::HIPAY_LOG_ERROR);
					header('HTTP/1.1 402 Payment Required');
					echo "payent required";
					exit;
				}
			}
			return true;
		}


		/**
		 * generate multibanco payment details
		 * 
		 * @param int  $order_id 
		 * @param float $order_value
		 * 
		 * @return array $result
		 */
		function GenerateReference($order_id, $order_value)
		{

			global $woocommerce;

			$order = new WC_Order( $order_id );
			$billing_data = get_post_meta($order_id);

			$billing_data['h_client_name'] = "";
			$billing_data['h_client_name'] .= "";
			$billing_data['h_client_email'] = "";
			$billing_data['h_client_phone'] = "";
			$billing_data['h_client_city'] = "";
			$billing_data['h_client_address'] = "";
			$billing_data['h_client_address'] .= "";
			$billing_data['h_client_postcode'] = "";

			$billing_data['h_client_name'] =  (isset($billing_data['_billing_first_name'][0])) ? $billing_data['_billing_first_name'][0] : $order->get_billing_first_name();
			$billing_data['h_client_name'] .= (isset($billing_data['_billing_last_name'][0])) ? $billing_data['_billing_last_name'][0] : $order->get_billing_last_name();
			$billing_data['h_client_email'] = (isset($billing_data['_billing_email'][0])) ? $billing_data['_billing_email'][0] : $order->get_billing_email();
			$billing_data['h_client_phone'] = (isset($billing_data['_billing_phone'][0])) ? $billing_data['_billing_phone'][0] : $order->get_billing_phone();
			$billing_data['h_client_city'] =  (isset($billing_data['_billing_city'][0])) ? $billing_data['_billing_city'][0] : $order->get_billing_city();
			$billing_data['h_client_address'] = (isset($billing_data['_billing_address_1'][0])) ? $billing_data['_billing_address_1'][0] : $order->get_billing_address_1();
			$billing_data['h_client_address'] .= (isset($billing_data['_billing_address_2'][0])) ? " " . $billing_data['_billing_address_2'][0] : $order->get_billing_address_2();
			$billing_data['h_client_postcode'] = (isset($billing_data['_billing_postcode'][0])) ? $billing_data['_billing_postcode'][0] : " " . $order->get_billing_postcode();

			$wsURL = "https://hm.comprafacil.pt/SIBSClick2/webservice/comprafacilWS.asmx?wsdl";
			if ($this->sandbox == "yes")
				$wsURL = "https://hm.comprafacil.pt/SIBSClick2Teste/webservice/comprafacilWS.asmx?wsdl";
			if ($this->entidade == "10241") {
				$wsURL = "https://hm.comprafacil.pt/SIBSClick/webservice/comprafacilWS.asmx?wsdl";
				if ($this->sandbox == "yes")
					$wsURL = "https://hm.comprafacil.pt/SIBSClickTeste/webservice/comprafacilWS.asmx?wsdl";
			}

			$ch = sha1($this->salt . $order_id);
			$permalink_structure = get_option('permalink_structure');
			if ($permalink_structure == "")
				$callback_url = site_url() . '?wc-api=WC_HipayMultibanco&order=' . $order_id . "&" . "ch=" . $ch;
			else
				$callback_url = site_url() . '/wc-api/WC_HipayMultibanco/?order=' . $order_id . "&" . "ch=" . $ch;

			try {

				$order_value = number_format($order_value, 2, ".", "");
				$parameters = array(
					"origin" => $callback_url,
					"username" => $this->username,
					"password" => $this->password,
					"amount" => $order_value,
					"additionalInfo" => "",
					"name" => $billing_data['h_client_name'],
					"address" => $billing_data['h_client_address'],
					"postCode" => $billing_data['h_client_postcode'],
					"city" => $billing_data['h_client_city'],
					"NIC" => "",
					"externalReference" => $order_id,
					"contactPhone" => $billing_data['h_client_phone'],
					"email" => $billing_data['h_client_email'],
					"IDUserBackoffice" => -1,
					"timeLimitDays" => (int) $this->timeLimitDays,
					"sendEmailBuyer" => false
				);

				$client = new SoapClient($wsURL);

				$res = $client->getReferenceMB($parameters);
				if ($res->getReferenceMBResult) {
					//$value = number_format($res->amountOut, 2);
					$this->entity = $res->entity;
					$this->reference = $res->reference;
					$res->error = "";
					return $res;
				} else {
					return $res;
				}

			} catch (Exception $e) {
				$res = new stdClass();
				$res->error = $e->getMessage();
				return $res;
			}
		}
	}


	/**
	 * filter - woocommerce_available_payment_gateways
	 * 
	 * @param array  $methods Payment methods
	 * 
	 * @return array $methods
	 */
	function filter_hipaymultibanco_gateway($methods)
	{

		global $woocommerce;

		if (isset($woocommerce->cart)) {
			if (get_woocommerce_currency() != WC_HipayMultibanco::HIPAY_MULTIBANCO_CURRENCY) {
				unset($methods['hipaymultibanco']);
			}
			$plugin_option = get_option('woocommerce_hipaymultibanco_settings');
			if (isset($plugin_option['hw_max_amount']))
				$hw_max_amount = $plugin_option['hw_max_amount'];
			else
				$hw_max_amount = 0;
			$currency_symbol = get_woocommerce_currency_symbol();
			$total_amount = $woocommerce->cart->get_total();
			$total_amount = str_replace($currency_symbol, "", $total_amount);
			$thousands_sep = wp_specialchars_decode(stripslashes(get_option('woocommerce_price_thousand_sep')), ENT_QUOTES);
			$total_amount = str_replace($thousands_sep, "", $total_amount);
			$decimals_sep = wp_specialchars_decode(stripslashes(get_option('woocommerce_price_decimal_sep')), ENT_QUOTES);
			if ($decimals_sep != ".")
				$total_amount = str_replace($decimals_sep, ".", $total_amount);
			$total_amount = floatval(preg_replace('#[^\d.]#', '', $total_amount));
			if ($total_amount > $hw_max_amount && $hw_max_amount > 0)
				unset($methods['hipaymultibanco']);
		}
		return $methods;
	}

	add_filter('woocommerce_available_payment_gateways', 'filter_hipaymultibanco_gateway');

	/**
	 * filter - woocommerce_payment_gateways
	 * 
	 * @param array  $methods Payment methods
	 * 
	 * @return array $methods
	 */
	function add_hipaymultibanco_gateway($methods)
	{
		$methods[] = 'WC_HipayMultibanco';
		return $methods;
	}

	add_filter('woocommerce_payment_gateways', 'add_hipaymultibanco_gateway');

}
