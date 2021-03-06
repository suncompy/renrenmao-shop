/**
 * 用户提现账户
 */
define('module/userAccount', function(require, exports, module){
  'use strict';

  var userAccount = {
    init: function(){
      var _self = this;
      _self.chooseBindType();
      _self.listBind();
    },

    /*选择绑定类型*/
    chooseBindType: function(){
      $('.Z_user_account_add').on('click', function(){
        $.ui.select('#Z_choose_bindtype');
      })
    },
    /*列表事件绑定*/
    listBind: function(){
      var _self = this;
      $('.Z_account_remove').on('click', function(){
        var id = $(this).data('id');
        if(id){
          _self.remove(id);
        }
      });
    },
    /*删除*/
    remove: function(id){
      var doRemove = function(){
        $.ajax({
          type: 'POST',
          url: $.U('userAccount/remove'),
          data: {id: id},
          dataType: 'json',
          success: function(res){
            if(res.status == 1){
              $('.Z_account_item_' + id).fadeOut();
              $.ui.success('删除成功');
            }else{
              $.ui.error(res.info);
            }
          }
        });
      }
      $.ui.confirm('确定删除该账户？', doRemove);
    },
  }

  module.exports = userAccount;
});