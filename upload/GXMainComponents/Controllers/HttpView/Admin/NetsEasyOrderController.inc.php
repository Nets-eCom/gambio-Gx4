<?php
/* --------------------------------------------------------------
   NetsEasyOrderController.inc.php 2017-09-18
   Gambio GmbH
   http://www.gambio.de
   Copyright (c) 2017 Gambio GmbH
   Released under the GNU General Public License (Version 2)
   [http://www.gnu.org/licenses/gpl-2.0.html]
   --------------------------------------------------------------
*/
require_once DIR_FS_CATALOG . 'includes/modules/payment/nets.php';
class NetsEasyOrderController extends AdminHttpViewController
{
	const ENDPOINT_TEST = 'https://test.api.dibspayment.eu/v1/payments/';

    const ENDPOINT_LIVE = 'https://api.dibspayment.eu/v1/payments/';

    const ENDPOINT_TEST_CHARGES = 'https://test.api.dibspayment.eu/v1/charges/';

    const ENDPOINT_LIVE_CHARGES = 'https://api.dibspayment.eu/v1/charges/';

    const RESPONSE_TYPE = "application/json";
    /**
     * @var $text \LanguageTextManager
     */
    protected $text;
    protected $nets;
    protected $payment_id;
	public $logger; 
    public function proceed(HttpContextInterface $httpContext)
    {
		$this->nets =  MainFactory::create_object('nets_ORIGIN');
		$this->payment_id = $_SESSION['NetsPaymentID'];
		
		$this->logger          = LogControl::get_instance();
        $this->text = MainFactory::create('LanguageTextManager', 'netseasypayment', $_SESSION['languages_id']);
        parent::proceed($httpContext);
    }
        
    public function actionDefault()
    {
            return true; 
    }
	
