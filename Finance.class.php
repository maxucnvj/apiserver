<?php
// +----------------------------------------------------------------------
// | Qiduoke 2015-1-15 上午11:11:10
// +----------------------------------------------------------------------
// | Copyright (c) 2014-2014 http://qidor.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: cnvj <1403729427@qq.com>
// +----------------------------------------------------------------------
use Appclass\user;

/**
 * 财务类
 * @author Administrator
 *
 */
class Finance extends user {
	
	/**
	 * 载入财务列表
	 *
	 * @param array $data        	
	 */
	public function financelist($data) {
		$returndata = array ();
		try {
			$config = self::$configs;
			$where = 'userid =' . $data ['userid'] . ' and status != 99';
			if ($data ['filter'] != "") {
				if ($data ['filter']) {
					$where .= " and getpay = 1";
				} else {
					$where .= " and getpay = 0";
				}
			}
			$count = $this->fetRowCount ( "dig_memberfinance", "id", $where );
			if ($count ['num']) {
				$tatolpage = ceil ( $count ['num'] / $config ['PAGESIZE'] );
				if (( int ) $data ['page'] > 0) {
					if ($tatolpage < ( int ) $data ['page']) {
						throw new Exception ( "页码超过了最大值" );
					}
				} else {
					$data ['page'] = 1;
				}
				$sql = "SELECT * FROM dig_memberfinance WHERE " . $where . " order by addtime desc limit " . ($config ['PAGESIZE'] * (( int ) $data ['page'] - 1)) . "," . $config ['PAGESIZE'];
				$result = $this->getAll ( $sql );
				if (! $result) {
					throw new Exception ( "没有找到财务记录" );
				}
				$datas = array ();
				foreach ( $result as $v ) {
					$datas [] = array (
							'remark' => $v ['remark'], // 备注
							'price' => ($v ['getpay'] ? "+" : "-") . $v ['price'], // 操作金额
							'addtime' => date ( 'Y-m-d H:i:s', $v ['addtime'] ), // 操作时间
							'done' => ($v ['done'] ? date ( 'Y-m-d H:i:s', $v ['done'] ) : ""), // 完成时间
							'issue' => $v ['issue'], // 期数
							'tradeno' => tradeno ( $v ['id'] ), // 流水号
							'getpay' => ($v ['getpay'] ? "收入" : "支出"), // 收支
							'account' => $v ['account'], // 支付宝帐号
							'belong' => $v ['belong'], // 收款人
							'reason' => $v ['reason'], // 被拒原因
							'voucher' => $v ['voucher'], // 转帐凭证
							'status' => $v ['status']  // 状态 （支出时 0申请中 1申请成功 2申请被拒）（收入时 都为0）
										);
				}
				$ret = array (
						'datalist' => $datas,
						'totalpage' => $tatolpage,
						'currentpage' => $data ['page'],
						'record' => $count ['num'] 
				);
				$returndata ['result'] = 1;
				$returndata ['data'] = json_encode ( $ret, JSON_UNESCAPED_UNICODE );
			} else {
				throw new Exception ( "还没有任何财务记录" );
			}
		} catch ( Exception $e ) {
			$returndata ['result'] = 0;
			$returndata ['code'] = 20006;
			$returndata ['msg'] = $e->getMessage ();
		}
		return $returndata;
	}
	
	/**
	 * 提现规则查询
	 *
	 * @param array $data        	
	 */
	public function findcashapplyrule($data) {
		$config = self::$configs;
		$applydone = $this->fetRowCount ( "dig_memberfinance", "id", "getpay = 0 and userid=" . $data ['userid'] . " and status = 0" );
		try {
			if ($applydone ['num']) {
				throw new Exception ( "上一次提现还没有完成，您可以撤消提现后再次进行申请", 1 );
			}
			$apply = $this->fetRowCount ( "dig_memberfinance", "id", "getpay = 0 and userid=" . $data ['userid'] . " and status = 1" );
			if ($apply ['num'] || $config ['CASHAPPLY'] ['first']) { // 不是第一次提现 或者第一次提现有要求
				if ($config ['CASHAPPLY'] ['lastprize']) { // 是否要求参与了上一期抽奖
					$issue = $this->getOne ( "SELECT issue FROM dig_prize order by issue desc limit 0,1" );
					$isapply = $this->fetRowCount ( 'dig_openning', "id", "issue =" . $issue ['issue'] . " and userid =" . $data ['userid'] );
					if (! $isapply ['num']) { // 没有参与上一期抽奖
						throw new Exception ( "没有参与上一期抽奖，快获取工分参与下一期的抽奖吧", 2 );
					}
				}
			}
			if(!self::getuserinfo($data['userid'], 'password')){
				throw new Exception ( "帐号密码还没有设置", 3 );
			}
			$status = 0;
			$msg = "";
		} catch ( Exception $e ) {
			$status = $e->getCode ();
			$msg = $e->getMessage ();
		}
		$returndata = array (
				'result' => 1,
				'data' => json_encode ( array (
						'status' => $status, // 状态 值见文档
						'msg' => $msg, // 规则内容
						'share' => $config ['CASHAPPLY'] ['share'], // 分享 1，0
						'min' => $config ['CASHAPPLY'] ['min'], // 最小值 0为不限制
						'max' => $config ['CASHAPPLY'] ['max'], // 最大值 0为不限制
						'first' => $config ['CASHAPPLY'] ['first'], // 第一次是否要求 1要求 0不要求
						'isfirst' => ($apply ['num'] ? 0 : 1)  // 是否是第一次提现 1是 0不是
								), JSON_UNESCAPED_UNICODE ) 
		);
		return $returndata;
	}
	
