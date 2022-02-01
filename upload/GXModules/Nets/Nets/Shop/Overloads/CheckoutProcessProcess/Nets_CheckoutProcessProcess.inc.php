<?php

/* --------------------------------------------------------------
  Nets CheckoutProcessProcess.inc.php 2022-01-22
  Gambio GmbH
  http://www.gambio.de
  Copyright (c) 2016 Gambio GmbH
  Released under the GNU General Public License (Version 2)
  [http://www.gnu.org/licenses/gpl-2.0.html]
  --------------------------------------------------------------
 */

/**
 * Class representing the checkout process overload for the Nets
 */
class Nets_CheckoutProcessProcess extends Nets_CheckoutProcessProcess_parent {

    /**
     * Proceed
     */
    public function proceed() {
        if ($this->check_redirect()) {
            if (isset($_SESSION['nets']['paymentid']) && !empty($_SESSION['nets']['paymentid'])) {
                $this->set_redirect_url(xtc_href_link(FILENAME_CHECKOUT_SUCCESS, '', 'SSL'));
                unset($_SESSION['nets']['paymentid']);
            }
            return true;
        }
        parent::proceed();
    }

}
