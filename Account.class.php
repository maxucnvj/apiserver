<?php
// +----------------------------------------------------------------------
// | Qiduoke 2015-4-19 下午3:55:00
// +----------------------------------------------------------------------
// | Copyright (c) 2014-2014  All rights reserved.
// +----------------------------------------------------------------------
// | Author: cnvj <1403729427@qq.com>
// +----------------------------------------------------------------------
use Appclass\Pdomysql;

/**
 * 帐号生成类 无需登录
 *
 * @author Administrator
 *
 */
class Account extends Pdomysql {
	
	/**
	 * 注册帐号
	 *
	 * @param array $data        	
	 * @return array
	 */
	public function reg($data) {
		$returndata = array ();
		try {
			if (!$data ['mobilecode']) {
				throw new Exception ( '没有取到手机串码，请重试' );
			}
			if (ismobile ( $data ['mobile'] )) {
				if ($this->redis->get(REDIS_PREFIX.'api_mobile_' . $data ['mobile']) != $data ['code'] || ! $data ['code']) {
					throw new Exception ( "手机验证码不正确" );
				}
				$this->redis->del(REDIS_PREFIX.'api_mobile_' . $data ['mobile']); // 删除手机验证码
				$count = $this->fetRowCount ( "clo_user", "id", "mobile = '" . $data ['mobile'] . "'" );
				if ($count ['num']) {
					if($data['password']){
						throw new Exception ( "该手机号码已经被注册，您可以直接登录或者重置密码" );
					}else{
						//直接登陆
						$sql = "SELECT * FROM `clo_user` WHERE mobile = " . $data ['mobile'];
						$res = $this->getOne ( $sql );
						$datas = array (
								'status' => $res ['status'],
								'id' => $res ['id'],
								'mobile' => $res ['mobile'],
								'integral' => $res ['integral'],
								'money' => $res ['money'],
								'gender' => $res ['gender'],
								'both' => (($res ['boths'] != null) ? date ( "Y-m-d", $res ['boths'] ) : null),
								'nickname' => $res ['nickname'],
								'picture' => $res ['picture']
						);
						$returndata ['result'] = 1;
						$returndata ['data'] = json_encode ( $datas, JSON_UNESCAPED_UNICODE );
						$this->redis->set(REDIS_PREFIX.'api_mobilecode_' . $res ['id'], $data['mobilecode'] ); // 记录用户登录信息
						return $returndata;
					}
				}

			} else {
				throw new Exception ( "手机号码格式不正确" );
			}
			$pass = $data ['password']?$data ['password']:rand(100000, 999999);
			$innerdata = array (
					'mobile' => $data ['mobile'],
					'password' => password ($pass),
					'status' => 1,
					'published' => time (),
					'integral' => 0,
					'deleted' => 0,
					'money' => 0.00,
					'regip' => get_real_ip()
			);
			$inner = $this->add ( "clo_user", $innerdata ); // 注册帐号
			if ($inner) {
				$id = $this->getLastId ();
				$this->redis->set(REDIS_PREFIX.'api_mobilecode_' . $id, $data['mobilecode'] ); // 记录用户登录信息				
			}else{
				throw new Exception('注册失败');
			}
			if(empty($data ['password'])){ //无密码注册
    			$values = urlencode ( '#code#=' . $pass );
    			$config = self::$configs; // 引入公共配置文档;
    			$url = $config ['MOBILECODE'] ['url'];
    			$params = 'mobile=' . $data ['mobile'] . '&tpl_id=' . $config ['MOBILECODE'] ['password_tpl_id'] . '&tpl_value=' . $values . '&key=' . $config ['MOBILECODE'] ['key'];
    			$res = $this->sendmsg ( $url, $params ); // 向接口发送密码
    			if ($res ['status']) {
    				$returndata ['result'] = 1;
    				$returndata ['data'] = json_encode ( array (
    						"status" => 1
    				), JSON_UNESCAPED_UNICODE );
    				$this->add ( "clo_mobilesend", array (
    						'mobile' => $data ['mobile'],
    						'status' => 1,
    						'addtime' => time (),
    						'content' => '注册初始密码为：'.$pass
    				) );
    			} else {
    				$this->add ( "clo_mobilesend", array (
    						'mobile' => $data ['mobile'],
    						'status' => 0,
    						'addtime' => time (),
    						'content' => '注册初始密码为：<' . $pass . '>' . $res ['content']
    				) );
    				throw new Exception ( $res ['content'] );
    			}
			}
			$returndata ['result'] = 1;
			$returndata ['data'] = json_encode ( array (
					"status" => 1,
					"id" => $id,
					'mobile' => $data ['mobile'],
					'integral' => 0,
					'money' => 0.00,
					'gender' => null,
					'both' => null,
					'nickname' => null,
					'picture' => null
			), JSON_UNESCAPED_UNICODE );
		} catch ( Exception $e ) {
			$returndata ['result'] = 0;
			$returndata ['code'] = 20006;
			$returndata ['msg'] = $e->getMessage ();
		}
		return $returndata;
	}
	
