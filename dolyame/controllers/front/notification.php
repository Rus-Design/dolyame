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

class DolyameNotificationModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();
        $id_order = Tools::getValue('order_id');

        $info = $this->getTransactionInfo($id_order);
        $order = new Order($id_order);
        if (
            $info['status'] === 'waiting_for_commit'
            || $info['status'] === 'wait_for_commit'
        ) {
            $cart_id = Tools::getValue('cart_id');
            $secure_key = Tools::getValue('secure_key');
            $cart = new Cart((int) $cart_id);

            $data = $this->prepareData($cart);

            $client = $this->initClient();
            $client->commit($order->id, $data);

            $orderHistory = $order->getHistory($this->context->language->id);
            if (false === in_array(OrderState::STATE_TINKOFF_DOLYAME_PAID, array_column($orderHistory, 'id_order_state'))) {
                $history = new OrderHistory();
                $history->id_order = $order->id;
                $history->changeIdOrderState(OrderState::STATE_TINKOFF_DOLYAME_PAID, $order);
                $history->add();
            }
        }
        if ($info['status'] !== 'committed') {
            exit();
        }

    }

    public function initClient()
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

    public function getTransactionInfo($id_order)
    {
        $client = $this->initClient();
        $result = $client->info($id_order);
        return $result;
    }

    public function prepareData($cart)
    {
        $getProducts = $cart->getProducts();
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

        $items = array();
        foreach ($getProducts as $product) {
            $items[] = [
                'name' => $product['name'],
                'quantity' => $product['quantity'],
                'price' => $product['price_wt'],
                'sku' => $product['reference'],
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
