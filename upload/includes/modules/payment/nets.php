<?php

/* --------------------------------------------
 * Class nets_ORIGIN.
 *
 * Payment gateway for Nets AS.
 *
 * @category   Payment Module
 *
 * @link https://www.nets.eu
 *
 * @author Nets <support@nets.eu>
 *
 * @copyright  2021 Nets
 *
 * @license MIT License
 *
 * VERSION HISTORY:
 * 1.0.0 Nets Payment Gateway.
  ---------      ----------------------------- */
defined('GM_HTTP_SERVER') or define('GM_HTTP_SERVER', HTTP_SERVER);
require_once(DIR_FS_CATALOG . 'system/classes/external/nets/Locale.php');

class nets_ORIGIN {

    public $code;
    public $title;
    public $description;
    public $tmpOrders = false;
    public $tmpStatus = 0;
    public $enabled;
    public $_coo_apa;
    public $sort_order;
    public $order_status;
    public $config;
    public $shopUrl;
    public $logger;

    public function __construct() {
        /** @var \NetsEasyPayment _coo_apa */
        $this->_coo_apa = MainFactory::create_object('NetsEasyPayment');
        $t_order = $GLOBALS['order'];
        $_SESSION['payment'] = $this->code = 'nets';
        $this->title = defined('MODULE_PAYMENT_NETS_TEXT_TITLE') ? MODULE_PAYMENT_NETS_TEXT_TITLE : '';
        $this->description = '';
        if (defined('MODULE_PAYMENT_NETS_TEXT_DESCRIPTION') && defined('DIR_WS_ADMIN')) {
            $version = '1.0.0';
            $this->description = '
				<div class="nets-container">
					<div class="nets-info">
						<p class="logo">
							<img src="' . GM_HTTP_SERVER . '/images/icons/payment/nets.png' . '" class="nets-easy">
						</p>
						<span class="subtxt">
							' . MODULE_PAYMENT_NETS_TEXT_INFO . '
						</span>
						<span class="version">
							' . MODULE_PAYMENT_NETS_TEXT_VERSION . $version . '
						</span>
						<p class="info">
							' . MODULE_PAYMENT_NETS_TEXT_DESCRIPTION . '
						</p>
					</div>
					<a class="btn-config" href="' . GM_HTTP_SERVER . DIR_WS_ADMIN . 'admin.php?do=NetsEasyConfiguration">
						' . $this->_coo_apa->get_text('configure') . '
					</a>
				</div>
			';
        }
        $this->enabled = defined('MODULE_PAYMENT_' . strtoupper($this->code) . '_STATUS') && filter_var(constant('MODULE_PAYMENT_' . strtoupper($this->code) . '_STATUS'), FILTER_VALIDATE_BOOLEAN);
        $this->sort_order = defined('MODULE_PAYMENT_NETS_SORT_ORDER') ? MODULE_PAYMENT_NETS_SORT_ORDER : '0';
        if (is_object($t_order)) {
            $this->update_status();
        }
        $this->config = $this->config();
        $this->shopUrl = GM_HTTP_SERVER . substr(DIR_WS_CATALOG, 0, -1);
        $this->logger = LogControl::get_instance();
        // IF NOT EXISTS gxnets table create it!!
        $result = xtc_db_query("CREATE TABLE IF NOT EXISTS `gxnets` (
		`gxnets_id` int(10) unsigned NOT NULL auto_increment,		
		`payment_id` varchar(50) default NULL,
		`charge_id` varchar(50) default NULL,
		`product_ref` varchar(55) collate latin1_general_ci default NULL,
		`charge_qty` int(11) default NULL,
		`charge_left_qty` int(11) default NULL,
		`updated` int(2) unsigned default '0',
		`created` datetime NOT NULL,
		`timestamp` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
		PRIMARY KEY (`gxnets_id`)
		)");
    }

    public function update_status() {
        $t_order = $GLOBALS['order'];
    }

    /**
     * @return false
     */
    public function pre_confirmation_check() {
        return false;
    }

