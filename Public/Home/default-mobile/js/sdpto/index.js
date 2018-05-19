$(function () {
    var userid = MyUrl.get("uid");
    if (!userid) {
        Msg.windowAlert("用户不存在！")
    }

    init(userid);

    function init(userid) {
        $.ajax({
            url: "http://www.mbaovip.com/Api/getUserBaseInfo/uid/" + userid,
            // url: "http://demo.mbaovip.com/Api/getUserBaseInfo/uid/19",
            // data: {url: wxurl},
            type: "get",
            // dataType: "json",
            success: function (data) {
                console.log(data);
                if (data.status == -1) {
                    Msg.windowAlert("用户不存在！");
                    return
                } else if (data.status == -2) {
                    Msg.windowAlert("用户不是推广者！");
                    return
                } else if (data.status == -3) {
                    Msg.windowAlert("推广者被禁用！");
                    return
                }
                var $usermsg=$(".user_box");
                $usermsg.find(".photo").html('<img src="'+data.avatar+'"/>');
                $usermsg.find(".nickname").html(data.nickname);
                $usermsg.find(".ui_code").find("span").html("邀请码");

                $(".qrcode").qrcode({width: "100%", height: "100%", text: data.qrcode_url});
            },
            error: function () {
                console.log("网络错误")
            }
        })
    }
});