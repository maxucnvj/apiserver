<?php
// +----------------------------------------------------------------------
// | Qiduoke      2015-1-6 下午4:59:27
// +----------------------------------------------------------------------
// | Copyright (c) 2014-2014 http://qidor.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: cnvj <1403729427@qq.com>
// +----------------------------------------------------------------------

namespace Appclass;

class user extends Pdomysql {
	
	/**
	 * 检查是否登录帐号
	 * @param array $config 全局配置
	 * @param array $userinfo 用户身份信息
	 */
	public function __construct($config,$userinfo,$redis){
		parent::__construct($config,$userinfo,$redis);
		if(!$userinfo['userid'] || $redis->get ( REDIS_PREFIX.'api_mobilecode_' . $userinfo['userid']) != $userinfo['mobilecode']){
			echo json_encode(array('result'=>null,'error'=>array('code'=>20007,'msg'=>'登录信息出错')),JSON_UNESCAPED_UNICODE);
			exit();
		}
	}
	
	/**
	 * 关闭数据库连接
	 * @see \Appclass\Pdomysql::__destruct()
	 */
	public function __destruct(){
		parent::__destruct();
	}

}

?>