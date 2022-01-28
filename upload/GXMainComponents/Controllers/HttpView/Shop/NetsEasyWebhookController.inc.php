<?php
MainFactory::load_class('HttpViewController');
class NetsEasyWebhookController extends HttpViewController {

    protected $hookResponse;    
    protected $payment_id;
    public $logger; 
	
	/**
     * Handle webhook call and save data
     */
    public function actionDefault() {
		
		$this->logger = LogControl::get_instance();		
		// IF NOT EXISTS gxnets_payment_status table create it!!
        $result = xtc_db_query("CREATE TABLE IF NOT EXISTS `gxnets_payment_status` (
		`id` int(10) unsigned NOT NULL auto_increment,		
		`order_id` varchar(50) default NULL,
		`payment_id` varchar(50) default NULL,
		`status` varchar(50) default NULL,	
		`updated` int(2) unsigned default '0',
		`created` datetime NOT NULL,
		`timestamp` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
		PRIMARY KEY (`id`)
		)");
		
		$oid = '';			 
		//echo "Hello, Welcome to webhook controller.";die;
		$this->logger->write_text_log('In webhooks', 'nets');
		//$this->logger->write_text_log($_POST, 'nets');
		
		$json = ''; 
        $hookResponse = file_get_contents('php://input');		
	    $this->logger->write_text_log($hookResponse, 'nets');
        $json = json_decode($hookResponse);
				
        if ($json) {
            $eid = $json->id;
            $pid = $json->data->paymentId;
            $mid = $json->merchantId;
            $event = $json->event;
            $response = preg_replace('/\s/', '', (json_encode($json->data)));
            $timestamp = $json->timestamp;
            $now = date('Y-m-d H:i:s');
            // prepping a controlled sorting order, since sorting a webhook by timestamp in general tend to be unreliable.
            if ($event == 'payment.created') {
                $order = 0;
            }
            if ($event == 'payment.checkout.completed') {
                $order = 1;
            }
            if ($event == 'payment.reservation.created') {
                $order = 2;
            }
            if ($event == 'payment.reservation.created.v2') {
                $order = 3;
            }
            if ($event == 'payment.cancel.created') {
                $order = 4;
            }
            if ($event == 'payment.charge.created') {
                $order = 5;
            }
            if ($event == 'payment.charge.created.v2') {
                $order = 6;
            }
            if ($event == 'payment.refund.completed') {
                $order = 7;
            }
            if ($event == 'payment.charge.failed') {
                $order = 8;
            }
			
			//Insert or update order status
			$oid = '';
			$oresult = xtc_db_query(
              "SELECT orders_id FROM `orders` WHERE orders_ident_key =  '".$pid."' limit 0,1"
            );
			if (!empty($oresult->num_rows)) {
              while ($orows = xtc_db_fetch_array($oresult)) {
				$oid = $orows['orders_id'];
              }
            }
			  
			$qresult = xtc_db_query(
              "SELECT id, status FROM `gxnets_payment_status` WHERE  payment_id =  '".$pid."' limit 0,1"
            );
            if (empty($qresult->num_rows)) {				 
				 $query = "insert into `gxnets_payment_status` (`order_id`, `payment_id`,  `status`, `created`) "
                            . "values ('" . $oid . "', '" . $pid . "', '" . $order . "', now())";
                 xtc_db_query($query);
            }else{
				//compare with previous $order and update only if it is greater than previous				
				while ($qrows = xtc_db_fetch_array($qresult)) {
					$status = $qrows['status'];
				}
				if($order > $status){					
					$quresult = xtc_db_query(
						"UPDATE `gxnets_payment_status` SET status = $order, order_id = '" . $oid . "' where  payment_id =  '".$pid."' "
					);				
				}
			}
			
        }
		
    }

}
