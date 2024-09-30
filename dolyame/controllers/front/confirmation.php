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

use Dolyame\Payment\Client;

class DolyameConfirmationModuleFrontController extends ModuleFrontController
{

    public function initContent()
    {
        parent::initContent();
        $cart_id = Tools::getValue('cart_id');
        $secure_key = Tools::getValue('secure_key');
        $cart = new Cart((int) $cart_id);
        $customer = new Customer((int) $cart->id_customer);
        $module_name = $this->module->displayName;
        $payment_status = Configuration::get('PS_OS_PAYMENT');
        $currency_id = (int) Context::getContext()->currency->id;
        $message = null;
        $data = $this->prepareData($cart);
        $order_id = Order::getOrderByCartId((int) $cart->id);
        $module_id = $this->module->id;
        $client   = $this->initClient();
        $response = $client->commit($cart_id, $data);
        $this->module->validateOrder($cart_id, $payment_status, $cart->getOrderTotal(), $module_name, $message, array(), $currency_id, false, $secure_key);
        Tools::redirect('index.php?controller=order-confirmation&id_cart=' . $cart_id . '&id_module=' . $module_id . '&id_order=' . $order_id . '&key=' . $secure_key);
    }

    private function initClient()
    {
        $key = _PS_MODULE_DIR_ . $this->module->name . DIRECTORY_SEPARATOR.'upload'.DIRECTORY_SEPARATOR.'private.key';
        $cert = _PS_MODULE_DIR_ . $this->module->name . DIRECTORY_SEPARATOR.'upload'.DIRECTORY_SEPARATOR.'open_api_cert.pem';
        $login = Configuration::get('DOLYAME_ACCOUNT_LOGIN');
        $pass = Configuration::get('DOLYAME_ACCOUNT_PASSWORD');

        $api = new Client($login, $pass);
        $api->setCertPath($cert);
        $api->setKeyPath($key);
        return $api;
    }

    private function prepareData($cart)
    {
        $carrier = new Carrier((int)($cart->id_carrier), $cart->id_lang);
        $carriername = $carrier->name;
        $total_shipping = $cart->getTotalShippingCost();
        $delivery_items = array();
        $delivery_items[] = array(
            "name" => 'Доставка ( ' . $carriername . ' ):',
            "price" => $total_shipping,
            "quantity" => 1,
        );
        $cart_rules = $cart->getCartRules();
        $value_discount = 0;
        foreach ($cart_rules as $cart_rule)
            $value_discount = $cart_rule['value_real'];

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
            'amount'         => $cart->getOrderTotal(),
            'items'          => array_merge($items, $delivery_items),
            'prepaid_amount' => $value_discount,
        ];
        return $data;
    }

}