	public function actionCharge()
    {
		//echo "<pre>Payment Id "; print_r($this->payment_id); echo "<br>";
		//echo "<pre>Post Request: "; print_r($_REQUEST);echo "<br>";		
 		 		
		$oxorder = $_REQUEST['oxorderid'];        
        $ref =  $_REQUEST['reference'];
		$name =  $_REQUEST['name']; 		
        $chargeQty = $_REQUEST['charge'];
		$unitPrice = $_REQUEST['price'];
		$taxRate = (int)$_REQUEST['taxrate'];
		
		$payment_id = $this->getPaymentId($oxorder); 		 
		$data = $this->getOrderItems($oxorder);
        // call charge api here
        $chargeUrl = $this->getChargePaymentUrl($payment_id);
		
        if (isset($ref) && isset($chargeQty)) {
            $totalAmount = 0;
            foreach ($data['order']['items'] as $key => $value) {
                if (in_array($ref, $value) && $ref === $value['reference'] ) {					                     
                    $unitPrice = $value['unitPrice'];
					$taxAmountPerProduct = $value['taxAmount'] / $value['quantity'];
					
					$value['taxAmount'] = $taxAmountPerProduct * $chargeQty;
                    $netAmount = $chargeQty * $unitPrice;
                    $grossAmount = $netAmount + $value['taxAmount'];					
					
					$value['quantity'] = $chargeQty;
                    $value['netTotalAmount'] = $netAmount;
                    $value['grossTotalAmount'] = $grossAmount;                    
                     
                    $itemList[] = $value;
                    $totalAmount += $grossAmount;
					 
                }
            }
            $body = [
                'amount' => $totalAmount,
                'orderItems' => $itemList
            ];
        } else {
            $body = [
                'amount' => $data['order']['amount']*100,
                'orderItems' => $data['order']['items']
            ];
        }		 
         
        //echo "<pre>body: ".$chargeUrl; print_r($body); die;		 
		$api_return = $this->getCurlResponse($chargeUrl, 'POST', json_encode($body)); 
        $response = json_decode($api_return, true);
		$this->logger->write_text_log( "Nets_Order_Overview getorder charge" . $api_return,'nets');
		 
		//save charge details in db for partial refund
		if(isset($ref) && isset($response['chargeId'])){
			$charge_query = "insert into `gxnets` (`payment_id`, `charge_id`,  `product_ref`, `charge_qty`, `charge_left_qty`,`created`) "
                    . "values ('" . $this->payment_id . "', '". $response['chargeId'] . "', '" . $ref . "', '" . $chargeQty . "', '" . $chargeQty . "',now())";
			xtc_db_query($charge_query);
		}else{
			if(isset($response['chargeId'])){
				 foreach ($data['order']['items'] as $key => $value) {				 
					$charge_query = "insert into `gxnets` (`payment_id`, `charge_id`,  `product_ref`, `charge_qty`, `charge_left_qty`,`created`) "
						. "values ('" . $this->payment_id . "', '". $response['chargeId'] . "', '" . $value['reference'] . "', '" . $value['quantity'] . "', '" . $value['quantity'] . "',now())";
					xtc_db_query($charge_query);
				 }
			}
		}
		 
		 
		xtc_redirect(xtc_href_link("orders.php?oID=$oxorder&action=edit&overview[do]=OrdersOverview", '', 'SSL'));		
    }
	/*
     * Function to capture nets transaction - calls Refund API
     * redirects to admin overview listing page
     */
    public function actionRefundbk()
    {
		//echo "<pre>Payment Id "; print_r($this->payment_id); echo "<br>";
		//echo "<pre>Post Request: "; print_r($_REQUEST);echo "<br>";	 
		 
		$oxorder = $_REQUEST['oxorderid'];        
        $ref =  $_REQUEST['reference'];
		$name =  $_REQUEST['name']; 		
        $refundQty  = $_REQUEST['refund'];
		$unitPrice = $_REQUEST['price'];
		$taxRate = (int)$_REQUEST['taxrate'];
		
		 		 
		$data = $this->getOrderItems($oxorder);
		$charge_id = $this->getChargeId($oxorder);
        // call charge api here         
		$refundUrl = $this->getRefundPaymentUrl($charge_id);
        if (isset($ref) && isset($refundQty)) {
            $totalAmount = 0;
            foreach ($data['order']['items'] as $key => $value) {
                if (in_array($ref, $value) && $ref === $value['reference'] ) {					                     
                    $unitPrice = $value['unitPrice'];
					$taxAmountPerProduct = $value['taxAmount'] / $value['quantity'];
					
					$value['taxAmount'] = $taxAmountPerProduct * $refundQty;
                    $netAmount = $refundQty * $unitPrice;
                    $grossAmount = $netAmount + $value['taxAmount'];					
					
					$value['quantity'] = $refundQty;
                    $value['netTotalAmount'] = $netAmount;
                    $value['grossTotalAmount'] = $grossAmount;                    
                     
                    $itemList[] = $value;
                    $totalAmount += $grossAmount;
					 
                }
            }
            $body = [
                'amount' => $totalAmount,
                'orderItems' => $itemList
            ];
        } else {
            $body = [
                'amount' => $data['order']['amount']*100,
                'orderItems' => $data['order']['items']
            ];
        }	
 		 		 
		$api_return = $this->getCurlResponse($refundUrl, 'POST', json_encode($body));		
        $response = json_decode($api_return, true);
		 
		xtc_redirect(xtc_href_link("orders.php?oID=$oxorder&action=edit&overview[do]=OrdersOverview", '', 'SSL'));
		
         
    }
	 /*
     * Function to capture nets transaction - calls Refund API
     * redirects to admin overview listing page
     */
    public function actionRefund()
    { 
		//echo "<pre>Post Request: "; print_r($_REQUEST);echo "<br>"; 
		$oxorder = $_REQUEST['oxorderid'];        
        $ref =  $_REQUEST['reference'];
		$name =  $_REQUEST['name']; 		
        $refundQty  = $_REQUEST['refund'];
		$unitPrice = $_REQUEST['price'];
		$taxRate = (int)$_REQUEST['taxrate'];
		
        $data = $this->getOrderItems($oxorder);        
		
		$api_return = $this->getCurlResponse($this->getApiUrl() . $this->getPaymentId($oxorder), 'GET');
        $chargeResponse = json_decode($api_return, true);		
		$refundEachQtyArr = array();
		$breakloop = false; 
		//echo "<pre>chargeResponse: "; print_r($chargeResponse);
		//For partial refund if condition
		if (isset($ref) && isset($refundQty)){
			 
			foreach ($chargeResponse['payment']['charges'] as $ky => $val) {
						  
				if (in_array($ref, $val['orderItems'][0]) && $ref == $val['orderItems'][0]['reference'] ){ 
						//from charge tabe deside charge id for refund						
						$charge_query = xtc_db_query(
							"SELECT `payment_id`, `charge_id`,  `product_ref`, `charge_qty`, `charge_left_qty` FROM gxnets WHERE payment_id = '" . $this->payment_id . "' AND charge_id = '" . $val['chargeId'] . "' AND product_ref = '" . $ref . "' AND charge_left_qty !=0"
						);
						if ($charge_query->num_rows) {		
							while ($crows = xtc_db_fetch_array($charge_query)) {
								$table_charge_left_qty = $refundEachQtyArr[$val['chargeId']] = $crows['charge_left_qty'];
							}
						}
						
						if($refundQty <= array_sum($refundEachQtyArr)){
								$leftqtyFromArr = array_sum($refundEachQtyArr)-$refundQty;
								$leftqty = $table_charge_left_qty - $leftqtyFromArr;
								$refundEachQtyArr[$val['chargeId']] = $leftqty;
								$breakloop = true;
						}
						if($breakloop){
							//echo "<pre>refundEachQtyArr: "; print_r($refundEachQtyArr);echo "<br>"; 
							foreach ($refundEachQtyArr as $key => $value) {
								$body = $this->getItemForRefund($ref, $value, $data);
									
								$refundUrl = $this->getRefundPaymentUrl($key);
								$this->getCurlResponse($refundUrl, 'POST', json_encode($body));								 
								$this->logger->write_text_log( "Nets_Order_Overview getorder refund" . json_encode($body),'nets');
								
								//update for left charge quantity
								$singlecharge_query = xtc_db_query(
									"SELECT  `charge_left_qty` FROM gxnets WHERE payment_id = '" . $this->payment_id . "' AND charge_id = '" . $key . "' AND product_ref = '" . $ref . "' AND charge_left_qty !=0 "
								);	
								if ($singlecharge_query->num_rows) {	
									while ($scrows = xtc_db_fetch_array($singlecharge_query)) {
										$charge_left_qty = $scrows['charge_left_qty'];
									}
								}								
								$charge_left_qty = $value - $charge_left_qty;
								if($charge_left_qty < 0 ){
									$charge_left_qty = -$charge_left_qty;
								}
								
								$qresult = xtc_db_query(
									"UPDATE gxnets SET charge_left_qty = $charge_left_qty WHERE payment_id = '" . $this->payment_id . "' AND charge_id = '" . $key . "' AND product_ref = '" . $ref . "'"
								);
							}
							
							break;
							 
						}		
							 
						 
				}
			} 
			
		}else{  
			
			//update for left charge quantity
			foreach ($chargeResponse['payment']['charges'] as $ky => $val) {
				$itemsArray = array();
				foreach ($val['orderItems'] as $key => $value) {	
					$itemsArray[] = array(
					'reference' => $value['reference'],
					'name' => $value['name'],
					'quantity' => $value['quantity'],
					'unit' => 'pcs',
					'unitPrice' => $value['unitPrice'],
					'taxRate' => $value['taxRate'],
					'taxAmount' => $value['taxAmount'],
					'grossTotalAmount' => $value['grossTotalAmount'],
					'netTotalAmount' => $value['netTotalAmount'],
					);
				
					$qresult = xtc_db_query(
						"UPDATE gxnets SET charge_left_qty = 0 WHERE payment_id = '" . $this->payment_id . "' AND charge_id = '" . $val['chargeId'] . "' AND product_ref = '" . $value['reference'] . "'"
					);
				}
				
				$itemsGrossPriceSumma = 0;
				foreach ($itemsArray as $total) {
					$itemsGrossPriceSumma += $total['grossTotalAmount'];
				}	 	
				$body = [
					'amount' => $itemsGrossPriceSumma,
					'orderItems' => $itemsArray
				];			
				//For Refund all
				//$charge_id = $this->getChargeId($oxorder);                 
				$refundUrl = $this->getRefundPaymentUrl($val['chargeId']);
				$api_return = $this->getCurlResponse($refundUrl, 'POST', json_encode($body));		
				$response = json_decode($api_return, true);
				$this->logger->write_text_log( "Nets_Order_Overview getorder refund" . $api_return,'nets');
				//echo "<pre>data: "; print_r($body);echo "<br>";
			}		
			
				
        }	
		//die;
		  
		xtc_redirect(xtc_href_link("orders.php?oID=$oxorder&action=edit&overview[do]=OrdersOverview", '', 'SSL'));
    }

