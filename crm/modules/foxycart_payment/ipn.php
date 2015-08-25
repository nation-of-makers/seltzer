<?php
/*
    Copyright 2009-2014 Edward L. Platt <ed@elplatt.com>
    
    This file is part of the Seltzer CRM Project
    ipn.php - Amazon Payment module IPN interface

    Seltzer is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    any later version.

    Seltzer is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with Seltzer.  If not, see <http://www.gnu.org/licenses/>.
*/
// We must be authenticated to insert into the database
// Save path of directory containing index.php
$crm_root = realpath(dirname(__FILE__) . '/../..');
// Bootstrap the crm
require_once('../../include/crm.inc.php');
$_SESSION['userId'] = 1;

//Script by David Hollander, www.foxy-shop.com
//version 1.0, 7/9/2012
 
//Set Globals and Get Settings
$apikey = "lVH6x8brBgMAWAC4BSvbcuQ3wSMuJnwWe8SB6XWx9z2V4uPvhaAjzgcZYTnD";
 
//-----------------------------------------------------
// TRANSACTION DATAFEED
//-----------------------------------------------------
if (isset($_POST["FoxyData"])) {
 
 
    //DECRYPT (required)
    //-----------------------------------------------------
    $FoxyData_decrypted = foxycart_decrypt($_POST["FoxyData"]);
    $xml = simplexml_load_string($FoxyData_decrypted, NULL, LIBXML_NOCDATA);
    $stuff = mysql_real_escape_string($FoxyData_decrypted);
    $sql = "
        INSERT INTO `xml_log`
        (
            `xml_data`
        )
        VALUES
        (
            ' $stuff '
        )
    ";
    $res = mysql_query($sql); 
    //For Each Transaction
    foreach($xml->transactions->transaction as $transaction) {
 
        //This variable will tell us whether this is a multi-ship store or not
        $is_multiship = 0;
 
        //Get FoxyCart Transaction Information
        //Simply setting lots of helpful data to PHP variables so you can access it easily
        //If you need to access more variables, you can see some sample XML here: http://wiki.foxycart.com/v/1.1/transaction_xml_datafeed
        $status =  (string)$transaction->status;
        $transaction_id = (string)$transaction->id;
        $transaction_date = (string)$transaction->transaction_date;
        $customer_ip = (string)$transaction->customer_ip;
        $customer_id = (string)$transaction->customer_id;
        $customer_first_name = (string)$transaction->customer_first_name;
        $customer_last_name = (string)$transaction->customer_last_name;
        $customer_company = (string)$transaction->customer_company;
        $customer_email = (string)$transaction->customer_email;
        $customer_password = (string)$transaction->customer_password;
        $customer_address1 = (string)$transaction->customer_address1;
        $customer_address2 = (string)$transaction->customer_address2;
        $customer_city = (string)$transaction->customer_city;
        $customer_state = (string)$transaction->customer_state;
        $customer_postal_code = (string)$transaction->customer_postal_code;
        $customer_country = (string)$transaction->customer_country;
        $customer_phone = (string)$transaction->customer_phone;
        
        $custom_fields = array();
        $receipt_url = (string)$transaction->receipt_url;
        if (!empty($transaction->custom_fields)) {
            foreach($transaction->custom_fields->custom_field as $custom_field) {
                $custom_fields[(string)$custom_field->custom_field_name] = (string)$custom_field->custom_field_value;
            }
        }
 
        //For Each Transaction Detail
        foreach($transaction->transaction_details->transaction_detail as $transaction_detail) {
            $product_name = (string)$transaction_detail->product_name;
            $product_code = (string)$transaction_detail->product_code;
            $product_quantity = (int)$transaction_detail->product_quantity;
            $product_price = (double)$transaction_detail->product_price;
            $product_shipto = (double)$transaction_detail->shipto;
            $category_code = (string)$transaction_detail->category_code;
            $product_delivery_type = (string)$transaction_detail->product_delivery_type;
            $sub_token_url = (string)$transaction_detail->sub_token_url;
            $subscription_frequency = (string)$transaction_detail->subscription_frequency;
            $subscription_startdate = (string)$transaction_detail->subscription_startdate;
            $subscription_nextdate = (string)$transaction_detail->subscription_nextdate;
            $subscription_enddate = (string)$transaction_detail->subscription_enddate;
 
            //These are the options for the product
            $transaction_detail_options = array();
            foreach($transaction_detail->transaction_detail_options->transaction_detail_option as $transaction_detail_option) {
                $product_option_name = $transaction_detail_option->product_option_name;
                $product_option_value = (string)$transaction_detail_option->product_option_value;
                $price_mod = (double)$transaction_detail_option->price_mod;
                $weight_mod = (double)$transaction_detail_option->weight_mod;
 
            }

        foreach($transaction->discounts->discount as $discount) { //FIXME: make this handle edge cases that i don't really want to think about right now
            $discount_amount = (double)$discount->amount;
        } 


$notes = "";

if ((!empty($status)) && ($status != "approved")) {
    $notes .= "transaction is $status. ";
    $cents = 0;
//    $transaction_id .= "-$status";
} else {

    $payment_opts = array(
        'filter' => array('confirmation' => $transaction_id)
    );
    // Check if the payment already exists
    $data = crm_get_data('payment', $payment_opts);
    if (count($data) > 0) {
        // Update transactions that have already been imported
        if (count($data) > 1) {
            // I hope this doesn't happen. Bail out now.
            die("Tried to update more than one payment");
        } else {
            foreach ($data as $payment) {
                // Get the CID in case Terry updated it
                $cid = $payment['credit_cid'];
                $notes = $payment['notes'];
                $notes = preg_replace('/transaction is \w+\./', $status, $notes);
                payment_delete($payment['pmtid']);
            }
        }
        //die("foxy"); //this doesn't really give us anything, so tell FC to shut up now.
    }

    // Parse the data and insert into the database
    // 'USD 12.34' goes to ['USD', '1234']
    $parts = explode(' ', $product_price);
    if(isset($debug)) {
        file_put_contents($debug, print_r($parts, true) . "\n", FILE_APPEND);
    }

    if ($product_quantity == 11) {
        $notes .= "Free month applied.";
        $product_quantity++;
    }

    if ($product_quantity == 12) {
        $notes .= "11 months charged.";
    }

    if (isset($discount_amount)) {
        $cents = ($product_price * $product_quantity + $discount_amount) * 100;
    } else {
        $cents = $product_price * 100 * $product_quantity;
    }

}

// Determine cid
$fullname = "$customer_first_name $customer_last_name";
$cid = '';
if (empty($cid)) {
       $esc_email = mysql_real_escape_string($customer_email); 
        $sql = "
        SELECT `cid` FROM `contact`
        WHERE `email` = '" . $customer_email . "'";
    $res = mysql_query($sql);
    $row = mysql_fetch_assoc($res);
    $cid = $row['cid'];
}

if (empty($cid)) {
    if (isset($customer_id)) {
        $notes .= "customer $customer_id";
    } else {
        $notes .= "$fullname: Wrong email address ";
        if ($fullname == " ") {
            $notes .= "and no name in metadata. ";
        }
    }
}

if ($product_code == "Donation" ){
  $cid = NULL;
  $notes = "$fullname Donation. No dues credit. ";
}

$description = "$product_name <a href='$receipt_url'>(receipt)</a>";

$payment = array(
    'date' => date('Y-m-d', strtotime( $transaction_date))
    , 'credit_cid' => $cid
    , 'debit_cid' => 0
    , 'code' => 'USD'
    , 'value' => (string)$cents
    , 'description' => $description
    , 'method' => 'FoxyCart'
    , 'confirmation' => $transaction_id
    , 'notes' => $notes 
);
$payment = payment_save($payment);
        }
 
        //If you have custom code to run for each order, put it here:
 
    }
 
    //All Done!
    die("foxy"); //Acknowledge notification received and logged
 
//-----------------------------------------------------
// NO POST CONTENT SENT
//-----------------------------------------------------
} else {
    die('No Content Received From Datafeed');
}
 
 
 
 
//Decrypt Data From Source
function foxycart_decrypt($src) {
        global $apikey;
    return rc4crypt::decrypt($apikey,urldecode($src));
}
 
 
// ======================================================================================
// RC4 ENCRYPTION CLASS
// Do not modify.
// ======================================================================================
/**
 * RC4Crypt 3.2
 *
 * RC4Crypt is a petite library that allows you to use RC4
 * encryption easily in PHP. It's OO and can produce outputs
 * in binary and hex.
 *
 * (C) Copyright 2006 Mukul Sabharwal [http://mjsabby.com]
 *     All Rights Reserved
 *
 * @link http://rc4crypt.devhome.org
 * @author Mukul Sabharwal <mjsabby@gmail.com>
 * @version $Id: class.rc4crypt.php,v 3.2 2006/03/10 05:47:24 mukul Exp $
 * @copyright Copyright &copy; 2006 Mukul Sabharwal
 * @license http://www.gnu.org/copyleft/gpl.html
 * @package RC4Crypt
 */
