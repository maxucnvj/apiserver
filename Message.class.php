<?php
// +----------------------------------------------------------------------
// | Qiduoke 2015-1-27 下午1:42:19
// +----------------------------------------------------------------------
// | Copyright (c) 2014-2014 http://qidor.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: cnvj <1403729427@qq.com>
// +----------------------------------------------------------------------
use Appclass\user;
use Appclass\Prpcrypt;
use Appclass\getuiapi\IGeTui;
/**
 * 客户信息交互类
 *
 * @author Administrator
 *        
 */
class Message extends user {
	
	/**
	 * 信息获取
	 *
	 * @param array $data        	
	 */
	public function getmessage($data) {
		$returndata = array ();
		try {
			$config = self::$configs;
			$connect = $config ['REDISCONNET'];
			if($config['SERVICEID'] == $_GET['appid']){
				$data['userid'] = 0; //如果来源于客服接口，刚调用客服公用ID
			}
			$frommsgid = $data ['fromuserid'] ? $data ['fromuserid'] : 0; // 取来自谁的信息
			$order = ($data ['userid'] > $frommsgid) ? ($data ['userid'] . "_" . $frommsgid) : ($frommsgid . "_" . $data ['userid']);
			$rdsname = $connect ['prefix'] . "_apimessage_" . $order; // 定义缓存键名
			$redis = new \Redis ();
			$redis->connect ( $connect ['host'], $connect ['port'] );
			if ($connect ['pass']) {
				$redis->auth ( $connect ['pass'] );
			}
			if (! $redis->exists ( $rdsname )) {
				throw new Exception ( "还没有任何消息记录" );
			}
			$count = $redis->lLen ( $rdsname );
			$number = 0;
			if ($data ['record'] && $count != $data ['record']) {
				$number = ($count - ( int ) $data ['record']); // 比新记录数比取的记录数多时
			}
			if ($count) {
				$page = $data ['page'] ? ( int ) ($data ['page'] - 1) : 0;
				$tatolpage = ceil ( ($count - $number) / $config ['PAGESIZE'] );
				if ($page >= $tatolpage) {
					throw new Exception ( "超出了页码" );
				}
			} else {
				throw new Exception ( "还没有任何消息记录" );
			}
			$datares = $redis->lrange ($rdsname,(($page * $config ['PAGESIZE']) + $number),(($page * $config ['PAGESIZE']) + $number)+$config ['PAGESIZE']-1);
			$datas = array ();
			foreach ( $datares as $v ) {
				$rs = json_decode ( $v, true );
				$datas [] = array (
						'content' => $rs ['content'], // 消息内容
						'addtime' => date ( "Y-m-d H:i:s", $rs ['addtime'] ), // 消息时间
						'userid' => $rs ['userid']  // 消息发送人ID
								);
			}
			if($page === 0){ //删除新的聊天记录
				$rdsname = $connect ['prefix'] . "_apimessagetouserid_".$data['userid']."_".$frommsgid;
				$redis->del($rdsname); //新的聊天内容取完了 删除
			}
			$redis->close ();
			$ret = array (
					'datalist' => $datas,
					'totalpage' => $tatolpage,
					'currentpage' => $data ['page'],
					'record' => ($data ['record'] ? $data ['record'] : $count) 
			);
			$returndata ['result'] = 1;
			$returndata ['data'] = json_encode ( $ret, JSON_UNESCAPED_UNICODE );
		} catch ( Exception $e ) {
			$returndata ['result'] = 0;
			$returndata ['code'] = 20006;
			$returndata ['msg'] = $e->getMessage ();
		}
		return $returndata;
	}
	
