<?php
// +----------------------------------------------------------------------
// | Qiduoke      2015-5-5 上午10:04:47
// +----------------------------------------------------------------------
// | Copyright (c) 2014-2014 http://clofood.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: cnvj <1403729427@qq.com>
// +----------------------------------------------------------------------

use Appclass\user;

/**
 * 更新数据
 *
 * @author Administrator
 *
 */
class Update extends user {
	
	/**
	 * 默认用户收货地址
	 * @param array $data
	 * @throws Exception
	 * @return multitype:number string NULL
	 */
	public function updateaddress($data){
		$returndata = array ();
		try {
			if(empty($data['addressid'])){
				throw new Exception('地址参数ID不能为空');
			}
			$this->update('clo_address', array('status'=>0), 'deleted = 0 and userid ='.$data['userid']);
			$del = $this->update('clo_address', array('status'=>1), 'deleted = 0 and userid ='.$data['userid'].' and id ='.$data['addressid']);
			if($del){
				$address = $this->fetAll('clo_address','*','status desc,published desc','deleted = 0 and userid ='.$data['userid']);
				$this->redis->set(REDIS_PREFIX.'address_'.$data['userid'],json_encode($address,JSON_UNESCAPED_UNICODE));
			}else{
				throw new Exception('用户地址设为默认失败');
			}
			$returndata ['result'] = 1;
			$returndata ['data'] = json_encode ( array('status'=>1), JSON_UNESCAPED_UNICODE );
		} catch ( Exception $e ) {
			$returndata ['result'] = 0;
			$returndata ['code'] = 20006;
			$returndata ['msg'] = $e->getMessage ();
		}
		return $returndata;
	}	
}
?>