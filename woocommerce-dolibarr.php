<?php
/**
 * Plugin Name: Woocommerce-Dolibarr
 * Plugin URI:
 * Description:
 * Version:
 * Author: Cédric Salvador
 * Author URI:
 * License: GPL3
 */

/* Copyright (C) 2013 Cédric Salvador  <csalvador@gpcsolutions.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

require_once '/var/www/wp-content/plugins/woocommerce/classes/class-wc-cart.php';

 if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
    if ( ! class_exists( 'WooCommerceDolibarr' ) ) {
        class WooCommerceDolibarr {
            public function __construct() {
                // called only after woocommerce has finished loading
                add_action( 'woocommerce_checkout_process', array( &$this, 'dolibarr_create_order' ) );

                // take care of anything else that needs to be done immediately upon plugin instantiation, here in the constructor
            }

            /**
             *  Hooks on process_checkout()
             *  While the order is processed, use the data to create a Dolibarr order via webservice
             */
            public function dolibarr_create_order() {
                global $woocommerce;
                require_once '/var/www/wp-content/plugins/woocommerce-dolibarr/nusoap/lib/nusoap.php';		// Include SOAP
                //Might want a conf file for this
                $WS_DOL_URL = 'http://192.168.56.1/webservices/server_order.php';	// If not a page, should end with /
                $ns='http://www.dolibarr.org/ns/';

                // Set the WebService URL
                $soapclient = new nusoap_client($WS_DOL_URL);
                if ($soapclient)
                {
                    $soapclient->soap_defencoding='UTF-8';
                    $soapclient->decodeUTF8(false);
                }

                // Call the WebService method and store its result in $result.
                //Might want a conf file for this
                $authentication=array(
                    'dolibarrkey'=>'5f5097ccee54436b831207f953428567',
                    'sourceapplication'=>'DEMO',
                    'login'=>'web',
                    'password'=>'web',
                    'entity'=>'');

                $order = array();
                //fill this array with all data required to create an order in Dolibarr
                $order['thirdparty_id'] = '1'; //we'll need to get that from WooCommerce and make sure it's the same in Dolibarr
                //$order['ref_ext']; Bullshit?
                $order['date'] = time();
                //$order['date_due'] = ; Needed?
                //$order['note_private'] = ; Needed?
                // $order['note_public'] = ; Needed?
                $order['status'] = 1;
                //$order['facturee'] = ; Needed?
                //$order['project_id'] = ; Needed?
                //$order['cond_reglement_id'] = ; Needed?
                //$order['demand_reason_id'] = ; Needed?
                $order['lines'] = array();
                //go through the product list and fill this array. Or just cheat, for now
                $_tax  = new WC_Tax(); //use this object to get the tax rates
                foreach($woocommerce->cart->cart_contents as $product) {
                    $line = array();
                    $line['type'] = get_post_meta($product['product_id'], 'type', 1);//    //How do we get this?
                    $line['desc'] = $product['data']->post->post_content;
                    $line['product_id'] = get_post_meta($product['product_id'], 'dolibarr_id', 1);
                    $line['vat_rate'] = $_tax->get_rates($product['data']->get_tax_class())[1]['rate'];
                    $line['qty'] = $product['quantity'];
                    $line['price'] = $product['data']->get_price();
                    $line['unitprice'] = $product['data']->get_price();
                    $line['total_net'] = $product['data']->get_price_excluding_tax($line['qty']);
                    $line['total'] = $product['data']->get_price_including_tax($line['qty']);
                    $line['total_vat'] = $line['total'] - $line['total_net'];
                    $order['lines'][] = $line;
                }

                //We're all set. Activate warp drive. There's a chance we teleport into a star and die a horrible death, but it's ok.
                $parameters = array($authentication, $order);
                $soapclient->call('createOrder',$parameters,$ns,'');
                // OK this seems to work, TODO put it in a warm, comfy plugin
            }
        }
        $GLOBALS['woocommerce-dolibarr'] = new WooCommerceDolibarr();
    }
}