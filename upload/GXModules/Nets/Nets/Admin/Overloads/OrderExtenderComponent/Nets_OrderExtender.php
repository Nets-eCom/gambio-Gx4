<?php

require_once DIR_FS_CATALOG . 'includes/modules/payment/nets.php';

class Nets_OrderExtender extends Nets_OrderExtender_parent {

    const ENDPOINT_TEST = 'https://test.api.dibspayment.eu/v1/payments/';
    const ENDPOINT_LIVE = 'https://api.dibspayment.eu/v1/payments/';
    const ENDPOINT_TEST_CHARGES = 'https://test.api.dibspayment.eu/v1/charges/';
    const ENDPOINT_LIVE_CHARGES = 'https://api.dibspayment.eu/v1/charges/';
    const RESPONSE_TYPE = "application/json";

    private $client;
    protected $_nets_log;
    protected $nets;
    protected $paymentId;
    protected $orderId;
    public $logger;

    public function __construct() {
        parent::__construct();
        $this->nets = MainFactory::create_object('nets_ORIGIN');
        $this->orderId = $_GET['oID'];
        $this->logger = LogControl::get_instance();
    }

    public function proceed() {
        $orderId = (int) $this->v_data_array['GET']['oID'];
        $PaymentId = $this->getPaymentId($orderId);
        if (!empty($PaymentId)) {
            $_SESSION['NetsPaymentID'] = $PaymentId;
        }
        $order = new order($orderId);
        $baseUrl = ENABLE_SSL_CATALOG ? HTTP_CATALOG_SERVER : HTTPS_CATALOG_SERVER;
        $nets_order_view = MainFactory::create_object('ContentView');
        $nets_order_view->set_template_dir(DIR_FS_CATALOG . 'GXModules/Nets/Nets/Admin/Html/');
        $nets_order_view->set_flat_assigns(true);
        $nets_order_view->set_content_template('nets_order_view.html');
        $nets_order_view->set_content_data('baseUrl', $baseUrl);
        $responseItems = $this->checkPartialItems($this->orderId);
        $_SESSION['responseItems'] = $responseItems;
        $nets_order_view->set_content_data('responseItems', $_SESSION['responseItems']);
        $status = $this->is_easy($this->orderId);
        $nets_order_view->set_content_data('status', $status);
        $api_return = $this->getCurlResponse($this->getApiUrl() . $this->paymentId, 'GET');
        $response = json_decode($api_return, true);
        $response['payment']['checkout'] = "";
        $nets_order_view->set_content_data('apiGetRequest', $response);
        $order_html = $nets_order_view->get_html();
        if (!empty($this->paymentId)) {
            $this->addContentToCollection('below_product_data', $order_html, '<span id="nets-easy">NETS Easy</span><span id="nets-logo"></span>');
        }
        parent::proceed();
    }

    /*
     * Function to fetch payment id from databse table gxnets
     * @param $order_id
     * @return nets payment id
     */

    public function getPaymentId($order_id) {
        $order_query = xtc_db_query(
                "SELECT orders_ident_key FROM " . TABLE_ORDERS . "  WHERE orders_id = '" . (int) $order_id . "'"
        );
        if ($order_query->num_rows) {
            while ($orows = xtc_db_fetch_array($order_query)) {
                $this->paymentId = $orows['orders_ident_key'];
            }
        }
        return $this->paymentId;
    }

    /*
     * Function to get list of partial charge/refund and reserved items list
     * @param order id
     * @return array of reserved, partial charged,partial refunded items
     */