class rc4crypt {
    /**
     * The symmetric encryption function
     *
     * @param string $pwd Key to encrypt with (can be binary of hex)
     * @param string $data Content to be encrypted
     * @param bool $ispwdHex Key passed is in hexadecimal or not
     * @access public
     * @return string
     */
    public static function encrypt ($pwd, $data, $ispwdHex = 0) {
        if ($ispwdHex) $pwd = @pack('H*', $pwd); // valid input, please!
        $key[] = '';
        $box[] = '';
        $cipher = '';
        $pwd_length = strlen($pwd);
        $data_length = strlen($data);
        for ($i = 0; $i < 256; $i++) {
            $key[$i] = ord($pwd[$i % $pwd_length]);
            $box[$i] = $i;
        }
        for ($j = $i = 0; $i < 256; $i++) {
            $j = ($j + $box[$i] + $key[$i]) % 256;
            $tmp = $box[$i];
            $box[$i] = $box[$j];
            $box[$j] = $tmp;
        }
        for ($a = $j = $i = 0; $i < $data_length; $i++) {
            $a = ($a + 1) % 256;
            $j = ($j + $box[$a]) % 256;
            $tmp = $box[$a];
            $box[$a] = $box[$j];
            $box[$j] = $tmp;
            $k = $box[(($box[$a] + $box[$j]) % 256)];
            $cipher .= chr(ord($data[$i]) ^ $k);
        }
        return $cipher;
    }
    /**
     * Decryption, recall encryption
     *
     * @param string $pwd Key to decrypt with (can be binary of hex)
     * @param string $data Content to be decrypted
     * @param bool $ispwdHex Key passed is in hexadecimal or not
     * @access public
     * @return string
     */
    public static function decrypt ($pwd, $data, $ispwdHex = 0) {
        return rc4crypt::encrypt($pwd, $data, $ispwdHex);
    }
}

session_destroy();