	/**
	 * 取注册手机验证码
	 *
	 * @param array $data        	
	 */
	public function regcode($data) {
		$returndata = array ();
		try {
			if(!$data ['mobilecode']) {
				throw new Exception ( '手机串码没有获取到,请重试' );
			}
			if (ismobile ( $data ['mobile'] )) {
				$count = $this->fetRowCount ( "clo_user", "id", "mobile = " . $data ['mobile'] );
				if ($count ['num'] && $data ['isreg'] != 2) {
					if ($data ['isreg'] == 1) {
						throw new Exception ( '该手机号码已经被注册，您可以直接登录或者重置密码' );
					}
				} else {
					if ($data ['isreg'] == 0) {
						throw new Exception ( '没有找到该手机号码，请重试' );
					}
				}
				if (! checkuseraction ( "regcode", 60, 0, $data ['mobilecode'] )) {
					throw new Exception ( '每60秒只能获取一次验证码' );
				}
			} else {
				throw new Exception ( '每手机号码格式不正确' );
			}
			$mobilecodetimes = (int)$this->redis->get(REDIS_PREFIX.'api_mobilecodetimes_' . $data ['mobilecode']);
			if($mobilecodetimes >= 10){ //一天只能发送十次验证码
				throw new Exception ( '为防止恶意发送验证码，一个手机一天只能发送十次验证码' );
			}
			$randcode = randnumber ( false ); // 生成验证码
			$values = urlencode ( '#code#=' . $randcode );
			$config = self::$configs; // 引入公共配置文档;
			$url = $config ['MOBILECODE'] ['url'];
			$params = 'mobile=' . $data ['mobile'] . '&tpl_id=' . $config ['MOBILECODE'] ['code_tpl_id'] . '&tpl_value=' . $values . '&key=' . $config ['MOBILECODE'] ['key'];
			$res = $this->sendmsg ( $url, $params ); // 向接口发送验证码
			if ($res ['status']) {
				$this->redis->setex(REDIS_PREFIX.'api_mobilecodetimes_' . $data ['mobilecode'],(strtotime(date("Y-m-d")." 23:59:59")-time()),($mobilecodetimes+1));//记录发送次数
				$this->redis->setex(REDIS_PREFIX.'api_mobile_' . $data ['mobile'], $config ['MOBILECODE'] ['sessiontimes'],$randcode); // 缓存验证码
				$returndata ['result'] = 1;
				$returndata ['data'] = json_encode ( array (
						"status" => 1 
				), JSON_UNESCAPED_UNICODE );
				$this->add ( "clo_mobilesend", array (
						'mobile' => $data ['mobile'],
						'status' => 1,
						'addtime' => time (),
						'content' => $randcode 
				) );
			} else {
				$this->add ( "clo_mobilesend", array (
						'mobile' => $data ['mobile'],
						'status' => 0,
						'addtime' => time (),
						'content' => '<' . $randcode . '>' . $res ['content'] 
				) );
				throw new Exception ( $res ['content'] );
			}
		} catch ( Exception $e ) {
			$returndata ['result'] = 0;
			$returndata ['code'] = 20006;
			$returndata ['msg'] = $e->getMessage ();
		}
		return $returndata;
	}
	
	/**
	 * 找回修改密码
	 *
	 * @param array $data        	
	 */
	public function getpassword($data) {
		$returndata = array ();
		try {
			if (ismobile ( $data ['mobile'] )) {
				if ($this->redis->get(REDIS_PREFIX.'api_mobile_' . $data ['mobile'] ) != $data ['code'] || ! $data ['code']) {
					throw new Exception ( '手机验证码不正确' );
				}
			} else {
				throw new Exception ( '手机号码格式不正确' );
			}
			$this->update ( "clo_user", array (
					'password' => password ( $data ['password'] ) 
			), "mobile = " . $data ['mobile'] );
			$returndata ['result'] = 1;
			$returndata ['data'] = json_encode ( array (
					"status" => 1 
			), JSON_UNESCAPED_UNICODE );
			$this->redis->del(REDIS_PREFIX.'api_mobile_' . $data ['mobile']); // 删除缓存
		} catch ( Exception $e ) {
			$returndata ['result'] = 0;
			$returndata ['code'] = 20006;
			$returndata ['msg'] = $e->getMessage ();
		}
		return $returndata;
	}
	
