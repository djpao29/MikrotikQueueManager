
<?php
include_once 'system/plugin/simplequeue.php';

sq_sync_customer_queue(
    $api,
    $customer->username,
    $customer->ip_address,
    $plan->download,
    $plan->upload
);
