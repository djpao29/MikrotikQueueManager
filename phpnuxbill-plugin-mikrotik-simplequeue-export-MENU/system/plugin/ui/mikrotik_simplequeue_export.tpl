{include file="sections/header.tpl"}

<div class="container">
  <div class="row">
    <div class="col-md-12">

      <div class="panel panel-default">
        <div class="panel-heading"><b>MikroTik Simple Queue Export (IP/32)</b></div>
        <div class="panel-body">

          {if $error}
            <div class="alert alert-danger"><b>Error:</b> {$error}</div>
          {/if}

          <form method="post">
            <div class="row">
              <div class="col-md-5">
                <label>Router</label>
                <select class="form-control" name="router_id" required>
                  <option value="">-- Select Router --</option>
                  {foreach from=$routers item=r}
                    <option value="{$r.id}">
                      {$r.name|default:$r.id} - {$r.ip_address|default:$r.ip|default:''}
                    </option>
                  {/foreach}
                </select>
                <small class="text-muted">Si no ves routers, revisa la tabla/fields en el plugin.</small>
              </div>

              <div class="col-md-4">
                <label>Mode</label>
                <select class="form-control" name="mode">
                  <option value="maxlimit">Max-Limit only</option>
                  <option value="pcq_auto">PCQ auto (mapping)</option>
                  <option value="pcq_plan_fields">PCQ from plan fields</option>
                </select>
                <small class="text-muted">PCQ auto usa un mapeo editable en el plugin.</small>
              </div>

              <div class="col-md-3">
                <label>&nbsp;</label>
                <div class="checkbox">
                  <label><input type="checkbox" name="dry_run" value="1"> Dry-run (no changes)</label>
                </div>
                <button class="btn btn-success" type="submit" name="run_export" value="1">Run Export</button>
              </div>
            </div>
          </form>

          {if $result && $result.report}
            <hr>
            <p><b>Mode:</b> {$result.report.mode} | <b>Dry-run:</b> {if $result.report.dry_run}Yes{else}No{/if}</p>
            <p><b>Total customers fetched:</b> {$result.report.total_customers}</p>

            <h4>Synced</h4>
            <div style="max-height: 420px; overflow:auto;">
              <table class="table table-bordered table-condensed">
                <thead>
                  <tr>
                    <th>User</th>
                    <th>IP</th>
                    <th>Queue</th>
                    <th>Action</th>
                    <th>Target</th>
                    <th>Max-Limit</th>
                    <th>PCQ Queue Types</th>
                  </tr>
                </thead>
                <tbody>
                  {foreach from=$result.report.synced item=s}
                    <tr>
                      <td>{$s.username}</td>
                      <td>{$s.ip}</td>
                      <td>{$s.queue}</td>
                      <td>{$s.action}</td>
                      <td>{$s.target}</td>
                      <td>{$s.maxlimit}</td>
                      <td>{$s.queue_types}</td>
                    </tr>
                  {/foreach}
                </tbody>
              </table>
            </div>

            {if $result.report.failed && $result.report.failed|@count > 0}
              <hr>
              <h4>Failed</h4>
              <table class="table table-bordered table-condensed">
                <thead><tr><th>User</th><th>IP</th><th>Error</th></tr></thead>
                <tbody>
                  {foreach from=$result.report.failed item=f}
                    <tr>
                      <td>{$f.username}</td>
                      <td>{$f.ip}</td>
                      <td>{$f.error}</td>
                    </tr>
                  {/foreach}
                </tbody>
              </table>
            {/if}
          {/if}

        </div>
      </div>

    </div>
  </div>
</div>

{include file="sections/footer.tpl"}
