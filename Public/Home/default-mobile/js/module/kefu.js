/**********微信模块**********/
define('module/kefu' ,function(require, exports, module){
    var opendiv = '';
  var kefu = {
       init : function(){
           this.bindEvent();
       },
      bindEvent : function(){
          $(document).off('click','#kefu').on('click','#kefu',this.dialogEvent);
          $(document).off('click','#closeBtn,#closeBtn2').on('click','#closeBtn,#closeBtn2',this.closeEvent);
          $(document).off('click','#submitBtn').on('click','#submitBtn',this.submitEvent);
      },
      submitEvent : function(){
          var url = $.U('Kefu/addmessage');
          var message_val = $('#message_box').val();
          var username_val = $('#username').val();
          if(kefu.regEvent(username_val) == false){
              kefu.layerEvent('你输入的号码不合法');
              return ;
          }
          if(message_val == ''){
              kefu.layerEvent('请输入10个字以上的信息');
              return ;
          }
          var param = {username:username_val,message:message_val};
          $.get(url,param,function(data){
              kefu.layerEvent(data.message);
              if(data.status == 1){
                  setTimeout(function(){
                      layer.close(opendiv);
                  },3000);
              }
          });
      },
      dialogEvent : function(){
          var html = '<header class="bar bar-nav"><a href="javascript:void(0)" id="closeBtn" class="pull-right icon icon-close"></a><h1 class="title">留言</h1></header>';
          html += '<div style="margin-top:45px;"><div class="control-group"><input type="text" name="username" value="" id="username" style="padding:0px 0px;text-indent: 15px;" placeholder="手机"></div><div class="control-group"><textarea rows="3" cols="20" placeholder="留言" style="padding:15px 0px;text-indent: 15px;" id="message_box"></textarea></div>';
          html += '<div class="control-action"><button class="btn btn-positive btn-block" id="submitBtn">确认</button><a href="javascript:void(0)" class="btn btn-block" id="closeBtn2">取消</a></div></div>';
          opendiv  = layer.open({
              type: 1
              ,content: html
              ,anim: 'up'
              ,style: 'position:fixed; left:0; top:0; width:100%; height:100%; border: none; -webkit-animation-duration: .5s; animation-duration: .5s;'
          });
      },
      closeEvent : function(){
          layer.close(opendiv);
      },regEvent : function(value){
          var validateReg = /^([1][34578]\d{9})|\d{6}$/;
          return validateReg.test(value);
      },layerEvent :function(str){
          layer.open({
              content: str
              ,skin: 'msg'
              ,time:2 //2秒后自动关闭
          });
      }
  };

  module.exports = kefu; //导出模块
});