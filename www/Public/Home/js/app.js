/**首页的欢迎页**/
//if(pageIndex == 1 || pageIndex == 2 || pageIndex == 3 || pageIndex == 4){
//    localStorage.statusIn = 1;
//}


//var hours = 24; // Reset when storage is more than 24hours hours*60*60*1000;
//var now = new Date().getTime();
//var setupTime = localStorage.getItem('setupTime');
//if (setupTime == null) {
//    localStorage.setItem('setupTime', now)
//} else {
//    if(now-setupTime > 10*1000) {
//        localStorage.removeItem("statusIn");
//        localStorage.removeItem("setupTime");
//        localStorage.setItem('setupTime', now);
//    }
//}






$(function(){
    function loadCSS(href) {
        var cssLink = $("<link rel='stylesheet' type='text/css' href='"+href+"'>");
        $("head").append(cssLink);
    }

    /**判断移动端，控制导航的样式**/
    if(/AppleWebKit.*Mobile/i.test(navigator.userAgent) || (/MIDP|SymbianOS|NOKIA|SAMSUNG|LG|NEC|TCL|Alcatel|BIRD|DBTEL|Dopod|PHILIPS|HAIER|LENOVO|MOT-|Nokia|SonyEricsson|SIE-|Amoi|ZTE|RIM|Windows Phone|Nexus|Nokia/.test(navigator.userAgent))){
        if(window.location.href.indexOf("?mobile")<0){
            try{
                if(/Android|webOS|iPhone|iPod|BlackBerry|RIM|Windows Phone|Nexus|Nokia/i.test(navigator.userAgent)){
                    loadCSS("css/website/style.mobile.css");
                }else if(/iPad/i.test(navigator.userAgent)){
                    loadCSS("css/website/style.mobile.css");
                }else{
                }
            }catch(e){}
        }
    }else{
        if(pageIndex == 1){
            $(".nav .menu-box .top-li:nth-child(2)").css("border-bottom","3px solid #53b983");
            $(".nav .menu-box .top-li:nth-child(2)>a>.text").css("color","#53b983");
        }else if(pageIndex == 2){
            $(".nav .menu-box .top-li:nth-child(3)").css("border-bottom","3px solid #f9af41");
            $(".nav .menu-box .top-li:nth-child(3)>a>.text").css("color","#f9af41");
        }else if(pageIndex == 3){
            $(".nav .menu-box .top-li:nth-child(1)").css("border-bottom","3px solid #1b75bb");
            $(".nav .menu-box .top-li:nth-child(1)>a>.text").css("color","#1b75bb");
        }else if(pageIndex == 4){
            $(".nav .menu-box .top-li:nth-child(4)").css("border-bottom","3px solid #53b983");
            $(".nav .menu-box .top-li:nth-child(4)>a>.text").css("color","#53b983");
        }
    }


    /**移动端导航点击菜单按钮时，禁止滚动**/
    $(".mobile-toggle-icon").click(function(){
        $(".menu-box").slideToggle();
        if($(".mobile-toggle-icon").hasClass("menu-icon")){
            $(".mobile-toggle-icon").removeClass("menu-icon").addClass("close-icon");
            $('body').bind('touchmove', function(e){e.preventDefault()});
        }else{
            $(".mobile-toggle-icon").removeClass("close-icon").addClass("menu-icon");
            $('body').unbind('touchmove');
        }
    });

});
