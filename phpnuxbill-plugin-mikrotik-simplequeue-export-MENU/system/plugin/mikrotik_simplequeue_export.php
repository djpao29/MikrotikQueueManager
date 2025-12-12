<?php
/**
 * Plugin: MikroTik Simple Queue Export (IP/32) for PHPNuxBill
 *
 * ✅ Follows PHPNuxBill plugin rules:
 * - All functions are prefixed with filename: mikrotik_simplequeue_export
 * - Registers menu via register_menu()
 * - Uses $ui, _admin(), Admin::_info(), etc.
 *
 * Install:
 * - Copy this file to: /system/plugin/mikrotik_simplequeue_export.php
 * - Copy tpl to:       /system/plugin/ui/mikrotik_simplequeue_export.tpl
 */

// Register menu (Admin)
if (function_exists('register_menu')) {
    register_menu(
        "Simple Queue Export",          // name
        true,                           // admin menu
        "mikrotik_simplequeue_export",  // function to run
        "NETWORK",                      // position (change if you want)
        "ion-shuffle"                   // icon
    );
}

/**
 * Main menu handler
 */
function mikrotik_simplequeue_export()
{
    global $ui;

    _admin();
    $ui->assign('_title', 'MikroTik Simple Queue Export');
    $ui->assign('_system_menu', 'network');

    $admin = Admin::_info();
    $ui->assign('_admin', $admin);

    // Load routers for dropdown (adjust table/fields if needed)
    $routers = [];
    if (class_exists('ORM')) {
        try {
            $routers = ORM::for_table('tbl_routers')->find_array();
        } catch (Exception $e) {
            try { $routers = ORM::for_table('routers')->find_array(); } catch (Exception $e2) {}
        }
    }
    $ui->assign('routers', $routers);

    $result = null;
    $error = null;

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_export'])) {
        $router_id = _post('router_id');
        $mode      = _post('mode', 'maxlimit'); // maxlimit | pcq_auto | pcq_plan_fields
        $dry_run   = isset($_POST['dry_run']) ? true : false;

        $out = mikrotik_simplequeue_export_run($router_id, $mode, $dry_run);
        if (isset($out['error']) && $out['error']) $error = $out['error'];
        $result = $out;
    }

    $ui->assign('error', $error);
    $ui->assign('result', $result);

    // tpl lives in /system/plugin/ui/
    $ui->display('mikrotik_simplequeue_export.tpl');
}

/**
 * Export logic
 */
function mikrotik_simplequeue_export_run($routerId, $mode = 'maxlimit', $dryRun = false)
{
    $report = [
        'mode' => $mode,
        'dry_run' => $dryRun,
        'total_customers' => 0,
        'synced' => [],
        'failed' => [],
    ];

    if (!class_exists('ORM')) {
        return ['error' => 'ORM not found. Run this inside PHPNuxBill.', 'report' => $report];
    }

    if (!class_exists('RouterOSAPI')) {
        return ['error' => 'RouterOSAPI not found. Make sure MikroTik API library exists in your PHPNuxBill.', 'report' => $report];
    }

    // Router fetch
    $router = null;
    try { $router = ORM::for_table('tbl_routers')->find_one($routerId); }
    catch (Exception $e) {
        try { $router = ORM::for_table('routers')->find_one($routerId); } catch (Exception $e2) {}
    }
    if (!$router) {
        return ['error' => 'Router not found. Check router table in this plugin.', 'report' => $report];
    }

    // Guess router fields (edit if your fields differ)
    $rHost = $router->ip_address ?? ($router->ip ?? ($router->router_ip ?? ''));
    $rUser = $router->username   ?? ($router->user ?? ($router->router_user ?? ''));
    $rPass = $router->password   ?? ($router->pass ?? ($router->router_pass ?? ''));
    $rPort = (int)($router->port ?? 8728);

    if (!$rHost || !$rUser) {
        return ['error' => 'Router fields missing. Edit router field mapping in plugin.', 'report' => $report];
    }

    $report['router'] = ['host'=>$rHost,'user'=>$rUser,'port'=>$rPort];

    $api = new RouterOSAPI();
    $api->port = $rPort;

    if (!$api->connect($rHost, $rUser, $rPass)) {
        return ['error' => 'Cannot connect to MikroTik. Check credentials and reachability.', 'report' => $report];
    }

    // Customers with static IP
    $customers = [];
    try {
        $customers = ORM::for_table('tbl_customers')
            ->select('tbl_customers.*')
            ->select('tbl_plans.download', 'plan_download')
            ->select('tbl_plans.upload', 'plan_upload')
            ->select('tbl_plans.pcq_down_type', 'pcq_down_type')
            ->select('tbl_plans.pcq_up_type', 'pcq_up_type')
            ->left_outer_join('tbl_plans', ['tbl_customers.plan_id', '=', 'tbl_plans.id'])
            ->where('tbl_customers.status', 'active')
            ->where_not_null('tbl_customers.ip_address')
            ->where_not_equal('tbl_customers.ip_address', '')
            ->find_array();
    } catch (Exception $e) {
        try {
            $customers = ORM::for_table('tbl_customers')
                ->where('status', 'active')
                ->where_not_null('ip_address')
                ->where_not_equal('ip_address', '')
                ->find_array();
        } catch (Exception $e2) {
            $api->disconnect();
            return ['error' => 'Cannot query customers. Edit customer table/fields in plugin.', 'report' => $report];
        }
    }

    $report['total_customers'] = count($customers);

    foreach ($customers as $c) {
        $username = $c['username'] ?? ($c['user'] ?? '');
        $ip       = $c['ip_address'] ?? ($c['ip'] ?? '');
        $down     = $c['plan_download'] ?? ($c['download'] ?? '');
        $up       = $c['plan_upload'] ?? ($c['upload'] ?? '');

        if (!$username || !$ip) continue;

        $queueName = 'PNB-' . $username;
        $comment   = 'PHPNuxBill SQ Export | user=' . $username;

        $queueTypes = null;

        if ($mode === 'pcq_auto') {
            $pcqDown = mikrotik_simplequeue_export_pcq_map_down($down);
            $pcqUp   = mikrotik_simplequeue_export_pcq_map_up($up);
            if ($pcqDown && $pcqUp) {
                if (mikrotik_simplequeue_export_mt_queue_type_exists($api, $pcqDown) &&
                    mikrotik_simplequeue_export_mt_queue_type_exists($api, $pcqUp)) {
                    $queueTypes = $pcqDown . '/' . $pcqUp;
                }
            }
        } elseif ($mode === 'pcq_plan_fields') {
            $pcqDown = $c['pcq_down_type'] ?? '';
            $pcqUp   = $c['pcq_up_type'] ?? '';
            if ($pcqDown && $pcqUp) {
                if (mikrotik_simplequeue_export_mt_queue_type_exists($api, $pcqDown) &&
                    mikrotik_simplequeue_export_mt_queue_type_exists($api, $pcqUp)) {
                    $queueTypes = $pcqDown . '/' . $pcqUp;
                }
            }
        }

        if ($dryRun) {
            $report['synced'][] = [
                'username'=>$username,'ip'=>$ip,'queue'=>$queueName,
                'action'=>'dry-run','target'=>$ip.'/32',
                'maxlimit'=>mikrotik_simplequeue_export_norm($down).'/'.mikrotik_simplequeue_export_norm($up),
                'queue_types'=>$queueTypes ?: ''
            ];
            continue;
        }

        try {
            $res = mikrotik_simplequeue_export_upsert_simple_queue($api, $queueName, $ip, $down, $up, $comment, $queueTypes);
            $report['synced'][] = array_merge(['username'=>$username,'ip'=>$ip,'queue'=>$queueName], $res);
        } catch (Exception $ex) {
            $report['failed'][] = ['username'=>$username,'ip'=>$ip,'error'=>$ex->getMessage()];
        }
    }

    $api->disconnect();

    return ['error' => null, 'report' => $report];
}

