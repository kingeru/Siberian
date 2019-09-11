<?php
/**
 *
 * Schema definition for 'payment_stripe_payment_method'
 *
 * Last update: 2019-09-11
 *
 */
$schemas = (!isset($schemas)) ? [] : $schemas;
$schemas['payment_stripe_payment_method'] = [
    'stripe_payment_method_id' => [
        'type' => 'int(11) unsigned',
        'auto_increment' => true,
        'primary' => true,
    ],
    'stripe_customer_id' => [
        'type' => 'int(11) unsigned',
        'index' => [
            'key_name' => 'stripe_customer_ids',
            'index_type' => 'BTREE',
            'is_null' => false,
            'is_unique' => false,
        ],
    ],
    'token' => [
        'type' => 'varchar(128)',
        'charset' => 'utf-8',
        'collation' => 'utf8_unicode_ci',
    ],
    'is_removed' => [
        'type' => 'tinyint(1)',
        'default' => '0',
    ],
    'created_at' => [
        'type' => 'datetime',
    ],
    'updated_at' => [
        'type' => 'datetime',
    ],
];