    public function checkPartialItems($orderId) {
        $orderItems = NetsEasyOrderController::getOrderItems($orderId);
        $products = [];
        $chargedItems = [];
        $refundedItems = [];
        $cancelledItems = [];
        $failedItems = [];
        $itemsList = [];
        if (!empty($orderItems)) {
            foreach ($orderItems['order']['items'] as $items) {
                $products[$items['reference']] = array(
                    'name' => $items['name'],
                    'quantity' => (int) $items['quantity'],
                    'taxRate' => $items['taxRate'],
                    'netprice' => $items['unitPrice'] / 100
                );
            }
            if (isset($orderItems['order']['amount'])) {
                $lists['orderTotalAmount'] = $orderItems['order']['amount'];
            }
        }
        $api_return = $this->getCurlResponse($this->getApiUrl() . $this->paymentId, 'GET');
        $response = json_decode($api_return, true);
        if (!empty($response['payment']['charges'])) {
            $qty = 0;
            $netprice = 0;
            $grossprice = 0;

            foreach ($response['payment']['charges'] as $key => $values) {

                for ($i = 0; $i < count($values['orderItems']); $i ++) {

                    if (array_key_exists($values['orderItems'][$i]['reference'], $chargedItems)) {
                        $qty = $chargedItems[$values['orderItems'][$i]['reference']]['quantity'] + $values['orderItems'][$i]['quantity'];
                        $price = $chargedItems[$values['orderItems'][$i]['reference']]['grossprice'] + number_format((float) ($values['orderItems'][$i]['grossTotalAmount'] / 100), 2, '.', '');
                        $priceGross = $price / $qty;
                        $netprice = $values['orderItems'][$i]['unitPrice'] * $qty;
                        $grossprice = $values['orderItems'][$i]['grossTotalAmount'] * $qty;
                        $chargedItems[$values['orderItems'][$i]['reference']] = array(
                            'reference' => $values['orderItems'][$i]['reference'],
                            'name' => $values['orderItems'][$i]['name'],
                            'quantity' => $qty,
                            'taxRate' => $values['orderItems'][$i]['taxRate'] / 100,
                            'grossprice' => $priceGross,
                            'currency' => $GLOBALS['order']->info['currency']
                        );
                    } else {
                        $priceOne = $values['orderItems'][$i]['grossTotalAmount'] / $values['orderItems'][$i]['quantity'];

                        $chargedItems[$values['orderItems'][$i]['reference']] = array(
                            'reference' => $values['orderItems'][$i]['reference'],
                            'name' => $values['orderItems'][$i]['name'],
                            'quantity' => $values['orderItems'][$i]['quantity'],
                            'taxRate' => $values['orderItems'][$i]['taxRate'] / 100,
                            'grossprice' => number_format((float) ($priceOne / 100), 2, '.', ''),
                            'currency' => $GLOBALS['order']->info['currency']
                        );
                    }
                }
            }
        }
        if (!empty($response['payment']['refunds'])) {
            $qty = 0;
            $netprice = 0;
            foreach ($response['payment']['refunds'] as $key => $values) {
                for ($i = 0; $i < count($values['orderItems']); $i ++) {
                    if (array_key_exists($values['orderItems'][$i]['reference'], $refundedItems)) {
                        $qty = $refundedItems[$values['orderItems'][$i]['reference']]['quantity'] + $values['orderItems'][$i]['quantity'];
                        $netprice = $values['orderItems'][$i]['unitPrice'] * $qty;
                        $grossprice = $values['orderItems'][$i]['grossTotalAmount'] * $qty;
                        $refundedItems[$values['orderItems'][$i]['reference']] = array(
                            'reference' => $values['orderItems'][$i]['reference'],
                            'name' => $values['orderItems'][$i]['name'],
                            'quantity' => $qty,
                            'grossprice' => number_format((float) (($grossprice / 100) / $qty), 2, '.', ''),
                            'currency' => $GLOBALS['order']->info['currency']
                        );
                    } else {
                        $refundedItems[$values['orderItems'][$i]['reference']] = array(
                            'reference' => $values['orderItems'][$i]['reference'],
                            'name' => $values['orderItems'][$i]['name'],
                            'quantity' => $values['orderItems'][$i]['quantity'],
                            'grossprice' => number_format((float) (($values['orderItems'][$i]['grossTotalAmount'] / 100) / $values['orderItems'][$i]['quantity']), 2, '.', ''),
                            'currency' => $GLOBALS['order']->info['currency']
                        );
                    }
                }
            }
        }
        if (isset($response['payment']['summary']['cancelledAmount'])) {
            foreach ($orderItems['order']['items'] as $items) {
                $cancelledItems[$items['reference']] = array(
                    'name' => $items['name'],
                    'quantity' => (int) $items['quantity'],
                    'netprice' => $items['unitPrice'] / 100
                );
            }
        }
        if (!isset($response['payment']['summary']['reservedAmount'])) {
            foreach ($orderItems['order']['items'] as $items) {
                $failedItems[$items['reference']] = array(
                    'name' => $items['name'],
                    'quantity' => (int) $items['quantity'],
                    'netprice' => $items['unitPrice'] / 100
                );
            }
        }
        // get list of partial charged items and check with quantity and send list for charge rest of items
        foreach ($products as $key => $prod) {
            if (array_key_exists($key, $chargedItems)) {
                $qty = $prod['quantity'] - $chargedItems[$key]['quantity'];
            } else {
                $qty = $prod['quantity'];
            }
            if (array_key_exists($key, $chargedItems) && array_key_exists($key, $refundedItems)) {
                if ($chargedItems[$key]['quantity'] == $refundedItems[$key]['quantity']) {
                    unset($chargedItems[$key]);
                }
            }
            if (array_key_exists($key, $chargedItems) && array_key_exists($key, $refundedItems)) {
                $qty = $chargedItems[$key]['quantity'] - $refundedItems[$key]['quantity'];
                if ($qty > 0)
                    $chargedItems[$key]['quantity'] = $qty;
            }
            if ($qty > 0) {
                $itemsList[] = array(
                    'name' => $prod['name'],
                    'reference' => $key,
                    'taxRate' => $prod['taxRate'] / 100,
                    'quantity' => $qty,
                    'netprice' => number_format((float) ($prod['netprice']), 2, '.', ''),
                    'grossprice' => number_format((float) ($prod['netprice'] * ("1." . $prod['taxRate'])), 2, '.', ''),
                    'currency' => $GLOBALS['order']->info['currency']
                );
            }
            if ($chargedItems[$key]['quantity'] > $prod['quantity']) {
                $chargedItems[$key]['quantity'] = $prod['quantity'];
            }
        }
        $reserved = $response['payment']['summary']['reservedAmount'];
        $charged = $response['payment']['summary']['chargedAmount'];
        $cancelled = $response['payment']['summary']['cancelledAmount'];
        $refunded = $response['payment']['summary']['refundedAmount'];

        if ($reserved != $charged && $reserved != $cancelled) {
            if (count($itemsList) > 0) {
                $lists['reservedItems'] = $itemsList;
            }
        }
        if (count($chargedItems) > 0 && $reserved === $charged) {
            $lists['chargedItems'] = $chargedItems;
        }
        if ($reserved != $charged && $reserved != $cancelled) {
            $lists['chargedItemsOnly'] = $chargedItems;
        }
        if (count($refundedItems) > 0) {
            $lists['refundedItems'] = $refundedItems;
        }
        if (count($cancelledItems) > 0) {
            $lists['cancelledItems'] = $itemsList;
        }
        if (count($failedItems) > 0) {
            $lists['failedItems'] = $itemsList;
        }
        return $lists;
    }

