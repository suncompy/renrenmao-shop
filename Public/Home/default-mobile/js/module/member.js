define('module/member', function(require, exports, module){

  'use strict';

  var member = {
    init: function(){
      // 初始化充值方式
      $('.J_recharge > li').on('click', function(){
        $(this).addClass('selected').children('input').prop('checked', true);
        $(this).siblings().removeClass('selected');
      });
    },
    /*手机绑定*/
    mobile_bind: function(){
      $('.Z_binded p a').click(function(){
        $('.Z_binded').hide();
        $('.Z_bind-form').removeClass('hide');
        $('.Z_mobile').focus();
      });
      var form = $('.Z_bind-form');
      form.on('submit', function(){
        $('.Z_submit').attr('disabled', true).val('处理中...');
        $.post(form.attr('action'), form.serialize(), function(json){
          if(json.status === 1){
            UI.success(json.info);
            core.reload(1000);
          }else{
            UI.error(json.info);
            $('.Z_submit').removeAttr('disabled').val('提交验证');
          }
        }, 'json');
        return false;
      });
    },
    /*手机端注册协议*/
    zhuce: function(){
        $('#zhuce-div').click(function(){
            $('#Z_spec_info').addClass('active');
        });
    },
    /*用户注册协议*/
    agree : function(){
        $('#registerBtn').on('tap', function() {
            return false;
            /*if($('#register_agree').hasClass('active')) {
             return true;
             }else{
             $.ui.error('请阅读并同意协议');
             return false;
             } */
        });
    },
    /*上级推荐人解绑*/
    unBind: function(uid){
      $.ui.confirm('确认要解除绑定吗?', function(){
        $.ajax({
          type: 'POST',
          url: $.U('Shop/UnBindRecommender'),
          data: {uid:uid},
          dataType: 'json',
          success: function(res){
            if(res.status == 1){
              $.ui.success(res.info);
              common.reload();
            }else{
              $.ui.error(res.info);
            }
          }
        });
      });
    },
    /*优惠券、礼品卡绑定*/
    discountBind:function(){
      var _self = this;
      //礼品卡
      $('.J_card_click').on('click',function(){
        $('.J_card_bind').css('display','block');
      })
      $('.J_cancel').on('click',function(){
        $('.J_card_bind').css('display','none');
      });
      $('.J_card_add').on('click',function(){
        _self.cardBind();
      });
      //优惠券
      $('.J_coupon_click').on('click',function(){
        $('.J_coupon_bind').css('display','block');
      });
      $('.Z_cancel').on('click',function(){
        $('.J_coupon_bind').css('display','none');
      });
      $('.J_coupon_add').on('click',function(){
        _self.couponBind();
      });
    },
    cardBind:function(){
      var number = $('#J_card_number').val();
      var password = $('#J_card_password').val();
      if(number == ''){
          $('#J_card_number').focus();
          $.ui.alert('请输入礼品卡卡号');
          return false;
      }
      if(password == ''){
        $('#J_card_password').focus();
        $.ui.alert('请输入礼品卡密码');
        return false;
      }
      $.ajax({
          type: 'POST',
          url: $.U('Member/cardBind'),
          data: {number: number, password: password},
          dataType: 'json',
          success: function(res){
            $('.J_card_bind').css('display','none');
            if(res.status == 1){
              $.ui.success(res.info);
              var is_expire = '';
              var html;
              if (res.data.is_expire == 1) {
                is_expire = 'date-expired';
              }
              if($('.list-empty').length > 0){
                $('.list-empty').remove();
                html = '<div class="card-list">\
                        <div class="item item-bg-1">\
                          <div class="hd">'+ res.data.name +'<span class="pull-right">卡号：'+res.data.number+'</span></div>\
                          <div class="bd"><h2>' + res.data.balance + '</h2></div>\
                          <div class="fd"><em class="pull-right '+is_expire+'">有效期：' + res.data.expire_time + '</div>\
                        </div>\
                      </div>';
                $('.content-padded').after(html);
              }else{
                 var html = '<div class="item item-bg-1">\
                                <div class="hd">'+ res.data.name +'<span class="pull-right">卡号：'+res.data.number+'</span></div>\
                                <div class="bd"><h2>' + res.data.balance + '</h2></div>\
                                <div class="fd"><em class="pull-right '+is_expire+'">有效期：' + res.data.expire_time + '</div>\
                            </div>';
                      $('.card-list').after(html);
              }
            }else{
              $.ui.alert(res.info);
            }
          }
        });
    },
    couponBind:function(){
      var number = $('#J_coupon_number').val();
      if(number == ''){
        $('#J_coupon_number').focus();
        $.ui.alert('请输入优惠券编号');
        return false;
      }
      $.ajax({
          type: 'POST',
          url: $.U('Member/couponactive'),
          data: {number: number},
          dataType: 'json',
          success: function(res){
            $('.J_coupon_bind').css('display','none');
            if(res.status == 1){
              $.ui.success(res.info);
              window.location.reload();
            }else{
              $.ui.alert(res.info);
            }
          }
        });
    }
  };

  member.init();
  module.exports = member;
});