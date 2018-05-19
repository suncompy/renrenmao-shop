/**
 * 发票js
 */
define('module/invoice', function (require, exports, module){

  'use strict';
  
  require('zepto.loadtype');

  var invoice = {
		init:function(){
			var _self = this;
			$('ul.Z_spec_item li.Z_sale_porp').on('click',function(){
				var index = $(this).index();
		        var type = $(this).attr('data-key');
		        $(this).addClass('select').siblings('li').removeClass('select');
		        $('div.form').eq(index).show().siblings('.form').hide();
		        $('div#m-invoice-main input[name="type"]').val(type);
			});
			
			$('button.btn-submit').on('click',function(){
				if(_self.validation()){
					_self.submit();
				}
				return false;
			});

            $("#tax").bind('change', function () {
                var a = $(this).val();
                   console.log(a);
                //  $("#findMsg").attr("value", inputVal);
                if(a == "个人"){
                    $('#tax_num').hide();
                    $('#title_cont').hide();
                }else{
                    $('#tax_num').show();
                    $('#title_cont').show();

                }
            });
            $("#tax1").bind('change', function () {
                var a = $(this).val();
                console.log(a);
                //  $("#findMsg").attr("value", inputVal);
                if(a == "个人"){
                    $('#tax_num1').hide();
                    $('#title_cont1').hide();
                }else{
                    $('#tax_num1').show();
                    $('#title_cont1').show();

                }
            });
		},
		/*发票表单验证*/
	    validation: function(type){
	      var _self = this,
	      type = $('div#m-invoice-nav input[name="type"]').val(),
	      /*普通发票内容*/
	      normal_title = $('div#m-invoice-box select[name="normal_title"]').val(),
          tax_num =   $('div#m-invoice-box input[name="tax_num"]').val(),
          title_content=$('div#m-invoice-box input[name="title_content"]').val(),
          normal_content = $('div#m-invoice-box select[name="normal_content"]').val(),
	      /*电子发票内容*/
          tax_num1 =   $('div#m-invoice-box input[name="tax_num1"]').val(),
          title_content1=$('div#m-invoice-box input[name="title_content1"]').val(),
          ele_title = $('div#m-invoice-box select[name="ele_title"]').val(),
	      ele_content = $('div#m-invoice-box select[name="ele_content"]').val(),
	      /*增值税发票内容*/
	      unit = $('div#m-invoice-box input[name="unit"]').val(),
	      code = $('div#m-invoice-box input[name="code"]').val(),
	      address = $('div#m-invoice-box input[name="address"]').val(),
	      tel = $('div#m-invoice-box input[name="tel"]').val(),
	      bank = $('div#m-invoice-box input[name="bank"]').val(),
	      account = $('div#m-invoice-box input[name="account"]').val(),
	      inc_content = $('div#m-invoice-box select[name="inc_content"]').val();
	      
	      if(type == '' || typeof type=='undefined'){
	          $.ui.alert('请选择类型');
	          return false;
	      }
	      /*普通发票内容*/
	      if(type == '1'){
	          if(normal_title == ''){
	              $.ui.alert('请填写发票title');
	              return false;
	          }
	          if(normal_content == '' || normal_content<1){
	              $.ui.alert('请选择发票内容');
	              return false;
	          }
	      }
	      /*电子发票内容检测*/
	      if(type == '2'){
	          if(ele_title == ''){
	              $.ui.alert('请填写发票title');
	              return false;
	          }
	          if(ele_content == '' || ele_content<1){
	              $.ui.alert('请选择发票内容');
	              return false;
	          }
	      }
	      /*电子发票内容检测*/
	      if(type == '3'){
	          if(unit == ''){
	              $.ui.alert('请填写单位名称');
	              return false;
	          }
	          if(code == ''){
	              $.ui.alert('请填写纳税人识别码');
	              return false;
	          }
	          if(address == ''){
	              $.ui.alert('请填写注册地址');
	              return false;
	          }
	          var pattern = /^1[34578]\d{9}$/;   
	          if(!pattern.test(tel)){
	              $.ui.alert('请填写正确的注册电话');
	              return false;
	          }
	          if(bank == ''){
	              $.ui.alert('请填写开户银行');
	              return false;
	          }
	          if(account == ''){
	              $.ui.alert('请填写银行账户');
	              return false;
	          }
	          if(inc_content == ''  || inc_content<1){
	              $.ui.alert('请选择发票内容');
	              return false;
	          }
	      }

	      return true;
	    },
	    /*提交发票信息*/
	    submit: function(){
	      var data = {
	        type: $('div#m-invoice-nav input[name="type"]').val(),
	        normal_title : $('div#m-invoice-box select[name="normal_title"]').val(),
	        normal_content : $('div#m-invoice-box select[name="normal_content"]').val(),

            tax_num :   $('div#m-invoice-box input[name="tax_num"]').val(),
            title_content :$('div#m-invoice-box input[name="title_content"]').val(),

	        ele_title : $('div#m-invoice-box select[name="ele_title"]').val(),
	        ele_content : $('div#m-invoice-box select[name="ele_content"]').val(),

            tax_num1 :   $('div#m-invoice-box input[name="tax_num1"]').val(),
            title_content1 :$('div#m-invoice-box input[name="title_content1"]').val(),


	        /*增值税发票内容*/
	        unit : $('div#m-invoice-box input[name="unit"]').val(),
	        code : $('div#m-invoice-box input[name="code"]').val(),
	        address : $('div#m-invoice-box input[name="address"]').val(),
	        tel : $('div#m-invoice-box input[name="tel"]').val(),
	        bank : $('div#m-invoice-box input[name="bank"]').val(),
	        account : $('div#m-invoice-box input[name="account"]').val(),
	        inc_content : $('div#m-invoice-box select[name="inc_content"]').val()
	        
	      }
	      $.ajax({
	        type: 'POST',
	        dataType: 'json',
	        url: $.U('Invoice/save'),
	        data: data,
	        success: function(res){
	          if(res.status === 1){
	            $.ui.success('恭喜，操作成功！');
	            $('#invoice_title').val(res.rtn);
	            var html = res.html;
	            $('p.payment span').remove();
	            $('p.payment a').before(html);
	            $('#invoice').removeClass('active');
	          }else{
	              $.ui.alert(res.info);
	          }
	        }
	      });
	    },
  };
  
  module.exports = invoice;
});