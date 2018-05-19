define('module/user', function(require, exports, module){

  'use strict';

  var user = {
    
    /*获取短信验证码*/
    getRandCode: function(reqtype){
      var button = $('.Z_mobile_randcode_button');
      var mobile = $('.Z_mobile');
      var verify = $('#verify');
      var check_time = function(){
        var now_time = new Date().getTime(),
            s_mobile_no = sessionStorage.getItem('mobile_no'),
            s_mobile_endtime = sessionStorage.getItem('mobile_endtime'),
            time;
        if(s_mobile_no === mobile.val() && s_mobile_endtime > now_time){
          time = parseInt((s_mobile_endtime - now_time)/1000);
          button.removeClass('btn-primary').attr('disabled', true).html('<i>'+ time +'</i> 秒后重试');
          window.setTimeout(check_time, 1000);
          return false;
        }else{
          button.addClass('btn-primary').removeAttr('disabled').html('获取验证码');
          return true;
        }
      };
      mobile.on('change', check_time);
      button.on('click', function(){
        var mobile_no = mobile.val();
        var img_verify = verify.val();
        if(!$.is_mobile_number(mobile_no)){
          $.ui.error('请填写正确的手机号码');
          mobile.focus();
          return false;
        }
        /*if(!img_verify || img_verify == undefined || img_verify == ''){
          $.ui.error('请输入图形验证码');
          return false;
        }*/
        var res = check_time();
        $('.Z_randcode').focus();
        if(res){
          button.removeClass('btn-primary').attr('disabled', true).html('正在请求.');
          var formhash = $('meta[property="formhash"]').attr('content');
          $.ajax({
            type: 'POST',
            url: $.U('/User/getRandCode', 'type='+reqtype),
            data: {mobile: mobile_no, formhash: formhash,verify:img_verify},
            dataType: 'json',
            success: function(res){
              if(res.status === 1){
                $.ui.success(res.info);
                sessionStorage.setItem('mobile_no', mobile_no);
                sessionStorage.setItem('mobile_endtime', res.endtime * 1000);
                check_time();
              }else{
                $.ui.error(res.info);
                check_time();
              }
            }
          });
        }
      });
    },
      /*删除礼品卡*/
      delCoupon: function(){
          $(".J_del_coupon").on('click',function(e){
              e.preventDefault();
              var id = $(this).attr('data-id');
              var obj = $(this).parent(".coupon_items");
              $.ui.confirm('要删除吗', function(){
                  $.ajax({
                      type: 'POST',
                      url: $.U('Member/delCoupon', 'id=' + id),
                      dataType: 'json',
                      success: function(res){
                          if(res.status == 1){
                              $.ui.success(res.info);
                              obj.remove();
                          }else{
                              $.ui.error(res.msg);
                          }
                      }
                  })
              });
          });
      },
    
    /*微信绑定页面*/
    wechatbind: function(){
      //监控手机号
      var checked_no = '';
      var check_mobile = function(mobile){
        
        if(checked_no === mobile){
          
            //console.log(mobile);
          return ;
        }else{
          checked_no = mobile;
        }
        var check_url = $.U('User/checkMobile');
        $.ajax({
          type: 'POST',
          url: check_url,
          data: {mobile: mobile},
          dataType: 'json',
          success: function(res){
            $('#Z_bind_randcode, #Z_bind_verify, #Z_bind_password, #Z_bind_repassword, #Z_forgetpwd').addClass('hide');
            checked_no = mobile;
            if(res.status === 1){
              $('#Z_bind_password, #Z_forgetpwd').removeClass('hide');
              $.ui.alert('手机号已注册，请输入密码进行绑定');
              $('#Z_bind_form').attr('action', $.U('User/bind'));
              $('#Z_bind_submit').text('绑定账号');
            }else{
              $('#Z_bind_randcode, #Z_bind_verify, #Z_bind_password, #Z_bind_repassword').removeClass('hide');
              $('#Z_forgetpwd').addClass('hide');
              $('#Z_bind_randcode').focus();
              $('#Z_bind_form').attr('action', $.U('User/register'));
              $('#Z_bind_submit').text('创建账号');
            }
          }
        });
      };
      $('#Z_bind_submit').on('click', function(){
        $('#Z_bind_form').submit();
      });
      $('.Z_mobile').bind('keyup blur', function(){
        var mobile = $(this).val();
        if($.is_mobile_number(mobile)){
          check_mobile(mobile);
        }
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
    
    /*init为初始化方法，自动执行*/
    init: function(){
    }
  };
  
  user.init();
  module.exports = user;
});