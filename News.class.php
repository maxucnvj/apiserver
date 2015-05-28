<?php
// +----------------------------------------------------------------------
// | Qiduoke 2015-1-22 下午2:47:25
// +----------------------------------------------------------------------
// | Copyright (c) 2014-2014 http://qidor.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: cnvj <1403729427@qq.com>
// +----------------------------------------------------------------------
use Appclass\user;

/**
 * 新闻文章页
 *
 * @author Administrator
 *        
 */
class News extends user {
	
	/**
	 * 列表页显示
	 *
	 * @param array $data        	
	 */
	public function newslist($data) {
		$returndata = array ();
		try {
			$config = self::$configs;
			if (! $data ['typeid']) {
				throw new Exception ( "类别不能设置为空" );
			}
			$count = $this->fetRowCount ( "dig_content", "id", "status = 1 and typeid=" . $data ['typeid'] );
			if ($count ['num']) { // 有记录取记录
				$tatolpage = ceil ( $count ['num'] / $config ['PAGESIZE'] );
				if (( int ) $data ['page'] > 0) {
					if ($tatolpage < ( int ) $data ['page']) {
						throw new Exception ( "没有更多的记录了" );
					}
				} else {
					$data ['page'] = 1;
				}
				$ress = rds ( 'apinews_typeid_' . $data ['typeid'] . '_page_' . $data ['page'] ); // 读取缓存记录
				if ($ress) {
					$res = json_decode ( $ress, true );
				} else {
					$res = $this->fetAll ( "dig_content", "id,title,description,addtime,typeid,blocktime", "addtime desc", "status = 1 and typeid =" . $data ['typeid'], $config ['PAGESIZE'] * ($data ['page'] - 1) . "," . $config ['PAGESIZE'] );
					rds ( 'apinews_typeid_' . $data ['typeid'] . '_page_' . $data ['page'], json_encode ( $res ), 600 );
				}
				$datas = array ();
				$redis = new \Redis ();
				$redis->connect ( $config ['REDISCONNET'] ['host'], $config ['REDISCONNET'] ['port'] );
				if ($config ['REDISCONNET'] ['pass']) {
					$redis->auth ( $config ['REDISCONNET'] ['pass'] );
				}
				foreach ( $res as $v ) {
					$datas [] = array (
							'id' => $v ['id'], // 文章ID
							'title' => $v ['title'], // 标题
							'description' => $v ['description'], // 简介
							'addtime' => date ( 'Y-m-d H:i:s', $v ['addtime'] ), // 添加时间
							'url' => "http://www.dighour.com/help/".$v['id'].".html",
							'status' => (!$v['blocktime'] || $v['blocktime'] > time())?($redis->get ( $config ['REDISCONNET'] ['prefix'] . "apinewsreadid_" . $v ['id'] . "_userid_" . $data ['userid'] ) ? 1 : 0):1  // 该用户有无读取 0没读 1读了
							);
				}
				$redis->close ();
				$ret = array (
						'datalist' => $datas,
						'totalpage' => $tatolpage,
						'currentpage' => $data ['page'],
						'record' => $count ['num'] 
				);
				$returndata ['result'] = 1;
				$returndata ['data'] = json_encode ( $ret, JSON_UNESCAPED_UNICODE );
			} else {
				throw new Exception ( "还没有任何记录" );
			}
		} catch ( Exception $e ) {
			$returndata ['result'] = 0;
			$returndata ['code'] = 20006;
			$returndata ['msg'] = $e->getMessage ();
		}
		return $returndata;
	}
	
	/**
	 * 文章显示
	 *
	 * @param array $data        	
	 */
	public function newscontent($data) {
		$returndata = array ();
		try {
			if((int)$data['id']<1){
				throw new Exception("不是一个有效ID");	
			}
			$res = rds("apinewscontent_".$data['id']);
			if($res){
				$res = json_decode ( $res, true );
			}else{
				$res = $this->fetOne("dig_content","content,blocktime","status =1 and id =".$data['id']);
				if(!$res){
					throw new Exception("没有找到相关内容");
				}
				rds("apinewscontent_".$data['id'],json_encode($res));
			}
			if($res['blocktime'] > time()){ //记录用户读取过新闻内容
				rds("apinewsreadid_" . $data ['id'] . "_userid_" . $data ['userid'],1,$res['blocktime']-time());
			}elseif(!$res['blocktime']){//如果文章时效为0 永久记录
				rds("apinewsreadid_" . $data ['id'] . "_userid_" . $data ['userid'],1);
			}
			$returndata ['result'] = 1;
			$returndata ['data'] = json_encode ( array("content"=>$res['content']), JSON_UNESCAPED_UNICODE );
		} catch ( Exception $e ) {
			$returndata ['result'] = 0;
			$returndata ['code'] = 20006;
			$returndata ['msg'] = $e->getMessage ();
		}
		return $returndata;
	}
}

?>