    /**
     * @return false
     */
    function process_button() {
        return false;
    }

    function payment_action() {
        return false;
    }

    /**
     * @return array|false
     */
    public function selection() {
        $selection = [
            'id' => $this->code,
            'module' => $this->title,
            'description' => $this->info,
        ];

        return $selection;
    }

    /**
     * @return string
     */
    protected function _getDescription() {
        $description = constant('MODULE_PAYMENT_NETS_DISPLAY_DESCRIPTION_' . strtoupper($_SESSION['language_code']));
        $description .= '
			<style> .nets .payment-module-icon img{background: initial !important;}</style>
			<br>
		';
        foreach ($this->getPaymentMethods() as $method) {
            if (constant(MODULE_PAYMENT_NETS_ . strtoupper($method)) === 'true') {
                $description .= $this->_getPaymentMethodIcon($method);
            }
        }
        return $description;
    }

    /**
     * @param $paymentMethod
     *
     * @return string
     */
    protected function _getPaymentMethodIcon($paymentMethod) {
        if (file_exists(DIR_FS_CATALOG . 'images/icons/payment/nets/card_' . $paymentMethod) . '.svg') {
            $src = xtc_href_link("images/icons/payment/nets/card_{$paymentMethod}.svg", '', 'SSL', false, false, false, true, true);
            return '<img src="' . $src . '" alt="' . $paymentMethod . '" width="70" style="margin: 7px 7px 7px 0;background: #fff;">';
        }
        return '';
    }

    /**
     * Loads Nets PHP Library.
     */
    public function register_autoloader() {
        spl_autoload_register(function ($class) {
            $root = SHOP_ROOT . 'system/classes/external';
            $classFile = $root . '/' . str_replace('\\', '/', $class) . '.php';
            if (file_exists($classFile)) {
                require_once $classFile;
            }
        });
    }

    /**
     * @return false
     */
    public function confirmation() {
        if (!isset($_GET['nets_success']) && $this->config['NETS_CHECKOUT_FLOW'] == 'embedded') {
            // Lookup Test mode setting
            if ($this->config['NETS_CHECKOUT_MODE']) {
                $netsUrl = "https://test.api.dibspayment.eu/v1/payments";
            } else {
                $netsUrl = "https://api.dibspayment.eu/v1/payments";
            }
            //build datastring
            $datastring = $this->createRequestObject();
            $response = $this->makeCurlRequest($netsUrl, $datastring);
            //Debug mode	
            if ($this->config['NETS_DEBUG_MODE'] == 'debug_front') {
                echo "<pre>";
                print_r($datastring);
                echo "<br><pre>";
                print_r($response);
                die;
            }
            $_SESSION['nets_checkout_info'] = defined('MODULE_PAYMENT_NETS_CHECKOUT_INFO') ? MODULE_PAYMENT_NETS_CHECKOUT_INFO : '';
            $_SESSION['nets']['paymentid'] = $response->paymentId;
        }
        return false;
    }

    public function savePaymentId($orderId) {
        $paymentId = $_SESSION['nets']['paymentid'];
        if (!empty($orderId)) {
            xtc_db_query(
                    "UPDATE " . TABLE_ORDERS . " SET orders_ident_key='{$paymentId}' WHERE orders_id='{$orderId}'"
            );
        }
        //save charge payment details in gxnets if auto capture is enabled
        if ($this->config['NETS_AUTO_CAPTURE']) {
            $chargeResponse = $this->makeCurlRequest($this->getApiUrl() . $paymentId, array(), 'GET');
            if (isset($chargeResponse)) {
                foreach ($chargeResponse->payment->charges as $ky => $val) {
                    foreach ($val->orderItems as $key => $value) {
                        if (isset($val->chargeId)) {
                            $charge_query = "insert into `gxnets` (`payment_id`, `charge_id`,  `product_ref`, `charge_qty`, `charge_left_qty`,`created`) "
                                    . "values ('" . $paymentId . "', '" . $val->chargeId . "', '" . $value->reference . "', '" . $value->quantity . "', '" . $value->quantity . "',now())";
                            xtc_db_query($charge_query);
                        }
                    }
                }
            }
        }
    }

