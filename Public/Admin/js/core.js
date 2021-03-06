/*扩展Core对象*/
(function($){
  /**
   * 获取Core基础配置
   * @type {object}
   */
  var Core = window.Core;

  /*基础对象检测*/
  Core || $.error('基础配置没有正确加载！');

  /**
   * 解析URL
   * @param  {string} url 被解析的URL
   * @return {object}     解析后的数据
   */
  Core.parse_url = function(url){
    var parse = url.match(/^(?:([a-z]+):\/\/)?([\w-]+(?:\.[\w-]+)+)?(?::(\d+))?([\w-\/]+)?(?:\?((?:\w+=[^#&=\/]*)?(?:&\w+=[^#&=\/]*)*))?(?:#([\w-]+))?$/i);
    parse || $.error('url格式不正确！');
    return{
      'scheme': parse[1],
      'host': parse[2],
      'port': parse[3],
      'path': parse[4],
      'query': parse[5],
      'fragment': parse[6]
    };
  }

  Core.parse_str = function(str){
    var value = str.split('&'), vars = {}, param;
    for(val in value){
      param = value[val].split('=');
      vars[param[0]] = param[1];
    }
    return vars;
  }

  Core.parse_name = function(name, type){
    if(type){
      /*下划线转驼峰*/
      name.replace(/_([a-z])/g, function($0, $1){
        return $1.toUpperCase();
      });

      /*首字母大写*/
      name.replace(/[a-z]/, function($0){
        return $0.toUpperCase();
      });
    }else{
      /*大写字母转小写*/
      name = name.replace(/[A-Z]/g, function($0){
        return '_' + $0.toLowerCase();
      });

      /*去掉首字符的下划线*/
      if(0 === name.indexOf('_')){
        name = name.substr(1);
      }
    }
    return name;
  }

  //scheme://host:port/path?query#fragment
  Core.U = function(url, vars, suffix){
    var info = this.parse_url(url), path = [], param = {}, reg;

    /* 验证info */
    info.path || $.error('url格式错误！');
    url = info.path;

    /* 组装URL */
    if(0 === url.indexOf('/')){ //路由模式
      this.MODEL[0] == 0 && $.error("该URL模式不支持使用路由!(" + url + ")");

      /* 去掉右侧分割符 */
      if("/" == url.substr(-1)){
        url = url.substr(0, url.length - 1)
      }
      url = ('/' == this.DEEP) ? url.substr(1) : url.substr(1).replace(/\//g, this.DEEP);
      url = '/' + url;
    }else{ //非路由模式
      /* 解析URL */
      path = url.split('/');
      path = [path.pop(), path.pop(), path.pop()].reverse();
      path[1] || $.error("ThinkPHP.U(" + url + ")没有指定控制器");

      if(path[0]){
        param[this.VAR[0]] = this.MODEL[1] ? path[0].toLowerCase() : path[0];
      }

      param[this.VAR[1]] = this.MODEL[1] ? this.parse_name(path[1]) : path[1];
      param[this.VAR[2]] = path[2];

      url = '?' + $.param(param);
    }

    /* 解析参数 */
    if(typeof vars === 'string'){
      vars = this.parse_str(vars);
    }else if(!$.isPlainObject(vars)){
      vars = {};
    }

    /* 解析URL自带的参数 */
    info.query && $.extend(vars, this.parse_str(info.query));

    if(vars){
      url += '&' + $.param(vars);
    }

    if(0 != this.MODEL[0]){
      url = url.replace('?' + (path[0] ? this.VAR[0] : this.VAR[1]) + '=', '/')
              .replace('&' + this.VAR[1] + '=', this.DEEP)
              .replace('&' + this.VAR[2] + '=', this.DEEP)
              .replace(/(\w+=&)|(&?\w+=$)/g, '')
              .replace(/[&=]/g, this.DEEP)
              .replace(/\/$/, '');

      /* 添加伪静态后缀 */
      if(false !== suffix){
        suffix = suffix || this.MODEL[2].split('|')[0];
        if(suffix){
          url += '.' + suffix;
        }
      }
    }

    url = this.APP + url;
    return url;
  }

  /* 设置表单的值 */
  Core.setValue = function(name, value){
    var first = name.substr(0, 1), input, i = 0, val;
    if(value === "")
      return;
    if("#" === first || "." === first){
      input = $(name);
    }else{
      input = $("[name='" + name + "']");
    }

    if(input.eq(0).is(":radio")){ //单选按钮
      input.filter("[value='" + value + "']").each(function(){
        this.checked = true
      });
    }else if(input.eq(0).is(":checkbox")){ //复选框
      if(!$.isArray(value)){
        val = new Array();
        val[0] = value;
      }else{
        val = value;
      }
      for(i = 0, len = val.length; i < len; i++){
        input.filter("[value='" + val[i] + "']").each(function(){
          this.checked = true
        });
      }
    }else{  //其他表单选项直接设置值
      input.val(value);
    }
  }

  /**
   * 实现类似PHP implode的函数，将对象组合为字符串
   * 
   * @param needle 事件节点
   * @param haystack 事件节点
   * @param argStrict 事件节点
   */
  Core.implode = function(glue, pieces){
    //  discuss at: http://phpjs.org/functions/implode/
    // original by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
    // improved by: Waldo Malqui Silva
    // improved by: Itsacon (http://www.itsacon.net/)
    // bugfixed by: Brett Zamir (http://brett-zamir.me)
    // example 1: implode(' ', ['Kevin', 'van', 'Zonneveld']);
    // returns 1: 'Kevin van Zonneveld'
    // example 2: implode(' ', {first:'Kevin', last: 'van Zonneveld'});
    // returns 2: 'Kevin van Zonneveld'

    var i = '';
    var retVal = '';
    var tGlue = '';
    if(arguments.length === 1){
      pieces = glue;
      glue = '';
    }
    if(typeof pieces === 'object'){
      if(Object.prototype.toString.call(pieces) === '[object Array]'){
        return pieces.join(glue);
      }
      for(i in pieces){
        retVal += tGlue + pieces[i];
        tGlue = glue;
      }
      return retVal;
    }
    return pieces;
  }

  /**
   * 让函数只执行一次
   */
  Core.runonce = function(func, time){
    var timer;
    return function(){
      window.clearTimeout(timer);
      timer = window.setTimeout(function(){
        func();
      }, time || 300);
    };
  }

  /**
   * 将uri转换为对象格式，用于前端参数传递
   *
   * @param uri URI 格式的数据
   */
  Core.uri_to_obj = function(uri){
    if(!uri)
      return{};
    var obj = {};
    var args = uri.split('&');
    var len;
    var arg;
    len = args.length;
    while(len-- > 0){
      arg = args[len];
      if(!arg){
        continue;
      }
      arg = arg.split('=');
      obj[arg[0]] = arg[1];
    }
    return obj;
  };

  /**
   * 获取事件节点的参数
   *
   * @param node 事件节点
   */
  Core.get_args = function(node){
    node.args || (node.args = this.uri_to_obj(node.attr('args')));
    return node.args;
  };

  /**
   * 获取字符串的真实长度
   */
  Core.get_str_length = function(str){
    return str.replace(/[\u0391-\uFFE5]/g, 'aa').length;
  }

  /**
   * 判断是否为微信浏览器
   */
  Core.is_weixin = function(){
    var user_agent = navigator.userAgent.toLowerCase();
    if(user_agent.match(/MicroMessenger/i) == 'micromessenger'){
      return true;
    }else{
      return false;
    }
  }

  /**
   * ajaxForm提交
   */
  var formhash = $('meta[property="formhash"]').attr('content');
    if(typeof($.ajaxSetup) === 'function'){
      $.ajaxSetup({
        data: {formhash: formhash}
      });
    }else if(typeof($.ajaxSettings) === 'object'){
      $.ajaxSettings.dataAdd = 'formhash=' + formhash;
    }
  /**
   * form提交注入hash 
   */
  $(function(){
    var formhash = $('meta[property="formhash"]').attr('content');
    $('form[method="post"]').each(function(){
      var _self = $(this);
      formhash_size =_self.find('input[name="formhash"]').size();
      if(formhash_size === 0){
        $('<input type="hidden" name="formhash" value="'+ formhash +'"/>').appendTo(_self);
      }
    });
  });
  
})($);