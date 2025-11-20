{if $status eq "creating"}
    <div class="alert alert-info">Đang tạo proxy, vui lòng đợi 2–5 phút…</div>
{else}
    <div class="panel panel-default">
        <div class="panel-heading">Proxy của bạn</div>
        <div class="panel-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>IP</th>
                            <th>Port</th>
                            <th>Username</th>
                            <th>Password</th>
                            <th>Type</th>
                        </tr>
                    </thead>
                    <tbody>
                        {foreach from=$proxies item=proxy}
                            <tr>
                                <td>{$proxy.proxy_ip}</td>
                                <td>{$proxy.proxy_port}</td>
                                <td>{$proxy.proxy_username}</td>
                                <td>{$proxy.proxy_password}</td>
                                <td>{$proxy.proxy_type}</td>
                            </tr>
                        {/foreach}
                    </tbody>
                </table>
            </div>
            <div class="form-group">
                <label>Copy tất cả (ip:port:user:pass)</label>
                <textarea class="form-control" rows="6" readonly>{foreach from=$proxies item=proxy}{$proxy.proxy_ip}:{$proxy.proxy_port}:{$proxy.proxy_username}:{$proxy.proxy_password}
{/foreach}</textarea>
            </div>
            <div class="btn-group">
                <a href="{$refresh_url}" class="btn btn-default">Làm mới</a>
                <form method="post" action="{$smarty.server.REQUEST_URI}" style="display:inline-block;margin-left:10px;">
                    <input type="hidden" name="modaction" value="resetpass" />
                    <button type="submit" class="btn btn-warning">Reset User/Pass</button>
                </form>
            </div>
        </div>
    </div>
{/if}
