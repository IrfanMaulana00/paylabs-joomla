<?php

defined('_JEXEC') or die('Restricted access');

if (!class_exists('Paylabs_Config'))
    require(VMPATH_PLUGINS . DS . 'vmpayment' . DS . 'paylabs' . DS . 'paylabs-php' . DS . 'Paylabs.php');

if (!class_exists('vmPSPlugin'))
    require(JPATH_VM_PLUGINS . DS . 'vmpsplugin.php');

if (!class_exists('VirtueMartModelOrders'))
    require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');

class plgVmpaymentPaylabs extends vmPSPlugin
{
    private $paymentConfigs = array();

    function __construct(&$subject, $config)
    {
        parent::__construct($subject, $config);

        $this->_loggable = TRUE;
        $this->_tablepkey = 'id';
        $this->_tableId = 'id';
        $this->tableFields = array_keys($this->getTableSQLFields());
        $varsToPush = array(
            'merchant_id' => array('', 'char'),
            'paylabs_mode' => array('', 'char'),
            'paylabs_public_key_sandbox' => array('', 'char'),
            'merchant_private_key_sandbox' => array('', 'char'),
            'paylabs_public_key' => array('', 'char'),
            'merchant_private_key' => array('', 'char'),
            'status_success' => array('', 'char'),
            'payment_logos' => array('', 'char'),
            'paylabsproduct' => array('', 'char'),
        );
        $this->setConfigParameterable($this->_configTableFieldName, $varsToPush);
    }

    function getVmPluginCreateTableSQL()
    {
        return $this->createTableSQL('Payment Paylabs Table');
    }

    function getTableSQLFields()
    {
        $sqlFields = array(
            'id' => 'int(11) UNSIGNED NOT NULL AUTO_INCREMENT',
            'virtuemart_order_id' => 'int(11) UNSIGNED',
            'order_number' => 'char(64)',
            'virtuemart_paymentmethod_id' => 'mediumint(1) UNSIGNED',
            'payment_name' => 'varchar(2000)',
            'payment_order_total' => 'decimal(15,5) NOT NULL DEFAULT \'0.00000\'',
            'payment_currency' => 'char(3)',
            'email_currency' => 'char(3)',
            'cost_per_transaction' => 'decimal(10,2)',
            'cost_min_transaction' => 'decimal(10,2)',
            'cost_percent_total' => 'decimal(10,2)',
            'tax_id' => 'smallint(1)'
        );

        return $sqlFields;
    }

    /**
     * Create the table for this plugin if it does not yet exist.
     * This functions checks if the called plugin is active one.
     * When yes it is calling the standard method to create the tables
     */
    function plgVmOnStoreInstallPaymentPluginTable($jplugin_id)
    {
        if (!defined('VM_VERSION') || VM_VERSION < 3) {
            // for older vm version
            return $this->onStoreInstallPaymentPluginTable($jplugin_id);
        } else {
            return $this->onStoreInstallPluginTable($jplugin_id);
        }
    }

    /**
     * Checks how many plugins are available. If only one, the user will not have the choice. Enter edit_xxx page
     * The plugin must check first if it is the correct type
     *
     * @param VirtueMartCart cart: the cart object
     * @return null if no plugin was found, 0 if more then one plugin was found,  virtuemart_xxx_id if only one plugin is found
     */
    function plgVmOnCheckAutomaticSelectedPayment(VirtueMartCart $cart, array $cart_prices = array())
    {
        return $this->onCheckAutomaticSelected($cart, $cart_prices);
    }