    /**
     * Function to check the nets payment status and display in admin order list backend page
     *
     * @return Payment Status
     */
    public function is_easy($oder_id) {
        if (!empty($oder_id)) {
            // Get order db status from orders_status_history if cancelled
            $orders_status_id = '';
            $order_query = xtc_db_query(
                    "SELECT orders_status_id FROM orders_status_history WHERE orders_id = '" . (int) $oder_id . "'"
            );
            if ($order_query->num_rows) {
                while ($orows = xtc_db_fetch_array($order_query)) {
                    $orders_status_id = $orows['orders_status_id'];
                }
                // if order is cancelled and payment is not updated as cancelled, call nets cancel payment api
                if ($orders_status_id === 99) {
                    $data = NetsEasyOrderController::getOrderItems($oder_id);
                    // call cancel api here
                    $cancelUrl = NetsEasyOrderController::getVoidPaymentUrl($this->paymentId);
                    $cancelBody = [
                        'amount' => $data['order']['amount'] * 100,
                        'orderItems' => $data['order']['items']
                    ];
                    try {
                        $this->getCurlResponse($cancelUrl, 'POST', json_encode($cancelBody));
                    } catch (Exception $e) {
                        return $e->getMessage();
                    }
                }
            }
            try {
                // Get payment status from nets payments api
                $api_return = $this->getCurlResponse($this->getApiUrl() . $this->paymentId, 'GET');
                $response = json_decode($api_return, true);
            } catch (Exception $e) {
                return $e->getMessage();
            }
            $dbPayStatus = '';
            $paymentStatus = '';
            $cancelled = $response['payment']['summary']['cancelledAmount'];
            $reserved = $response['payment']['summary']['reservedAmount'];
            $charged = $response['payment']['summary']['chargedAmount'];
            $refunded = $response['payment']['summary']['refundedAmount'];
            $pending = $response['payment']['refunds'][0]['state'] == "Pending";
            $partialc = $reserved - $charged;
            $partialr = $reserved - $refunded;
            $chargeid = $response['payment']['charges'][0]['chargeId'];
            $chargedate = $response['payment']['charges'][0]['created'];

            if ($reserved) {
                if ($cancelled) {
                    $langStatus = "cancel";
                    $paymentStatus = "Canceled";
                    $dbPayStatus = 1; // For payment status as cancelled in gxnets db table
                } else if ($charged && $pending != 'Pending') {
                    if ($reserved != $charged) {
                        $paymentStatus = "Partial Charged";
                        $langStatus = "partial_charge";
                        $dbPayStatus = 3; // For payment status as Partial Charged in gxnets db table                         
                    } else if ($refunded) {
                        if ($reserved != $refunded) {
                            $paymentStatus = "Partial Refunded";
                            $langStatus = "partial_refund";
                            $dbPayStatus = 5; // For payment status as Partial Charged in gxnets db table                             
                        } else {
                            $paymentStatus = "Refunded";
                            $langStatus = "refunded";
                            $dbPayStatus = 6; // For payment status as Refunded in gxnets db table
                        }
                    } else {
                        $paymentStatus = "Charged";
                        $langStatus = "charged";
                        $dbPayStatus = 4; // For payment status as Charged in gxnets db table
                    }
                } else if ($pending) {
                    $paymentStatus = "Refund Pending";
                    $langStatus = "refund_pending";
                } else {
                    $paymentStatus = 'Reserved';
                    $langStatus = "reserved";
                    $dbPayStatus = 2; // For payment status as Authorized in gxnets db table
                }
            } else {
                $paymentStatus = "Failed";
                $langStatus = "failed";
                $dbPayStatus = 0; // For payment status as Failed in gxnets db table
            }
            //Change order status for failed payment	
            /* if (isset($paymentStatus) && $paymentStatus == 'Failed' && $errorCode !=401) {
              $qresult = xtc_db_query(
              "SELECT orders_status_id FROM `orders_status`  WHERE orders_status_name = 'Payment aborted' limit 0,1"
              );
              if (!empty($qresult->num_rows)) {
              while ($qrows = xtc_db_fetch_array($qresult)) {
              $orders_status_id = $qrows['orders_status_id'];
              }
              }

              if (isset($orders_status_id)) {
              $oresult = xtc_db_query(
              "SELECT orders_id FROM `orders`  WHERE orders_id = '" . $oder_id . "' AND orders_status = $orders_status_id  limit 0,1"
              );
              if (empty($oresult->num_rows)) {
              $qresult = xtc_db_query(
              "UPDATE " . TABLE_ORDERS . " SET orders_status = $orders_status_id where orders_id = '" . $oder_id . "' "
              );
              //clear cache to reflect status in page
              $coo_cache_control = MainFactory::create_object('CacheControl');
              $coo_cache_control->clear_content_view_cache();
              $coo_cache_control->clear_templates_c();
              $coo_cache_control->clear_template_cache();
              $coo_cache_control->clear_css_cache();
              $coo_cache_control->clear_shop_offline_page_cache();
              $coo_cache_control->clear_data_cache();
              xtc_redirect(xtc_href_link("orders.php?oID=$oder_id&action=edit&overview[do]=OrdersOverview", '', 'SSL'));
              }
              }
              } */
            return array(
                'payStatus' => $paymentStatus,
                'langStatus' => $langStatus
            );
        }
    }

