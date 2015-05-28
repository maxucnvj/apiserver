<?php
// +----------------------------------------------------------------------
// | Qiduoke 2015-1-4 下午1:57:29
// +----------------------------------------------------------------------
// | Copyright (c) 2014-2014 http://qidor.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: cnvj <1403729427@qq.com>
// +----------------------------------------------------------------------

/**
 * 公共函数
 */

/**
 * 密码加密
 *
 * @param string $str
 *        	要加密的字符串
 * @return string 返回加密后的密码
 *        
 */
function password($str = "123456") {
	$string = 2127;
	return strrev ( md5 ( md5 ( $str ) . $string ) ); // 先加密后加盐再反转字符串
}

/**
 * 判断手机号码格式是否正确
 *
 * @param string $str
 *        	要判断的手机号号
 * @return bool 返回是否
 */
function ismobile($str) {
	$p = '/^1[3|4|5|7|8]\d{9}$/';
	return preg_match ( $p, $str );
}

/**
 * 判断email地址是否正确
 *
 * @param string $str
 *        	要判断的email
 * @return bool 返回是否
 */
function isemail($str) {
	$p = '/^\w+\@\w+\.\w{2,8}$/';
	return preg_match ( $p, $str );
}

/**
 * 取得随机数据 验证方法前五位数相加除以5取整组组合
 *
 * @param bool $str
 *        	是否验证随机数 默认验证
 * @return string 返回随机数
 */
function randnumber($str = true) {
	if ($str) {
		$restr = rand ( 10000, 99999 );
		$res = str_split ( $restr );
		$s = 0;
		foreach ( $res as $v ) {
			$s += $v;
		}
		$restr .= floor ( $s / 5 );
	} else {
		$restr = rand ( 100000, 999999 );
	}
	return $restr;
}

/**
 * 判断用户行为，多少秒内可执行的次数
 *
 * @param string $action        	
 * @param integer $seccend
 *        	默认60秒
 * @param integer $times
 *        	默认1次
 * @return bool
 */
function checkuseraction($action, $seccend = 60, $times = 0, $mobilecode = NULL) {
	if (! $mobilecode) {
		$mobilecode = session_id ();
	}
	$time = rds ( $mobilecode . '_' . $action );
	if ($time > $times) {
		return false;
	} else {
		rds ( $mobilecode . '_' . $action, ($time + 1), $seccend );
		return true;
	}
}

/**
 * redis string 缓存函数
 *
 * @param string $key        	
 * @param string $value
 *        	值名
 * @param string $expire
 *        	过期时间
 * @param array $connect
 *        	redis缓存连接
 */
function rds($key, $value = "", $expire = null, $connect = NULL) {
	if (! $connect) {
		$config = require dirname ( __FILE__ ) . '/common.inc.php'; // 引入公共配置文档;
		$connect = $config ['REDISCONNET'];
	}
	$redis = new \Redis ();
	$redis->connect ( $connect ['host'], $connect ['port'] );
	if ($connect ['pass']) {
		$redis->auth ( $connect ['pass'] );
	}
	if ($value) {
		if ($expire) {
			$res = $redis->setex ( $connect ['prefix'] . $key, $expire, $value );
		} else {
			$res = $redis->set ( $connect ['prefix'] . $key, $value );
		}
	} else {
		if ($value === "") {
			$res = $redis->get ( $connect ['prefix'] . $key );
		} else {
			$res = $redis->del ( $connect ['prefix'] . $key );
		}
	}
	$redis->close ();
	return $res;
}

/**
 * gettoken 请求微信access_token
 *
 * @param string $url:接口地址        	
 * @return string
 */
