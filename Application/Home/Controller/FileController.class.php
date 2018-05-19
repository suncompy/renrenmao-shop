<?php
/**
 * 文件控制器
 * 主要用于下载模型的文件上传和下载
 * @version 2014091618
 * @author Max.Yu <max@jipu.com>
 */

namespace Home\Controller;

class FileController extends HomeController {

  /**
   * 文件上传
   * @author Max.Yu <max@jipu.com>
   */
  public function upload(){
    $return  = array('status' => 1, 'info' => '上传成功', 'data' => '');
    /* 调用文件上传组件上传文件 */
    $File = D('File');
    $file_driver = C('DOWNLOAD_UPLOAD_DRIVER');
    $info = $File->upload(
      $_FILES,
      C('DOWNLOAD_UPLOAD'),
      C('DOWNLOAD_UPLOAD_DRIVER'),
      C("UPLOAD_{$file_driver}_CONFIG")
    );

    //记录附件信息
    if($info){
      $return['data'] = think_encrypt(json_encode($info['download']));
    } else {
      $return['status'] = 0;
      $return['info']   = $File->getError();
    }

    //返回JSON数据
    $this->ajaxReturn($return);
  }

  /**
   * 文件下载
   * @author Max.Yu <max@jipu.com>
   */
  public function download($id = null){
    if(empty($id) || !is_numeric($id)){
      $this->error('参数错误！');
    }

    $logic = D('Download', 'Logic');
    if(!$logic->download($id)){
      $this->error($logic->getError());
    }
  }
  
  /**
   * 上传图片
   * @author Max.Yu <max@jipu.com>
   */
  public function uploadPicture(){
    //TODO: 用户登录检测
    //返回标准数据
    $return  = array('status' => 1, 'info' => '上传成功', 'data' => '');
    //调用文件上传组件上传文件
    $Picture = D('Picture');
    $info = $Picture->upload($_FILES, C('PICTURE_UPLOAD'));
    //记录图片信息
    if($info){
      $return['status'] = 1;
      $temp = is_mobile() ? $info['file'] : $info['file'];
      $return = array_merge($temp, $return);
      //修正IOS图片上传图片自动反转问题
      rotate('./'.$return['path']);
    } else {
      $return['status'] = 0;
      $return['info']   = $Picture->getError();
    }

    if(is_numeric($_GET['compress_h']) && is_numeric($_GET['compress_w']) && $return['path']){
      $return['path'] = get_image_thumb($return['path'] , $_GET['compress_h'] , $_GET['compress_w']);
    }
    
    //返回JSON数据
    $this->ajaxReturn($return);
  }
  /**
   * 上传banner图
   * @author buddha <[email address]>
   * @return [type] [description]
   */
  public function uploadBanner(){
    $return  = array('status' => 1, 'info' => '上传失败', 'data' => '','pid'=>'');
    $base64 = htmlspecialchars($_POST['str']);
    if (preg_match('/^(data:\s*image\/(\w+);base64,)/', $base64, $result)) {
        $type = $result[2];
        //判断文件夹存在与否
        $rootPath = "./Uploads/Banner/";
        if(!file_exists($rootPath)){
          mkdir($rootPath,0777,true);
        }
        $new_file = $rootPath. time() . ".{$type}";
        if (file_put_contents($new_file, base64_decode(str_replace($result[1], '', $base64)))) {
          //写入文件夹之后，写入数据库
          $str = ltrim($new_file,'.');
          $arr['path'] = $str;
          $arr['md5'] = md5($str);
          $arr['sha1'] = md5(md5($str));
          $arr['status'] = 1;
          $arr['create_time'] = NOW_TIME;
          $return['pid'] = M('Picture')->add($arr);
          $return['info'] = '上传成功！';
          //写入文件夹之后，写入数据库
          $return['data'] = $str;
        }
    }
    $this->ajaxReturn($return);
  }
}