	/**
	 * 修改支付密码
	 * 
	 * @param array $data 
	 */
	public function getpaypassword($data){
		$returndata = array ();
		try {
			if (ismobile ( $data ['mobile'] )) {
				if ($this->redis->get(REDIS_PREFIX.'api_mobile_' . $data ['mobile'] ) != $data ['code'] || ! $data ['code']) {
					throw new Exception ( '手机验证码不正确' );
				}
			} else {
				throw new Exception ( '手机号码格式不正确' );
			}
			$this->update ( "clo_user", array (
					'paypassword' => password ( $data ['paypassword'].$data ['mobile'] )
			), "mobile = " . $data ['mobile'] );
			$returndata ['result'] = 1;
			$returndata ['data'] = json_encode ( array (
					"status" => 1
			), JSON_UNESCAPED_UNICODE );
			$this->redis->del(REDIS_PREFIX.'api_mobile_' . $data ['mobile']); // 删除缓存
		} catch ( Exception $e ) {
			$returndata ['result'] = 0;
			$returndata ['code'] = 20006;
			$returndata ['msg'] = $e->getMessage ();
		}
		return $returndata;
	}
	
	/**
	 * 登录帐号
	 *
	 * @param array $data        	
	 */
	public function login($data) {
		$returndata = array ();
		try {
			if (! ismobile ( $data ['mobile'] )) {
				throw new Exception ( '手机号码格式不正确' );
			}
			if (!$data ['mobilecode']) {
				throw new Exception ( '没有取到手机串码，请重试' );
			}
			$sql = "SELECT * FROM `clo_user` WHERE mobile = " . $data ['mobile'];
			$res = $this->getOne ( $sql );
			if ($res) {
				if ($res ['password'] == password ( $data ['password'] )) {
					if (! $res ['status']) {
						throw new Exception ( '帐号被禁用' );
					}
				} else {
					throw new Exception ( '密码不正确' );
				}
			} else {
				throw new Exception ( '帐号不存在' );
			}
			$datas = array (
					'status' => $res ['status'],
					'id' => $res ['id'],
					'mobile' => $res ['mobile'],
					'integral' => $res ['integral'],
					'money' => $res ['money'],
					'gender' => $res ['gender'],
					'both' => (($res ['boths'] != null) ? date ( "Y-m-d", $res ['boths'] ) : null),
					'nickname' => $res ['nickname'],
					'picture' => $res ['picture'] 
			);
			$returndata ['result'] = 1;
			$returndata ['data'] = json_encode ( $datas, JSON_UNESCAPED_UNICODE );
			$this->redis->set(REDIS_PREFIX.'api_mobilecode_' . $res ['id'], $data['mobilecode'] ); // 记录用户登录信息
		} catch ( Exception $e ) {
			$returndata ['result'] = 0;
			$returndata ['code'] = 20006;
			$returndata ['msg'] = $e->getMessage ();
		}
		return $returndata;
	}
	
	/**
	 * 第三方微信登录
	 *
	 * @param array $data
	 */
	public function wxlogin($data){
		$returndata = array ();
		try {
			if(!$data['unionid']){
				throw new Exception("没有授权，请重新登陆");
			}
			if (!$data ['mobilecode']) {
				throw new Exception ( '没有取到手机串码，请重试' );
			}
			$user = $this->fetOne("clo_userthirdlogin","userid","unionid ='".$data['unionid']."'");
			if($user['userid']){//存在在的帐户，执行登陆
				$sql = "SELECT  `clo_user` WHERE id = " . $user ['userid'];
				$res = $this->getOne ( $sql );
				if ($res) {
						if (! $res ['status']) {
							throw new Exception ( '帐号被禁用' );
						}
				} else {
					throw new Exception ( '帐号不存在' );
				}
				$datas = array (
					'status' => $res ['status'],
					'id' => $res ['id'],
					'mobile' => $res ['mobile'],
					'integral' => $res ['integral'],
					'money' => $res ['money'],
					'gender' => $res ['gender'],
					'both' => (($res ['boths'] != null) ? date ( "Y-m-d", $res ['boths'] ) : null),
					'nickname' => $res ['nickname'],
					'picture' => $res ['picture'] 
				);
				$returndata ['result'] = 1;
				$returndata ['data'] = json_encode ( $datas, JSON_UNESCAPED_UNICODE );
				$this->redis->set(REDIS_PREFIX.'api_mobilecode_' . $res ['id'], $data['mobilecode'] ); // 记录用户登录信息
			}else{ //不存在的帐号，执行注册
				$returndata ['result'] = 1;
				$returndata ['data'] = json_encode ( array(), JSON_UNESCAPED_UNICODE );
			}
		}catch (Exception $e){
			$returndata ['result'] = 0;
			$returndata ['code'] = 20006;
			$returndata ['msg'] = $e->getMessage ();
		}
		return $returndata;
	}
	
