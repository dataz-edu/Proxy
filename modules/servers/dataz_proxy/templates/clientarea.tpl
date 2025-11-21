{if $status eq "creating"}
    <div class="alert alert-info">Đang tạo proxy, vui lòng đợi 2–5 phút…</div>
{else}
<div class="container-fluid" style="padding:0;">
    <h3>Thông Tin Proxy</h3>
    <p class="text-muted">Mỗi proxy bao gồm cả HTTP và SOCKS5 trên cùng một địa chỉ IP.</p>
    {foreach from=$proxies item=proxy}
    <div class="card mb-4" style="border-radius:10px; box-shadow:0 10px 25px rgba(0,0,0,0.08); overflow:hidden;">
        <div class="card-header" style="background:linear-gradient(135deg, #001b3d, #0051ff); color:#ffffff;">
            <div class="d-flex justify-content-between align-items-center">
                <div><strong>Proxy #{$proxy.id}</strong></div>
                <div>
                    {if $proxy.status == 'active'}
                        <span class="badge badge-success">HOẠT ĐỘNG</span>
                    {elseif $proxy.status == 'creating'}
                        <span class="badge badge-warning">ĐANG TẠO</span>
                    {elseif $proxy.status == 'disabled'}
                        <span class="badge badge-danger">TẠM DỪNG</span>
                    {else}
                        <span class="badge badge-secondary">KHÁC</span>
                    {/if}
                </div>
            </div>
        </div>
        <div class="card-body" style="padding:20px;">
            <div class="row mb-3">
                <div class="col-md-4 col-12 mb-2">
                    <small class="text-muted">ĐỊA CHỈ IP</small>
                    <div class="font-weight-bold">{$proxy.proxy_ip}</div>
                </div>
                <div class="col-md-4 col-12 mb-2">
                    <small class="text-muted">HTTP PORT</small>
                    <div class="font-weight-bold">{$proxy.http_port}</div>
                </div>
                <div class="col-md-4 col-12 mb-2">
                    <small class="text-muted">SOCKS5 PORT</small>
                    <div class="font-weight-bold">{$proxy.socks5_port}</div>
                </div>
            </div>
            <div class="row mb-3">
                <div class="col-md-4 col-12 mb-2">
                    <small class="text-muted">USERNAME</small>
                    <div class="d-flex align-items-center">
                        <span class="font-weight-bold">{$proxy.proxy_username}</span>
                        <button type="button" class="btn btn-link btn-sm ml-2 copy-btn" data-copy="{$proxy.proxy_username}">Copy</button>
                    </div>
                </div>
                <div class="col-md-4 col-12 mb-2">
                    <small class="text-muted">PASSWORD</small>
                    <div class="d-flex align-items-center">
                        <span class="font-weight-bold">{$proxy.proxy_password}</span>
                        <button type="button" class="btn btn-link btn-sm ml-2 copy-btn" data-copy="{$proxy.proxy_password}">Copy</button>
                    </div>
                </div>
                <div class="col-md-4 col-12 mb-2">
                    <form method="post" action="{$refresh_url}" class="mb-0">
                        <input type="hidden" name="modaction" value="resetpass" />
                        <button type="submit" class="btn btn-warning btn-block" style="background:#0051ff; border-color:#0051ff;">Reset User/Pass</button>
                    </form>
                </div>
            </div>
            <div class="mb-3">
                <label class="font-weight-bold">Định Dạng Hiển Thị</label>
                <select class="form-control proxy-format-select"
                    data-ip="{$proxy.proxy_ip}"
                    data-http-port="{$proxy.http_port}"
                    data-socks-port="{$proxy.socks5_port}"
                    data-user="{$proxy.proxy_username}"
                    data-pass="{$proxy.proxy_password}">
                    <option value="ip_port_user_pass">ip:port:user:pass</option>
                    <option value="pipe_ip_port_user_pass">Xử lý dấu ngăn |</option>
                    <option value="user_pass_at_domain_port">user:pass@domain:port</option>
                    <option value="http_user_pass_at_domain_port">http://user:pass@domain:port</option>
                    <option value="socks_user_pass_at_domain_port">socks5://user:pass@domain:port</option>
                    <option value="http_domain_port_user_pass">http://domain:port:user:pass</option>
                    <option value="socks_domain_port_user_pass">socks5://domain:port:user:pass</option>
                </select>
            </div>
            <div class="row">
                <div class="col-md-6 col-12 mb-3">
                    <label class="text-uppercase" style="letter-spacing:0.5px;">HTTP PROXY</label>
                    <div class="input-group">
                        <input type="text" class="form-control proxy-http-output" readonly style="background:#111827; color:#fff;" />
                        <div class="input-group-append">
                            <button class="btn btn-primary copy-output" type="button" data-target="http">Copy</button>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-12 mb-3">
                    <label class="text-uppercase" style="letter-spacing:0.5px;">SOCKS5 PROXY</label>
                    <div class="input-group">
                        <input type="text" class="form-control proxy-socks-output" readonly style="background:#111827; color:#fff;" />
                        <div class="input-group-append">
                            <button class="btn btn-primary copy-output" type="button" data-target="socks">Copy</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    {/foreach}
