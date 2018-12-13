/**
 * Created by zxm on 2017/5/20.
 */
var selectImgTake = {
    "init":function(divId,maxSelectNumber){
        if(maxSelectNumber==null||maxSelectNumber==""){
            selectImgTake.initSelectItemEvent(divId);
        }else{
            selectImgTake.initSelectEvent(divId,maxSelectNumber);
        }
    },
    "initSelectEvent":function(divId,maxSelectNumber){
        $("#"+divId+" .item .img_show img").on("click",function(){
            console.log(11);
            var i_display = $(this).parent().parent().find(".img_isCheck i").css("display");
            if(i_display=="none"){
                if(maxSelectNumber!=-1){
                    var selectImgDivs = selectImgTake.getSelectImgs(divId);
                    if(selectImgDivs.length>=maxSelectNumber){
                        layer.msg("最多只能选择"+maxSelectNumber+"张图片");
                        return;
                    }
                }
                $(this).parent().parent().find(".img_isCheck i").css("display","block");
                $(this).parent().parent().attr("ischecked","true");
            }else{
                $(this).parent().parent().find(".img_isCheck i").css("display","none");
                $(this).parent().parent().removeAttr("ischecked");
            }
        });
    },
    "initSelectItemEvent":function(divId){
        $("#"+divId+" .item").on("click",function(){
            var i_display = $(this).find(".img_isCheck i").css("display");
            var item_id   = $(this).attr('data-id');
            var title     = $(this).find('.img_title').html();
            var image     = $(this).find('.img_show img').attr('src');
            if(i_display=="none"){
                var html = '<div class="'+item_id+'" data-num-iid="'+item_id+'" data-title="'+title+'" data-image-url="'+image+'"></div>';
                $("#"+divId+"Data").append(html);
                $(this).find(".img_isCheck i").css("display","block");
                $(this).attr("ischecked","true");
            }else{
                $("#"+divId+"Data").find('.'+item_id).remove();
                $(this).find(".img_isCheck i").css("display","none");
                $(this).removeAttr("ischecked");
            }
        });
    },
    "getSelectItems":function(divId){
        var selectImgDivs = $("#"+divId+"Data").children();
        return selectImgDivs;
    },
    "getSelectImgs":function(divId){
        var selectImgDivs = $("#"+divId+" .item[ischecked='true']");
        return selectImgDivs;
    },
    "cancelInit":function(divId){
        $("#"+divId+" .item").off("click");
        $(".img_isCheck i").css("display","none");
        $("#"+divId+" .item").removeAttr("ischecked");
    }
}