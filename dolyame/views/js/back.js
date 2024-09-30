/**
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
*
* Don't forget to prefix your containers with your own identifier
* to avoid any conflicts with others containers.
*/
$(document).ready(function () {
    var $input = $("#autocomplete_product, #autocomplate_product_down");
    $input.autocomplete(
        'ajax-tab.php' + '?rand=' + new Date().getTime(),
        {
            minChars: 3,
            max: 100,
            width: 500,
            selectFirst: false,
            scroll: false,
            dataType: "json",
            formatItem: function (data, i, max, value, term) {
                return value;
            },
            parse: function (data) {
                var mytab = [];
                for (var i = 0; i < data.products.length; i++)
                    mytab[mytab.length] = {
                        data: data.products[i],
                        value: '<img src="' + data.products[i].image_link + '">' + data.products[i].name
                    };
                return mytab;
            },
            extraParams: {
                controller: 'AdminVwbStockManager',
                token: currentToken,
                action: 'searchProducts',
                product_search: $input.val()
            }
        })
        .result(function (event, data, formatted) {
            var html_product = '';

            html_product += '<div class="form-control-static"><button type="button" class="btn btn-default delSelectionProduct" data-id="'+data.id_product+'"><i class="icon-remove text-danger"></i><input type="hidden" name="id_selection_products[]" value="'+data.id_product+'"></button>&nbsp;'+data.name+'</div>'

            $('#selectionProducts').append(html_product);
        });
});

$(document).on('click', '.delSelectionProduct', function () {
    $(this).closest('div').remove();
});