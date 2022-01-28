<?php
/*	--------------------------------------------------------------
	NetsEasyConfigurationController.inc.php 2017-09-18
	Gambio GmbH
	http://www.gambio.de
	Copyright (c) 2017 Gambio GmbH
	Released under the GNU General Public License (Version 2)
	[http://www.gnu.org/licenses/gpl-2.0.html]
	--------------------------------------------------------------
*/

class NetsEasyConfigurationController extends AdminHttpViewController
{
	/**
	 * @var $text \LanguageTextManager
	 */
	protected $text;


	public function proceed(HttpContextInterface $httpContext)
	{
		$this->text = MainFactory::create('LanguageTextManager', 'netseasypayment', $_SESSION['languages_id']);
		parent::proceed($httpContext);
	}


	public function actionDefault()
	{
		$title     = new NonEmptyStringType($this->text->get_text('configuration_heading'));
		$template  = new ExistingFile(new NonEmptyStringType(DIR_FS_ADMIN . '/html/content/nets_easy_configuration.html'));
		$netsEasy = MainFactory::create_object('NetsEasyPayment');

		$data = MainFactory::create('KeyValueCollection', [
			'form_action' 			=> xtc_href_link('admin.php', 'do=NetsEasyConfiguration/SaveConfiguration'), 
			'live_secret_key' 		=> $netsEasy->live_secret_key, 
			'live_checkout_key'		=> $netsEasy->live_checkout_key, 
			'test_secret_key' 		=> $netsEasy->test_secret_key, 
			'test_checkout_key' 	=> $netsEasy->test_checkout_key, 
			'checkout_mode' 		=> $netsEasy->checkout_mode, 
			'checkout_flow' 		=> $netsEasy->checkout_flow, 
			'terms_url' 			=> $netsEasy->terms_url, 
			'merchant_url' 			=> $netsEasy->merchant_url, 
			'auto_capture' 			=> $netsEasy->auto_capture, 
			'icon_bar' 				=> $netsEasy->icon_bar, 
			'wb_url' 				=> $netsEasy->wb_url, 
			'wb_auth' 				=> $netsEasy->wb_auth, 
			'db_back' 				=> $netsEasy->db_back, 
			'db_front' 				=> $netsEasy->db_front
		]);
        $assets = MainFactory::create('AssetCollection', [MainFactory::create('Asset', 'netseasypayment.lang.inc.php'),]);
        return MainFactory::create('AdminLayoutHttpControllerResponse', $title, $template, $data, $assets);
	}


	public function actionSaveConfiguration()
	{
		$netsEasy = MainFactory::create_object('NetsEasyPayment');

		$liveSecretKey 		= $this->_getPostData('live_secret_key');
		$liveCheckoutKey 	= $this->_getPostData('live_checkout_key');
		$testSecretKey 		= $this->_getPostData('test_secret_key');
		$testCheckoutKey 	= $this->_getPostData('test_checkout_key');

		$checkoutMode 		= $this->_getPostData('checkout_mode');
		$checkoutFlow 		= $this->_getPostData('checkout_flow');
		$termsUrl 			= $this->_getPostData('terms_url');
		$merchantUrl 		= $this->_getPostData('merchant_url');
		$autoCapture 		= (bool)$this->_getPostData('auto_capture') ? '1' : '0';

		$iconBar 			= $this->_getPostData('icon_bar');
		$wbUrl  			= $this->_getPostData('wb_url');
		$wbAuth 			= $this->_getPostData('wb_auth');
		$dbBack 			= (bool)$this->_getPostData('db_back') ? '1' : '0';
		$dbFront 			= (bool)$this->_getPostData('db_front') ? '1' : '0';

		$liveSecretKey 		= strip_tags($liveSecretKey);
		$liveCheckoutKey 	= strip_tags($liveCheckoutKey);
		$testSecretKey 		= strip_tags($testSecretKey);
		$testCheckoutKey 	= strip_tags($testCheckoutKey);
		$termsUrl 			= strip_tags($termsUrl);
		$merchantUrl 		= strip_tags($merchantUrl);
		$sortOrder 			= strip_tags($sortOrder);
		$iconBar 			= strip_tags($iconBar);
		$wbAuth 			= strip_tags($wbAuth);


        $netsEasy->live_secret_key 		= $liveSecretKey;
        $netsEasy->live_checkout_key 	= $liveCheckoutKey;
        $netsEasy->test_secret_key 		= $testSecretKey;
        $netsEasy->test_checkout_key 	= $testCheckoutKey;
        $netsEasy->checkout_mode 		= $checkoutMode;
        $netsEasy->checkout_flow 		= $checkoutFlow;
        $netsEasy->terms_url 			= $termsUrl;
        $netsEasy->merchant_url 		= $merchantUrl;
        $netsEasy->auto_capture 		= $autoCapture;
        $netsEasy->icon_bar 			= $iconBar;
        $netsEasy->wb_url 				= $wbUrl;
        $netsEasy->wb_auth 				= $wbAuth;
        $netsEasy->db_back 				= $dbBack;
        $netsEasy->db_front 			= $dbFront;		

		$GLOBALS['messageStack']->add_session($this->text->get_text('configuration_saved'), 'success');

		return MainFactory::create('RedirectHttpControllerResponse', xtc_href_link('admin.php', 'do=NetsEasyConfiguration'));
	}
}