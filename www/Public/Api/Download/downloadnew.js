(function () {
    //轮播
    var tt = true;
    var t = true;
    var android_url = "http://pic.taodianke.com/static/App/zhaimiaosh.apk";
    var ios_url = "https://itunes.apple.com/cn/app/id1342122564?mt=8";
    $(".m-banner .ul-flash li").on("swiperight", function () {
        if (t) {
            tt = false;
            t = false;
            var a = $(".m-banner .ul-flash .cur_li").index();
            var b = $(".m-banner .ul-flash li:last").index();
            $(".m-banner .ul-flash li:last").css({marginLeft: "-14.6rem"});
            $(".m-banner .ul-flash ").prepend($(".m-banner .ul-flash li:last"));
            $(".m-banner .ul-flash li:first").animate({marginLeft: 0}, "slow", function () {
                t = true;

                var data = $(".m-banner .ul-flash").find("li:first").attr("data-index");
                $(".m-banner .ul-list").find("li").eq(data).addClass("cur_li").siblings("li").removeClass("cur_li");
            });

        }

    });
    $(".m-banner .ul-flash li").on("swipeleft", function () {
        if (t) {
            t = false;
            tt = false;
            var a = $(".m-banner .ul-flash .cur_li").index();
            var b = $(".m-banner .ul-flash li:last").index();
            $(".m-banner .ul-flash li:first").animate({
                marginLeft: -14.6 + 'rem'
            }, 500, function () {
                $(".m-banner .ul-flash").append($(".m-banner .ul-flash li:first"));
                $(".m-banner .ul-flash li:last").css({marginLeft: "0rem"});
                t = true;

                var data = $(".m-banner .ul-flash").find("li:first").attr("data-index");
                $(".m-banner .ul-list").find("li").eq(data).addClass("cur_li").siblings("li").removeClass("cur_li");
            });
        }

    });
    /*自动*/
    window.setInterval(function () {
        if (tt) {
            var a = $(".m-banner .ul-flash .cur_li").index();
            var b = $(".m-banner .ul-flash li:last").index();
            $(".m-banner .ul-flash li:first").animate({
                marginLeft: -14.6 + 'rem'
            }, 500, function () {
                $(".m-banner .ul-flash").append($(".m-banner .ul-flash li:first"));
                $(".m-banner .ul-flash li:last").css({marginLeft: "0px"});

                var data = $(".m-banner .ul-flash").find("li:first").attr("data-index");
                $(".m-banner .ul-list").find("li").eq(data).addClass("cur_li").siblings("li").removeClass("cur_li");
            });
        }
        else {
            tt = true;
        }
    }, 3000);


    var ua = navigator.userAgent.toLowerCase();
    if (ua.match(/iphone/i) == "iphone" || ua.match(/ipad/i) == "ipad") {
        $(".copy_btn_android").hide();
        $(".copy_btn_ios").show();
        var clipboard = new Clipboard(".copy_btn_ios");
        clipboard.on("success", function (e) {
            $(".copy_btn_ios").html("复制成功");
            e.clearSelection();
        });
        clipboard.on("error", function (e) {
            $(".copy_btn_ios").html("复制失败");
            alert("可能由于手机浏览器的版本问题，您并不能进行复制，请手动长按复制");
        });
    } else {
        $(".copy_btn_android").show();
        $(".copy_btn_ios").hide();
        var clipboard = new Clipboard(".copy_btn_android");
        clipboard.on("success", function (e) {
            $(".copy_btn_android").html("复制成功");
            e.clearSelection();
        });
        clipboard.on("error", function (e) {
            $(".copy_btn_android").html("复制失败");
            alert("可能由于手机浏览器的版本问题，您并不能进行复制，请手动长按复制");
        });
    }
    var isIos = /(iPhone|iPad|iPod|iOS)/i.test(navigator.userAgent);
    if (isIos) {
        $(".m-down").attr("href", ios_url);
    } else {
        $(".m-down").attr("href", android_url);
    }
})();