    /* Get order Items to refund and pass them to refund api */
     public function getItemForRefund($ref,$refundQty, $data) {				 
		$totalAmount = 0;
		foreach ($data['order']['items'] as $key => $value) {
			if (in_array($ref, $value) && $ref === $value['reference'] ) {					                     
				$unitPrice = $value['unitPrice'];
				$taxAmountPerProduct = $value['taxAmount'] / $value['quantity'];
				
				$value['taxAmount'] = $taxAmountPerProduct * $refundQty;
				$netAmount = $refundQty * $unitPrice;
				$grossAmount = $netAmount + $value['taxAmount'];					
				
				$value['quantity'] = $refundQty;
				$value['netTotalAmount'] = $netAmount;
				$value['grossTotalAmount'] = $grossAmount;                    
				 
				$itemList[] = $value;
				$totalAmount += $grossAmount;				 
			}
		}
		$body = [
			'amount' => $totalAmount,
			'orderItems' => $itemList
		];          
		return $body;
     }
	/*
     * Function to capture nets transaction - calls Cancel API
     * redirects to admin overview listing page
     */
    public function actionCancel()
    {
		//echo "<pre>Post Request: "; print_r($_REQUEST);echo "<br>";	die;
		$oxorder = $_REQUEST['oxorderid']; 
        $data = $this->getOrderItems($oxorder);
        $payment_id = $this->getPaymentId($oxorder);
		
        // call cancel api here
        $cancelUrl = $this->getVoidPaymentUrl($payment_id);         
		$body = [
                'amount' => $data['order']['amount']*100,
                'orderItems' => $data['order']['items']
        ];
		  
		  
        $api_return = $this->getCurlResponse($cancelUrl, 'POST', json_encode($body));
        $response = json_decode($api_return, true);
		
		xtc_redirect(xtc_href_link("orders.php?oID=$oxorder&action=edit&overview[do]=OrdersOverview", '', 'SSL'));
        
    }
	   

