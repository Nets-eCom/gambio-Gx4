<?php
/* --------------------------------------------------------------
	NetsEasyPayment.inc.php 2020-04-21
	Gambio GmbH
	http://www.gambio.de
	Copyright (c) 2014 Gambio GmbH
	Released under the GNU General Public License (Version 2)
	[http://www.gnu.org/licenses/gpl-2.0.html]
	--------------------------------------------------------------
*/

use Gambio\Core\Logging\LoggerBuilder;

class NetsEasyPayment
{
	const CONFIG_PREFIX = 'NETS_';

	protected $_configuration;
	protected $_txt;

	public function __construct()
	{
		$this->_txt = MainFactory::create_object('LanguageTextManager', array('netseasypayment', $_SESSION['languages_id']));
		$this->_configuration = $this->_load_configuration();
	}


	protected function _load_configuration()
	{
		$baseUrl = ENABLE_SSL_CATALOG ? HTTP_CATALOG_SERVER : HTTPS_CATALOG_SERVER;
		$t_cfg = array(
			'live_secret_key' 		=> '', 
			'live_checkout_key' 	=> '', 
			'test_secret_key' 		=> '', 
			'test_checkout_key' 	=> '', 
			'checkout_mode' 		=> 'test', // test|live
			'checkout_flow' 		=> 'redirect', // redirect|embedded
			'terms_url' 			=> $baseUrl.'/shop_content.php?coID=3', 
			'merchant_url' 			=> $baseUrl.'/shop_content.php?coID=4', 
			'auto_capture' 			=> '0', 
			'icon_bar' 				=> 'http://easymoduler.dk/icon/img?set=2&icons=VISA_MC_MTRO_PP_RP', 
			'wb_url' 				=> $baseUrl.'/shop.php?do=NetsEasyWebhook', 
			'wb_auth' 				=> '0', 
			'db_back' 				=> '0', 
			'db_front' 				=> '0'
		);

		foreach($t_cfg as $cfg_key => $cfg_value)
		{
			$t_db_cfg_key = self::CONFIG_PREFIX.strtoupper($cfg_key);
			$t_db_cfg_value = gm_get_conf($t_db_cfg_key);
			if($t_db_cfg_value !== null)
			{
				$t_cfg[$cfg_key] = $t_db_cfg_value;
			}
		}
		return $t_cfg;
	}


	protected function _save_configuration($p_key = null)
	{
		foreach($this->_configuration as $cfg_key => $cfg_value)
		{
			if($p_key !== null && $cfg_key != $p_key)
			{
				continue;
			}
			$t_db_cfg_key = self::CONFIG_PREFIX.strtoupper($cfg_key);
			gm_set_conf($t_db_cfg_key, xtc_db_input($cfg_value));
		}
	}


	public function __get($p_varname)
	{
		$t_value = null;
		if(array_key_exists($p_varname, $this->_configuration))
		{
			$t_value = $this->_configuration[$p_varname];
		}
		return $t_value;
	}


	public function __set($p_varname, $p_value)
	{
		if(array_key_exists($p_varname, $this->_configuration))
		{
			$this->_configuration[$p_varname] = $p_value;
			$this->_save_configuration($p_varname);
		}
	}


	public function __isset($p_varname)
	{
		$t_isset = false;
		if(in_array($p_varname, $this->_configuration))
		{
			$t_isset = true;
		}
		return $t_isset;
	}


	/* ---------------------------------------------------------------------------------------------- */

	public function get_text($placeholder)
	{
		return $this->_txt->get_text($placeholder);
	}


	public function get_status($order_statuses)
	{
        $orderStatusReadService = StaticGXCoreLoader::getService('OrderStatus');
        $order_statuses         = [];

        foreach ($orderStatusReadService->findAll() as $orderStatus) {
            $order_statuses[] = [
                'id'   => (string)$orderStatus->getId(),
                'name' => $orderStatus->getName(MainFactory::create('LanguageCode', new StringType($_SESSION['language_code']))),
            ];
        }
		return $order_statuses;
	}


	public function replaceLanguagePlaceholders($content)
	{
		while(preg_match('/##(\w+)\b/', $content, $matches) === 1)
		{
			$replacement = $this->get_text($matches[1]);
			if(empty($replacement))
			{
				$replacement = $matches[1];
			}
			$content = preg_replace('/##'.$matches[1].'/', $replacement.'$1', $content, 1);
		}
		return $content;
	}


	public function is_enabled($t_is_enabled)
	{
		$t_is_enabled = (defined('MODULE_PAYMENT_NETS_STATUS') && filter_var(constant('MODULE_PAYMENT_NETS_STATUS'), FILTER_VALIDATE_BOOLEAN) === true);
		$t_is_enabled = $t_is_enabled && strpos(MODULE_PAYMENT_STATUS, 'nets.php') !== false;
		return $t_is_enabled;
	}

	public function is_installed($installed)
	{
		$installed = defined('MODULE_PAYMENT_NETS_STATUS');
		return $installed;
	}
}

class NetsEasyPaymentException extends Exception {}