	/**
	 * 提现申请
	 */
	public function cashapply($data) {
		$returndata = array ();
		try {
			$config = self::$configs;
			if (! (ismobile ( $data ['account'] ) || isemail ( $data ['account'] ))) {
				throw new Exception ( "提现帐号不正确" );
			}
			if (strlen ( $data ['belong'] ) < 2) {
				throw new Exception ( "提现姓名不正确" );
			}
			if (password($data['password']) != self::getuserinfo($data['userid'], 'password')) {
				throw new Exception ( "密码不正确" );
			}
			$applydone = $this->fetRowCount ( "dig_memberfinance", "id", "getpay = 0 and userid=" . $data ['userid'] . " and status = 0" );
			if ($applydone ['num']) {
				throw new Exception ( "上一次提现还没有完成" );
			}
			$apply = $this->fetRowCount ( "dig_memberfinance", "id", "getpay = 0 and userid=" . $data ['userid'] . " and status = 1" );
			
			if ($apply ['num'] || $config ['CASHAPPLY'] ['first']) { // 不是第一次提现 或者第一次提现有要求
				if ($config ['CASHAPPLY'] ['min']) { // 最小提现
					if ($config ['CASHAPPLY'] ['min'] > $data ['money']) {
						throw new Exception ( "提现金额小于最小要求金额" );
					}
				}
				if ($config ['CASHAPPLY'] ['max']) { // 最大提现
					if ($config ['CASHAPPLY'] ['max'] < $data ['money']) {
						throw new Exception ( "提现金额大于最大要求金额" );
					}
				}
				if ($config ['CASHAPPLY'] ['lastprize']) { // 是否参与了上次抽奖
					$issue = $this->getOne ( "SELECT issue FROM dig_prize order by issue desc limit 0,1" );
					$isapply = $this->fetRowCount ( 'dig_openning', "id", "issue =" . $issue ['issue'] . " and userid =" . $data ['userid'] );
					if (! $isapply ['num']) {
						throw new Exception ( "没有参与上一期的抽奖" );
					}
				}
			}
			$money = $this->fetOne ( "dig_member", "money", "id =" . $data ['userid'] );
			if ($money ['money'] < $data ['money']) { // 提现金额大于帐户金额
				throw new Exception ( "提现金额大于帐户资金" );
			}
			$this->add ( "dig_memberfinance", array (
					'userid' => $data ['userid'],
					'getpay' => 0,
					'price' => $data ['money'],
					'addtime' => time (),
					'remark' => $data ['remark'],
					'account' => $data ['account'],
					'belong' => $data ['belong'],
					'status' => 0 
			) );
			$id = $this->getLastId ();
			$returndata ['result'] = 1;
			$returndata ['data'] = json_encode ( array (
					'tradeno' => tradeno ( $id ) 
			), JSON_UNESCAPED_UNICODE );
		} catch ( Exception $e ) {
			$returndata ['result'] = 0;
			$returndata ['code'] = 20006;
			$returndata ['msg'] = $e->getMessage ();
		}
		return $returndata;
	}
	
	/**
	 * 取消提现申请
	 */
	public function cancelapply($data) {
		$returndata = array ();
		try {
			$id = tradenode ( $data ['tradeno'] );
			$status = $this->fetOne("dig_memberfinance","status","id =".$id." and getpay = 0 and userid =".$data['userid']);
			if($status){
				if($status['status'] !== "0"){
					throw new Exception ( "提现状态已经变更,不能取消" );
				}
				$r = $this->execute("update dig_memberfinance set status = 99 where id =".$id);
				if(!$r){
					throw new Exception ( "操作失败" );
				}
				$returndata ['result'] = 1;
				$returndata ['data'] = json_encode ( array (
						'status' => 1
				), JSON_UNESCAPED_UNICODE );
			}else {
				throw new Exception ( "没有找到流水号" );
			}
		} catch ( Exception $e ) {
			$returndata ['result'] = 0;
			$returndata ['code'] = 20006;
			$returndata ['msg'] = $e->getMessage ();
		}
		return $returndata;
	}
}

?>