    /**
     * Process after buyer set confirm purchase in check out< it loads a new page with widget
     */
    function plgVmConfirmedOrder($cart, $order)
    {
        date_default_timezone_set('Asia/Jakarta');
        if (!($this->_currentMethod = $this->getVmPluginMethod($order['details']['BT']->virtuemart_paymentmethod_id))) {
            return NULL; // Another method was selected, do nothing
        }

        if (!$this->selectedThisElement($this->_currentMethod->payment_element)) {
            return FALSE;
        }

        VmConfig::loadJLang('com_virtuemart', true);
        // Prepare data that should be stored in the database
        $transaction_data = $this->prepareTransactionData($order, $cart);
        $this->storePSPluginInternalData($transaction_data);

        $userinfo = $order['details']['BT']->virtuemart_user_id != 0 ? $order['details']['BT']->email : $_SERVER['REMOTE_ADDR'];
        $phoneNumber = empty($order['details']['BT']->phone_1) ? "0000000000" : $order['details']['BT']->phone_1;
        $fullName = $order['details']['BT']->first_name . " " . $order['details']['BT']->last_name;

        //generate Signature
        $configs = $this->getPaymentConfigs($order['details']['BT']->virtuemart_paymentmethod_id);

        $date = date("Y-m-d") . "T" . date("H:i:s.B") . "+07:00";
        $merchantId = $configs['merchant_id'];
        $paylabsMode = $configs['paylabs_mode'];
        $publicKey = $paylabsMode == "sandbox" ? $configs['paylabs_public_key_sandbox'] : $configs['paylabs_public_key'];
        $privateKey = $paylabsMode == "sandbox" ? $configs['merchant_private_key_sandbox'] : $configs['merchant_private_key'];
        $order_id = $order['details']['BT']->virtuemart_order_id;
        $order_number = $order['details']['BT']->order_number;
        $def_curr = $this->getCurrencyCodeById($order['details']['BT']->order_currency);
        $success_url = $configs['success_url'];
        $order_total = $order['details']['BT']->order_total;
        $url = Paylabs_Config::getBaseUrl($paylabsMode);

        $requestId = $order_id . "-" . $order_number . "-" . time();

        $body = [
            'requestId' => $requestId,
            'merchantTradeNo' => $requestId,
            'merchantId' => $merchantId,
            'amount' => number_format($order_total, 2, '.', ''),
            'phoneNumber' => $phoneNumber,
            'productName' => $order_number,
            'redirectUrl' => JURI::root() . 'index.php?option=com_virtuemart&view=vmplg&task=pluginresponsereceived&on=' . $order['details']['BT']->order_number . '&pm=' . $order['details']['BT']->virtuemart_paymentmethod_id . '&Itemid=' . vRequest::getInt('Itemid') . '&lang=' . vRequest::getCmd('lang', ''),
            'notifyUrl' => JURI::root() . 'index.php?option=com_virtuemart&view=vmplg&task=notify&tmpl=component' . '&lang=' . vRequest::getCmd('lang', ''),
            'payer' => $fullName
        ];

        $path = "/payment/v2/h5/createLink";
        $sign = Paylabs_VtWeb::generateHash($privateKey, $body, $path, $date);
        if ($sign->status == false) {
            echo $sign->desc;
            die();
        }

        try {
            $redirUrl = Paylabs_VtWeb::createTrascation($url . $path, $body, $sign->sign, $date);
            if (isset($redirUrl->errCodeDes)) {
                echo $redirUrl->errCodeDes;
                die();
            }
            $redirUrl = $redirUrl->url;
        } catch (Exception $e) {
            echo $e->getMessage();
            die();
        }

        //might be required if virtual account
        $cart->emptyCart();
        $order_history = array(
            'customer_notified' => 1,
            'virtuemart_order_id' => $order_id,
            'comments' => 'Payment Link : <a href="' . $redirUrl . '" target="_NEW">' . $this->_currentMethod->payment_name . '</a>',
            'order_status' => 'P', //status set to pending
        );
        $orderModel = VmModel::getModel('orders');
        $orderModel->updateStatusForOneOrder($order_id, $order_history, FALSE);

        $app = JFactory::getApplication();
        $msg = 'redirecting';
        $app->redirect($redirUrl);
        //vRequest::setVar('html', $html);				       
    }

    /**
     * This event is fired after the payment method has been selected. It can be used to store
     * additional payment info in the cart.
     *
     * @param VirtueMartCart $cart : the actual cart
     * @return null if the payment was not selected, true if the data is valid, error message if the data is not vlaid
     */
    function plgVmOnSelectCheckPayment(VirtueMartCart $cart, &$msg)
    {
        return $this->OnSelectCheck($cart);
    }

    /**
     * This event is fired to display the pluginmethods in the cart (edit shipment/payment) for exampel
     *
     * @param object|VirtueMartCart $cart Cart object
     * @param integer $selected ID of the method selected
     * @param $htmlIn
     * @return bool True on success, false on failures, null when this plugin was not selected.
     * On errors, JError::raiseWarning (or JError::raiseError) must be used to set a message.
     */
    function plgVmDisplayListFEPayment(VirtueMartCart $cart, $selected = 0, &$htmlIn)
    {
        //ToDo add image logo
        return $this->displayListFE($cart, $selected, $htmlIn);
    }