	/**
	 * 信息提交
	 *
	 * @param array $data        	
	 */
	public function putmessage($data) {
		$returndata = array ();
		try {
			if (trim ( $data ['content'] ) == "") {
				throw new Exception ( "提交信息内容不能为空" );
			}
			$config = self::$configs;
			$connect = $config ['REDISCONNET'];
			if($config['SERVICEID'] == $_GET['appid']){
				$data['userid'] = 0; //如果来源于客服接口，刚调用客服公用ID
			}
			$tomsgid = $data ['touserid'] ? $data ['touserid'] : 0; // 发送给谁的信息
			$order = ($data ['userid'] > $tomsgid) ? ($data ['userid'] . "_" . $tomsgid) : ($tomsgid . "_" . $data ['userid']);
			$rdsname = $connect ['prefix'] . "_apimessage_" . $order; // 定义缓存键名
			$datas = array (
					'content' => $data ['content'],
					'addtime' => time (),
					'userid' => $data['userid'] 
			);
			$redis = new \Redis ();
			$redis->connect ( $connect ['host'], $connect ['port'] );
			if ($connect ['pass']) {
				$redis->auth ( $connect ['pass'] );
			}
			$redis->lPush ( $rdsname, json_encode ( $datas ) );
			$redis->incr ( $connect ['prefix'] . "_apimessagetouserid_" . $data ['userid'] . "_" . $tomsgid ); //分别和正在对话的用户加上新消息
			$redis->incr ( $connect ['prefix'] . "_apimessagetouserid_" . $tomsgid . "_" . $data ['userid'] ); //分别和正在对话的用户加上新消息
			$redis->close ();
			// 这里可以插入推送消息的方法
			if($tomsgid){ //不是发给系统的刚推送信息
				$datathrid = array(
					'message' => array(
						'typeid' => 1, //消息状态见文档
						'title' => '',
						'content' => $data ['content'],
						'addtime' => date('Y-m-d H:i:s'),
						'userid' => $data['userid']	//发送者ID			
					),//要发送的信息数组
					'clientid'=> self::getuserinfo($tomsgid,'clientid'), //第三方推送客户端ID
					'expire' => 36,//推送过期时长		
				);
				$thridsend = $this->thridpush($datathrid);
				if( $thridsend !== 1){ //推送失败
					
				}
	   		}
			//
			$returndata ['result'] = 1;
			$returndata ['data'] = json_encode ( array (
					'result' => 1,
					'addtime'=>date('Y-m-d H:i:s') 
			), JSON_UNESCAPED_UNICODE );
		} catch ( Exception $e ) {
			$returndata ['result'] = 0;
			$returndata ['code'] = 20006;
			$returndata ['msg'] = $e->getMessage ();
		}
		return $returndata;
	}
	
	/**
	 * 加载对话信息
	 * 
	 * @param array $data        	
	 */
	public function loaduser($data){
		$returndata = array();
		try {
			$config = self::$configs;
			$connect = $config ['REDISCONNET'];
			if($config['SERVICEID'] == $_GET['appid']){
				$data['userid'] = 0; //如果来源于客服接口，刚调用客服公用ID
			}
			
			$redis = new \Redis ();
			$redis->connect ( $connect ['host'], $connect ['port'] );
			if ($connect ['pass']) {
				$redis->auth ( $connect ['pass'] );
			}
			$res = $redis->keys( $connect ['prefix'] . "_apimessagetouserid_".$data['userid']."*"); //查找有没有新消息接收
			$infouser = array();
			foreach ($res as $v){
				$i = 1;
				if($i>=50){ //一次最多只读取最新的50条消息
					break;
				}
				$userinfo = explode("_", str_replace($connect ['prefix'] . "_apimessagetouserid_", "", $v));
				$userid = ($userinfo[0] == $data['userid'])?$userinfo[1]:$userinfo[0];
				$userinfo = self::getuserinfo($userid,'id,nickname,picture');
				$userinfo['chatnumber'] = $redis->get($v);
				$infouser[] = $userinfo;
				$i++;
			}
			$redis->close ();
			if($res){
				$returndata ['result'] = 1;
				$returndata ['data'] = json_encode ($infouser,JSON_UNESCAPED_UNICODE );
			}
			
		} catch (Exception $e) {
			$returndata ['result'] = 0;
			$returndata ['code'] = 20006;
			$returndata ['msg'] = $e->getMessage ();
		}
		return $returndata;
	}
	
