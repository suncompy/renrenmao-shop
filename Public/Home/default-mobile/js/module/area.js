/**
 *地区联动
 */
define('module/area', function(require, exports, module){
  'use strict';
  var area = {
    web:function(){
      var url = $.U('Area/getChild');
      $('.area>select').change(function(){
          handledata(this);
      });
      function handledata(obj){
        var values = $(obj).val();
        // alert(values);
        $.ajax({
            type: "POST",
            url: url,
            data: 'pid='+ values,
            dataType: 'json',
            async: false,
            success: function(data){
              if(data){
                var html = '';
                var next = $(obj).next();
                $.each( data , function(ins,el){
                    html += "<option value='"+el.id+"'>"+el.title+"</option>" ;
                })
                $(next).empty();
                $(next).append(html);
                $(next).find('option').first().trigger('change');
              }
            }
        });
      }
    }
  }

  module.exports = area;
});