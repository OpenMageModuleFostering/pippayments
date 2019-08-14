<?php

/*
 * PiP Payments Module Model
 *
 * Just redirects the user to the payment modules controller
 *
 */

class PiP_PiPPayments_Model_Standard extends Mage_Payment_Model_Method_Abstract {
    protected $_code  = 'pippayments';

    // Redirect URL when place order is clicked
    public function getOrderPlaceRedirectUrl() {

        return Mage::getURL('pippayments/payment/redirect');

    }

}