</div>
{/if}

<script type="text/javascript">
(function ($) {
    function formatProxy(sel) {
        var ip = sel.data('ip');
        var httpPort = sel.data('http-port');
        var socksPort = sel.data('socks-port');
        var user = sel.data('user');
        var pass = sel.data('pass');
        var domain = ip;
        var val = sel.val();
        var httpStr = '';
        var socksStr = '';
        switch (val) {
            case 'pipe_ip_port_user_pass':
                httpStr = ip + '|' + httpPort + '|' + user + '|' + pass;
                socksStr = ip + '|' + socksPort + '|' + user + '|' + pass;
                break;
            case 'user_pass_at_domain_port':
                httpStr = user + ':' + pass + '@' + domain + ':' + httpPort;
                socksStr = user + ':' + pass + '@' + domain + ':' + socksPort;
                break;
            case 'http_user_pass_at_domain_port':
            case 'socks_user_pass_at_domain_port':
                httpStr = 'http://' + user + ':' + pass + '@' + domain + ':' + httpPort;
                socksStr = 'socks5://' + user + ':' + pass + '@' + domain + ':' + socksPort;
                break;
            case 'http_domain_port_user_pass':
                httpStr = 'http://' + domain + ':' + httpPort + ':' + user + ':' + pass;
                socksStr = 'socks5://' + domain + ':' + socksPort + ':' + user + ':' + pass;
                break;
            case 'socks_domain_port_user_pass':
                httpStr = 'http://' + domain + ':' + httpPort + ':' + user + ':' + pass;
                socksStr = 'socks5://' + domain + ':' + socksPort + ':' + user + ':' + pass;
                break;
            default:
                httpStr = ip + ':' + httpPort + ':' + user + ':' + pass;
                socksStr = ip + ':' + socksPort + ':' + user + ':' + pass;
        }
        return {http: httpStr, socks: socksStr};
    }

    function updateOutputs(card) {
        var sel = card.find('.proxy-format-select');
        var outputs = formatProxy(sel);
        card.find('.proxy-http-output').val(outputs.http);
        card.find('.proxy-socks-output').val(outputs.socks);
    }

    function copyText(text) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function(){ alert('Đã copy'); });
        } else {
            var temp = $('<textarea>');
            $('body').append(temp);
            temp.val(text).select();
            document.execCommand('copy');
            temp.remove();
            alert('Đã copy');
        }
    }

    $(document).ready(function(){
        $('.card').each(function(){
            updateOutputs($(this));
        });

        $(document).on('change', '.proxy-format-select', function(){
            updateOutputs($(this).closest('.card'));
        });

        $(document).on('click', '.copy-output', function(){
            var card = $(this).closest('.card');
            if ($(this).data('target') === 'http') {
                copyText(card.find('.proxy-http-output').val());
            } else {
                copyText(card.find('.proxy-socks-output').val());
            }
        });

        $(document).on('click', '.copy-btn', function(){
            copyText($(this).data('copy'));
        });
    });
})(jQuery);
</script>
