<?php
/**
* 2007-2023 PrestaShop
*
* NOTICE OF LICENSE
*
* Любое копирование или перепродажа модуля запрещена.
*  @author    Andrey U <info@rus-design.com>
*  @copyright 2007-2023 Rus-Design
*/

if (!defined('_PS_VERSION_')) {
    exit;
}
use Dolyame\Payment\Client;

class Dolyame extends PaymentModule
{
    protected $config_form = false;
    protected $_postErrors = array();

    public function __construct()
    {
        $this->name = 'dolyame';
        $this->tab = 'payments_gateways';
        $this->version = '1.0.0';
        $this->author = 'Andrey U';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->module_key = '';

        parent::__construct();

        $this->displayName = $this->l('Dolyame');
        $this->description = $this->l('Dolyame payment by Tinkoff');
        // $this->ps_versions_compliancy = array('min' => '1.5', 'max' => _PS_VERSION_);
    }

    public function install()
    {
        $this->addOrderState($this->l('В ожидании оплаты банком (TinkOff Долями)'));
        $this->addOrderState($this->l('Успешный платёж (TinkOff Долями)'));
        $this->addOrderState($this->l('Неуспешный платёж (TinkOff Долями)'));
        $this->addOrderState($this->l('Отменённый платёж (TinkOff Долями)'));

        if (extension_loaded('curl') == false)
        {
            $this->_errors[] = $this->l('You have to enable the cURL extension on your server to install this module');
            return false;
        }

        return parent::install() &&
            $this->registerHook('header') &&
            $this->registerHook('payment') &&
//            $this->registerHook('paymentReturn') &&
            $this->registerHook('actionOrderStatusPostUpdate') &&
            $this->registerHook('paymentOptions');

        Db::getInstance()->execute(
            'CREATE TABLE IF NOT EXISTS `'. _DB_PREFIX_ .'tinkoff_dolyame_orders` (
                `id_tinkoff_dolyame_order` int(11) NOT NULL AUTO_INCREMENT,
                `id_order` int(11) NOT NULL,
                `payment_id` varchar(255) NOT NULL,
                `status` tinyint(1) default 0,
                PRIMARY KEY (`id_tinkoff_dolyame_order`)
            ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;'
        );

    }

    public function uninstall()
    {
        $this->deleteOrderState();
        return parent::uninstall();
    }

    public function addOrderState($name)
    {
        $state_exist = false;
        $states = OrderState::getOrderStates((int)$this->context->language->id);

        foreach ($states as $state) {
            if (in_array($name, $state)) {
                $state_exist = true;
                break;
            }
        }

        if (!$state_exist) {
            $order_state = new OrderState();
            if($name=='В ожидании оплаты банком (TinkOff Долями)')
            {
                $order_state->color = '#FF8C00';
            }elseif ($name=='Успешный платёж (TinkOff Долями)')
            {
                $order_state->color = '#32CD32';
            }elseif ($name=='Неуспешный платёж (TinkOff Долями)')
            {
                $order_state->color = '#8f0621';
            }elseif ($name=='Отменённый платёж (TinkOff Долями)')
            {
                $order_state->color = '#8f0621';
            }

            $order_state->send_email = true;
            $order_state->module_name = 'dolyame';
            $order_state->template = '';
            $order_state->name = array();
            $languages = Language::getLanguages(false);
            foreach ($languages as $language)
                $order_state->name[ $language['id_lang'] ] = $name;

            $order_state->add();
        }

        return true;
    }

    public function deleteOrderState()
    {
        $states = OrderState::getOrderStates((int)$this->context->language->id);
        foreach ($states as $state) {
            if($state['module_name']=='dolyame')
            {
                $delete_state= new OrderState($state['id_order_state']);
                $delete_state->delete();
            }
        }
    }