    /*
     * Function to get order items to pass capture, refund, cancel api
     * @param $oxorder oxid order id alphanumeric
     * @param $orderno order no numeric
     * @return array order items and amount
     */
	  

    public function getOrderItems($orderId) {
         
		//get order products
		$taxRateShipping = $order_total  = 0;
		$product_query = xtc_db_query(
				"SELECT products_model,products_name,products_price,final_price,products_tax,products_quantity FROM orders_products  WHERE orders_id = '" . (int) $orderId. "'"
		);		
		if ($product_query->num_rows) {			
			while ($prows = xtc_db_fetch_array($product_query)) {	  
				 				 
				$quantity = (int)$prows['products_quantity'];
				$product = $prows['products_price']; // product price incl. VAT in DB format 
				$tax = $prows['products_tax']; // Tax rate in DB format
				$taxFormat = '1' . str_pad(number_format((float) $tax, 2, '.', ''), 5, '0', STR_PAD_LEFT);
				$unitPrice = round(round(($product * 100) / $taxFormat, 2) * 100);
				$netAmount = round($quantity * $unitPrice);
				$grossAmount = round($quantity * ($product * 100));
				$taxAmount = $grossAmount - $netAmount;
				$taxRateShipping = $prows['products_tax'];
				$taxRate = number_format($prows['products_tax'],2) * 100 ;
				
				$itemsArray[] = array(
					'reference' => $prows['products_model'],
					'name' => $prows['products_name'],
					'quantity' => $quantity,
					'unit' => 'pcs',
					'unitPrice' => $unitPrice,
					'taxRate' => $taxRate,
					'taxAmount' => $taxAmount,
					'grossTotalAmount' => $grossAmount,
					'netTotalAmount' => $netAmount
				);
			
				  
			}
		}
		         
        //shipping items
		$shippingCost  = '';
		$order_query = xtc_db_query(
				"SELECT title,value,class FROM orders_total  WHERE orders_id = '" . (int) $orderId . "'"
		);		
		if ($order_query->num_rows) {			
			while ($orows = xtc_db_fetch_array($order_query)) {
				
				if($orows['class']=='ot_shipping' && $orows['value'] > 0){
					$shippingCost  = $orows['value'];
				}
				if($orows['class']=='ot_total' && $orows['value'] > 0){
					$order_total  = number_format($orows['value'],2);
				}
				
				//FOR COUPON 
				if($orows['class']=='ot_coupon'){
					if (preg_match('/"([^"]+)"/', $orows['title'], $couponCode)) {
						if(isset($couponCode[1])){
							$coupon_query = xtc_db_query(
									"SELECT coupon_id FROM coupons WHERE coupon_code = '" . $couponCode[1] . "'"
							);		
							if ($coupon_query->num_rows) {			
								while ($crows = xtc_db_fetch_array($coupon_query)) {
									$couponId = $crows['coupon_id'];
								}
							}				
						}	
					}
				} 
				
				
			}				 
		}
		
		
        if (!empty($shippingCost)) {              
			
			//easy calc method  
			$quantity = 1;
            $shipping = (isset($shippingCost)) ? $shippingCost:0; // shipping price incl. VAT in DB format 
            $tax = (isset($taxRateShipping))? $taxRateShipping : 0; // Tax rate in DB format
            $taxFormat = '1' . str_pad(number_format((float) $tax, 2, '.', ''), 5, '0', STR_PAD_LEFT);
            $unitPrice = round(round(($shipping * 100) / $taxFormat, 2) * 100);
            $netAmount = round($quantity * $unitPrice);
            $grossAmount = round($quantity * ($shipping * 100));
            $taxAmount = $grossAmount - $netAmount;		
             
            //$unitPrice = $_SESSION['shipping']['cost'] * 100;           
			
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
        /* if (!empty($couponId)) {            			 
			//get coupon details	
			$db = StaticGXCoreLoader::getDatabaseQueryBuilder();
			$couponRow = $db->get_where('coupons', ['coupon_id' => $couponId])->row_array();					
			//if coupon type fix amount
			$quantity = 1;
			$couponTaxRate = 0 ;		
			$unitPrice = $couponTaxAmount = $couponRow['coupon_amount'] * 100;
			//if coupon type percent
			if($couponRow['coupon_type'] == 'P'){
				// items total sum for discount
				$itemsPriceSumma = 0;
				foreach ($itemsArray as $total) {
					$itemsPriceSumma += $total['grossTotalAmount'];
				}
				$unitPrice = $itemsPriceSumma;
				$couponTaxRate = $couponTaxAmount;
				$taxCalculationString = 1 + ($couponTaxAmount / 100); // 1.25
				$grossTotalAmount = ($itemsPriceSumma / 100) * $taxCalculationString;
				$couponTaxAmount = ($grossTotalAmount - ($itemsPriceSumma / 100))   ;
				$couponTaxAmount = round((float)$couponTaxAmount/100, 2) * 100;
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
		*/	 
         
        // items total sum
        $itemsGrossPriceSumma = 0;
        foreach ($itemsArray as $total) {
            $itemsGrossPriceSumma += $total['grossTotalAmount'];
        }

        // compile datastring
        $data = array(
            'order' => array(
                'items' => $itemsArray,
                'amount' => $order_total,
                'currency' => $order->info['currency']                 
            ) 
        );		
         
        return $data;
    }  

     

    public function debugMode()
    {
        $debug = $this->getConfig()->getConfigParam('nets_blDebug_log');
        return $debug;
    }

    public function getResponse($oxoder_id)
    {
		$payment_id = $this->getPaymentId($oxoder_id);
        $api_return = $this->getCurlResponse($this->getApiUrl() . $payment_id, 'GET');
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

    private function prepareAmount($amount = 0)
    {
        return (int) round($amount * 100);
    }

    

    /*
     * Function to fetch payment method type from databse table oxorder
     * @param $oxorder_id
     * @return payment method
     */
    public function getPaymentMethod($order_id)
    {
      
		
    }

    /*
     * Function to fetch charge id from databse table oxnets
     * @param $oxorder_id
     * @return nets charge id
     */
    private function getChargeId($oxoder_id)
    {		 
		$api_return = $this->getCurlResponse($this->getApiUrl() . $this->getPaymentId($oxoder_id), 'GET');
        $response = json_decode($api_return, true);
        return $response['payment']['charges'][0]['chargeId'];
    }

    /*
     * Function to fetch secret key to pass as authorization
     * @return secret key
     */
    public function getSecretKey()
    {
		if ($this->nets->config['NETS_CHECKOUT_MODE'] == 'test') {
            return  $this->nets->config()['NETS_TEST_SECRET_KEY'];             
        } else {
            return  $this->nets->config()['NETS_LIVE_SECRET_KEY'];             
        }
         
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

    /*
     * Function to fetch charge api url
     * @param $paymentId
     * @return charge api url
     */
    public function getChargePaymentUrl(string $paymentId)
    {
        return ($this->nets->config['NETS_CHECKOUT_MODE'] == 'test') ? self::ENDPOINT_TEST . $paymentId . '/charges' : self::ENDPOINT_LIVE . $paymentId . '/charges';
    }

    /*
     * Function to fetch cancel api url
     * @param $paymentId
     * @return cancel api url
     */
    public function getVoidPaymentUrl(string $paymentId)
    {
        return ($this->nets->config['NETS_CHECKOUT_MODE'] == 'test') ? self::ENDPOINT_TEST . $paymentId . '/cancels' : self::ENDPOINT_LIVE . $paymentId . '/cancels';
    }

    /*
     * Function to fetch refund api url
     * @param $chargeId
     * @return refund api url
     */
    public function getRefundPaymentUrl($chargeId)
    {
        return ($this->nets->config['NETS_CHECKOUT_MODE'] == 'test') ? self::ENDPOINT_TEST_CHARGES . $chargeId . '/refunds' : self::ENDPOINT_LIVE_CHARGES . $chargeId . '/refunds';
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
		$this->logger->write_text_log($info['http_code'],'nets');
		 
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
	

    

}