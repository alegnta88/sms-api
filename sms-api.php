<?php
/*
Plugin Name: WooCommerce New Order sms Action
Description: A plugin that triggers an action when a new order is created in WooCommerce
Version: 1.0
Author: Alegnta Lolamo
*/
// use Buzz\Browser;
// use Buzz\Client\FileGetContents;

// $client = new FileGetContents(new Psr17ResponseFactory());
// $browser = new Browser($client, new Psr17RequestFactory());

// Add the action hook
add_action( 'woocommerce_new_order', 'bing_new_order_action', 10, 2 );

function bing_new_order_action( $order_id, $order ) {
    // Prepare the original SMS message
    $order_phone_number = $order->get_billing_phone();
    $order_key = $order->get_order_key();
    $subOrderKey = mb_substr($order_key, -4, 4);
    $subOrderNumber = mb_substr(strval($order_id), -4, 4);
    $verification_code = $subOrderKey.$subOrderNumber;
    $order_total_amount = $order->get_total();
    $message = 'ውድ ደንበኛችን፣ የትዕዛዝ ቁጥሮ: ' . $order_id . ' ነው። የማረጋገጫ ኮዶ: '. $verification_code .' ነው። '. 'የትዛዞ አጠቃላይ ዋጋ፡ '.$order_total_amount.' ብር ነው። ከገበሬው ስላዘዙ እናመሰግናለን። kegeberew.com | 9858';

    // Check if payment method is direct bank transfer
    if ( $order->get_payment_method() === 'bacs' ) {
        // Generate a unique token for the customer
        $unique_token = md5( uniqid( rand(), true ) );

        // Build the unique link
        $unique_link = 'https://pb365.kegeberew.com/transaction_form.php?order_id=' . $order_id . '&token=' . $unique_token;

        // Store the unique token in order meta for reference
        update_post_meta( $order_id, '_unique_token', $unique_token );

        // Append the unique link to the SMS message
        $message .= "\nእባክዎ ይህንን ሊንክ በመጫን የከፈሉበትን ባንክ ስምና ማረጋገጫ ቁጥሮን ( Transaction No.) ያስገቡ " . $unique_link;
    }

    // Send SMS with the message
    createRequest($message, $order_phone_number, $order_id);
}


function createRequest($text, $phone, $order_id){
  $base_URL = 'http://sms.purposeblacketh.com/api/general/send-sms';
  $ch = curl_init();
  $headers = array(
    "Accept: application/json",
    "Content-Type: application/json",
    "charset: utf-8"
  );
  curl_setopt($ch, CURLOPT_URL, $base_URL);
  curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
  curl_setopt($ch, CURLOPT_HEADER, 0);

  $request_body = array(
    'phone' => strval($phone),
    'text' => strval($text)
  );

  $logger = wc_get_logger();


  // curl_setopt($ch, CURLOPT_POST, true);
  $data = json_encode($request_body);

  // Set request method 
  // curl_setopt($ch, CURL_CUSTOMREQUEST, 'POST');
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
  curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
  curl_setopt($ch, CURLOPT_VERBOSE, true);

  // Timeout in seconds 
  curl_setopt($ch, CURLOPT_TIMEOUT, 60);

  $server_output = curl_exec($ch);

  $httpReturnCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

  // $logger->info(wc_print_r("The http return code is: ". strval($httpReturnCode) ." for: ". $phone. "\n", true), array('source' => 'order-created-sms-notifier-log'));
  // $logger->info(wc_print_r("Curl error no is: ". strval(curl_errno($ch). "\n")), array('source' => 'order-created-sms-notifier-log'));

  if (curl_errno($ch)){
    $error = curl_error($ch);
    $logger->error(wc_print_r( "Sms not sent for:". $phone ."Order number is: ". $order_id . " Error: ". $error ), array('source' => 'order-created-sms-notifier-log' ));
    throw new Exception(curl_error($ch));
    return; 
  }

  $decoded_output = json_decode($server_output);
  // $logger->info(wc_print_r( $server_output), array('source' => 'order-created-sms-notifier-log'));
  
  if ($httpReturnCode == 200){
    $logger->notice(wc_print_r("SMS sent for: ". $phone . " content: ". $text . " Sms server response: The decoded output is: " . $decoded_output->message, true), array('source' => 'order-created-sms-notifier-log'));

  } else {
    $logger->error(wc_print_r("SMS not sent for: ". $phone . "content: ". $text. " Sms server response: The decoded output is: ".$decoded_output, true), array('source' => 'order-created-sms-notifier-log'));
  }
  
  curl_close($ch);

}
