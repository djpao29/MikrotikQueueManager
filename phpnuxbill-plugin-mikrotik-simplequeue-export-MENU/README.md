# PHPNuxBill Plugin: MikroTik Simple Queue Export (IP/32)

✅ Follows official PHPNuxBill plugin guidelines:
- function prefix = filename (`mikrotik_simplequeue_export_*`)
- integrates to menu using `register_menu()`
- uses `$ui`, `_admin()`, `Admin::_info()`, and `$ui->display()` (Smarty)

## Install
Copy:
- `system/plugin/mikrotik_simplequeue_export.php` -> `/system/plugin/`
- `system/plugin/ui/mikrotik_simplequeue_export.tpl` -> `/system/plugin/ui/`

## Menu
The plugin registers an admin menu item:
- Name: **Simple Queue Export**
- Position: **NETWORK**

Change the menu position by editing:
`register_menu(..., "NETWORK", ...)`

## Notes
Database tables/fields may vary by version.
Edit the table/field mappings inside the plugin if needed:
- routers: `tbl_routers`
- customers: `tbl_customers`
- plans: `tbl_plans`
