<?php
/**
* 用户提现账户控制器
* @author Justin <justin@jipu.com>
*/

namespace Home\Controller;

class UserAccountController extends HomeController{
  
  protected function _initialize(){
    //记录当前页URL地址Cookie，点击我的登录完成后跳转至个人中心
    Cookie('__forward__', $_SERVER['REQUEST_URI']);
    parent::_initialize();
    //判断是否登录
    parent::login();
  }
  
  function index(){
    $where = array(
      'uid' => UID,
      'status' => 1
    );
    
    $lists = $this->lists('UserAccount', $where);
    $this->lists = A('UserAccount', 'Event')->getHiddenAccount($lists);
    //可提现余额
    $this->withdraw_finance = A('Finance', 'Event')->getWithDrawFinance();
    $this->meta_title = '提现账户';
    $this->display();
  }

  /**
   * 添加银行卡或支付宝账号
   * @author Max.Yu <max@jipu.com>
   */
  public function add($type = 'alipay'){
    $user = M('User')->where(array('id'=>UID))->find();
    //加载提现银行
    $this->assign('user',$user);
    $this->lists = C('BANK_LISTS');
    $this->meta_title = '绑定支付宝或银行卡';
    $this->type = $type;
    $this->display();
  }
  
  public function remove($id = null){
    if(IS_POST){
      if(empty($id)){
        $this->error('非法请求！');
      }
      $res = M('UserAccount')->where(array('id' => $id, 'uid' => UID))->setField('status', -1);
      if($res){
        $this->success('删除提现账户成功！');
      }else{
        $this->error('删除提现账户失败！');
      }
    }else{
      $this->error('非法请求');
    }
  }
  
  public function update(){
    if(IS_POST){
      $model = D('UserAccount');
      if(false !== $model->update()){
        $this->success('添加成功！', U('index'));
      }else{
        $error = $model->getError();
        $this->error(empty($error) ? '未知错误！' : $error);
      }
    }else{
      $this->redirect('index');
    }
  }
  
}