    /**
     * @return false
     */
    public function before_process() {
        if (!isset($_GET['nets_success'])) {
            // Lookup Test mode setting
            if ($this->config['NETS_CHECKOUT_MODE']) {
                $netsUrl = "https://test.api.dibspayment.eu/v1/payments";
            } else {
                $netsUrl = "https://api.dibspayment.eu/v1/payments";
            }
            //build datastring
            $datastring = $this->createRequestObject();
            $response = $this->makeCurlRequest($netsUrl, $datastring);
            //Debug mode
            if ($this->config['NETS_DEBUG_MODE'] == 'debug_front') {
                echo "<pre>";
                print_r($datastring);
                echo "<br><pre>";
                print_r($response);
                die;
            }
            $_SESSION['nets']['paymentid'] = $response->paymentId;
            //language support for checkout frame 
            if ($_SESSION['language_code'] == 'en') {
                $lang = 'en-GB';
            }
            if ($_SESSION['language_code'] == 'de') {
                $lang = 'de-DE';
            }
            if ($_SESSION['language_code'] == 'dk') {
                $lang = 'da-DK';
            }
            if ($_SESSION['language_code'] == 'se') {
                $lang = 'sv-SE';
            }
            if ($_SESSION['language_code'] == 'no') {
                $lang = 'nb-NO';
            }
            if ($_SESSION['language_code'] == 'fi') {
                $lang = 'fi-FI';
            }
            if ($_SESSION['language_code'] == 'pl') {
                $lang = 'pl-PL';
            }
            if ($_SESSION['language_code'] == 'nl') {
                $lang = 'nl-NL';
            }
            if ($_SESSION['language_code'] == 'fr') {
                $lang = 'fr-FR';
            }
            if ($_SESSION['language_code'] == 'es') {
                $lang = 'es-ES';
            }
            xtc_redirect($response->hostedPaymentPageUrl . "&language=$lang");
        }
        return false;
    }

    /**
     * @return false
     */
    public function after_process() {
        try {
            if (isset($_GET['nets_success'])) {
                $orderId = new IdType((int) $GLOBALS['insert_id']);
                $this->savePaymentId($orderId);
                //update order reference id in easy portal				 
                $response = $this->makeCurlRequest($this->getApiUrl() . $_SESSION['nets']['paymentid'], array(), "GET");
                $refUpdate = [
                    'reference' => $GLOBALS['insert_id'],
                    'checkoutUrl' => $response->payment->checkout->url
                ];
                $responses = $this->makeCurlRequest($this->getUpdateRefUrl($_SESSION['nets']['paymentid']), $refUpdate, 'PUT');
                return true;
            } else {
                $this->logger->write_text_log('Nets payment failed', 'nets');
            }
        } catch (\Nets\NetsException $e) {
            
        }
        return false;
    }

    function getPaymentMethods() {
        $paymentMethods = [
            'masterpass', 'mastercard', 'visa', 'apple_pay', 'maestro', 'jcb', 'american_express', 'wirpay',
            'paypal', 'bitcoin', 'sofortueberweisung_de', 'airplus', 'billpay', 'bonuscard', 'cashu', 'cb',
            'diners_club', 'direct_debit', 'discover', 'elv', 'ideal', 'invoice', 'myone', 'paysafecard',
            'postfinance_card', 'postfinance_efinance', 'swissbilling', 'twint', 'barzahlen', 'bancontact',
            'giropay', 'eps', 'google_pay', 'klarna_paynow', 'klarna_paylater', 'oney'
        ];
        return $paymentMethods;
    }

    /*
     * Generate request object in json format that will be sended to API
     * @return array
     * 
     */

