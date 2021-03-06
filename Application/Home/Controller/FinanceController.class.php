<?php
/**
 * 现金账户
 * @version 2015102310
 * @author Max.Yu <max@jipu.com>
 */

namespace Home\Controller;

class FinanceController extends HomeController {
  
  protected function _initialize(){
    parent::_initialize();
    //判断是否登录
    parent::login();
    $this->assign('member', get_member_info(UID));
  }
  
  /**
   * 现金账户首页
   * @author Max.Yu <max@jipu.com>
   */
  public function index(){
    //可提现余额
    $this->withdraw_finance = A('Finance', 'Event')->getWithDrawFinance();
    $this->meta_title = '现金账户';
    $this->display();
  }
}
