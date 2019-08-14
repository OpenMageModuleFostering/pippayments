<?php

/*
 * PiP Payment Module - Payment Controller
 *
 * Generates and redirects the customer to the PIP Bill Page
 * Also handles callback from PiP
 *
 */

class PiP_PiPPayments_PaymentController extends Mage_Core_Controller_Front_Action  {

    // PiP URLs
    // Production
    protected $_url = 'https://pip-it.net/os/mgr/create';
    // Testing
    protected $_test = 'https://uat.pip-it.net/os/mgr/create';


    // Handle PiP Callbacks
    public function callbackAction() {

        $request = $this->getRequest();
        if (!$request->isPost() or !is_numeric($request->getPost('BARCODE'))) {
            // Error header
            $this->getResponse()->setHttpResponseCode(400); // Bad request
        }else{
            // Get out Post Data and Settings
            $barcode = $request->getPost('BARCODE');
            $order_id = $request->getPost('VENDOR_ORDER_ID');
            $sign = $request->getPost('SIGN');
            // PiP Merchant Secret
            $secret = Mage::getStoreConfig('payment/pippayments/secret');
            // Send notification after successfull callback?
            $notify = Mage::getStoreConfig('payment/pippayments/notify');

            $request_type = '';
            $x_date = '';

            // Determine request type - CREATE, PAID, EXPIRE
            if (is_numeric($request->getPost('CREATE_DATE'))){
                $request_type = 'create';
                $x_date = $request->getPost('CREATE_DATE');
            } else if (is_numeric($request->getPost('PAID_DATE'))) {
                $request_type = 'paid';
                $x_date = $request->getPost('PAID_DATE');
            } else if (is_numeric($request->getPost('EXPIRE_DATE'))) {
                $request_type = 'expire';
                $x_date = $request->getPost('EXPIRE_DATE');
            }


            // Get our order
            $order = new Mage_Sales_Model_Order();
            $order->loadByIncrementId($order_id);


            if ($request_type == '') {
                Mage::log('PIP Callback - Invalid Callback');
                $this->getResponse()->setHttpResponseCode(400); // Bad request
            }else {

                // Authenticate
                $auth = sha1($barcode.$order_id.$x_date.$secret);

                // Order comment for notification
                $order_comment = NULL;

                // Ensure Authenticated request
                if ($auth == $sign) {
                    // Auth OK
                    switch ($request_type) {
                        case 'create':
                            // Mark Order as Pending Payment - PIP Create
                            $order->setState(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT, true, 'PIP Status: PENDING, PIP Barcode: '.$barcode, $notify)->save();
                            $order_comment = 'Status: Pending, Barcode: '.$barcode;
                            break;
                         case 'paid':
                            // Mark Order as Paid - PIP Paid
                            $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, true, 'PIP Status: PAID, PIP Barcode: '.$barcode, $notify )->save();
                            $order_comment = 'Status: Paid, Barcode: '.$barcode;
                            break;
                        case 'expire':
                            // Mark Order as Cencelled - PIP Expire
                            $order->setState(Mage_Sales_Model_Order::STATE_CANCELED, true, 'PIP Status: EXPIRED, PIP Barcode: '.$barcode, $notify)->save();
                            $order_comment = 'Status: Expired, Barcode: '.$barcode;
                            break;
                    }

                    // Send notification?
                    if ($notify == TRUE) {
                        $order->sendOrderUpdateEmail($notify, $order_comment); // $notify is boolean
                    }

                    $this->getResponse()->setHttpResponseCode(200);
                }else {
                    // Auth failure
                    Mage::log('PIP Callback - Auth Failure');
                    $this->getResponse()->setHttpResponseCode(403); // Forbidden request
                }
            }
        }

    }

    // Redirect Action - Has to be a double redirect as the order doesn't exist in the cart checkout
    public function redirectAction() {
        // Get Order from session details
        $order = new Mage_Sales_Model_Order();
        $order_id = Mage::getSingleton('checkout/session')->getLastRealOrderId();
        $order->loadByIncrementId($order_id);


        // Extract what we need from the order
        $order_value = round($order->getGrandTotal(), 2);
        $order_value = number_format($order_value, 2, '.', '');
        $currency = $order->getOrderCurrencyCode();

        // Get Order Email and Phone
        $email = $order->getCustomerEmail();
        $tel = $order->getBillingAddress()->getTelephone();

        // Get Vendor Name and Secret from settings
        $vendor_name = Mage::getStoreConfig('payment/pippayments/vendor_name');
        $secret = Mage::getStoreConfig('payment/pippayments/secret');

        // Standard Checkout URLs
        $reply_url = Mage::getURL('checkout/onepage/success');
        $cancel_url = Mage::getURL('checkout/onepage/failure');


        // Generate signature
        $sign = sha1($vendor_name.$order_value.$email.$order_id.$secret);

        // Base URL
        $url = $this->_url;

        // Test Mode?
        /*if (Mage::getStoreConfig('payment/pippayments/test')) {
            $url = $this->_test;
        }*/

        // GET Params
        $params = array(
                'VENDOR_NAME' => $vendor_name,
                'VALUE' => $order_value,
                'CUST_EMAIL' => $email,
                'VENDOR_ORDER_ID' => $order_id,
                'SIGN' => $sign,
                'REPLY_URL' => $reply_url,
                'CANCEL_URL' => $cancel_url,
                'CURRENCY' => $currency,
                'MOBILE' => $tel,
            );

        // Build URL string
        $string = '';
        foreach($params as $key=>$value) { $string .= $key.'='.$value.'&'; }
        $string = rtrim($string, '&');

        $url .='?'.$string;

        // Redirect to url
        $this->getResponse()->setRedirect($url);

        // DEBUG - Output URL as link
        //echo '<a href="'.$url.'">'.$url.'</a>';

    }
}