    public function createRequestObject() {
        global $order;
        global $shipping;
        // product items
        foreach ($order->products as $item) {
            // easy calc method
            $quantity = $item['qty'];
            $product = $item['price']; // product price incl. VAT in DB format 
            $tax = $item['tax']; // Tax rate in DB format
            $taxFormat = '1' . str_pad(number_format((float) $tax, 2, '.', ''), 5, '0', STR_PAD_LEFT);
            $unitPrice = round(round(($product * 100) / $taxFormat, 2) * 100);
            $netAmount = round($quantity * $unitPrice);
            $grossAmount = round($quantity * ($product * 100));
            $taxAmount = $grossAmount - $netAmount;
            $itemsArray[] = array(
                'reference' => $item['model'],
                'name' => $item['name'],
                'quantity' => $quantity,
                'unit' => 'pcs',
                'unitPrice' => $unitPrice,
                'taxRate' => $item['tax'] * 100,
                'taxAmount' => $taxAmount,
                'grossTotalAmount' => $grossAmount,
                'netTotalAmount' => $netAmount
            );
        }
        //shipping items
        if (!empty($_SESSION['shipping']['cost'])) {
            //get shipping tax rate
            $shippingCostsControl = CartShippingCostsControl::get_instance();
            $selectedShippingModuleArray = $shippingCostsControl->get_selected_shipping_module();
            [$selectedShippingModule, $selectedShippingMethod] = explode('_', key($selectedShippingModuleArray));
            $selectedShippingModuleLabel = current($selectedShippingModuleArray);
            $shippingObj = MainFactory::create('shipping');
            $selectedModuleQuote = $shippingObj->quote($selectedShippingMethod, $selectedShippingModule);
            if (!empty($selectedModuleQuote)) {
                $shippingTaxRate = $selectedModuleQuote[0]['tax'];
            }
            // get shiiping cost including tax
            $this->orderDetailsCartContentView = MainFactory::create_object('OrderDetailsCartContentView');
            $cartShippingCostsControl = MainFactory::create_object('CartShippingCostsControl', array(), true);
            $this->orderDetailsCartContentView->setCartShippingCostsControl($cartShippingCostsControl);
            $cartShippingCostsValue = $cartShippingCostsControl->get_shipping_costs(false, false, '', false, true);
            $shippingTotalCostArray = explode(' ', $cartShippingCostsValue);
            //easy calc method  
            $quantity = 1;
            $shipping = (isset($shippingTotalCostArray[0])) ? str_replace(",", ".", $shippingTotalCostArray[0]) : 0; // shipping price incl. VAT in DB format 
            $tax = (isset($shippingTaxRate)) ? $shippingTaxRate : 0; // Tax rate in DB format
            $taxFormat = '1' . str_pad(number_format((float) $tax, 2, '.', ''), 5, '0', STR_PAD_LEFT);
            $unitPrice = round(round(($shipping * 100) / $taxFormat, 2) * 100);
            $netAmount = round($quantity * $unitPrice);
            $grossAmount = round($quantity * ($shipping * 100));
            $taxAmount = $grossAmount - $netAmount;
            $itemsArray[] = array(
                'reference' => 'Shipping',
                'name' => 'Shipping',
                'quantity' => $quantity,
                'unit' => 'pcs',
                'unitPrice' => $unitPrice,
                'taxRate' => $tax * 100,
                'taxAmount' => $taxAmount,
                'grossTotalAmount' => $grossAmount,
                'netTotalAmount' => $netAmount
            );
        }
        //Discount items
        if (!empty($_SESSION['cc_id'])) {
            //get coupon details	
            $db = StaticGXCoreLoader::getDatabaseQueryBuilder();
            $couponRow = $db->get_where('coupons', ['coupon_id' => $_SESSION['cc_id']])->row_array();
            //if coupon type fix amount
            $quantity = 1;
            $couponTaxRate = 0;
            $unitPrice = $couponTaxAmount = $couponRow['coupon_amount'] * 100;
            //if coupon type percent
            if ($couponRow['coupon_type'] == 'P') {
                // items total sum for discount
                $itemsPriceSumma = 0;
                foreach ($itemsArray as $total) {
                    $itemsPriceSumma += $total['grossTotalAmount'];
                }
                $unitPrice = $itemsPriceSumma;
                $couponTaxRate = $couponTaxAmount;
                $taxCalculationString = 1 + ($couponTaxAmount / 100); // 1.25
                $grossTotalAmount = ($itemsPriceSumma / 100) * $taxCalculationString;
                $couponTaxAmount = ($grossTotalAmount - ($itemsPriceSumma / 100));
                $couponTaxAmount = round((float) $couponTaxAmount / 100, 2) * 100;
            }
            $itemsArray[] = array(
                'reference' => 'discount',
                'name' => 'discount',
                'quantity' => $quantity,
                'unit' => 'pcs',
                'unitPrice' => $unitPrice,
                'taxRate' => $couponTaxRate,
                'taxAmount' => -$couponTaxAmount,
                'grossTotalAmount' => -$couponTaxAmount,
                'netTotalAmount' => -$couponTaxAmount
            );
        }
        // items total sum
        $itemsGrossPriceSumma = 0;
        foreach ($itemsArray as $total) {
            $itemsGrossPriceSumma += $total['grossTotalAmount'];
        }
        // compile datastring
		$x_reference = uniqid();
        $data = array(
            'order' => array(
                'items' => $itemsArray,
                'amount' => floatval($itemsGrossPriceSumma),
                'currency' => $order->info['currency'],
                'reference' => $x_reference
            ),
            'checkout' => array(
                'charge' => ($this->config['NETS_AUTO_CAPTURE']) ? 'true' : 'false',
                'publicDevice' => 'false'
            ),
			
        );
		//Webhooks 
        if ($this->config['NETS_WB_AUTH'] !='0') {
			$webHookUrl = $this->config['NETS_WB_URL'];
			$data['notifications'] = array(
				'webhooks' =>array(
									array(
										'eventName' => 'payment.checkout.completed',
										'url' => $webHookUrl,
										'authorization' => $this->config['NETS_WB_AUTH']
									),
									array(
										'eventName' => 'payment.charge.created',
										'url' => $webHookUrl,
										'authorization' => $this->config['NETS_WB_AUTH']
									),
									array(
										'eventName' => 'payment.refund.completed',
										'url' => $webHookUrl,
										'authorization' => $this->config['NETS_WB_AUTH']
									),
									array(
										'eventName' => 'payment.cancel.created',
										'url' => $webHookUrl,
										'authorization' => $this->config['NETS_WB_AUTH']
									)
							)
			);
        }
        // checkout type switch		
        if ($this->config['NETS_CHECKOUT_FLOW'] === 'redirect') {
            $data['checkout']['integrationType'] = 'HostedPaymentPage';
            $data['checkout']['cancelUrl'] = xtc_href_link(FILENAME_CHECKOUT_PAYMENT, 'nets_cancel=1', 'SSL');
            $data['checkout']['returnUrl'] = xtc_href_link(FILENAME_CHECKOUT_PROCESS, 'nets_success=1', 'SSL');
        } else {
            $data['checkout']['integrationType'] = 'EmbeddedCheckout';
            $data['checkout']['url'] = xtc_href_link(FILENAME_CHECKOUT_CONFIRMATION, '', 'SSL');
        }
        $data['checkout']['termsUrl'] = $this->config['NETS_TERMS_URL'];
        $data['checkout']['merchantTermsUrl'] = $this->config['NETS_MERCHANT_URL'];
        $data['checkout']['merchantHandlesConsumerData'] = true;
        //consumer data
        $customerTypeArray = array();
        if (!empty($order->customer['company'])) {
            $customerTypeArray = array('name' => $order->customer['company'],
                'contact' => array(
                    'firstName' => $order->customer['firstname'],
                    'lastName' => $order->customer['lastname'],
                )
            );
            $customerType = 'company';
        } else {
            $customerTypeArray = array(
                'firstName' => $order->customer['firstname'],
                'lastName' => $order->customer['lastname'],
            );
            $customerType = 'privatePerson';
        }
        $consumerData = array(
            'email' => $order->customer['email_address'],
            "shippingAddress" => array(
                "addressLine1" => $order->customer['street_address'] . ' ' . $order->customer['city'] . ' ' . $order->billing['postcode'],
                "addressLine2" => $order->customer['street_address'] . ' ' . $order->customer['city'] . ' ' . $order->billing['postcode'],
                "postalCode" => $order->customer['postcode'],
                "city" => $order->customer['city'],
                "country" => $order->customer['country']['iso_code_3']
            ),
            "$customerType" => $customerTypeArray
        );
        //Get phone prefix from phone no
        $number = $order->customer['telephone'];
        $phonePrefix = $GLOBALS['countriesList'][$order->customer['country']['iso_code_2']]['phone'];
        $consumerData['phoneNumber'] = ['prefix' => "+$phonePrefix", 'number' => $number];
        if ($this->validateAddress($consumerData)) {
            $data['checkout']['consumer'] = $consumerData;
        }
        return $data;
    }

