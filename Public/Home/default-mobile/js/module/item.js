/**
 *商品模块：商品规格选择，加入收藏，更改数量
 */
define('module/item', function(require, exports, module){
  'use strict';

  var user = require('user'),
      cart = require('cart'),
      slider = require('slider'),
      common = require('common');
      
  var item = {
    sku_choose_size: 0,
    sku_selected_size: 0,
    spc_data_info: '',
    is_buynow: 0,
    init: function(spc_data_info, seckill){
      var _self = this;
      //显示、隐藏底部浮动购买按钮
      _self.toggleFixedBar();
      //初始化
      _self.sku_choose_size = $('.Z_sku_box').size();
      _self.spc_data_info = spc_data_info;
      //绑定遮罩切换
      _self.toggleBackdrop();

      //绑定规格选择事件
      $('.Z_sku_box li.Z_sale_porp').not('.out-of-stock').on('click', function(){
        //选中的规格项样式控制
        $(this).addClass('select').siblings('.Z_sale_porp').removeClass('select');
        //根据当前页面用户所选规格的code组合获取对应价格和库存数量重新设置页面显示的价格和库存
        var data_code = $(this).attr('data-code'),
            spec_selected = $('.Z_spec_item li.select'),
            spec_all_code = '',
            spec_all_args = '',
            selected_spc_info = '';
        //统计已选规格项数量
        _self.sku_selected_size = spec_selected.size();
        if((_self.sku_choose_size > 0) && (_self.sku_selected_size == _self.sku_choose_size)){
          //拼接已选规格信息
          var i = 0;
          $.each(spec_selected, function(k, v){
            if(i == 0){
              spec_all_code = $(v).attr('data-code');
              spec_all_args = $(v).attr('args');
              selected_spc_info = selected_spc_info + $(v).attr('title');
            }else{
              spec_all_code = spec_all_code + '-' + $(v).attr('data-code');
              spec_all_args = spec_all_args + '&' + $(v).attr('args');
              selected_spc_info = selected_spc_info + "，" + $(v).attr('title');
            }
            i++;
          });

          var price = 0;
          var quantity = 0;
          var spec_data = new Array();

          if(_self.spc_data_info){
            var spec_data_info = $.parseJSON(_self.spc_data_info);
            if(!$.isEmptyObject(spec_data_info)){
              $.each(spec_data_info, function(k, spec){
                spec_data[spec.spc_code] = new Array();
                spec_data[spec.spc_code]['price'] = spec.price;
                spec_data[spec.spc_code]['quantity'] = spec.quantity;
              });
            }

            //获取当前用户选择的规格组合对应的价格和库存数量

            if(spec_data[spec_all_code]){
              price = spec_data[spec_all_code]['price'];
              quantity = spec_data[spec_all_code]['quantity'];

              //设置页面显示的价格与库存数量
              $('.Z_spec_price').html(price);
              $('.Z_spec_quantity').html(quantity);
              $('#Z_item_price').val(price);
              $('#Z_item_stock').val(quantity);
            }
          }

          //判断用户所选商品是否还有库存
          if($('#Z_item_stock').val() <= 0){
            $.ui.error('抱歉，您选购的商品库存不足');
            $('#Z_spec_submit').addClass('disabled').unbind('click');
            return false;
          }else{
            $('#Z_spec_submit').removeClass('disabled');
            //_self.specSubmitEvent();
          }

          //设置已选规格内容
          $('#Z_selected_info').html('已选：' + selected_spc_info + "，数量 <span id=\"Z_selected_quantity\">" + $('#Z_item_quantity').val() + '</span>');

          //隐藏表单传值
          $('#Z_item_spec').val(spec_all_args);
          $('#Z_item_code').val(spec_all_code);
        }
      });
            
      //绑定添加购物车事件
      $('#Z_add2cart').on('click', function(){
        //判断是否已经选齐规格
        if(_self.checkSpecSelect() == 1 && $('#Z_item_stock').val() > 0){
          cart.add();
        }else{
          _self.openSpcModal();
          _self.is_buynow = 0;
          return false;
        }
      });
      
      /*秒杀*/
      _self.seckill(seckill);
      
      //绑定立即购买事件
      $('#Z_quick_buy').on('click', function(){
        //判断是否已经选齐规格
        if(_self.checkSpecSelect() == 1 && $('#Z_item_stock').val() > 0){
          cart.add(1);
        }else{
          _self.openSpcModal();
          _self.is_buynow = 1;
          return false;
        }
      });

      //绑定规格选择确认事件
      _self.specSubmitEvent();

      $('.Z_quantity_act').on('click', function(){
        if($(this).hasClass('disabled')){return false;}
        var option = $(this).data('option');
        _self.quantity(option);
      });

      //绑定收藏事件
       $('.fav').on('click', function(){
         _self.addFav($(this).data('id'));
       });
    },
    
    //绑定规格选择确认事件
    specSubmitEvent : function(){
      var _self = this;
      $('#Z_spec_submit').on('click', function(){
        if(_self.checkSpecSelect() == 1){
          //关闭规格选择弹窗
          _self.closeSpecModal();
          //立即购买 or 加入购物车 TODO:增加判断
          (_self.is_buynow == 1) ? cart.add(1) : cart.add(0);
          //重置数量和已选规格
          _self.resetNumSpec();
        }else{
          $.ui.error('请您选择规格');
        }
      });
    },
    
    /*秒杀*/
    seckill : function(seckill){
      if(seckill){
        var now = Math.round(new Date().getTime()/1000);
        if(now > seckill.expire_time){
          //过期
          return;
        }else{
          if(now < seckill.start_time){
            //尚未开始
            $('.Z_seckill_time').html('距离秒杀开始有<br/><span class="text-danger day">00</span>天<span class="text-danger hour">00</span>时<span class="text-danger minute">00</span>分<span class="text-danger second">00</span>秒');
            this.countDown(this.unix_to_datetime(seckill.start_time), ".Z_seckill_time");
          }else{
            //进行中
            $('.Z_seckill_time').html('距离秒杀结束有<br/><span class="text-danger day">00</span>天<span class="text-danger hour">00</span>时<span class="text-danger minute">00</span>分<span class="text-danger second">00</span>秒');
            this.countDown(this.unix_to_datetime(seckill.expire_time), ".Z_seckill_time");
            $('.Z_seckill_btn').removeClass('disabled').attr('id', 'Z_quick_buy');
          }
        }
      }
    },
    
    /*
     * 时间戳转字符串
     * return 2015-10-16 15:10:32
     */
    unix_to_datetime : function(unix) {
        var now = new Date(parseInt(unix) * 1000);
        return now.toISOString().substring(0, 10) + ' ' + now.toTimeString().substring(0, 8);
    },

    /*倒计时*/
    countDown : function(time, id){
      var day_elem = $(id).find('.day');
      var hour_elem = $(id).find('.hour');
      var minute_elem = $(id).find('.minute');
      var second_elem = $(id).find('.second');
      var reg=new RegExp("-","g");
      time=time.replace(reg,"/");
      var end_time = new Date(time).getTime();//月份是实际月份-1
      var sys_second = (end_time - new Date().getTime())/1000;
      var timer = setInterval(function(){
        if (sys_second > 1) {
          sys_second -= 1;
          var day = Math.floor((sys_second / 3600) / 24);
          var hour = Math.floor((sys_second / 3600) % 24);
          var minute = Math.floor((sys_second / 60) % 60);
          var second = Math.floor(sys_second % 60);
          day_elem && $(day_elem).text(day);//计算天
          $(hour_elem).text(hour<10 ? "0" + hour : hour);//计算小时
          $(minute_elem).text(minute < 10 ? "0" + minute : minute);//计算分钟
          $(second_elem).text(second < 10 ? "0" + second : second);//计算秒杀
        } else {
          clearInterval(timer);
          common.reload();
        }
      }, 1000);
    },
    
    /*判断是否选齐规格*/
    checkSpecSelect: function(){
      var _self = this;
      return (_self.sku_selected_size < _self.sku_choose_size) ? 0 : 1;
    },
    /*重置已选择的商品数量和规格*/
    resetNumSpec: function(){
      var _self = this;
      $('#Z_item_quantity').val(1);
      $('.Z_spec_item li').removeClass('select');
      _self.sku_selected_size = 0;
    },
    /*显示/隐藏底部浮动购买按钮*/
    toggleFixedBar: function(){
      window.addEventListener('touchmove', function(event){
        if(!event.touches.length){
          return;
        }
        var touch = event.touches[0],
            startY = touch.pageY,
            height = $('#Z_buy_box').offset().top,
            fixed_navbar = $('.Z_fixed_navbar');
        if(startY - height > 300){
          fixed_navbar.addClass('layer-show');
        }else{
          fixed_navbar.removeClass('layer-show');
        }
      });
    },
    /*遮罩切换*/
    toggleBackdrop: function(){
      window.addEventListener('touchend', function(event){
        var spec_modal = document.querySelector('#Z_spec_info');
        if(spec_modal){
          spec_modal.classList.contains('active') ? $.ui.showBackdrop() : $.ui.hideBackdrop();
        }
      });
    },
    /*打开规格选择面板*/
    openSpcModal: function(is_buynow){
      var spec_modal = document.querySelector('#Z_spec_info');
      if(spec_modal && spec_modal.classList.contains('modal')){
        spec_modal.classList.add('active');
        $.ui.showBackdrop();
      }
    },
    /*关闭规格选择弹窗*/
    closeSpecModal: function(){
      var spec_modal = document.querySelector('#Z_spec_info');
      if(spec_modal && spec_modal.classList.contains('modal')){
        spec_modal.classList.remove('active');
        $.ui.hideBackdrop();
      }
    },
    emptyTips: function(){
      $.ui.message('抱歉，该商品暂时缺货！');
    },
    /*切换规格选择面板中商品图片*/
    switchPic: function(src){
      $('#Z_item_spc .img img').attr("src", src);
    },
    /*添加收藏*/
    addFav: function(id){
      if(C.UID <= 0){
        window.location.href = $.U('User/login');
        return false;
      }
      if($('#Z_item_'+id).find('.icon').hasClass('icon-like-fill')){
        $.ui.error('已经收藏过了！');
        return false;
      }
      $.ajax({
        type: 'POST',
        url: $.U('/Fav/add'),
        data: {id: id},
        dataType: 'json',
        success: function(res){
          if(res.status == 1){
            $.ui.success(res.info);
            $('#Z_item_' + id).html('<i class="icon icon-like-fill"></i>');
          }else if(res.status === 0){
            $.ui.error(res.info);
          }
        }
      });
    },
    /*删除收藏*/
    removeFav: function(id){
      $.ajax({
        type: 'POST',
        url: $.U('/Fav/remove'),
        data: {id: id},
        dataType: 'json',
        success: function(res){
          if(res.status === 1){
            $.ui.success(res.info);
            $('#Z_favitem_' + id).fadeOut(500, function(){
              $(this).remove();
              if($('.Z_item').size() === 0){
                $('.Z_item_list').html('<p class="list-empty">您暂时没有收藏商品！</p>');
              }
            });
          }else if(res.status === 0){
            $.ui.error(res.info);
          }
        }
      });
    },
    /*更改数量*/
    quantity: function(op){
      var quantity = $('#Z_item_quantity').val();
      var stock = $('#Z_item_stock').val(),
          quota = $('.Z_quota_num').size() === 1 ? parseInt($('.Z_quota_num').text()) : stock;
          stock = quota < stock? quota : stock;
 
      var new_quantity = (op == 'plus') ? parseInt(quantity) + 1 : parseInt(quantity) - 1;
      if(new_quantity >= 1 && new_quantity <= stock){
        $('#Z_item_quantity').val(new_quantity);
        $('#Z_selected_quantity').text(new_quantity);
      }
      $('.minus, .plus').removeClass('disabled');
      if(new_quantity <= 1){
        $('.minus').addClass('disabled');
      }
      if(new_quantity >= stock){
        $('.plus').addClass('disabled');
      }
    },
    /*选择商品页面*/
    selectItem: function (){
        $(document).on('click', '.Z_item_add', function (){
            var _obj = $(this),
                itemid = _obj.data('itemid');

            $.ajax({
                type: 'POST',
                url: $.U('Shop/addItem'),
                dataType: 'json',
                data: {itemid: itemid},
                success: function (res){
                    if(res.status == 1){
                        $.ui.success(res.info);
                        _obj.find('i').addClass('icon-jianhao1').removeClass('icon-jiahao1');
                        _obj.addClass('Z_item_delete').removeClass('Z_item_add');
                        _obj.find('span').html("移除分销");
                    }else{
                        $.ui.error(res.info);
                    }
                }
            });
        });
    },
    /*分销用户登陆，删除分销商品*/
    deleteSpdItem: function(){
      $(document).on('click','.Z_item_delete',function(){
        var _obj = $(this),
            item_id = _obj.data('itemid');
        var doRemoveItem = function(){
            if(!item_id){
              return false;
            }else{
              $.ajax({
                type:'POST',
                url: $.U('Shop/removeItem'),
                data:{itemid:item_id},
                dataType: 'json',
                success:function(res){
                  //console.log(res);
                  if(res.status == 1){
                      $.ui.success(res.info);
                      _obj.find('i').addClass('icon-jiahao1').removeClass('icon-jianhao1');
                      _obj.addClass('Z_item_add').removeClass('Z_item_delete');
                      _obj.find('span').html('加入分销');
                  }else{
                      $.ui.error(res.info);
                  }
                }
              });
            }
          };
          $.ui.confirm('确定删除该商品？', doRemoveItem);
      });
    }
  }

  module.exports = item;
});