    /**
     * Check if the payment conditions are fulfilled for this payment method
     *
     * @param VirtueMartCart $cart
     * @param int $method
     * @param array $cart_prices : cart prices
     * @return true : if the conditions are fulfilled, false otherwise
     *
     */
    protected function checkConditions($cart, $method, $cart_prices)
    {
        return true;
    }

    /**
     * This method is fired when showing the order details in the frontend.
     * It displays the method-specific data.
     *
     * @param $virtuemart_order_id
     * @param $virtuemart_paymentmethod_id
     * @param $payment_name
     * @return mixed Null for methods that aren't active, text (HTML) otherwise
     */
    function plgVmOnShowOrderFEPayment($virtuemart_order_id, $virtuemart_paymentmethod_id, &$payment_name)
    {
        $this->onShowOrderFE($virtuemart_order_id, $virtuemart_paymentmethod_id, $payment_name);
    }

    /**
     * Calculate the price (value, tax_id) of the selected method
     * It is called by the calculator
     * This function does NOT to be reimplemented. If not reimplemented, then the default values from this function are taken.
     *
     * @param VirtueMartCart $cart the current cart
     * @param array cart_prices the new cart prices
     * @return null if the method was not selected, false if the shiiping rate is not valid any more, true otherwise
     *
     *
     */
    function plgVmonSelectedCalculatePricePayment(VirtueMartCart $cart, array &$cart_prices, &$cart_prices_name)
    {
        return $this->onSelectedCalculatePrice($cart, $cart_prices, $cart_prices_name);
    }

    function plgVmgetPaymentCurrency($virtuemart_paymentmethod_id, &$paymentCurrencyId)
    {
        if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
            return null; // Another method was selected, do nothing
        }

        if (!$this->selectedThisElement($method->payment_element)) {
            return false;
        }

        $this->getPaymentCurrency($method);

