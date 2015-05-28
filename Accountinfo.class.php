<?php
// +----------------------------------------------------------------------
// | Qiduoke 2015-1-6 下午5:41:02
// +----------------------------------------------------------------------
// | Copyright (c) 2014-2014 http://qidor.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: cnvj <1403729427@qq.com>
// +----------------------------------------------------------------------
use Appclass\user;

/**
 * 帐号管理类
 *
 * @author Administrator
 *        
 */
class Accountinfo extends user {
	
	/**
	 * 取用户收货地址
	 * @param array $data
	 * @return multitype:number string NULL
	 */
	public function address($data){
		$returndata = array ();
		try {
			$address = json_decode($this->redis->get(REDIS_PREFIX.'address_'.$data['userid']),true);
			if(!$address){
				$address = $this->fetAll('clo_address','*','status desc,published desc','deleted = 0 and userid ='.$data['userid']);
				$this->redis->set(REDIS_PREFIX.'address_'.$data['userid'],json_encode($address,JSON_UNESCAPED_UNICODE));
			}
			$addressdata = array();
			foreach ($address as $v){
				$addressdata[] = array(
					'addressid' => $v['id'],
					'realname'	=> $v['realname'],
					'mobile'	=> $v['mobile'],
					'province'	=> $v['province'],
					'city'		=> $v['city'],
					'district'	=> $v['district'],
				    'provinceid'=> $v['provinceid'],
				    'cityid'	=> $v['cityid'],
				    'districtid'=> $v['districtid'],
				    'street'    => $v['street'],
				    'streetid'  => $v['streetid'],
				    'village'   => $v['village'],
				    'villageid' => $v['villageid'],
					'address'	=> $v['address'],
					'status'	=> $v['status']					
				);
			}			
			$returndata ['result'] = 1;
			$returndata ['data'] = json_encode ( array('datalist'=>$addressdata), JSON_UNESCAPED_UNICODE );
		} catch ( Exception $e ) {
			$returndata ['result'] = 0;
			$returndata ['code'] = 20006;
			$returndata ['msg'] = $e->getMessage ();
		}
		return $returndata;		
	}
	
	/**
	 * 取用户单个收货地址
	 * @param array $data
	 * @return multitype:number string NULL
	 */
	public function singleaddress($data){
	    $returndata = array ();
	    try {
	        $v = $this->fetOne('clo_address','*','deleted = 0 and userid ='.$data['userid'].' and id ='.$data['addressid'] );
	        if(!$v){
	           throw new Exception('不存在的地址'); 
	        }
	        $addressdata = array(
	                'addressid' => $v['id'],
	                'realname'	=> $v['realname'],
	                'mobile'	=> $v['mobile'],
	                'province'	=> $v['province'],
	                'city'		=> $v['city'],
	                'district'	=> $v['district'],
	                'provinceid'=> $v['provinceid'],
	                'cityid'	=> $v['cityid'],
	                'districtid'=> $v['districtid'],
	                'street'    => $v['street'],
	                'streetid'  => $v['streetid'],
	                'village'   => $v['village'],
	                'villageid' => $v['villageid'],
	                'address'	=> $v['address'],
	                'status'	=> $v['status']
	            );
	        $returndata ['result'] = 1;
	        $returndata ['data'] = json_encode ( $addressdata, JSON_UNESCAPED_UNICODE );
	    } catch ( Exception $e ) {
	        $returndata ['result'] = 0;
	        $returndata ['code'] = 20006;
	        $returndata ['msg'] = $e->getMessage ();
	    }
	    return $returndata;
	}
	