	/**
	 * 第三方微信登录
	 *
	 * @param array $data
	 */
	public function qqlogin($data){
		$returndata = array ();
		try {
			if(!$data['qqopenid']){
				throw new Exception("没有授权，请重新登陆");
			}
			if (!$data ['mobilecode']) {
				throw new Exception ( '没有取到手机串码，请重试' );
			}
			$user = $this->fetOne("clo_userthirdlogin","userid","qqopenid ='".$data['qqopenid']."'");
			if($user['userid']){//存在在的帐户，执行登陆
				$sql = "SELECT  `clo_user` WHERE id = " . $user ['userid'];
				$res = $this->getOne ( $sql );
				if ($res) {
					if (! $res ['status']) {
						throw new Exception ( '帐号被禁用' );
					}
				} else {
					throw new Exception ( '帐号不存在' );
				}
				$datas = array (
						'status' => $res ['status'],
						'id' => $res ['id'],
						'mobile' => $res ['mobile'],
						'integral' => $res ['integral'],
						'money' => $res ['money'],
						'gender' => $res ['gender'],
						'both' => (($res ['boths'] != null) ? date ( "Y-m-d", $res ['boths'] ) : null),
						'nickname' => $res ['nickname'],
						'picture' => $res ['picture']
				);
				$returndata ['result'] = 1;
				$returndata ['data'] = json_encode ( $datas, JSON_UNESCAPED_UNICODE );
				$this->redis->set(REDIS_PREFIX.'api_mobilecode_' . $res ['id'], $data['mobilecode'] ); // 记录用户登录信息
			}else{ //不存在的帐号
				$returndata ['result'] = 1;
				$returndata ['data'] = json_encode ( array(), JSON_UNESCAPED_UNICODE );
			}
		}catch (Exception $e){
			$returndata ['result'] = 0;
			$returndata ['code'] = 20006;
			$returndata ['msg'] = $e->getMessage ();
		}
		return $returndata;
	}
	