function gettoken() {
	$config = require dirname ( __FILE__ ) . '/common.inc.php'; // 引入公共配置文档;
	$connect = $config ['REDISCONNET'];
	$redis = new \Redis ();
	$redis->connect ( $connect ['host'], $connect ['port'] );
	if ($connect ['pass']) {
		$redis->auth ( $connect ['pass'] );
	}
	$access_token = rds ( 'weixin_access_token' );
	if (! $access_token) {
		$url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=" . $config ['WEIXIN_APP'] ['AppID'] . "&secret=" .  $config ['WEIXIN_APP'] ['AppSecret'];
		$response = curl ( $url );
		if ($response) {
			$data = json_decode ( $response, true );
			if ($data ['access_token']) {
				$access_token = $data ['access_token'];
				rds ( 'weixin_access_token', $data ['access_token'], $data ['expires_in'] - 10 );
			} else {
				$access_token = $data ['errcode'];
			}
		}
	}
	$redis->close ();
	return $access_token;
}


/**
 * 取用户联合ID
 * @param unknown $openid
 * @return boolean|mixed
 */
function getunionid($openid){
	$ACCESS_TOKEN = gettoken();
	if((int)$ACCESS_TOKEN > 0){
		return false;
	}
	$url = "https://api.weixin.qq.com/cgi-bin/user/info?access_token=".$ACCESS_TOKEN."&openid=".$openid."&lang=zh_CN";
	$response = curl ( $url );
	if ($response) {
		$data = json_decode ( $response, true );
		if ($data ['unionid']) {
			return $data ['unionid'];
		}else{
			return false;
		}
	}
}



/**
 * CURL提交
 *
 * @param string $url        	
 * @param string $params        	
 * @param number $ispost        	
 * @param number $isssl        	
 */
function curl($url, $params = FALSE, $ispost = 0, $isssl = 1) {
	// $httpInfo = array ();
	$ch = curl_init ();
	
	curl_setopt ( $ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0 );
	curl_setopt ( $ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 5.1) AppleWebKit/537.22 (KHTML, like Gecko) Chrome/25.0.1364.172 Safari/537.22' );
	curl_setopt ( $ch, CURLOPT_CONNECTTIMEOUT, 30 );
	if ($isssl) {
		curl_setopt ( $ch, CURLOPT_SSL_VERIFYPEER, false );
		curl_setopt ( $ch, CURLOPT_SSL_VERIFYHOST, false );
	}
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
		echo "cURL Error: " . curl_error ( $ch );
		return false;
	}
	// $httpCode = curl_getinfo ( $ch, CURLINFO_HTTP_CODE );
	// $httpInfo = array_merge ( $httpInfo, curl_getinfo ( $ch ) );
	curl_close ( $ch );
	return $response;
}

/**
 * 由ID补位流水号
 *
 * @param number $id        	
 * @return number
 */
function tradeno($id = 0) {
	$string = "0000000000000000000000";
	$str = 1429;
	$len = strlen ( $id );
	$str .= substr ( $string, 0, (12 - $len) );
	$str .= $id;
	return $str;
}

/**
 * 由流水号转为id
 *
 * @param
 *        	number tradeno
 * @return number
 */
function tradenode($tradeno) {
	return ( int ) str_replace ( "1429", "", $tradeno );
}

/**
 * 取真实IP
 * 
 * @return string
 */
function get_real_ip() {
	$ip = false;
	if (! empty ( $_SERVER ["HTTP_CLIENT_IP"] )) {
		$ip = $_SERVER ["HTTP_CLIENT_IP"];
	}
	if (! empty ( $_SERVER ['HTTP_X_FORWARDED_FOR'] )) {
		$ips = explode ( ", ", $_SERVER ['HTTP_X_FORWARDED_FOR'] );
		if ($ip) {
			array_unshift ( $ips, $ip );
			$ip = FALSE;
		}
		for($i = 0; $i < count ( $ips ); $i ++) {
			if (! eregi ( "^(10|172.16|192.168).", $ips [$i] )) {
				$ip = $ips [$i];
				break;
			}
		}
	}
	return ($ip ? $ip : $_SERVER ['REMOTE_ADDR']);
}

?>