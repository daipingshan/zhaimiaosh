/**
 * Created by daipingshan on 2018/1/22.
 */
layui.config({
    base: JS_PATH
}).use(['form', 'layer', 'jquery', 'laypage'], function () {
    var form = layui.form(),
        layer = parent.layer === undefined ? layui.layer : parent.layer,
        laypage = layui.laypage,
        $ = layui.jquery;
    getData(1);
    function getData(page) {
        var username = $(".search_input").val();
        var count = 0;
        var newArray = data = [];
        $.get('/Admin/index', {username: username, page: page}, function (res) {
            if (username != '') {
                data = res.info.data;
                count = res.info.count;
                for (var i = 0; i < data.length; i++) {
                    var linksStr = data[i];

                    function changeStr(data) {
                        var dataStr = '';
                        var showNum = data.split(eval("/" + username + "/ig")).length - 1;
                        if (showNum > 1) {
                            for (var j = 0; j < showNum; j++) {
                                dataStr += data.split(eval("/" + username + "/ig"))[j] + "<i style='color:#03c339;font-weight:bold;'>" + username + "</i>";
                            }
                            dataStr += data.split(eval("/" + username + "/ig"))[showNum];
                            return dataStr;
                        } else {
                            dataStr = data.split(eval("/" + username + "/ig"))[0] + "<i style='color:#03c339;font-weight:bold;'>" + username + "</i>" + data.split(eval("/" + username + "/ig"))[1];
                            return dataStr;
                        }
                    }

                    if (linksStr.username.indexOf(username) > -1) {
                        linksStr["username"] = changeStr(linksStr.username);
                    }
                    if (linksStr.real_name.indexOf(username) > -1) {
                        linksStr["real_name"] = changeStr(linksStr.real_name);
                    }
                    newArray.push(linksStr);
                }
                data = newArray;
            } else {
                data = res.info.data;
                count = res.info.count;
            }
            $(".links_content").html(renderDate(data));
            if (page == 1) {
                pages(count);
            }
        })
    }

    //查询
    $(".search_btn").click(function () {
        if ($(".search_input").val() != '') {
            var index = layer.msg('查询中，请稍候', {icon: 16, time: false, shade: 0.8});
            getData(1);
            setTimeout(function () {
                layer.close(index);
            }, 1000);
        } else {
            layer.msg("请输入需要查询的内容");
        }
    })

    //添加友情链接
    $(".linksAdd_btn").click(function () {
        $('#reset').click();
        layui.layer.open({
            title: "添加管理员",
            type: 1,
            area: ['600px', '300px'],
            content: $('#admin-box'),
        })
    })

    //操作
    $("body").on("click", ".links_edit", function () {  //编辑
        $('#reset').click();
        var _this = $(this);
        $('#admin-box input[name=username]').val(_this.data('username'));
        $('#admin-box input[name=real_name]').val(_this.data('name'));
        $('#admin-box #admin_id').val(_this.data('id'));
        layui.layer.open({
            title: "编辑管理员",
            type: 1,
            area: ['600px', '300px'],
            content: $('#admin-box'),
        })
    })

    //登录按钮事件
    form.on("submit(save-admin)", function (data) {
        var _btn = data.elem;
        _btn.disabled = true;
        var url = "/Admin/add"
        if (data.field.id > 0) {
            url = "/Admin/update";
        }
        $.post(url, data.field, function (res) {
            layer.msg(res.info);
            if (res.status == 1) {
                layer.msg(res.info, function () {
                    window.location.reload();
                })
            } else {
                _btn.disabled = false;
                layer.msg(res.info)
            }
        });
        return false;
    });

    $("body").on("click", ".links_del", function () {  //删除
        var _this = $(this);
        layer.confirm('确定要更新当前用户状态吗？', {icon: 3, title: '提示信息'}, function (index) {
            $.post("/Admin/setStatus", {id: _this.data('id')}, function (res) {
                layer.msg(res.info);
                if (res.status == 1) {
                    setTimeout(function () {
                        window.location.reload(true);
                    }, 1000);
                }
            });
            layer.close(index);
        })
    });

    /**
     * @param count
     * @param is_get
     */
    function pages(count) {
        laypage({
            cont: "page",
            pages: Math.ceil(count / LIMIT_NUM),
            jump: function (obj, first) {
                if (first) {
                    form.render();
                } else {
                    var index = layer.msg('加载中，请稍候', {icon: 16, time: false, shade: 0.8});
                    getData(obj.curr);
                    setTimeout(function () {
                        layer.close(index);
                    }, 500);
                }

            }
        })
    }

//渲染数据
    function renderDate(data) {
        var dataHtml = '';
        if (data.length != 0) {
            for (var i = 0; i < data.length; i++) {
                dataHtml += '<tr>'
                    + '<td>' + data[i].username + '</td>'
                    + '<td>' + data[i].real_name + '</td>'
                    + '<td>' + data[i].add_time + '</td>'
                    + '<td>' + data[i].last_ip + '</td>'
                    + '<td>' + data[i].last_time + '</td>';
                if (data[i].status == 1) {
                    dataHtml += '<td style="color:#5FB878">启用</td>'
                        + '<td>'
                        + '<a class="layui-btn layui-btn-mini links_edit" data-id="' + data[i].id + '" data-username="' + data[i].username + '" data-name="' + data[i].real_name + '"><i class="iconfont icon-edit"></i> 编辑</a>'
                        + '<a class="layui-btn layui-btn-danger layui-btn-mini links_del" data-id="' + data[i].id + '"><i class="layui-icon">&#x1007;</i> 禁用</a>'
                        + '</td>';
                } else {
                    dataHtml += '<td style="color:#f00">禁用</td>'
                        + '<td>'
                        + '<a class="layui-btn layui-btn-mini links_edit" data-id="' + data[i].id + '" data-username="' + data[i].username + '" data-name="' + data[i].real_name + '"><i class="iconfont icon-edit"></i> 编辑</a>'
                        + '<a class="layui-btn layui-btn-danger layui-btn-mini links_del" data-id="' + data[i].id + '"><i class="layui-icon">&#x1005;</i> 启用</a>'
                        + '</td>';
                }
                dataHtml += '</tr>';
            }
        } else {
            dataHtml = '<tr><td colspan="7" align="center">暂无数据</td></tr>';
        }
        return dataHtml;
    }
})
