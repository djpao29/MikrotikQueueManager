
<?php
function sq_norm_rate(string $v): string {
    return trim($v) === '' ? '0' : trim($v);
}

function sq_upsert_simple_queue($api, string $queueName, string $targetIp, string $down, string $up, string $comment = ''): array {
    $targetCidr = $targetIp . '/32';
    $maxLimit = sq_norm_rate($down) . '/' . sq_norm_rate($up);

    $api->write('/queue/simple/print', false);
    $api->write('?name=' . $queueName, true);
    $rows = $api->read();

    if (is_array($rows) && isset($rows[0]['.id'])) {
        $api->write('/queue/simple/set', false);
        $api->write('=.id=' . $rows[0]['.id'], false);
        $api->write('=target=' . $targetCidr, false);
        $api->write('=max-limit=' . $maxLimit, false);
        if ($comment) $api->write('=comment=' . $comment, false);
        $api->write('', true);
        $api->read();
        return ['ok'=>true,'action'=>'updated'];
    }

    $api->write('/queue/simple/add', false);
    $api->write('=name=' . $queueName, false);
    $api->write('=target=' . $targetCidr, false);
    $api->write('=max-limit=' . $maxLimit, false);
    if ($comment) $api->write('=comment=' . $comment, false);
    $api->write('', true);
    $api->read();
    return ['ok'=>true,'action'=>'created'];
}

function sq_sync_customer_queue($api, string $username, string $ip, string $down, string $up): array {
    return sq_upsert_simple_queue(
        $api,
        'PNB-' . $username,
        $ip,
        $down,
        $up,
        'PHPNuxBill SimpleQueue'
    );
}
