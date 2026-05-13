<?php
// logic/patient/stripe_config.php

// Replace these with your actual Stripe Test API Keys
define('STRIPE_SECRET_KEY', 'sk_test_51TWKojHQVNEpEehPZzCmvA9wgKMKCYxcCKciDCyzBIT8arXMgjefhVfYmfXR9Q64zXFYv1SdQ0o4DPZc5U3ojzDv00jybL4NPa');
define('STRIPE_PUBLISHABLE_KEY', 'pk_test_51TWKojHQVNEpEehPoglWyeX89VBaf3yznD2ijwa8eTqjx5lOgboY8PgAM82kFUheSqlR3Ro6YXDmRfCGLOmmrXst00ny4GhwNt');
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