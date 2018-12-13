function colok(){
	 $("#tiger_bj").css("display","none");
}

//搜索导航浮动//这个JS放在jquery下面
var tiger_nav_search_show=function(){
	$(window).on('scroll',function(){
		if($(window).scrollTop()>$("#head_seach").offset().top){
			$("#pf_seach").show();
		}
		else{
			$("#pf_seach").hide();
		}
	})
}

if($("#head_seach").size()>0){
    tiger_nav_search_show();
}