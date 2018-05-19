<?php

namespace Addons\UploadImage;
use Common\Controller\Addon;

/**
 * 图片上传插件,可截取
 * @author Max.Yu <max@jipu.com>
 */

class UploadImageAddon extends Addon{

  public $info = array(
    'name' => 'UploadImage',
    'title' => '图片上传（可裁剪）',
    'description' => '图片上传插件（可裁剪）',
    'status' => 1,
    'author' => 'Buddha',
    'version' => '2.0'
  );

  public function install(){
    return true;
  }

  public function uninstall(){
    return true;
  }

  //实现的UploadImage钩子方法
  public function uploadImage($param){
    $param['images'] = get_images_info($param['value'], 'id, path');
    $this->assign('param', $param);
    $tpl = ($param['tpl']) ? $param['tpl'] : 'upload';
    $this->display($tpl);
  }
}