	/**
	 * 绑定第三方登录信息
	 * 
	 * @param array $data
	 */
	public function lockaccount($data){
		$returndata = array ();
		try {
			if(!$data['qqopenid'] && !$data['unionid']){
				throw new Exception("没有一个要绑定的第三方的登陆帐号");
			}
			if (!$data ['mobilecode']) {
				throw new Exception ( '没有取到手机串码，请重试' );
			}
			if (ismobile ( $data ['mobile'] )) {
				if ($this->redis->get(REDIS_PREFIX.'api_mobile_' . $data ['mobile'] ) != $data ['code'] || ! $data ['code']) {
					throw new Exception ( '手机验证码不正确' );
				}
			} else {
				throw new Exception ( '手机号码格式不正确' );
			}
			
			$res = $this->fetOne( "clo_user", "*", "mobile = " . $data ['mobile'] );
			if($res){//存在在的帐户，执行绑定
				$sql = "SELECT  `clo_userthirdlogin` WHERE userid = " . $res ['id'];
				$res = $this->getOne ( $sql );
				if ($res) {//有过绑定，执行修改
					if (! $res ['status']) {
						throw new Exception ( '帐号被禁用' );
					}
					if($data['qqopenid']){
						$sql = "update  `clo_userthirdlogin` set qqopenid = '".$data['qqopenid']."' WHERE userid = " . $res ['id'];
					}else{
						$sql = "update  `clo_userthirdlogin` set unionid = '".$data['unionid']."' WHERE userid = " . $res ['id'];
					}
					$this->execute($sql);
				} else { //没有过绑定，执行新建
					if($data['qqopenid']){
						$array = array('qqopenid'=>$data['qqopenid']);
					}else{
						$array = array('unionid'=>$data['unionid']);
					}
					$this->add('clo_userthirdlogin', array_merge($array,array('userid'=>$res ['id'])));
				}
				$datas = array (
						'status' => $res ['status'],
						'id' => $res ['id'],
						'mobile' => $res ['mobile'],
						'integral' => $res ['integral'],
						'money' => $res ['money'],
						'gender' => $res ['gender'],
						'both' => (($res ['boths'] != null) ? date ( "Y-m-d", $res ['boths'] ) : null),
						'nickname' => $res ['nickname'],
						'picture' => $res ['picture']
				);
				$this->redis->set(REDIS_PREFIX.'api_mobilecode_' . $res ['id'], $data['mobilecode'] ); // 记录用户登录信息
			}else{ //不存在的帐号 执行注册
				$innerdata = array (
						'mobile' => $data ['mobile'],
						'password' => $data ['password']?password ( $data ['password'] ):NULL,
						'status' => 1,
						'published' => time (),
						'integral' => 0,
						'deleted' => 0,
						'money' => 0.00,
						'regip' => get_real_ip()
				);
				$inner = $this->add ( "clo_user", $innerdata ); // 注册帐号
				if ($inner) {
					$id = $this->getLastId ();
					if($data['qqopenid']){
						$array = array('qqopenid'=>$data['qqopenid']);
					}else{
						$array = array('unionid'=>$data['unionid']);
					}
					$this->add('clo_userthirdlogin', array_merge($array,array('userid'=>$id)));
					$this->redis->set(REDIS_PREFIX.'api_mobilecode_' . $id, $data['mobilecode'] ); // 记录用户登录信息
				}else{
					throw new Exception('注册失败');
				}
				$datas = array (
					"status" => 1,
					"id" => $id,
					'mobile' => $data ['mobile'],
					'integral' => 0,
					'money' => 0.00,
					'gender' => null,
					'both' => null,
					'nickname' => null,
					'picture' => null
				);
				$this->redis->del(REDIS_PREFIX.'api_mobile_' . $data ['mobile']); // 删除手机验证码
			}
			$returndata ['result'] = 1;
			$returndata ['data'] = json_encode ( $datas, JSON_UNESCAPED_UNICODE );
		}catch (Exception $e){
			$returndata ['result'] = 0;
			$returndata ['code'] = 20006;
			$returndata ['msg'] = $e->getMessage ();
		}
		return $returndata;
	}
	
	
	/**
	 * 向接口发送验证码
	 *
	 * @param string $url
	 *        	接口地址
	 * @param string $params
	 *        	发送参数
	 * @return multitype:number string
	 */
	private function sendmsg($url, $params) {
		$content = $this->juhecurl ( $url, $params, 1 );
		if ($content) {
			$result = json_decode ( $content, true );
			// print_r($result);
			
			// 错误码判断
			$error_code = $result ['error_code'];
			if ($error_code == 0) {
				$array = array (
						'status' => 1 
				);
			} else {
				$array = array (
						'status' => 0,
						'content' => $error_code . ':' . $result ['reason'] 
				);
			}
		} else {
			$array = array (
					'status' => 0,
					'content' => '接口没有返回任何数据' 
			);
		}
		return $array;
	}
	
	/**
	 * CRUL提交
	 *
	 * @param string $url        	
	 * @param string $params        	
	 * @param number $ispost        	
	 * @return boolean mixed
	 */
	private function juhecurl($url, $params = false, $ispost = 0) {
		$httpInfo = array ();
		$ch = curl_init ();
		
		curl_setopt ( $ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0 );
		curl_setopt ( $ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 5.1) AppleWebKit/537.22 (KHTML, like Gecko) Chrome/25.0.1364.172 Safari/537.22' );
		curl_setopt ( $ch, CURLOPT_CONNECTTIMEOUT, 30 );
		curl_setopt ( $ch, CURLOPT_TIMEOUT, 30 );
		curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, true );
		if ($ispost) {
			curl_setopt ( $ch, CURLOPT_POST, true );
			curl_setopt ( $ch, CURLOPT_POSTFIELDS, $params );
			curl_setopt ( $ch, CURLOPT_URL, $url );
		} else {
			if ($params) {
				curl_setopt ( $ch, CURLOPT_URL, $url . '?' . $params );
			} else {
				curl_setopt ( $ch, CURLOPT_URL, $url );
			}
		}
		$response = curl_exec ( $ch );
		if ($response === FALSE) {
			// echo "cURL Error: " . curl_error($ch);
			return false;
		}
		$httpCode = curl_getinfo ( $ch, CURLINFO_HTTP_CODE );
		$httpInfo = array_merge ( $httpInfo, curl_getinfo ( $ch ) );
		curl_close ( $ch );
		return $response;
	}
}

?>