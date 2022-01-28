# NETS A/S - Gambio 4 Payment Module
============================================

|Module       | Nets Easy Payment Module for Gambio 4
|----------------------------------------------------
|Author       | `Nets eCom`
|Prefix       | `EASY-GX4`
|Shop Version | `4+`
|Version      | `1.0.0`
|Guide        | https://tech.nets.eu/shopmodules
|Github       | https://github.com/Nets-eCom/gambio4_easy

## INSTALLATION

### Download / Installation

Follow these steps to install the plugin.

01. Download the plugin from above github link.
02. Extract the zip file.
03. Copy the extracted files and paste in root directory.
04. Login to Admin Panel.
05. Click on Toolbox.
06. Clear Cache.
07. Go To Modules -> Payment Systems.
08. Click on “Miscellaneous” tab and find the Module “Nets Easy”.
09. Install the Module. After installation, press “Configure”.
10. Set your API Credentials and configuration.

NOTE : Hover on blue info icons for more information on specific setting.

### Features
01. Supports two different checkout types : Hosted Payment Window | Embedded Checkout.
02. Custom and modernized checkout page with Embedded Checkout.
03. Fully syncronized payment statuses backend orders and Easy portal. 
04. Supports partial and full charge/refunds from backend order details page.
05. Custom webhook events for real-time payment statuses.
06. Custom Build-in debugging features.

### Configuration
01. To configure and setup the plugin navigate to : Admin >> Modules >> Payment Systems >> Miscellaneous >> added modules >> Nets Easy 
02. Locate the Nets Easy plugin and press the Configure button to access Configuration.

* Settings Description
01. Login to your Nets Easy account (https://portal.dibspayment.eu/). Test and Live Keys can be found in Company > Integration.
02. Payment Environment. Select between Test/Live transactions. Live mode requires an approved account. Testcard information can be found here: https://tech.dibspayment.com/easy/test-information 
03. Checkout Flow. Redirect / Embedded. Select between 2 checkout types. Redirect - Nets Hosted loads a new payment page. Embedded checkout inserts the payment window directly on the checkout page.
04. Enable auto-capture. This function allows you to instantly charge a payment straight after the order is placed.
   NOTE. Capturing a payment before shipment of the order might be liable to restrictions based upon legislations set in your country. Misuse can result in your Easy account being forfeit.
05. Webhook URL. Is per default set to the in-build custom event listener, that handles payment statuses on your orders paid with Nets Easy.
06. Webhook Auth. Set your custom password on incoming webhook event data to tighten security.
07. Debug mode. Optionally activate this feature and copy/send debug content to our support, in case you experience issues with your transactions.

### Operations
* Order Details / Order Status
01. Navigate to admin > Orders > Orders. Press on view (Eye Icon) to access order details.
02. Choose your desired order status in order history. Payment Id is searchable in Nets Easy portal.
03. All transactions by Nets are accessible in our portal : https://portal.dibspayment.eu/login

### Troubleshooting
* Nets payment plugin is not visible as a payment method
- Ensure the Nets plugin is available in the extension configuration.
- Edit the Easy Checkout configuration, Choose the status Enable.

* Nets payment window is blank
- Ensure your keys in Nets plugin Settings are correct and with no additional blank spaces.
- Temporarily deactivate 3.rd party plugins that might effect the functionality of the Nets plugin.
- Check if there is any temporary technical inconsistencies : https://nets.eu/Pages/operational-status.aspx

* Payments in live mode dont work
- Ensure you have an approved Live Easy account for production.
- Ensure your Live Easy account is approved for payments with selected currency.
- Ensure payment method data is correct and supported by your Nets Easy agreement.

### Contact
* Nets customer service
- Nets Easy provides support for both test and live Easy accounts. Contact information can be found here : https://nets.eu/en/payments/customerservice/

** CREATE YOUR FREE NETS EASY TEST ACCOUNT HERE : https://portal.dibspayment.eu/registration **
