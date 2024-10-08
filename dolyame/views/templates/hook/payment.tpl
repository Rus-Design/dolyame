{*
* 2007-2023 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2023 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*}

<div class="row">
	<div class="col-xs-12">
		<p class="payment_module" id="dolyame_payment_button">
				<a data-payment="dolyame" class="dolyame" href="{$link->getModuleLink('dolyame', 'redirect', array(), true)|escape:'htmlall':'UTF-8'}" title="{l s='Оплата Долями' mod='dolyame'}">
					<img src="{$module_dir|escape:'htmlall':'UTF-8'}/logo.png" alt="{l s='Оплата Долями' mod='dolyame'}" width="32" height="32" />
					{l s='Оплата Долями' mod='dolyame'}
				</a>
		</p>
	</div>
</div>
{*<p class="payment_module">*}
{*	<a class="dolyame" data-payment="dolyame" href="{$link->getModuleLink('dolyame', 'redirect', array(), true)|escape:'htmlall':'UTF-8'}" title="{l s='Оплачу долями' mod='dolyame'}">*}
{*		{l s='Оплачу долями' mod='dolyame'}*}
{*	</a>*}

{*</p>*}
