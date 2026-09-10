<?php

function getAllApiKeys() {

    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => "https://sandbox.basysiqpro.com/user/apikeys",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "GET",
        CURLOPT_HTTPHEADER => array(
            "Authorization: api_2EBVkTLDZxKhYDY6oIOZs3bzHag",
            "Content-Type: application/json"
        ),
    ));

    $response = curl_exec($curl);

    $result = json_decode($response, true);
    echo $response;

    curl_close($curl);
}

echo('HIII');

// echo(getAllApiKeys());

$newApiKey = array(
    "name" => "friendlyname",
    "type" => "api"
);

function createApiKey($newApiKey) {
    print_r($newApiKey);
    $curl = curl_init();
    $payload = json_encode($newApiKey);
    curl_setopt_array($curl, array(
        CURLOPT_URL => "https://sandbox.basysiqpro.com/user/apikey",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => array(
            "Authorization: pub_2EBVlXu0hIfXvbwwYFdelREcV5R",
            "Content-Type: application/json"
        ),
    ));
    echo("NExt: ");
    $response = curl_exec($curl);
    $result = json_decode($response, true);
    curl_close($curl);
    echo "RE: " . $response;
}

createApiKey($newApiKey);


$transaction = array(
    "type" => "sale",
    "amount" => 1112,
    "tax_amount" => 0,
    "shipping_amount" => 0,
    "currency" => "USD",
    "description" => "test transaction",
    "order_id" => "someOrderID",
    "po_number" => "somePONumber",
    "ip_address" => "4.2.2.2",
    "email_receipt" => false,
    "email_address" => "user@home.com",
    "create_vault_record" => true,
    "payment_method" => array(
        "token" => "o2l38DVc7itdhjDnP7PsoMZFx6FZkjSQ",
    ),
    "billing_address" => array(
        "first_name" => "John",
        "last_name" => "Smith",
        "company" => "Test Company",
        "address_line_1" => "123 Some St",
        "address_line_2" => "",
        "city" => "Wheaton",
        "state" => "IL",
        "postal_code" => "60187",
        "country" => "US",
        "phone" => "5555555555",
        "fax" => "5555555555",
        "email" => "help@website.com"
    ),
    "shipping_address" => array(
        "first_name" => "John",
        "last_name" => "Smith",
        "company" => "Test Company",
        "address_line_1" => "123 Some St",
        "address_line_2" => "",
        "city" => "Wheaton",
        "state" => "IL",
        "postal_code" => "60187",
        "country" => "US",
        "phone" => "5555555555",
        "fax" => "5555555555",
        "email" => "help@website.com"
    )
);

function processTransaction ($transaction) {
    $curl = curl_init();
    $payload = json_encode($transaction);
    curl_setopt_array($curl, array(
        CURLOPT_URL => "https://sandbox.basysiqpro.com/api/transaction",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => array(
            "Authorization: api_2EMHUIKiegw2u9PjIC3YX9NVE1U",
            "Content-Type: application/json"
        ),
    ));
    $response = curl_exec($curl);
    curl_close($curl);
    echo("DONE");
    echo $response;
}

processTransaction($transaction);
?>