	/**
	 * 图片上传
	 *
	 * @param array $data        	
	 */
	public function picturedataup($data) {
		$returndata = array ();
		try {
			$config = self::$configs; // 引入公共配置文档;
			if (! $data ['exp'] || ! empty ( $_POST ['picturedata'] )) {
				$randstr = randnumber ( false );
				$sign = md5 ( $randstr . rds ( "apiconnect_" . $_GET ['appid'] ) ); // 加密上传标识
				$params = "appid=" . $_GET ['appid'] . "&randstr=" . $randstr . "&exp=" . $data ['exp'] . "&sign=" . $sign . "&updata=" . urlencode ( $_POST ['picturedata'] );
				$response = curl ( $config ['PICTURESERVER'], $params, 1, 0 );
				if ($response) {
					$response = json_decode ( $response, true );
					if ($response ['result']) {
						$datas = array (
								'picurl' => $response ['result'] ['picurl'] 
						);
						$this->update ( "dig_member", array (
								'picture' => $datas ['picurl'] 
						), "id =" . $data ['userid'] );
					} else {
						throw new Exception ( $response ['error'] ['msg'] );
					}
				} else {
					throw new Exception ( '图片上传到图片服务器失败' );
				}
			} else {
				throw new Exception ( '图片上传参数不能为空' );
			}
			$returndata ['result'] = 1;
			$returndata ['data'] = json_encode ( $datas, JSON_UNESCAPED_UNICODE );
		} catch ( Exception $e ) {
			$returndata ['result'] = 0;
			$returndata ['code'] = 20006;
			$returndata ['msg'] = $e->getMessage ();
		}
		return $returndata;
	}
	
	
	/**
	 * 保存用户信息
	 *
	 * @param array $data        	
	 */
	public function saveuserinfo($data) {
		$returndata = array ();
		$isrun = false;
		try {
			if ($data ['nickname'] != "") { // 保存昵称
				if (mb_strlen ( $data ['nickname'], "UTF-8" ) > 30) {
					throw new Exception ( "昵称不能超过十个汉字" );
				}
				if (strlen ( $data ['nickname'] ) >= 2) {
					$this->update ( "dig_member", array (
							'nickname' => $data ['nickname'] 
					), "id =" . $data ['userid'] );
					$isrun = true;
				} else {
					throw new Exception ( "昵称长度不在允许范围内" );
				}
			}
			
			if ($data ['mobile'] != "") { // 修改手机号码
				if (ismobile ( $data ['mobile'] )) {
					if (rds ( 'api_mobile_' . $data ['mobile'] ) != $data ['code'] || ! $data ['code']) {
						throw new Exception ( "手机验证码错误" );
					}
					$count = $this->fetRowCount("dig_member","id","mobile ='".$data['mobile']."'");
					if($count['num']){
						throw new Exception ( "手机号码已经存在，不能进行修改" );
					}
					$password = $this->fetOne ( "dig_member", "password", "id =" . $data ['userid'] );
					if ($password ['password'] == password ( $data ['password'] ) && $data ['password']) {
						$sql = "UPDATE dig_member SET mobile = '" . $data ['mobile'] . "' WHERE id =" . $data ['userid'];
						$this->execute ( $sql );
						$isrun = true;
						rds ( 'api_mobile_' . $data ['mobile'], null ); // 清空验证码
					} else {
						throw new Exception ( "原始密码错误" );
					}
				} else {
					throw new Exception ( "手机号码格式错误" );
				}
			}
			
			if ($data ['gender'] != "") { // 保存性别 fields{0,1,NULL}
				$this->update ( "dig_member", array (
						'gender' => (($data ['gender'] != "null") ? ($data ['gender'] ? 1 : 0) : null) 
				), "id =" . $data ['userid'] );
				$isrun = true;
			}
			
			if ($data ['both'] != "") { // 保存生日
				$this->update ( "dig_member", array (
						'boths' => (($data ['both'] != "null") ? strtotime ( $data ['both'] ) : null) 
				), "id =" . $data ['userid'] );
				$isrun = true;
			}
			
			if ($data ['province'] != "" || $data ['city'] != "") { // 保存省市
				$this->update ( "dig_member", array (
						'shengfei' => (($data ['province'] != "null") ? $data ['province'] : null),
						'city' => (($data ['city'] != "null") ? $data ['city'] : null) 
				), "id =" . $data ['userid'] );
				$isrun = true;
			}
			
			if ($isrun) {
				$returndata ['result'] = 1;
				$returndata ['data'] = json_encode ( array (
						'status' => 1 
				), JSON_UNESCAPED_UNICODE );
			} else {
				throw new Exception ( "没有任何操作" );
			}
		} catch ( Exception $e ) {
			$returndata ['result'] = 0;
			$returndata ['code'] = 20006;
			$returndata ['msg'] = $e->getMessage ();
		}
		return $returndata;
	}
	
	
	
	/**
	 * 取用户实时积分
	 *
	 * @param array $data        	
	 */
	public function getusergrade($data) {
		$returndata = array ();
		try {
			$r = $this->fetOne ( "clo_user", "integral", "id =" . $data ['userid'] );
			$integral = ($r ['integral'] ? $r ['integral'] : 0);
			$returndata ['result'] = 1;
			$returndata ['data'] = json_encode ( array (
					'status' => 1,
					'integral' => $integral 
			), JSON_UNESCAPED_UNICODE );
		} catch ( Exception $e ) {
		}
		return $returndata;
	}
	