        $paymentCurrencyId = $method->payment_currency;
    }

    function plgVmDeclarePluginParamsPayment($name, $id, &$data)
    {
        return $this->declarePluginParams('payment', $name, $id, $data);
    }

    function plgVmSetOnTablePluginParamsPayment($name, $id, &$table)
    {
        return $this->setOnTablePluginParams($name, $id, $table);
    }

    /**
     * Addition triggers for VM3
     * @param $data
     * @return bool
     */
    function plgVmDeclarePluginParamsPaymentVM3(&$data)
    {
        return $this->declarePluginParams('payment', $data);
    }

    /**
     * This method is fired when showing when priting an Order
     * It displays the the payment method-specific data.
     *
     * @param $order_number
     * @param integer $method_id method used for this order
     * @return mixed Null when for payment methods that were not selected, text (HTML) otherwise
     * @internal param int $_virtuemart_order_id The order ID
     */
    function plgVmOnShowOrderPrintPayment($order_number, $method_id)
    {
        return $this->onShowOrderPrint($order_number, $method_id);
    }

    public function getCurrencyCodeById($currency_id)
    {
        $db = JFactory::getDBO();
        $query = $db->getQuery(true);
        $query->select($db->quoteName(array('currency_code_3')));
        $query->from($db->quoteName('#__virtuemart_currencies'));
        $query->where($db->quoteName('virtuemart_currency_id') . '=' . $db->quote($currency_id));
        $db->setQuery($query, 0, 1);

        $result = $db->loadRow();
        return $result ? $result[0] : false;
    }

    /**
     * Get Payment configs
     * @param $payment_id
     * @return array|bool
     */
    public function getPaymentConfigs($payment_id = false)
    {
        if (!$this->paymentConfigs && $payment_id) {

            $db = JFactory::getDBO();
            $query = $db->getQuery(true);
            $query->select($db->quoteName(array('payment_params')));
            $query->from($db->quoteName('#__virtuemart_paymentmethods'));
            $query->where($db->quoteName('virtuemart_paymentmethod_id') . '=' . $db->quote($payment_id));
            $db->setQuery($query, 0, 1);
            $result = $db->loadRow();

            if (strlen($result[0]) > 0) {
                $payment_params = array();
                foreach (explode("|", $result[0]) as $payment_param) {
                    if (empty($payment_param)) {
                        continue;
                    }
                    $param = explode('=', $payment_param);
                    $payment_params[$param[0]] = substr($param[1], 1, -1);
                }
                $this->paymentConfigs = $payment_params;
            }
        }
        // $jsonBody = json_encode($this->paymentConfigs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        // return json_decode($jsonBody, true);
        return $this->paymentConfigs;
    }

    private function getUserProfileData($orderInfo)
    {
        return array(
            'customer[city]' => $orderInfo->city,
            'customer[state]' => $orderInfo->virtuemart_state_id,
            'customer[address]' => $orderInfo->address_1,
            'customer[country]' => $orderInfo->virtuemart_country_id,
            'customer[zip]' => $orderInfo->zip,
            'customer[username]' => $orderInfo->virtuemart_user_id,
            'customer[firstname]' => $orderInfo->first_name,
            'customer[lastname]' => $orderInfo->last_name,
            'email' => $orderInfo->email,
        );
    }

    /**
     * Extends the standard function in vmplugin. Extendst the input data by virtuemart_order_id
     * Calls the parent to execute the write operation
     *
     * @param $values
     * @param int $primaryKey
     * @param bool $preload
     * @return array
     * @internal param array $_values
     * @internal param string $_table
     */
    protected function storePSPluginInternalData($values, $primaryKey = 0, $preload = FALSE)
    {

        if (!class_exists('VirtueMartModelOrders')) {
            require(VMPATH_ADMIN . DS . 'models' . DS . 'orders.php');
        }
        if (!isset($values['virtuemart_order_id'])) {
            $values['virtuemart_order_id'] = VirtueMartModelOrders::getOrderIdByOrderNumber($values['order_number']);
        }
        return $this->storePluginInternalData($values, $primaryKey, 0, $preload);
    }

    /**
     * @param $order
     * @param $cart
     * @return array
     */
    public function prepareTransactionData($order, $cart)
    {
        // Prepare data that should be stored in the database
        return array(
            'order_number' => $order['details']['BT']->order_number,
            'payment_name' => $this->_currentMethod->payment_name,
            'virtuemart_paymentmethod_id' => $cart->virtuemart_paymentmethod_id,
            'cost_per_transaction' => $this->_currentMethod->cost_per_transaction,
            'cost_percent_total' => $this->_currentMethod->cost_percent_total,
            'payment_currency' => $this->_currentMethod->payment_currency,
            'payment_order_total' => $order['details']['BT']->order_total,
            'tax_id' => $this->_currentMethod->tax_id,
        );
    }

    protected function renderPluginName($activeMethod)
    {
        $return = '';
        $plugin_name = $this->_psType . '_name';
        $plugin_desc = $this->_psType . '_desc';
        $description = '';
        // 		$params = new JParameter($plugin->$plugin_params);
        // 		$logo = $params->get($this->_psType . '_logos');
        $logosFieldName = $this->_psType . '_logos';
        $logos = $activeMethod->$logosFieldName;
        if (!empty($logos)) {
            $return .= $this->displayLogos($logos) . ' ';
        }
        $pluginName = $return . '<span class="' . $this->_type . '_name">' . $activeMethod->$plugin_name . '</span>';
        if (!empty($activeMethod->$plugin_desc)) {
            $pluginName .= '<span class="' . $this->_type . '_description">' . $activeMethod->$plugin_desc . '</span>';
        }
        //$pluginName .= $this->displayExtraPluginNameInfo($activeMethod);
        return $pluginName;
    }


    /**
     * @param $html
     * @return bool|null|string
     */
    function plgVmOnPaymentResponseReceived(&$html)
    {

        if (!class_exists('VirtueMartCart')) {
            require(VMPATH_SITE . DS . 'helpers' . DS . 'cart.php');
        }
        if (!class_exists('shopFunctionsF')) {
            require(VMPATH_SITE . DS . 'helpers' . DS . 'shopfunctionsf.php');
        }
        if (!class_exists('VirtueMartModelOrders')) {
            require(VMPATH_ADMIN . DS . 'models' . DS . 'orders.php');
        }
        VmConfig::loadJLang('com_virtuemart_orders', TRUE);

        // the payment itself should send the parameter needed.
        $virtuemart_paymentmethod_id = vRequest::getInt('pm', 0);
        $order_number = vRequest::getString('on', 0);
        $vendorId = 0;
        if (!($this->_currentMethod = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
            return NULL; // Another method was selected, do nothing
        }
        if (!$this->selectedThisElement($this->_currentMethod->payment_element)) {
            return NULL;
        }
        if (!($virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber($order_number))) {
            return NULL;
        }
        if (!($payments = $this->getDatasByOrderNumber($order_number))) {
            return '';
        }
        VmConfig::loadJLang('com_virtuemart');
        $orderModel = VmModel::getModel('orders');
        $order = $orderModel->getOrder($virtuemart_order_id);

        $this->_currentMethod->payment_currency = $this->getPaymentCurrency($this->_currentMethod, $order['details']['BT']->payment_currency_id);
        $payment_name = $this->renderPluginName($this->_currentMethod);
        $payment = end($payments);

        // to do: this
        //$this->debugLog($payment, 'plgVmOnPaymentResponseReceived', 'debug', false);
        if (!class_exists('CurrencyDisplay')) {
            require(VMPATH_ADMIN . DS . 'helpers' . DS . 'currencydisplay.php');
        }
        $currency = CurrencyDisplay::getInstance('', $order['details']['BT']->payment_currency_id);

        if (isset($_GET['resultCode']) && isset($_GET['merchantOrderId']) && isset($_GET['reference']) && $_GET['resultCode'] == '00') {
            $success = true;
        } else if (isset($_GET['resultCode']) && isset($_GET['merchantOrderId']) && isset($_GET['reference']) && $_GET['resultCode'] == '01') {
            $success = true;
        } else {
            $success = false;
        }

        $html = $this->renderByLayout('stdresponse', array(
            "payment_name" => $payment_name,
            "reference" => $_GET['reference'],
            "order" => $order,
            "currency" => $currency,
            "success" => $success,
        ));

        //We delete the old stuff
        // get the correct cart / session
        $cart = VirtueMartCart::getCart();
        $cart->emptyCart();
        return TRUE;
    }

    function plgVmOnPaymentNotification()
    {

        if (!class_exists('VirtueMartModelOrders')) {
            require(VMPATH_ADMIN . DS . 'models' . DS . 'orders.php');
        }

        $jsonData = file_get_contents('php://input');
        $headers = getallheaders();
        if (!$jsonData or !$headers) {
            return false;
        }

        // Ambil nilai header yang dibutuhkan
        $partnerId = $headers['x-partner-id'];
        $requestId = $headers['x-request-id'];
        $timestamp = $headers['x-timestamp'];
        $signature = $headers['x-signature'];

        // Proses body callback
        $data = json_decode($jsonData, true);
        $status = $data['status'];
        $errCode = $data['errCode'];
        $merchantTradeNo = $data['merchantTradeNo'];
        $split = explode("-", $merchantTradeNo);
        $orderId = isset($split[0]) ? $split[0] : 0;
        $orderNumber = isset($split[1]) ? $split[1] : 0;

        if ($status == '02' && $errCode == "0") {

            $orderModel = VmModel::getModel('orders');
            $order = $orderModel->getOrder($orderId);
            if (!$order) return false;

            $configs = $this->getPaymentConfigs($order['details']['BT']->virtuemart_paymentmethod_id);
            $paylabsMode = $configs['paylabs_mode'];
            $publicKey = $paylabsMode == "sandbox" ? $configs['paylabs_public_key_sandbox'] : $configs['paylabs_public_key'];
            $privateKey = $paylabsMode == "sandbox" ? $configs['merchant_private_key_sandbox'] : $configs['merchant_private_key'];

            // Validasi signature
            $validate = Paylabs_VtWeb::validateTransaction($publicKey, $signature, $jsonData, $timestamp);

            if ($validate) {
                $order_history = array(
                    'customer_notified' => 1, //send notification to user
                    'virtuemart_order_id' => $orderId,
                    'comments' => 'payment was successful',
                    'order_status' => $configs['status_success'],
                );
                $orderModel->updateStatusForOneOrder($orderId, $order_history, TRUE);
            }

            $date = date("Y-m-d") . "T" . date("H:i:s.B") . "+07:00";
            $requestId = $data['merchantTradeNo'] . "-" . $data['successTime'];

            $response = array(
                "merchantId" => $data['merchantId'],
                "requestId" => $requestId,
                "errCode" => "0"
            );

            $signature = Paylabs_VtWeb::generateHash($privateKey, $response, "/index.php", $date);
            if ($signature->status == false) return false;

            // Set HTTP response headers
            header("Content-Type: application/json;charset=utf-8");
            header("X-TIMESTAMP: " . $date);
            header("X-SIGNATURE: " . $signature->sign);
            header("X-PARTNER-ID: " . $data['merchantId']);
            header("X-REQUEST-ID: " . $requestId);

            // Encode the response as JSON and output it
            echo json_encode($response, JSON_UNESCAPED_UNICODE);
            die();
        }
    }
}
