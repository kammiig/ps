<?php

declare(strict_types=1);

return [
    'api_url' => env('WHMCS_API_URL', 'https://planeticsolution.com/clientarea/includes/api.php'),
    'client_area_url' => env('WHMCS_URL', env('WHMCS_CLIENT_AREA_URL', 'https://planeticsolution.com/clientarea')),
    'api_identifier' => env('WHMCS_API_IDENTIFIER', ''),
    'api_secret' => env('WHMCS_API_SECRET', ''),
    'api_access_key' => env('WHMCS_API_ACCESS_KEY', ''),
    'api_ssl_verify' => filter_var(env('WHMCS_API_SSL_VERIFY', true), FILTER_VALIDATE_BOOLEAN),
    'local_bridge_url' => env('WHMCS_LOCAL_API_BRIDGE_URL', ''),
    'local_bridge_token' => env('WHMCS_LOCAL_API_BRIDGE_TOKEN', ''),
    'payment_method' => env('WHMCS_PAYMENT_METHOD', 'stripe'),
    'domain_registrar' => env('WHMCS_DOMAIN_REGISTRAR', ''),
    'use_price_override' => filter_var(env('WHMCS_USE_PRICE_OVERRIDE', false), FILTER_VALIDATE_BOOLEAN),
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
            'whm_package' => env('WHMCS_STARTER_WHM_PACKAGE', 'planetic_starter'),
        ],
        'business-hosting' => [
            'pid' => (int) env('WHMCS_BUSINESS_HOSTING_PID', 2),
            'billing_cycle' => env('WHMCS_BUSINESS_BILLING_CYCLE', 'monthly'),
            'whm_package' => env('WHMCS_BUSINESS_WHM_PACKAGE', 'planetic_business'),
        ],
        'pro-hosting' => [
            'pid' => (int) env('WHMCS_PRO_HOSTING_PID', 3),
            'billing_cycle' => env('WHMCS_PRO_BILLING_CYCLE', 'monthly'),
            'whm_package' => env('WHMCS_PRO_WHM_PACKAGE', 'planetic_pro'),
        ],
        'agency-hosting' => [
            'pid' => (int) env('WHMCS_AGENCY_HOSTING_PID', 4),
            'billing_cycle' => env('WHMCS_AGENCY_BILLING_CYCLE', 'monthly'),
            'whm_package' => env('WHMCS_AGENCY_WHM_PACKAGE', 'planetic_agency'),
        ],
        'ecommerce-hosting' => [
            'pid' => (int) env('WHMCS_ECOMMERCE_HOSTING_PID', 4),
            'billing_cycle' => env('WHMCS_ECOMMERCE_BILLING_CYCLE', 'monthly'),
            'whm_package' => env('WHMCS_ECOMMERCE_WHM_PACKAGE', 'planetic_agency'),
        ],
        'wordpress-hosting-plan' => [
            'pid' => (int) env('WHMCS_WORDPRESS_HOSTING_PID', 3),
            'billing_cycle' => env('WHMCS_WORDPRESS_BILLING_CYCLE', 'monthly'),
            'whm_package' => env('WHMCS_WORDPRESS_WHM_PACKAGE', 'planetic_pro'),
        ],
        'reseller-hosting-plan' => [
            'pid' => (int) env('WHMCS_RESELLER_HOSTING_PID', 4),
            'billing_cycle' => env('WHMCS_RESELLER_BILLING_CYCLE', 'monthly'),
            'whm_package' => env('WHMCS_RESELLER_WHM_PACKAGE', 'planetic_agency'),
        ],
    ],
    'website_package' => [
        'pid' => (int) env('WHMCS_WEBSITE_PACKAGE_PID', 5),
        'billing_cycle' => env('WHMCS_WEBSITE_BILLING_CYCLE', 'onetime'),
        'register_domain' => filter_var(env('WHMCS_WEBSITE_REGISTER_DOMAIN', true), FILTER_VALIDATE_BOOLEAN),
        'domain_price_override' => env('WHMCS_WEBSITE_DOMAIN_PRICE_OVERRIDE', '0.00'),
        'price_override' => env('WHMCS_WEBSITE_PRICE_OVERRIDE', '199.00'),
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
