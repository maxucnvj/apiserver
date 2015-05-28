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
 * 数据写入
 *
 * @author Administrator
 *
 */
class Insert extends user {
	
	/**
	 * 添加评价
	 * @param array $data
	 */
	public function comment($data){
		$returndata = array ();
		try {
			if(!is_int($data['productid'])){
				throw new Exception('错误的ID参数');
			}
			if(!is_int($data['order_no'])){
				throw new Exception('订单编号不能为空');
			}
			if(!is_int($data['stars'])){
				throw new Exception('评星参数错误');
			}
			$commentlen = strlen($data['comment']);
			if($commentlen >2000 && $commentlen <=10){
				throw new Exception('评价内容长度不符合');
			}
			$v = $this->fetRowCount('clo_orders_products','id','productid = '.$data['productid'].' and order_id = "'.$data['order_no'].'" and userid ='.$data['userid']);
			if($v['num'] <= 0){
				throw new Exception('您还没有购买商品不能评价');
			}
			$v = $this->fetRowCount('clo_comments','id','productid='.$data['productid'].' and order_no ="'.$data['order_no'].'"');
			if($v['num'] > 0){
				throw new Exception('您对这个商品已经有过评价');
			}
			$res = $this->add('clo_comments', array(
				'productid' => $data['productid'],
				'order_no'  => $data['order_no'],
				'published' => time(),
				'stars'		=> $data['stars'],
				'comment'	=> $data['comment'],
				'deleted'	=> 0,
				'userid'	=> $data['userid']						
			));
			if($res){
				$this->redis->incr(REDIS_PREFIX.'comment_'.$data['productid']);
			}
			$returndata ['result'] = 1;
			$returndata ['data'] = json_encode (array('status'=>1), JSON_UNESCAPED_UNICODE );
		} catch ( Exception $e ) {
			$returndata ['result'] = 0;
			$returndata ['code'] = 20006;
			$returndata ['msg'] = $e->getMessage ();
		}
		return $returndata;
	}
	
	/**
	 * 添加或修改地址
	 * @param array $data
	 * @throws Exception
	 * @return multitype:number string NULL
	 */
	public function addaddress($data){
		$returndata = array ();
		try {
			if(empty($data['realname'])){
				throw new Exception('收货人姓名不能为空');
			}
			if(!ismobile($data['mobile'])){
				throw new Exception('不是一个有效的手机号码');
			}
			if(empty($data['address'])){
				throw new Exception('收货人地址不能为空');
			}
			if(empty($data['province']) || empty($data['provinceid'])){
				throw new Exception('收货人省份不能为空');
			}
			if(empty($data['city']) || empty($data['cityid'])){
				throw new Exception('收货人城市不能为空');
			}
			if(empty($data['street']) || empty($data['streetid'])){
			    throw new Exception('收货人街道不能为空');
			}
			if(empty($data['village']) || empty($data['villageid'])){
			    throw new Exception('收货人小区不能为空');
			}
			if(isset($data['addressid']) && $data['addressid']!=''){
				$res = $this->update('clo_address', array(
						'userid' => $data['userid'],
						'realname'  => $data['realname'],
						'mobile'	=> $data['mobile'],
						'address'	=> $data['address'],
						'province'	=> $data['province'],
						'city'		=> $data['city'],
						'district'	=> $data['district'],
    				    'provinceid'=> $data['provinceid'],
    				    'cityid'	=> $data['cityid'],
    				    'street'    => $data['street'],
    				    'streetid'  => $data['streetid'],
    				    'village'   => $data['village'],
    				    'villageid' => $data['villageid'],
    				    'districtid'=> $data['districtid'],
						'deleted'	=> 0,
						'status'	=> 1
				),'userid ='.$data['userid'].' and id='.$data['addressid']);
			}else{
				$res = $this->add('clo_address', array(
						'userid' => $data['userid'],
						'realname'  => $data['realname'],
						'published' => time(),
						'mobile'	=> $data['mobile'],
						'address'	=> $data['address'],
						'province'	=> $data['province'],
						'city'		=> $data['city'],
						'district'	=> $data['district'],
    				    'provinceid'=> $data['provinceid'],
    				    'cityid'	=> $data['cityid'],
    				    'districtid'=> $data['districtid'],
    				    'street'    => $data['street'],
    				    'streetid'  => $data['streetid'],
    				    'village'   => $data['village'],
    				    'villageid' => $data['villageid'],
						'deleted'	=> 0,
						'status'	=> 1
				));
			}
			if($res){
				$addressid = isset($data['addressid'])?$data['addressid']:$this->getLastId();
				$this->update('clo_address', array('status'=>0), 'deleted = 0 and userid ='.$data['userid'].' and id !='.$addressid);
				$address = $this->fetAll('clo_address','*','status desc,published desc','deleted = 0 and userid ='.$data['userid']);
				$this->redis->set(REDIS_PREFIX.'address_'.$data['userid'],json_encode($address,JSON_UNESCAPED_UNICODE));
			}else{
				throw new Exception('操作失败，请重试');
			}
			$returndata ['result'] = 1;
			$returndata ['data'] = json_encode (array('status'=>1,'addressid'=>$addressid), JSON_UNESCAPED_UNICODE );
		} catch ( Exception $e ) {
			$returndata ['result'] = 0;
			$returndata ['code'] = 20006;
			$returndata ['msg'] = $e->getMessage ();
		}
		return $returndata;
	}
}
?>