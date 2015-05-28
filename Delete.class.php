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
 * 删除数据
 *
 * @author Administrator
 *
 */
class Delete extends user {
	
	/**
	 * 用户收货地址删除
	 * @param array $data
	 * @throws Exception
	 * @return multitype:number string NULL
	 */
	public function deladdress($data){
		$returndata = array ();
		try {
			if(empty($data['addressid'])){
				throw new Exception('地址参数ID不能为空');
			}
			$addressid = explode(',', $data['addressid']);
			$addid = '';
			foreach ($addressid as $v){
			    if($v>0){
			        $addid .= $v.',';
			    }
			}
			$addid = substr($addid, 0,-1);
			$del = $this->update('clo_address', array('deleted'=>1), 'deleted = 0 and userid ='.$data['userid'].' and id in ('.$addid.')');
			if($del){
				$address = $this->fetAll('clo_address','*','status desc,published desc','deleted = 0 and userid ='.$data['userid']);
				$this->redis->set(REDIS_PREFIX.'address_'.$data['userid'],json_encode($address,JSON_UNESCAPED_UNICODE));
			}else{
				throw new Exception('用户地址删除失败'.$addid);
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