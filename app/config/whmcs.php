<?php

declare(strict_types=1);

return [
    'api_url' => env('WHMCS_API_URL', 'https://planeticsolution.com/clientarea/includes/api.php'),
    'client_area_url' => env('WHMCS_URL', env('WHMCS_CLIENT_AREA_URL', 'https://planeticsolution.com/clientarea')),
    'api_identifier' => env('WHMCS_API_IDENTIFIER', ''),
    'api_secret' => env('WHMCS_API_SECRET', ''),
    'payment_method' => env('WHMCS_PAYMENT_METHOD', 'stripe'),
    'currency_id' => (int) env('WHMCS_CURRENCY_ID', 1),
    'default_billing_cycle' => env('WHMCS_DEFAULT_BILLING_CYCLE', 'monthly'),
    'nameservers' => array_values(array_filter([
        env('WHMCS_NS1', ''),
        env('WHMCS_NS2', ''),
        env('WHMCS_NS3', ''),
        env('WHMCS_NS4', ''),
        env('WHMCS_NS5', ''),
    ])),
    'hosting_products' => [
        'starter-hosting' => [
            'pid' => (int) env('WHMCS_STARTER_HOSTING_PID', 1),
            'billing_cycle' => env('WHMCS_STARTER_BILLING_CYCLE', 'monthly'),
        ],
        'business-hosting' => [
            'pid' => (int) env('WHMCS_BUSINESS_HOSTING_PID', 2),
            'billing_cycle' => env('WHMCS_BUSINESS_BILLING_CYCLE', 'monthly'),
        ],
        'wordpress-hosting-plan' => [
            'pid' => (int) env('WHMCS_WORDPRESS_HOSTING_PID', 3),
            'billing_cycle' => env('WHMCS_WORDPRESS_BILLING_CYCLE', 'monthly'),
        ],
        'reseller-hosting-plan' => [
            'pid' => (int) env('WHMCS_RESELLER_HOSTING_PID', 4),
            'billing_cycle' => env('WHMCS_RESELLER_BILLING_CYCLE', 'monthly'),
        ],
    ],
    'website_package' => [
        'pid' => (int) env('WHMCS_WEBSITE_PACKAGE_PID', 5),
        'billing_cycle' => env('WHMCS_WEBSITE_BILLING_CYCLE', 'onetime'),
        'register_domain' => filter_var(env('WHMCS_WEBSITE_REGISTER_DOMAIN', true), FILTER_VALIDATE_BOOLEAN),
        'domain_price_override' => env('WHMCS_WEBSITE_DOMAIN_PRICE_OVERRIDE', '0.00'),
    ],
    'domain_pricing' => [
        '.com' => ['price' => '12.99', 'regperiod' => 1],
        '.co.uk' => ['price' => '9.99', 'regperiod' => 1],
        '.net' => ['price' => '13.99', 'regperiod' => 1],
        '.org' => ['price' => '12.99', 'regperiod' => 1],
        '.xyz' => ['price' => '11.99', 'regperiod' => 1],
        '.online' => ['price' => '14.99', 'regperiod' => 1],
    ],
];
