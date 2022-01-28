<?php
require_once DIR_FS_CATALOG . 'includes/modules/payment/nets.php';

class Nets_OrderExtender extends Nets_OrderExtender_parent
{
	const ENDPOINT_TEST = 'https://test.api.dibspayment.eu/v1/payments/';

    const ENDPOINT_LIVE = 'https://api.dibspayment.eu/v1/payments/';

    const ENDPOINT_TEST_CHARGES = 'https://test.api.dibspayment.eu/v1/charges/';

    const ENDPOINT_LIVE_CHARGES = 'https://api.dibspayment.eu/v1/charges/';

    const RESPONSE_TYPE = "application/json";

    private $client;

    protected $_nets_log;

    //s
	protected $nets;
	protected $paymentId;
	protected $orderId;
	public $logger; 
	public function __construct()
    {
		
		parent::__construct();		
        $this->nets =  MainFactory::create_object('nets_ORIGIN');
		$this->orderId = $_GET['oID'];		
		$this->logger          = LogControl::get_instance(); 
		// IF NOT EXISTS Mehabub!!
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


    public function proceed()
    {
		$orderId = (int)$this->v_data_array['GET']['oID'];
		$PaymentId = $this->getPaymentId($orderId);
		if(!empty($PaymentId)){
			$_SESSION['NetsPaymentID'] = $PaymentId;
		}	
		
		//$response = $this->updatePaymentStatus();
        
        $order = new order($orderId);
		$baseUrl = ENABLE_SSL_CATALOG ? HTTP_CATALOG_SERVER : HTTPS_CATALOG_SERVER;		 

		$nets_order_view = MainFactory::create_object('ContentView');
		$nets_order_view->set_template_dir(DIR_FS_CATALOG .'GXModules/Nets/Nets/Admin/Html/');
		$nets_order_view->set_flat_assigns(true);
		$nets_order_view->set_content_template('nets_order_view.html');		 
		$nets_order_view->set_content_data('baseUrl', $baseUrl);		 	
		 
		$responseItems = $this->checkPartialItems($this->orderId);
		$_SESSION['responseItems'] = 	$responseItems ;
		$nets_order_view->set_content_data('responseItems', $_SESSION['responseItems']);
		
		$status = $this->is_easy($this->orderId);		 
		$nets_order_view->set_content_data('status', $status);
		
		$api_return = $this->getCurlResponse($this->getApiUrl() . $this->paymentId, 'GET');
        $response = json_decode($api_return, true);
		$response['payment']['checkout'] = "";
		$nets_order_view->set_content_data('apiGetRequest', $response);
		//echo "<pre>Request: "; print_r($responseItems);echo "<br>";die;
		//$statusResponse = $this->is_easy($this->orderId); 	 
		
		$order_html = $nets_order_view->get_html();	
		
        if (!empty($this->paymentId)){            
			$this->addContentToCollection('below_product_data', $order_html , '<span id="nets-easy">NETS Easy</span><span id="nets-logo"></span>' );
        }
		
        parent::proceed();
    }
	
	//update payment status on order view page based on API get request
    public function updatePaymentStatus() {
		
        if ($this->nets->config['NETS_CHECKOUT_MODE'] == 'test') {
            $secretKey = $this->nets->config()['NETS_TEST_SECRET_KEY'];
            $apiUrl = "https://test.api.dibspayment.eu/v1/payments/";
        } else {
            $secretKey = $this->nets->config()['NETS_LIVE_SECRET_KEY'];
            $apiUrl = 'https://api.dibspayment.eu/v1/payments/';
        }
         
        $secretKeyArr = explode("-", $secretKey);
        if (isset($secretKeyArr['3'])) {
            $secretKey = $secretKeyArr['3'];
        }

        if (isset($_GET['oID'])) {
            $order_id = $_GET['oID'];
            
            if (!empty($this->paymentId)) {
				//get generated ids of easy payment statuses
                $netsOrderStatuses = array();
				$result = xtc_db_query(
                    "SELECT orders_status_id, orders_status_name FROM `" . TABLE_ORDERS_STATUS . "`  WHERE language_id = 1 group by orders_status_name"
				);
                
                if (!empty($result->num_rows)) {					 
					while ($rows = xtc_db_fetch_array($result)) {                    
                        $netsOrderStatuses[$rows['orders_status_name']] = $rows['orders_status_id'];
                    }					
                }
				
                //get payment responce
				$ch = curl_init($apiUrl . $this->paymentId);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                    "Content-Type: application/json",
                    "Accept: application/json",
                    "Authorization: " . $secretKey) // secret-key
                );
                $response = json_decode(curl_exec($ch));
				 
				 
				
                $chargeid = $pending = $refunded = $charged = $reserved = $cancelled =  $dbPayStatus = '';
                if (isset($response->payment->summary->cancelledAmount)) {
                    $cancelled = $response->payment->summary->cancelledAmount;
                }
                if (isset($response->payment->summary->reservedAmount)) {
                    $reserved = $response->payment->summary->reservedAmount;
                }
                if (isset($response->payment->summary->chargedAmount)) {
                    $charged = $response->payment->summary->chargedAmount;
                }
                if (isset($response->payment->summary->refundedAmount)) {
                    $refunded = $response->payment->summary->refundedAmount;
                }
                if (isset($response->payment->refunds[0]->state)) {
                    $pending = $response->payment->refunds[0]->state == "Pending";
                }
                if (isset($response->payment->charges[0]->chargeId)) {
                    $chargeid = $response->payment->charges[0]->chargeId;
                }

				
                if ($reserved) {
                    if ($cancelled) {
                        $paymentStatus = $netsOrderStatuses["Canceled"]; //7 cancelled
                    } else if ($charged) {
                        if ($reserved != $charged) {
                            $paymentStatus = $netsOrderStatuses["Partial Charged"]; // Partial Charged
                        } else if ($refunded) {
                            if ($reserved != $refunded) {
                                $paymentStatus = $netsOrderStatuses["Partial Refunded"]; // Partial Refunded
                            } else {
                                $paymentStatus = $netsOrderStatuses["Refunded"]; //11 Refunded
                            }
                        } else if ($pending) {
                            $paymentStatus = $netsOrderStatuses["Refund Pending"]; //Refund Pending
                        } else {
                            $paymentStatus = $netsOrderStatuses["Charged"]; // Charged
                        }
                    } else if ($pending) {
                        $paymentStatus = $netsOrderStatuses["Refund Pending"]; //Refund Pending
                    } else { 
                        $paymentStatus = $netsOrderStatuses["Reserved"]; // Reserved
                    }
                } else { 
                    $paymentStatus = $netsOrderStatuses["Failed"]; // 10 Failed
                }
				if(empty($paymentStatus)){
					$paymentStatus = $netsOrderStatuses["Processing"];
				} 
                //update payment status of charged payment
				
				$qresult = xtc_db_query(
                    "SELECT orders_id FROM `" . TABLE_ORDERS . "`  WHERE orders_status = $paymentStatus AND orders_id = '" . $_GET['oID'] . "'"
				);
					
                if (isset($paymentStatus) && empty($qresult->num_rows) && !empty($response)) {					
					$qresult = xtc_db_query(
						"UPDATE " . TABLE_ORDERS . " SET orders_status = $paymentStatus where orders_id = '" . $_GET['oID'] . "' "
					);                    
                }
				return $response;
            }
			
        }
    }


	/*
     * Function to fetch payment id from databse table oxnets
     * @param $oxorder_id
     * @return nets payment id
     */
    public function getPaymentId($order_id)
    {
        $order_query = xtc_db_query(
				"SELECT orders_ident_key FROM " . TABLE_ORDERS . "  WHERE orders_id = '" . (int) $order_id . "'"
		);		
		if ($order_query->num_rows) {			
			while ($orows = xtc_db_fetch_array($order_query)) {
				 $this->paymentId  = $orows['orders_ident_key'];
			}
		}
		return $this->paymentId;
    }


	/*
     * Function to get list of partial charge/refund and reserved items list
     * @param oxorder id
     * @return array of reserved, partial charged,partial refunded items
     */
    public function checkPartialItems($oxid)
    {
		$orderItems = NetsEasyOrderController::getOrderItems($oxid);
		   
        //$prodItems = $this->getOrderItems($oxid);
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
					'quantity' => (int)$items['quantity'],
					'taxRate' => $items['taxRate'],
					'netprice' => $items['unitPrice']/100
				);
			}
			if (isset($orderItems['order']['amount'])) {
//				$lists['dataString'] = ($orderItems);
				$lists['orderTotalAmount'] = $orderItems['order']['amount'];
            }
		}
		 		
		
        $api_return = $this->getCurlResponse($this->getApiUrl() . $this->paymentId, 'GET');
        $response = json_decode($api_return, true);
		  
        if (! empty($response['payment']['charges'])) {
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
							'taxRate' => $values['orderItems'][$i]['taxRate']/100,
                            'grossprice' => $priceGross,							
							//'grossprice' => number_format((float) (($grossprice / 100) / $qty), 2, '.', ''),
							//'netprice' => number_format((float) ($netprice / 100), 2, '.', ''),
							'currency' => $GLOBALS['order']->info['currency']
                        );
                    } else {
						//if(count($values['orderItems'])===1){
							//$priceOne = $values['orderItems'][$i]['grossTotalAmount'];
						//}else{
							$priceOne = $values['orderItems'][$i]['grossTotalAmount'] / $values['orderItems'][$i]['quantity'];
						//}
                        
                        $chargedItems[$values['orderItems'][$i]['reference']] = array(
							'reference' => $values['orderItems'][$i]['reference'],
                            'name' => $values['orderItems'][$i]['name'],
                            'quantity' => $values['orderItems'][$i]['quantity'],
							'taxRate' => $values['orderItems'][$i]['taxRate']/100,
                            'grossprice' => number_format((float) ($priceOne / 100), 2, '.', ''),							
							//'grossprice' => number_format((float) (($values['orderItems'][$i]['grossTotalAmount'] / 100) / $values['orderItems'][$i]['quantity']), 2, '.', ''),
							//'netprice' => number_format((float) ($values['orderItems'][$i]['unitPrice'] / 100), 2, '.', ''),
							'currency' => $GLOBALS['order']->info['currency']
                        );
                    }
                }
            }
        }
   
   
        if (! empty($response['payment']['refunds'])) {
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
							//'netprice' => number_format((float) ($netprice / 100), 2, '.', ''),
							'currency' => $GLOBALS['order']->info['currency']
						);
                    } else {
                        $refundedItems[$values['orderItems'][$i]['reference']] = array(
							'reference' => $values['orderItems'][$i]['reference'],
							'name' => $values['orderItems'][$i]['name'],
							'quantity' => $values['orderItems'][$i]['quantity'],							
							'grossprice' => number_format((float) (($values['orderItems'][$i]['grossTotalAmount'] / 100) / $values['orderItems'][$i]['quantity']), 2, '.', ''),
							//'netprice' => number_format((float) ($values['orderItems'][$i]['unitPrice'] / 100), 2, '.', ''),
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
					'quantity' => (int)$items['quantity'],
					'netprice' => $items['unitPrice']/100
				);
			}
        }

        if (!isset($response['payment']['summary']['reservedAmount'])) {
			foreach ($orderItems['order']['items'] as $items) {
				$failedItems[$items['reference']] = array(
					'name' => $items['name'],
					'quantity' => (int)$items['quantity'],
					'netprice' => $items['unitPrice']/100
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
					//echo $chargedItems[$key]['quantity']."_".$refundedItems[$key]['quantity'];die;
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
                    'taxRate' => $prod['taxRate']/100,
                    'quantity' => $qty,
                    'netprice' => number_format((float) ($prod['netprice']), 2, '.', ''),
                    'grossprice' => number_format((float) ($prod['netprice']*("1.".$prod['taxRate'])), 2, '.', ''),
                    'currency' => $GLOBALS['order']->info['currency']
                );
            }

			

			if ($chargedItems[$key]['quantity'] > $prod['quantity']) {
				$chargedItems[$key]['quantity'] = $prod['quantity'];
			}
        }
		//echo "<pre>Post item: "; print_r($chargedItems);echo "<br>";die;
		
	

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

		//echo "<pre>s"; print_r($lists);die;
        // pass reserved, charged, refunded items list to frontend
        return $lists;
    }


	/**
     * Function to check the nets payment status and display in admin order list backend page
     *
     * @return Payment Status
     */
    public function is_easy($oder_id)
    {         
        if (!empty($oder_id)) {     
             // Get order db status from gxorder if cancelled
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
					//$data = $this->getOrderItems($oder_id, false);
					$data = NetsEasyOrderController::getOrderItems($oder_id);
					// call cancel api here
					$cancelUrl = NetsEasyOrderController::getVoidPaymentUrl($this->paymentId);					 
					$cancelBody = [
							'amount' => $data['order']['amount']*100,
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
                    $dbPayStatus = 1; // For payment status as cancelled in oxnets db table
                } else if ($charged && $pending!='Pending') {
                    if ($reserved != $charged) {
                        $paymentStatus = "Partial Charged";
                        $langStatus = "partial_charge";
                        $dbPayStatus = 3; // For payment status as Partial Charged in oxnets db table
                        //$oDB = oxDb::getDb(true);
                        //$oDB->Execute("UPDATE oxnets SET partial_amount = '{$partialc}' WHERE oxorder_id = '{$oxoder_id}'");
                        //$oDB->Execute("UPDATE oxnets SET charge_id = '{$chargeid}' WHERE oxorder_id = '{$oxoder_id}'");
                        //$oDB->Execute("UPDATE oxorder SET oxpaid = '{$chargedate}' WHERE oxid = '{$oxoder_id}'");
                    } else if ($refunded) {
                        if ($reserved != $refunded) {
                            $paymentStatus = "Partial Refunded";
                            $langStatus = "partial_refund";
                            $dbPayStatus = 5; // For payment status as Partial Charged in oxnets db table
                            //$oDB = oxDb::getDb(true);
                            //$oDB->Execute("UPDATE oxnets SET partial_amount = '{$partialr}' WHERE oxorder_id = '{$oxoder_id}'");
                            //$oDB->Execute("UPDATE oxnets SET charge_id = '{$chargeid}' WHERE oxorder_id = '{$oxoder_id}'");
                            //$oDB->Execute("UPDATE oxorder SET oxpaid = '{$chargedate}' WHERE oxid = '{$oxoder_id}'");
                        } else {
                            $paymentStatus = "Refunded";
                            $langStatus = "refunded";
                            $dbPayStatus = 6; // For payment status as Refunded in oxnets db table
                        }
                    } else {
                        $paymentStatus = "Charged";
                        $langStatus = "charged";
                        $dbPayStatus = 4; // For payment status as Charged in oxnets db table
                    }
                } else if ($pending) {
                    $paymentStatus = "Refund Pending";
                    $langStatus = "refund_pending";
                } else {
                    $paymentStatus = 'Reserved';
                    $langStatus = "reserved";
                    $dbPayStatus = 2; // For payment status as Authorized in oxnets db table
                }
            } else {
                $paymentStatus = "Failed";
                $langStatus = "failed";
                $dbPayStatus = 0; // For payment status as Failed in oxnets db table
            }
            
			//Change order status for failed payment	
			if(isset($paymentStatus) && $paymentStatus == 'Failed'){
				 	 
				$qresult = xtc_db_query(
					"SELECT orders_status_id FROM `orders_status`  WHERE orders_status_name = 'Payment aborted' limit 0,1"
				);					  
				if (!empty($qresult->num_rows)) {
					while ($qrows = xtc_db_fetch_array($qresult)) {
						$orders_status_id = $qrows['orders_status_id'];
					}
				}
				 
				if(isset($orders_status_id)){
					$oresult = xtc_db_query(
						"SELECT orders_id FROM `orders`  WHERE orders_id = '" . $oder_id . "' AND orders_status = $orders_status_id  limit 0,1"
					);					  
					if (empty($oresult->num_rows)) {
						$qresult = xtc_db_query(
							"UPDATE " . TABLE_ORDERS . " SET orders_status = $orders_status_id where orders_id = '" . $oder_id . "' "
						);
						//clear cache to reflect status in page
						$coo_cache_control         = MainFactory::create_object('CacheControl');
						$coo_cache_control->clear_content_view_cache();
						$coo_cache_control->clear_templates_c();
						$coo_cache_control->clear_template_cache();
						$coo_cache_control->clear_css_cache();
						$coo_cache_control->clear_shop_offline_page_cache();
						$coo_cache_control->clear_data_cache();
						xtc_redirect(xtc_href_link("orders.php?oID=$oder_id&action=edit&overview[do]=OrdersOverview", '', 'SSL'));	
					}					
				}
				
			 
			} 
			
            return array(
                'payStatus' => $paymentStatus,
                'langStatus' => $langStatus
            );
        }
    }

	 
	public function getCurlResponse($url, $method = "POST", $bodyParams = NULL)
    {
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
        // print_r(curl_getinfo($oCurl));
        $result = curl_exec($oCurl);
 
        $info = curl_getinfo($oCurl);

        switch ($info['http_code']) {
            case 401:
                $error_message = 'NETS Easy authorization filed. Check your secret/checkout keys';
				$this->logger->write_text_log($error_message,'nets');
                break;
            case 400:
                $error_message = 'NETS Easy Bad request: Please check request params/headers ';
				$this->logger->write_text_log($error_message,'nets');
                break;
			case 402:
                $error_message = 'Payment Required';
				$this->logger->write_text_log($error_message,'nets');
                break;	
            case 500:
                $error_message = 'Unexpected error';
				$this->logger->write_text_log($error_message,'nets');
                break;
        }
        if (! empty($error_message)) {
			$this->logger->write_text_log($error_message,'nets');
            //nets_log::log($this->_nets_log, "netsOrder Curl request error, $error_message");
        }
        curl_close($oCurl);

        return $result;
    }
	
	 /*
     * Function to fetch payment api url
     *
     * @return payment api url
     */
    public function getApiUrl()
    {
		if ($this->nets->config['NETS_CHECKOUT_MODE'] == 'test') {        
            return self::ENDPOINT_TEST;
        } else {
            return self::ENDPOINT_LIVE;
        }
    }
	
	public function getResponse($oxoder_id)
    {
        $api_return = $this->getCurlResponse($this->getApiUrl() . $this->getPaymentId($oxoder_id), 'GET');
        $response = json_decode($api_return, true);
        $result = json_encode($response, JSON_PRETTY_PRINT);
        return $result;
    }

    /*
     * Function to fetch headers to be passed in guzzle http request
     * @return headers array
     */
    private function getHeaders()
    {
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
    public function getSecretKey()
    {
		if ($this->nets->config['NETS_CHECKOUT_MODE'] == 'test') { 
            $secretKey =  $this->nets->config()['NETS_TEST_SECRET_KEY'];             
        } else {
            $secretKey =  $this->nets->config()['NETS_LIVE_SECRET_KEY'];             
        }		 
		
        return $secretKey;
    }
	
	 
	
}