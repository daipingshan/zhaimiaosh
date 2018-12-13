(function (doc, win) {
    var docEl = doc.documentElement,
        resizeEvt = 'orientationchange' in window ? 'orientationchange' : 'resize',
        recalc = function () {
            var clientWidth = docEl.clientWidth;
            if (!clientWidth) return;
               docEl.style.fontSize =  (clientWidth/7.5) + 'px'; 
               if(clientWidth>1024){
            	   docEl.style.fontSize =  '100px'; 
               }
        };
    if (!doc.addEventListener) return;
    win.addEventListener(resizeEvt, recalc, false);
    //doc.addEventListener('DOMContentLoaded', recalc, false);
    recalc();
})(document, window);