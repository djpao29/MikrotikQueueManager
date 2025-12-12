
<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../system/plugin/simplequeue.php';

$customers = ORM::for_table('customers')
    ->where('status', 'active')
    ->where_not_null('ip_address')
    ->find_many();

foreach ($customers as $c) {
    $api = new RouterOSAPI();
    if ($api->connect($c->router_ip, $c->router_user, $c->router_pass)) {
        sq_sync_customer_queue($api, $c->username, $c->ip_address, $c->plan_down, $c->plan_up);
        $api->disconnect();
    }
}