    public function getContent()
    {
            $output = $this->context->smarty->fetch($this->local_path.'views/templates/admin/configure.tpl');
            if (((bool)Tools::isSubmit('submitDolyameModule')) == true) {
                $this->_postValidation();
                if (!count($this->_postErrors)) {
                    $this->postProcess();
                } else {
                    foreach ($this->_postErrors as $err) {
                        $output .= $this->displayError($err);
                    }
                }
            } else {
                $output .= '<br />';
            }
    
            $this->context->smarty->assign('module_dir', $this->_path);
    
            return $output.$this->renderForm();
    }

    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitDolyameModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(), /* Add values for your inputs */
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($this->getConfigForm()));
    }

    /**
     * Create the structure of your form.
     */
    protected function getConfigForm()
    {
        return array(
            'form' => array(
                'legend' => array(
                'title' => $this->l('Settings'),
                'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'col' => 3,
                        'type' => 'text',
                        'desc' => $this->l('Минимальная сумма корзины. Если сумма в корзине ниже минимума, данный способ оплаты не будет доступен'),
                        'name' => 'DOLYAME_MIN_CART',
                        'label' => $this->l('Минимальная сумма корзины'),
                    ),
                    array(
                        'col' => 3,
                        'type' => 'text',
                        'desc' => $this->l('Максимальная сумма корзины. Если сумма в корзине превышает максимум, данный способ оплаты не будет доступен'),
                        'name' => 'DOLYAME_MAX_CART',
                        'label' => $this->l('Максимальная сумма корзины'),
                    ),
                    array(
                        'col' => 3,
                        'type' => 'text',
                        'desc' => $this->l('Введите id товаров через запятую. Если в корзине находятся эти товары данный способ оплаты не будет доступен'),
                        'name' => 'DOLYAME_PRODUCTS',
                        'label' => $this->l('Ограничить для товаров'),
                    ),
                    array(
                        'col' => 3,
                        'type' => 'text',
                        'desc' => $this->l('Введите id категорий через запятую. Если в корзине находятся товары из этих категорий данный способ оплаты не будет доступен'),
                        'name' => 'DOLYAME_CATEGORIES',
                        'label' => $this->l('Ограничить для категорий товаров'),
                    ),
                    array(
                        'col' => 3,
                        'type' => 'text',
                        'desc' => $this->l('Введите id брендов через запятую. Если в корзине находятся товары от этих брендов данный способ оплаты не будет доступен'),
                        'name' => 'DOLYAME_MANUFACTURERS',
                        'label' => $this->l('Ограничить для брендов товаров'),
                    ),
                    array(
                        'col' => 3,
                        'type' => 'text',
                        'desc' => $this->l('Введите id статусов заказа при которых необходимо выполнить возврат оплаты, через запятую. При данном статусе будет осуществлен возврат Д/С клиенту и отменен заказ в системе Долями'),
                        'name' => 'DOLYAME_CANCEL_STATUSES',
                        'label' => $this->l('Статусы для осуществления возврата'),
                    ),
                    array(
                        'col' => 3,
                        'type' => 'text',
                        'desc' => $this->l('Логин Долями'),
                        'name' => 'DOLYAME_ACCOUNT_LOGIN',
                        'label' => $this->l('Логин'),
                    ),
                    array(
                        'col' => 3,
                        'type' => 'text',
                        'desc' => $this->l('Пароль Долями'),
                        'name' => 'DOLYAME_ACCOUNT_PASSWORD',
                        'label' => $this->l('Пароль'),
                    ),
                    array(
                        'type' => 'file',
                        'label' => $this->l('Ключ'),
                        'name' => 'DOLYAME_KEY',
                        'desc' => $this->l('Проверьте наличие ключа по адресу ' . Tools::getShopDomainSsl(true) . '/modules/dolyame/upload/private.key'),
                    ),
                    array(
                        'type' => 'file',
                        'label' => $this->l('Сертификат MTLS'),
                        'name' => 'DOLYAME_CERT',
                        'desc' => $this->l('Проверьте наличие сертификата по адресу ' . Tools::getShopDomainSsl(true) . '/modules/dolyame/upload/open_api_cert.pem'),
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );
    }

    public function getPath()
    {
        return $this->_path;
    }

    protected function getConfigFormValues()
    {
        return array(
            'DOLYAME_KEY' => Configuration::get('DOLYAME_KEY'),
            'DOLYAME_CERT' => Configuration::get('DOLYAME_CERT'),
            'DOLYAME_MIN_CART' => Configuration::get('DOLYAME_MIN_CART'),
            'DOLYAME_MAX_CART' => Configuration::get('DOLYAME_MAX_CART'),
            'DOLYAME_PRODUCTS' => Configuration::get('DOLYAME_PRODUCTS'),
            'DOLYAME_CATEGORIES' => Configuration::get('DOLYAME_CATEGORIES'),
            'DOLYAME_MANUFACTURERS' => Configuration::get('DOLYAME_MANUFACTURERS'),
            'DOLYAME_CANCEL_STATUSES' => Configuration::get('DOLYAME_CANCEL_STATUSES'),
            'DOLYAME_ACCOUNT_LOGIN' => Configuration::get('DOLYAME_ACCOUNT_LOGIN'),
            'DOLYAME_ACCOUNT_PASSWORD' => Configuration::get('DOLYAME_ACCOUNT_PASSWORD'),
        );
    }

    protected function _postValidation()
    {
        if (Tools::isSubmit('submitDolyameModule')) {
            if (!Tools::getValue('DOLYAME_MIN_CART')) {
                $this->_postErrors[] = $this->l('Введите минимальную сумму');
            } elseif (!Tools::getValue('DOLYAME_MAX_CART')) {
                $this->_postErrors[] = $this->l('Введите максимальную сумму');
            } elseif (!Tools::getValue('DOLYAME_ACCOUNT_LOGIN')) {
                $this->_postErrors[] = $this->l('Введите логин');
            } elseif (!Tools::getValue('DOLYAME_ACCOUNT_PASSWORD')) {
                $this->_postErrors[] = $this->l('Введите пароль');
            }
        }
    }

    protected function postProcess()
    {
        if (isset($_FILES['DOLYAME_KEY'])
            && isset($_FILES['DOLYAME_KEY']['tmp_name'])
            && !empty($_FILES['DOLYAME_KEY']['tmp_name']))
        {
            $file_name = 'private';
                if (!move_uploaded_file($_FILES['DOLYAME_KEY']['tmp_name'], dirname(__FILE__).DIRECTORY_SEPARATOR.'upload'.DIRECTORY_SEPARATOR.$file_name.'.key'))
                    return $this->displayError($this->l('An error occurred while attempting to upload the file.'));
                else
                {
                    if (Configuration::get('DOLYAME_KEY') != $file_name)
                        @unlink(dirname(__FILE__).DIRECTORY_SEPARATOR.'upload'.DIRECTORY_SEPARATOR.Configuration::get('DOLYAME_KEY'));
                }
        }

        if (isset($_FILES['DOLYAME_CERT'])
            && isset($_FILES['DOLYAME_CERT']['tmp_name'])
            && !empty($_FILES['DOLYAME_CERT']['tmp_name']))
        {
            $file_name = 'open_api_cert';
            if (!move_uploaded_file($_FILES['DOLYAME_CERT']['tmp_name'], dirname(__FILE__).DIRECTORY_SEPARATOR.'upload'.DIRECTORY_SEPARATOR.$file_name.'.pem'))
                return $this->displayError($this->l('An error occurred while attempting to upload the file.'));
            else
            {
                if (Configuration::get('DOLYAME_CERT') != $file_name)
                    @unlink(dirname(__FILE__).DIRECTORY_SEPARATOR.'upload'.DIRECTORY_SEPARATOR.Configuration::get('DOLYAME_CERT'));
            }
        }

        $form_values = $this->getConfigFormValues();
        foreach (array_keys($form_values) as $key) {
            Configuration::updateValue($key, Tools::getValue($key));
        }
    }

    /**
     * Add the CSS & JavaScript files you want to be added on the FO.
     */
    public function hookHeader()
    {
        $this->context->controller->addJS($this->_path.'/views/js/front.js');
        $this->context->controller->addCSS($this->_path.'/views/css/front.css');
    }

    /**
     * This method is used to render the payment button,
     * Take care if the button should be displayed or not.
     */
    public function hookPayment($params)
    {
            $dolyame_products = explode(',',Configuration::get('DOLYAME_PRODUCTS'));
            $dolyame_categories = explode(',',Configuration::get('DOLYAME_CATEGORIES'));
            $dolyame_manufacturers = explode(',',Configuration::get('DOLYAME_MANUFACTURERS'));
    
            $dolyame_min_cart = (int)Configuration::get('DOLYAME_MIN_CART');
            $dolyame_max_cart = (int)Configuration::get('DOLYAME_MAX_CART');
    
            $total = $params['cart']->getOrderTotal(true, Cart::BOTH);
    
            if ($total < $dolyame_min_cart || $total > $dolyame_max_cart)
                return false;
    
            $this->smarty->assign('module_dir', $this->_path);
    
            $products = $params['cart']->getProducts(true);
    
            foreach ($products as $product)
                if ((in_array($product['id_product'],$dolyame_products))
                    || (in_array($product['id_category_default'],$dolyame_categories))
                    || (in_array($product['id_manufacturer'],$dolyame_manufacturers))
                    || (strpos($product['name'], 'сертификат') !== false) ) {
                    return false;
                }
    
            $address = new Address($params['cart']->id_address_delivery);
    
    
            if (($this->isAvailable($address->id_country) || $params['cart']->isVirtualCart()) && $total < $dolyame_max_cart) {
                $this->context->smarty->assign( 'payment_name', $this->name);
                return $this->display(__FILE__, 'payment.tpl');
            }
    
            return false;
    }

    public function isAvailable($id_country)
    {
        $validCountries = [
            177 => 1,
            52 => 1,
            45 => 1,
            118 => 1
        ];

        return  isset($validCountries[$id_country]);
    }

    /**
     * This hook is used to display the order confirmation page.
     */
//    public function hookPaymentReturn($params)
//    {
//        if ($this->active == false)
//            return;
//
//        $order = $params['objOrder'];
//
//        if ($order->getCurrentOrderState()->id != Configuration::get('PS_OS_ERROR'))
//            $this->smarty->assign('status', 'ok');
//
//        $this->smarty->assign(array(
//            'id_order' => $order->id,
//            'reference' => $order->reference,
//            'params' => $params,
//            'total' => Tools::displayPrice($params['total_to_pay'], $params['currencyObj'], false),
//        ));
//
//        return $this->display(__FILE__, 'views/templates/hook/confirmation.tpl');
//    }

    /**
     * Return payment options available for PS 1.7+
     *
     * @param array Hook parameters
     *
     * @return array|null
     */
    public function hookPaymentOptions($params)
    {
            if (!$this->active) {
                return;
            }
            if (!$this->checkCurrency($params['cart'])) {
                return;
            }
            $dolyame_products = explode(',',Configuration::get('DOLYAME_PRODUCTS'));
            $dolyame_categories = explode(',',Configuration::get('DOLYAME_CATEGORIES'));
            $dolyame_manufacturers = explode(',',Configuration::get('DOLYAME_MANUFACTURERS'));
    
            $dolyame_min_cart = (int)Configuration::get('DOLYAME_MIN_CART');
            $dolyame_max_cart = (int)Configuration::get('DOLYAME_MAX_CART');
    
            $total = $params['cart']->getOrderTotal(true, Cart::BOTH);
    
            if ($total < $dolyame_min_cart || $total > $dolyame_max_cart)
                return;
    
            $products = $params['cart']->getProducts(true);
    
            foreach ($products as $product)
                if ((in_array($product['id_product'],$dolyame_products))
                    || (in_array($product['id_category_default'],$dolyame_categories))
                    || (in_array($product['id_manufacturer'],$dolyame_manufacturers))
                    || (strpos($product['name'], 'сертификат') !== false) ) {
                    return;
                }
    
            $option = new \PrestaShop\PrestaShop\Core\Payment\PaymentOption();
            $option->setCallToActionText($this->l('Оплата Долями'))
                ->setAction($this->context->link->getModuleLink($this->name, 'redirect', array(), true));
    
            return [
                $option
            ];
    }

    public function checkCurrency($cart)
    {
        $currency_order = new Currency($cart->id_currency);
        $currencies_module = $this->getCurrency($cart->id_currency);
        if (is_array($currencies_module)) {
            foreach ($currencies_module as $currency_module) {
                if ($currency_order->id == $currency_module['id_currency']) {
                    return true;
                }
            }
        }
        return false;
    }

    public function hookActionOrderStatusPostUpdate($params) {
        $order = new Order($params['id_order']);
        if ($order->payment == $this->name) {
            $order_state = $params['newOrderStatus'];
            $cancel_order_states = explode(',',Configuration::get('DOLYAME_CANCEL_STATUSES'));
            if (in_array($order_state->id, $cancel_order_states)) {
                $client   = $this->initClient();
                $cart = new Cart($params['cart']->id);
                $getProducts = $cart->getProducts();
                $items = array();
                foreach ($getProducts as $product) {
                    $items[] = [
                        'name' => $product['name'],
                        'quantity' => $product['quantity'],
                        'price' => $product['price_wt'],
                        'sku' => $product['reference']
                    ];
                }
                $data = [
                    'amount'                  => $cart->getOrderTotal(),
                    'returned_items'          => $items,
                    'refunded_prepaid_amount' => 0,
                ];
                $response = $client->refund($params['cart']->id, $data);
            }
        }
    }

    private function initClient()
    {
        $key = _PS_MODULE_DIR_ . $this->name . DIRECTORY_SEPARATOR.'upload'.DIRECTORY_SEPARATOR.'private.key';
        $cert = _PS_MODULE_DIR_ . $this->name . DIRECTORY_SEPARATOR.'upload'.DIRECTORY_SEPARATOR.'open_api_cert.pem';
        $login = Configuration::get('DOLYAME_ACCOUNT_LOGIN');
        $pass = Configuration::get('DOLYAME_ACCOUNT_PASSWORD');

        $api = new Client($login, $pass);
        $api->setCertPath($cert);
        $api->setKeyPath($key);
        return $api;
    }
}
