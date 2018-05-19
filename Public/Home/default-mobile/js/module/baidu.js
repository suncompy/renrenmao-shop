define('module/baidu', function(require, exports, module){

  'use strict';
  require('webuploader');
  var $ = require('jquery');
  var baidu = { 
    upload:function(bindid ,handle_url ,jspath){
      
      // 初始化Web Uploader
        var uploader = WebUploader.create({
            // 选完文件后，是否自动上传。
            auto: true,
            // swf文件路径
            swf: jspath+'/webuploader/Uploader.swf',
            // 文件接收服务端。
            server: handle_url,
            // 选择文件的按钮。可选。
            // 内部根据当前运行是创建，可能是input元素，也可能是flash.
            pick: {
                id:bindid,
                multiple :false,
            },
            // 只允许选择图片文件。
            accept: {
                title: 'Images',
                extensions: 'gif,jpg,jpeg,bmp,png',
                mimeTypes: 'image/*',
            },
        });
        uploader.on( 'uploadSuccess' , function( file ,response) {
          var $ = require('zepto');
            if(response.status == 0){
                $.ui.alert('上传失败，请重新上传试试');
            }else{
              $('.img').attr('src' ,response.path);
              $('.img_value').val(response.id);
              $.ui.alert("上传成功");
            }
        });
    },
    
    /*init为初始化方法，自动执行*/
    init: function(){

    }
  };
  
  baidu.init();
  module.exports = baidu;
});