/** ===== Helpers (prefixed) ===== */
function mikrotik_simplequeue_export_norm($v){
    $v = trim((string)$v);
    return $v === '' ? '0' : $v;
}

function mikrotik_simplequeue_export_mt_queue_type_exists($api, $typeName){
    $api->write('/queue/type/print', false);
    $api->write('?name=' . $typeName, true);
    $r = $api->read();
    return is_array($r) && count($r) > 0;
}

function mikrotik_simplequeue_export_upsert_simple_queue($api, $queueName, $ip, $down, $up, $comment='', $queueTypes=null){
    $target = trim($ip) . '/32';
    $maxLimit = mikrotik_simplequeue_export_norm($down) . '/' . mikrotik_simplequeue_export_norm($up);

    $api->write('/queue/simple/print', false);
    $api->write('?name=' . $queueName, true);
    $rows = $api->read();

    $isUpdate = (is_array($rows) && count($rows) > 0 && isset($rows[0]['.id']));
    if ($isUpdate){
        $id = $rows[0]['.id'];
        $api->write('/queue/simple/set', false);
        $api->write('=.id=' . $id, false);
    } else {
        $api->write('/queue/simple/add', false);
        $api->write('=name=' . $queueName, false);
    }

    $api->write('=target=' . $target, false);
    $api->write('=max-limit=' . $maxLimit, false);
    if ($queueTypes) $api->write('=queue=' . $queueTypes, false);
    if ($comment !== '') $api->write('=comment=' . $comment, false);

    $api->write('', true);
    $api->read();

    return [
        'action' => $isUpdate ? 'updated' : 'created',
        'target' => $target,
        'maxlimit' => $maxLimit,
        'queue_types' => $queueTypes ?: '',
    ];
}

/** PCQ auto mapping (EDIT WITH YOUR EXACT QUEUE TYPE NAMES) */
function mikrotik_simplequeue_export_pcq_map_down($download){
    $d = strtolower(trim((string)$download));
    $map = [
        '1024k'   => '1 MB DOWS',
        '2048k'   => '2 MB DOWS',
        '3072k'   => '3 MB DOWS',
        '4096k'   => '4 MB DOWS',
        '5120k'   => '5 MB  DOWS  rafaga de 10',
        '6144k'   => '6 MB DOWS',
        '7168k'   => '7 MB DOWS',
        '8192k'   => '8 MB DOWS',
        '9216k'   => '9 MB DOWS',
        '10240k'  => '10 MB DOWS',
        '15360k'  => '15 MB DOWS',
        '20480k'  => '20 MB  DOWS',
        '30960k'  => '30 megas dows ',
        '40960k'  => '40 MB DOWS',
        '102400k' => '100 MB DOWS',
        '100m'    => '100M DOWS',
        '1k'      => 'MOROSOS dows',
    ];
    return $map[$d] ?? '';
}
function mikrotik_simplequeue_export_pcq_map_up($upload){
    $u = strtolower(trim((string)$upload));
    $map = [
        '1024k'   => '1 MB UP ',
        '2048k'   => '2 MB  UP ',
        '3072k'   => '3 MB UP',
        '5120k'   => '5 MB UP',
        '6144k'   => '6 up',
        '7168k'   => '7 up',
        '9216k'   => '9 up',
        '10240k'  => '10 up',
        '20480k'  => '20 up',
        '30720k'  => '30 up',
        '102400k' => '100 megas up',
        '1k'      => 'MOROSO UP ',
    ];
    return $map[$u] ?? '';
}
