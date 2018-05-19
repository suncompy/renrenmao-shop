!(function(seajs) {
  'use strict';
  seajs.config({
    // Sea.js 的基础路径
    base: C.JS + '/',
    // 别名配置
    alias: {
      //Lib
      'zepto': 'lib/zepto/zepto',
      'zepto.loadmore': 'plugin/zepto.loadmore',
      'zepto.loadtype': 'plugin/zepto.loadtype',
      'swipeSlide': 'plugin/zepto.swipeSlide.js',
      'zepto.imglazyload': 'plugin/zepto.imglazyload',
      'fastclick': 'lib/fastclick/fastclick',
      'webuploader': 'webuploader/webuploader.js',
      'jquery': 'lib/jquery/jquery-1.10.2.min.js',
      'wx': 'http://res.wx.qq.com/open/js/jweixin-1.0.0.js',
      'chart': 'lib/chart/chart.min',
      'echarts': 'lib/echarts/echarts',
      'layer':'lib/layer_mobile/layer',
      
      //Public Module
      'wechat': 'module/wechat',
      'core': 'module/core',
      'common': 'module/common',
      'slider': 'module/slider',
      'sosomap': 'module/sosomap',
      'loadmore': 'module/loadmore',
        'kefu':'module/kefu',
      
      //App Module
      'index': 'module/index',
      'item': 'module/item',
      'order': 'module/order',
      'comment': 'module/comment',
      'cart': 'module/cart',
      'coupon': 'module/coupon',
      'receiver': 'module/receiver',
      'member': 'module/member',
      'user': 'module/user',
      'crowdfunding': 'module/crowdfunding',
      'address': 'module/address',
      'sdp': 'module/sdp',
      'shop': 'module/shop',
      'union': 'module/union',
      'userAccount': 'module/userAccount',
      'message': 'module/message',
      'delivery': 'module/delivery',
      'redpackage': 'module/redpackage',
      'invoice': 'module/invoice',
      'join': 'module/join',
      'baidu': 'module/baidu',
      'area': 'module/area',
    },
    // 变量配置
    vars: {
      'locale': 'zh-cn',
    },
    
    //加版本号
    map: [
      [ /^(.*\.(?:css|js))(.*)$/i, '$1?1512311525' ]
    ],

    // 预加载项
    // preload: ['zepto', 'fastclick', 'core', 'common'],
    preload: ['zepto', 'core', 'common'],

    // 调试模式
    debug: true,
    // 文件编码
    charset: 'utf-8'
  });
  
}(window.seajs));