	/**
	 * 取用户实时金额
	 *
	 * @param array $data        	
	 */
	public function getusermoney($data) {
		$returndata = array ();
		try {
			$r = $this->fetOne ( "clo_user", "money", "id =" . $data ['userid'] );
			$money = ($r ['money'] ? $r ['money'] : "0.00");
			$returndata ['result'] = 1;
			$returndata ['data'] = json_encode ( array (
					'status' => 1,
					'money' => $money 
			), JSON_UNESCAPED_UNICODE );
		} catch ( Exception $e ) {
		}
		return $returndata;
	}
	
	
	/**
	 * 绑定第三方推送clientid
	 *
	 * @param array $data        	
	 */
	public function thirdpushclientid($data) {
		$returndata = array ();
		try {
			if ($data ['clientid']) {
			} else {
				throw new Exception ( "客户端ID不能为空" );
			}
			$this->execute ( "update dig_digno set clientid = '" . $data ['clientid'] . "' where userid =" . $data ['userid'] );
			$returndata ['result'] = 1;
			$returndata ['data'] = json_encode ( array (
					'status' => 1 
			), JSON_UNESCAPED_UNICODE );
		} catch ( Exception $e ) {
			$returndata ['result'] = 0;
			$returndata ['code'] = 20006;
			$returndata ['msg'] = $e->getMessage ();
		}
		return $returndata;
	}
	
	/**
	 * 取购物车数据
	 * @param array $data
	 */
	public function buycart($data){
		$returndata = array ();
		try {
			$buycart = $this->redis->zRange(REDIS_PREFIX.'buycart_'.$data['userid'],0,-1,true);
			$datalist = array();
			if($buycart){
	               	$pro_no = implode(',', array_keys($buycart));		
				    $products = $this->fetAll('clo_products','product_no,picture,product_name,shopprice,sales_name,status,deleted','','product_no in ('.$pro_no.')');
				    foreach ($products as $v){
				        if($v['status'] !== 0 && $v['deleted'] !== 1){
				            $datalist[] = array(
				                'picture' => $v['picture'],
				                'product_name' => $v['product_name'],
				                'sales_name' => $v['sales_name'],
				                'shopprice'    => $v['shopprice'],
				                'product_no'   => $v['product_no'],
				                'quantity'     => $buycart[$v['product_no']]
				            );
				        }
				    }
			}
			$returndata ['result'] = 1;
			$returndata ['data'] = json_encode (array('datalist'=>$datalist), JSON_UNESCAPED_UNICODE );
		} catch ( Exception $e ) {
			$returndata ['result'] = 0;
			$returndata ['code'] = 20006;
			$returndata ['msg'] = $e->getMessage ();
		}
		return $returndata;
	}
	
	/**
	 * 向购物车添加数据
	 * @param array $data
	 */
	public function addbuycart($data){
	    $returndata = array ();
	    try {
	        if(empty($data['product_no']) || empty($data['quantity'])){
	             throw new Exception("提交的参数错误"); 
	        }
	        $product = $this->fetOne('clo_products','status,deleted','product_no ="'.$data['product_no'].'"');
	        if($product){
	            if($product['status'] === 0 || $product['deleted'] === 1){
	                throw new Exception("商品已经下架或删除");
	            }
	        }else{
	            throw new Exception("商品不存在");
	        }
	        $this->redis->zIncrBy(REDIS_PREFIX.'buycart_'.$data['userid'], intval($data['quantity']), $data['product_no']);
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
	 * 删除购物车中的数据
	 * @param array $data
	 */
	public function unsetbuycart($data){
	    $returndata = array ();
	    try {
	        if(empty($data['product_no'])){
	            throw new Exception("提交的参数错误");
	        }
	        foreach (explode(',', $data['product_no']) as $v){
	           $this->redis->zDelete(REDIS_PREFIX.'buycart_'.$data['userid'],$v);
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
	 * 更新购物车商品数量
	 * @param array $data
	 */
	public function updatebuycart($data){
	    $returndata = array ();
	    try {
	        if(empty($data['product_no']) || empty($data['quantity'])){
	            throw new Exception("提交的参数错误");
	        }
	        $this->redis->zAdd(REDIS_PREFIX.'buycart_'.$data['userid'],intval($data['quantity']),$data['product_no']);
	        $returndata ['result'] = 1;
	        $returndata ['data'] = json_encode (array('status'=>1), JSON_UNESCAPED_UNICODE );
	    } catch ( Exception $e ) {
	        $returndata ['result'] = 0;
	        $returndata ['code'] = 20006;
	        $returndata ['msg'] = $e->getMessage ();
	    }
	    return $returndata;
	}
}

?>