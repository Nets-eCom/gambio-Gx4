<?php
/* --------------------------------------------------------------
	netseasypayment.lang.inc.php 2021-12-20
	Gambio GmbH
	http://www.gambio.de
	Copyright (c) 2015 Gambio GmbH
	Released under the GNU General Public License (Version 2)
	[http://www.gnu.org/licenses/gpl-2.0.html]
   --------------------------------------------------------------
*/

$t_language_text_section_content_array = [
    'configuration_heading' 				=> 'Nets Easy Configuration',
    'credentials' 							=> 'API Credentials',
    'live_secret_key'  						=> 'Live Secret Key',
    'live_checkout_key' 					=> 'Live Checkout Key',
    'test_secret_key' 						=> 'Test Secret Key',
    'test_checkout_key' 					=> 'Test Checkout Key',
    'live_secret_placeholder' 				=> 'live-secret-key-00000000000000000000000000000000',
    'live_checkout_placeholder' 			=> 'live-checkout-key-00000000000000000000000000000000',
    'test_secret_placeholder' 				=> 'test-secret-key-00000000000000000000000000000000',
    'test_checkout_placeholder' 			=> 'test-checkout-key-00000000000000000000000000000000',
    'settings' 								=> 'Settings',
    'checkout_mode' 						=> 'Checkout Mode',
    'mode_live' 							=> 'Live',
    'mode_test' 							=> 'Test',
    'checkout_flow' 						=> 'Checkout Flow',
    'mode_redirect' 						=> 'Hosted Payment Page',
    'mode_embedded' 						=> 'Embedded Checkout',
    'terms_url' 							=> 'Terms & Conditions URL',
    'terms_url_placeholder' 				=> 'Insert your Terms & Conditions URL here',
    'merchant_url' 							=> 'Merchant Terms URL',
    'merchant_url_placeholder' 				=> 'Insert your Merchant Terms URL here',
    'auto_capture' 							=> 'Auto Capture',
    'misc' 									=> 'Misc.',
    'wb_url' 								=> 'Webhook URL',
    'wb_url_placeholder' 					=> 'https://example.com/shop.php?do=NetsEasyWebhook',
    'wb_auth' 								=> 'Webhook Auth',
    'wb_auth_placeholder' 					=> 'AZ-1234567890-az',
    'icon_bar' 								=> 'Icon Bar',
    'icon_bar_placeholder' 					=> 'Insert your Icon Bar URL here',
    'debug_mode' 							=> 'Debug Mode',
    'config_save' 							=> 'save configuration',
    'configuration_saved' 					=> 'Configuration saved',
	'mode_frontend' 						=> 'Frontend mode',
    'mode_backend' 							=> 'Backend mode',
	'mode_none' 							=> 'Select Mode Option',
    'info' 									=> 'Information',
    'version' 								=> 'Ver.',
    'portal' 								=> 'Portal',
    'easy_portal' 							=> 'Nets Easy Portal',
    'website' 								=> 'Website',
    'support' 								=> 'Support',
    'account' 								=> 'Get an unlimited and free test account within minutes ',
    'account_link' 							=> 'Here',

	'tooltip_apikeys' 						=> 'Log in to your Nets Easy account and navigate to :: Company > Integration',
	'tooltip_apikeys_url' 					=> 'Nets Easy portal',
	'tooltip_checkoutmode' 					=> 'Choose between Test Sandbox or Live production mode',
	'tooltip_checkoutflow' 					=> 'Choose between redirect Hosted Payment Page or a nested Embedded Checkout',
	'tooltip_termsurl' 						=> 'Please insert the full url to your terms and conditions page',
	'tooltip_merchanturl' 					=> 'Please insert the full url to your Privacy Policy page',
	'tooltip_autocapture' 					=> 'Auto capture allows you to instant charge your orders upon succesful payment reservation',
	'tooltip_wb_url' 						=> 'Webhook URL is set to Nets custom endpoint. Live mode (Production) requires your site to run on SSL.',
	'tooltip_wb_auth' 						=> 'Set your Webhook Authorization Key here. Key must be between 8-64 characters. Key can only consist of [A-Z]-[a-z]-[0-9]. Set value to 0 (zero) to turn OFF webhook functionality.',
	'tooltip_iconbar' 						=> 'This link loads in a set of payment icons displayed on Nets Easy payment method during checkout. You can generate a custom set',
	'tooltip_iconbar_link'					=> 'here',
	'tooltip_debug' 						=> 'When activating Debug mode, Hidden Data will be displayed. This can be emailed to our support to quickly find root cause in case of transaction fails',

    'not_installed' 						=> 'Please install our plugin before you can configure the plugin settings',
    'install_link' 							=> 'Install plugin',
];
