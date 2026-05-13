<?php
// logic/patient/stripe_config.php

// Load keys from local file if exists, otherwise use placeholders
if (file_exists(__DIR__ . '/stripe_keys.php')) {
    require_once __DIR__ . '/stripe_keys.php';
} else {
    define('STRIPE_SECRET_KEY', 'sk_test_placeholder');
    define('STRIPE_PUBLISHABLE_KEY', 'pk_test_placeholder');
}

define('STRIPE_CURRENCY', 'usd');
define('STRIPE_FEE_CENTS', 1000); // $10.00 USD
define('STRIPE_FEE_LABEL', '$10.00');

// Helper function to call Stripe API using cURL
function stripe_api_request($method, $endpoint, $data = null)
{
    $url = "https://api.stripe.com/v1/" . $endpoint;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, STRIPE_SECRET_KEY . ":");

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        }
    }

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_errno($ch)) {
        throw new Exception(curl_error($ch));
    }

    curl_close($ch);

    $result = json_decode($response, true);
    if ($http_code >= 400) {
        $error_msg = $result['error']['message'] ?? 'Unknown Stripe API Error';
        throw new Exception($error_msg);
    }

    return $result;
}
?>