    public function getCurlResponse($url, $method = "POST", $bodyParams = NULL) {
        $result = '';
        // initiating curl request to call api's
        $oCurl = curl_init();
        curl_setopt($oCurl, CURLOPT_URL, $url);
        curl_setopt($oCurl, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($oCurl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($oCurl, CURLOPT_HTTPHEADER, $this->getHeaders());
        if ($method == "POST" || $method == "PUT") {
            curl_setopt($oCurl, CURLOPT_POSTFIELDS, $bodyParams);
        }
        $result = curl_exec($oCurl);
        $info = curl_getinfo($oCurl);
        switch ($info['http_code']) {
            case 401:
                $error_message = 'NETS Easy authorization failed. Check your secret/checkout keys';
                $this->logger->write_text_log($error_message, 'nets');
                break;
            case 400:
                $error_message = 'NETS Easy Bad request: Please check request params/headers ';
                $this->logger->write_text_log($error_message, 'nets');
                break;
            case 402:
                $error_message = 'Payment Required';
                $this->logger->write_text_log($error_message, 'nets');
                break;
            case 500:
                $error_message = 'Unexpected error';
                $this->logger->write_text_log($error_message, 'nets');
                break;
        }
        if (!empty($error_message)) {
            $this->logger->write_text_log($error_message, 'nets');
        }
        curl_close($oCurl);

        return $result;
    }

    /*
     * Function to fetch payment api url
     *
     * @return payment api url
     */

    public function getApiUrl() {
        if ($this->nets->config['NETS_CHECKOUT_MODE'] == 'test') {
            return self::ENDPOINT_TEST;
        } else {
            return self::ENDPOINT_LIVE;
        }
    }

    public function getResponse($oder_id) {
        $api_return = $this->getCurlResponse($this->getApiUrl() . $this->getPaymentId($oder_id), 'GET');
        $response = json_decode($api_return, true);
        $result = json_encode($response, JSON_PRETTY_PRINT);
        return $result;
    }

    /*
     * Function to fetch headers to be passed in guzzle http request
     * @return headers array
     */

    private function getHeaders() {
        return [
            "Content-Type: " . self::RESPONSE_TYPE,
            "Accept: " . self::RESPONSE_TYPE,
            "Authorization: " . $this->getSecretKey()
        ];
    }

    /*
     * Function to fetch secret key to pass as authorization
     * @return secret key
     */

    public function getSecretKey() {
        if ($this->nets->config['NETS_CHECKOUT_MODE'] == 'test') {
            $secretKey = $this->nets->config()['NETS_TEST_SECRET_KEY'];
        } else {
            $secretKey = $this->nets->config()['NETS_LIVE_SECRET_KEY'];
        }
        return $secretKey;
    }

}