    /**
     * 
     * @param string $url
     * @param array $data
     * @param type $method
     * @return string
     */
    protected function makeCurlRequest($url, $data = array(), $method = 'POST') {
        $ch = curl_init();
        $headers[] = 'Content-Type: text/json';
        $headers[] = 'Accept: test/json';
        $headers[] = 'commercePlatformTag: gambioGX4';
        if ($this->config['NETS_CHECKOUT_MODE'] == 'test') {
            $headers[] = 'Authorization: ' . str_replace('-', '', trim($this->config['NETS_TEST_SECRET_KEY']));
        } else {
            $headers[] = 'Authorization: ' . str_replace('-', '', trim($this->config['NETS_LIVE_SECRET_KEY']));
        }
        $postData = $data;
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        if ($postData) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
        }
        $response = curl_exec($ch);
        $info = curl_getinfo($ch);
        switch ($info['http_code']) {
            case 401:
                $message = 'NETS Easy authorization failed. Check your keys';
                break;
            case 400:
                $message = 'NETS Easy. Bad request: ' . $response;
                break;
            case 404:
                $message = 'Payment or charge not found';
                break;
            case 500:
                $message = 'Unexpected error';
                break;
        }
        if (!empty($message)) {
            $this->logger->write_text_log($message, 'nets');
        }
        if (curl_error($ch)) {
            $this->logger->write_text_log(curl_error($ch), 'nets');
        }
        if ($info['http_code'] == 200 || $info['http_code'] == 201 || $info['http_code'] == 400) {
            if ($response) {
                $responseDecoded = json_decode($response);
                $this->logger->write_text_log($response, 'nets');
                return ($responseDecoded) ? $responseDecoded : null;
            }
        }
    }

    public function check() {
        if (!isset($this->_check)) {
            $check_query = xtc_db_query("select `value` from `gx_configurations` where `key` = 'configuration/MODULE_PAYMENT_" . strtoupper($this->code) . "_STATUS'");
            $this->_check = xtc_db_num_rows($check_query);
        }

        return $this->_check;
    }

    public function install() {
        $config = $this->_configuration();
        $sort_order = 0;
        foreach ($config as $key => $data) {
            $install_query = "insert into `gx_configurations` (`key`, `value`,  `legacy_group_id`, `sort_order`, `type`, `last_modified`) "
                    . "values ('configuration/MODULE_PAYMENT_" . strtoupper($this->code) . "_" . $key . "', '"
                    . $data['value'] . "', '6', '" . $sort_order . "', '" . addslashes($data['type'])
                    . "', now())";
            xtc_db_query($install_query);
            $sort_order++;
        }
    }

    public function _configuration() {
        $config = [
            'STATUS' => [
                'value' => 'True',
                'type' => 'switcher',
            ],
            'ALLOWED' => [
                'value' => '',
            ],
            'SORT_ORDER' => [
                'value' => '0',
            ],
            'ORDER_STATUS_ID' => [
                'value' => '1',
                'type' => 'order-status',
            ],
            'ALIAS' => [
                'value' => 'NETS',
            ]
        ];
        return $config;
    }

    public function remove() {
        xtc_db_query("delete from `gx_configurations` where `key` in ('" . implode("', '", $this->keys()) . "')");
    }

    public function keys() {
        $ckeys = array_keys($this->_configuration());
        $keys = [];
        foreach ($ckeys as $k) {
            $keys[] = 'configuration/MODULE_PAYMENT_' . strtoupper($this->code) . '_' . $k;
        }
        return $keys;
    }

    public function config() {
        $db = StaticGXCoreLoader::getDatabaseQueryBuilder();
        $configurations = $db->select('key, value')
                ->like('key', 'NETS')
                ->order_by('sort_order ASC')
                ->get('gx_configurations')
                ->result_array();
        $newArr = array();
        foreach ($configurations as $row) {
            $strArr = explode('/', $row['key']);
            if ((isset($strArr[1])) ? $strArr[1] : '') {
                $newArr[$strArr[1]] = $row['value'];
            }
        }
        return $newArr;
    }

    function validateAddress($address) {
        return !empty($address['shippingAddress']['country']) && !empty($address['shippingAddress']['postalCode']) && (!empty($address['shippingAddress']['addressLine1']) || !empty($address['shippingAddress']['addressLine1']));
    }

    /**
     * @deprecated The method will be replaced by a checkout service soon.
     */
    public function reset() {
        $_SESSION['cart']->reset(true);
        // unregister session variables used during checkout
        unset($_SESSION['sendto']);
        unset($_SESSION['billto']);
        unset($_SESSION['shipping']);
        unset($_SESSION['payment']);
        unset($_SESSION['comments']);
        unset($_SESSION['last_order']);
        unset($_SESSION['tmp_oID']);
        unset($_SESSION['cc']);
        unset($_SESSION['nvpReqArray']);
        unset($_SESSION['reshash']);
        $GLOBALS['last_order'] = $this->order_id;
        //GV Code Start
        if (isset($_SESSION['credit_covers'])) {
            unset($_SESSION['credit_covers']);
        }
        // GX-Customizer:
        if (is_object($_SESSION['coo_gprint_cart'])) {
            $_SESSION['coo_gprint_cart']->empty_cart();
        }
    }

    /**
     * @return false
     */
    public function getPaymentId() {
        if (!empty($_SESSION['nets']['paymentid'])) {
            return $_SESSION['nets']['paymentid'];
        }
        $this->setPaymentMethod();
        // Lookup Test mode setting
        if ($this->config['NETS_CHECKOUT_MODE']) {
            $netsUrl = "https://test.api.dibspayment.eu/v1/payments";
        } else {
            $netsUrl = "https://api.dibspayment.eu/v1/payments";
        }
        $ro = $this->createRequestObject();
        $response = $this->makeCurlRequest($netsUrl, $ro);
        if (!empty($response->paymentId) && empty($_SESSION['nets']['paymentid'])) {
            $_SESSION['nets']['paymentid'] = $response->paymentId;
        }
        return $response;
    }

    protected function setPaymentMethod() {
        $_SESSION['payment_method'] = array(
            'code' => 'nets',
            'title' => 'Nets',
            'sort_order' => '1');
    }

    /*
     * Function to get payment api url based on environment i.e live or test
     * return payment api url
     */

    public function getApiUrl() {
        if ($this->config['NETS_CHECKOUT_MODE']) {
            return "https://test.api.dibspayment.eu/v1/payments/";
        } else {
            return "https://api.dibspayment.eu/v1/payments/";
        }
    }

    /*
     * Function to get update reference api url based on environment i.e live or test
     * return update reference api url
     */

    public function getUpdateRefUrl($paymentId) {
        return $this->getApiUrl() . $paymentId . '/referenceinformation';
    }

}

MainFactory::load_origin_class('nets');