	/**
	 * 查询最新的聊天记录内容
	 * @param unknown $data
	 */
	public function getnewmessage($data){
		$returndata = array();
		try{
			$config = self::$configs;
			$connect = $config ['REDISCONNET'];
			if($config['SERVICEID'] == $_GET['appid']){
				$data['userid'] = 0; //如果来源于客服接口，刚调用客服公用ID
			}				
			$redis = new \Redis ();
			$redis->connect ( $connect ['host'], $connect ['port'] );
			if ($connect ['pass']) {
				$redis->auth ( $connect ['pass'] );
			}
			$dailog = array();
			if($data['getuserid']){ //要取正在对话的新内容
				$rdsname = $connect ['prefix'] . "_apimessagetouserid_".$data['userid']."_".$data['getuserid'];
				$newcount = $redis->get($rdsname); //取新内容
				if($newcount >= 1){ //如果有新的内容则读取
					$order = ($data ['userid'] > $data['getuserid']) ? ($data ['userid'] . "_" . $data['getuserid']) : ($data['getuserid'] . "_" . $data ['userid']);
					$rdsname = $connect ['prefix'] . "_apimessage_" . $order; // 定义缓存键名
					$newcontent = $redis->lrange($rdsname,0,($newcount-1));
					$datas = array ();
					foreach ( $newcontent as $v ) {
						$rs = json_decode ( $v, true );
						$datas [] = array (
								'content' => $rs ['content'], // 消息内容
								'addtime' => date ( "Y-m-d H:i:s", $rs ['addtime'] ), // 消息时间
								'userid' => $rs ['userid']  // 消息发送人ID
						);
					}
					$dailog = $datas; //准备返回正在聊天的内容
					$rdsname = $connect ['prefix'] . "_apimessagetouserid_".$data['userid']."_".$data['getuserid'];
					$redis->del($rdsname); //新的聊天内容取完了 删除
				}
			}
			$res = $redis->keys( $connect ['prefix'] . "_apimessagetouserid_".$data['userid']."*"); //查找有没有新消息接收
			$infouser = array();
			foreach ($res as $v){
				$i = 1;
				if($i>=50){ //一次最多只读取最新的50条消息
					break;
				}				
				$userinfo = explode("_", str_replace($connect ['prefix'] . "_apimessagetouserid_", "", $v));
				$userid = ($userinfo[0] == $data['userid'])?$userinfo[1]:$userinfo[0];
				if($userid != $data['getuserid']){
				$userinfo = self::getuserinfo($userid,'id,nickname,picture');
				$userinfo['chatnumber'] = $redis->get($v);
				$infouser[] = $userinfo;
				}
				$i++;
			}
			$returndata ['result'] = 1;
			$returndata ['data'] = json_encode (array(
				'userinfo' => $infouser,
				'dailog' => $dailog	
			),JSON_UNESCAPED_UNICODE );
			$redis->close();
		}catch (Exception $e){
			$returndata ['result'] = 0;
			$returndata ['code'] = 20006;
			$returndata ['msg'] = $e->getMessage ();
		}
		return $returndata;
	}
	
	/**
	 * 信息推送
	 * 
	 * @param array $data        	
	 */
	function thridpush($data) {
		if(!$data['clientid']){
			return "没有指定用户，不能推送";
		}
		$config = self::$configs ['THIRDPUSHAPP'];
		define ( 'APPKEY', $config ['AppKey'] );
		define ( 'APPID', $config ['AppID'] );
		define ( 'MASTERSECRET', $config ['MasterSecret'] );
		define ( 'HOST', 'http://sdk.open.api.igexin.com/apiex.htm' );
		$psks = new Prpcrypt ( $config ['cryptkey'] ); // 加解密方法
		$data ['message'] = $psks->encrypt ( json_encode ( $data['message'], JSON_UNESCAPED_UNICODE ), $config ['cryptid'] );
		$igt = new IGeTui ( HOST, APPKEY, MASTERSECRET );
		$template =  new IGtTransmissionTemplate();
		$template->set_appId(APPID);//应用appid
		$template->set_appkey(APPKEY);//应用appkey
		$template->set_transmissionType(2);//透传消息类型
		$template->set_transmissionContent($data ['message']);//透传内容
		//个推信息体
		$message = new IGtSingleMessage();		
		$message->set_isOffline(true);//是否离线
		$message->set_offlineExpireTime(3600*($data['expire']?$data['expire']:12)*1000);//离线时间
		$message->set_data($template);//设置推送消息类型
		$message->set_PushNetWorkType(0);//设置是否根据WIFI推送消息，1为wifi推送，0为不限制推送
		//接收方
		$target = new IGtTarget();
		$target->set_appId(APPID);
		$target->set_clientId($data['clientid']);
		$rep = $igt->pushMessageToSingle($message,$target);
		if($rep['result']==ok){
			return 1;
		}else{
			return $rep;
		}
	}
}

?>