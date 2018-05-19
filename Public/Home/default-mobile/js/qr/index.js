var arr = ["fadeInDown 1s ease 0.5s 1 normal both", "rollInRight 1s ease 1s 1 normal both", "rollInLeft 1s ease 1s 1 normal both", "zoomIn 1s ease 2s 1 normal both", "fadeInLeft 0.5s ease 2.5s 1 normal both", "fadeInRight 0.5s ease 2.5s 1 normal both"];

$(function () {

    var $model = $("section");
    for (var i = 0; i < $model.length; i++) {
        $model.eq(i).attr("data-i", i);
    }
    moveGo($model.eq(0));

    var startx, starty;
    //根据起点终点返回方向 1向上 2向下 3向左 4向右 0未滑动
    function getDirection(startx, starty, endx, endy) {
        var angx = endx - startx;
        var angy = endy - starty;
        var result = 0;

        //如果滑动距离太短
        if (Math.abs(angx) < 2 && Math.abs(angy) < 2) {
            return result;
        }

        if (angy > 0) {
            result = 1;
        } else {
            result = 2;
        }

        return result;
    }

    //手指接触屏幕
    document.addEventListener("touchstart", function (e) {
        startx = e.touches[0].pageX;
        starty = e.touches[0].pageY;
    }, false);
    //手指离开屏幕
    document.addEventListener("touchend", function (e) {
        var endx, endy;
        endx = e.changedTouches[0].pageX;
        endy = e.changedTouches[0].pageY;
        var direction = getDirection(startx, starty, endx, endy);
        switch (direction) {
            case 0:
             //   console.log("未滑动！");
                break;
            case 1:
             //   console.log("自上而下！");
                move("up");
                break;
            case 2:
             //   console.log("自下而上！");
                move("down");
                break;
            default:
        }
    }, false);

    function move(direction) {
        var $zCurrent = $("section.show");
        var index = $zCurrent.attr("data-i");
        var obj = null;
        var objLast = null;
        if (direction === "up") {
            objLast = $zCurrent.removeClass("show");
            if (index == 0) {
                obj = $model.eq(this.length - 1).addClass("show");
            } else {
                obj = $zCurrent.prev().addClass("show");
            }

        } else if (direction === "down") {
            objLast = $zCurrent.removeClass("show");
            if (index == $model.length - 1) {
                obj = $model.eq(0).addClass("show");
            } else {
                obj = $zCurrent.next().addClass("show");
            }
        }
        moveCancel(objLast);
        moveGo(obj);
    }

    function moveGo(o) {
        for (var i = 0; i < arr.length; i++) {
            o.find("li")[i].style.animation = arr[i];
        }
    }

    function moveCancel(o) {
        for (var i = 0; i < arr.length; i++) {
            o.find("li")[i].removeAttribute('style');
        }
    }
});