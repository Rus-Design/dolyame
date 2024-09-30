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

class DolyameRedirectModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();
        if ($id_cart = Tools::getValue('id_cart')) {
            $cart = new Cart($id_cart);
            if (!Validate::isLoadedObject($cart)) {
                $cart = $this->context->cart;
            }
        }else {
            $cart = $this->context->cart;
        }

        $data = $this->prepareData($cart);
        
        $redirectUrl = $this->createPaymentLink($data);
        Tools::redirectLink($redirectUrl);
    }

    private function prepareData($cart)
    {
        $address = new Address($cart->id_address_delivery);
        $orderId = Order::getOrderByCartId($cart->id);
        $carrier = new Carrier((int)($cart->id_carrier), $cart->id_lang);
        $carriername = $carrier->name;
        $total_shipping = $cart->getTotalShippingCost();
        $delivery_items = array();
        $delivery_items[] = array(
            "name" => 'Доставка ( ' . $carriername . ' ):',
            "price" => $total_shipping,
            "quantity" => 1,
        );
        $getProducts = $cart->getProducts();
        $cart_rules = $cart->getCartRules();
        $value_discount = 0;
        foreach ($cart_rules as $cart_rule)
            $value_discount = $cart_rule['value_real'];
            
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
            'order'            => [
                'id'             => strval($cart->id),
                'amount'         => $cart->getOrderTotal(),
                'prepaid_amount' => $value_discount,
                'items'          => array_merge($items, $delivery_items),
            ],
            'client_info'      => [
                'first_name' => $this->context->customer->firstname,
                'last_name'  => $this->context->customer->lastname,
                'phone'      => $address->phone_mobile,
                'email'      => $this->context->customer->email,
            ],
            'notification_url' => $this->context->link->getModuleLink('dolyame', 'notification', ['order_id' => $orderId, 'cart_id'=>$cart->id, 'secure_key'=>$cart->secure_key], true),
            'fail_url'         => $this->context->link->getPageLink('order', null, null, 'step=3'),
            'success_url'      => $this->context->link->getModuleLink('dolyame', 'confirmation', ['order_id' => $orderId, 'cart_id'=>$cart->id, 'secure_key'=>$cart->secure_key], true),
        ];

        return $data;
    }

    private function createPaymentLink($data)
    {
        $client   = $this->initClient();
        $response = $client->create($data);
        return $response['link'];
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

}
