<?php
/**
 * 用户账户模型
 * @version 2015080615 
 * @author Justin <justin@jipu.com>
 */

namespace Home\Model;

class UserAccountModel extends HomeModel{
  
  /**
   * 自动验证规则
   * @var array
   */
  protected $_validate = array(
    array('name', 'require', '名字不能为空', self::MUST_VALIDATE, 'regex', self::MODEL_BOTH),
    array('bankname', 'checkBankname', '请选择银行', self::MUST_VALIDATE, 'callback', self::MODEL_BOTH),
    array('account', 'require', '账户不能为空', self::MUST_VALIDATE, 'regex', self::MODEL_BOTH),
    array('reaccount', 'require', '确认账户不能为空', self::MUST_VALIDATE, 'regex', self::MODEL_BOTH),
    array('reaccount', 'checkAccount', '两次输入的账户不一致', self::MUST_VALIDATE, 'callback', self::MODEL_BOTH),
	array('verify','checkVerify', '验证码错误', self::MUST_VALIDATE, 'callback', self::MODEL_BOTH),
	array('randcode', 'checkRandcode', '短信验证码错误', self::MUST_VALIDATE, 'callback', self::MODEL_BOTH),
	);

  /**
   * 自动完成规则
   * @var array
   */
  protected $_auto = array(
    array('uid', 'is_login', self::MODEL_INSERT, 'function'),
    array('create_time', NOW_TIME, self::MODEL_BOTH),
  );
  
  function checkAccount(){
    return I('post.account') === I('post.reaccount');
  }
  
  /**
   * 检测手机验证码
   * @return bool
   * @version 2015080615
   * @author Justin <justin@jipu.com>
   */
  function checkRandcode(){
    $mobile = M('User')->getFieldById(UID, 'mobile');
    return check_ypsms_code($mobile, 2, I('post.randcode')); 
  }
  
  /**
   * 检测是否选择银行
   * @return bool
   * @version 2015080711
   * @author Justin <justin@jipu.com>
   */
  function checkBankname(){
    if('bankcard' == I('post.type') && !I('post.bankname')){
      return false;
    } 
    return true;
  }
  
  /**
   * 检测是否绑定提现账户
   * @param int $uid 用户id，默认UID
   * @return int 1有0无
   * @version 2015080709
   * @author Justin <justin@jipu.com>
   */
  function checkBind($uid = UID){
    $where = array(
      'uid' => $uid,
      'status' => '1'
    );
    return $this->where($where)->find() ? 1 : 0;
  }
	/**
     * 验证图形验证码
     */
   function checkVerify(){
        $code = I("post.verify");
        return check_verify($code, $id = 1, $reset = true);
    }
  
  
  
}
