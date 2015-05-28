<?php
// +----------------------------------------------------------------------
// | Qiduoke      2015-2-5 上午10:04:47
// +----------------------------------------------------------------------
// | Copyright (c) 2014-2014 http://clofood.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: cnvj <1403729427@qq.com>
// +----------------------------------------------------------------------

use Appclass\Pdomysql;

/**
 * 产品展示类
 *
 * @author Administrator
 *
 */
class Products extends Pdomysql {

	/**
	 * 商品列表页
	 * @param array $data
	 * @throws Exception
	 * @return multitype:number string NULL
	 */
	public function search($data){
		$returndata = array ();
		try {
			$sql = 'SELECT * FROM clo_products where deleted = 0';
			if(!empty($data['keywords'])){
				$sql .= ' and product_name like "%'.$data['keywords'].'%"';
			}
			if(!empty($data['category'])){
				$sql .= ' and category ='.$data['category'];
			}
			$sqlcount = $sql;
			$page = (isset($data['page']) && intval($data['page'])>0)?intval($data['page']):1;
			$pagenumber = (isset($data['pagelist']) && intval($data['pagelist'])>0)?intval($data['pagelist']):PAGENUMBER;
			$sql .= ' order by id desc limit '.($page-1)*$pagenumber.','.$pagenumber;
			$datarecode = $this->getAll($sql);
			$record = $this->getRowCount($sqlcount);
			$datalist = array();
			foreach ($datarecode as $v){
				$datalist[] = array(
					'id'		   => $v['id'],
				    'product_no'   => $v['product_no'],	
					'product_name' => $v['product_name'],
					'sales_name'   => $v['sales_name'],
					'inventory'    => $v['inventory'],
					'salequantity' => $v['initialinventory']-$v['inventory'],
					'comment'	   => intval($this->redis->get(REDIS_PREFIX.'comment_'.$v['id'])),
					'supermarket'  => number_format($v['supermarket'],2),
					'shopprice'	   => number_format($v['shopprice'],2),
					'picture'	   => $v['picture'],
					'status'	   => $v['status'],
					'published'    => date('Y-m-d H:i:s',$v['published'])
				);
			}
			$returndata ['result'] = 1;
			$returndata ['data'] = json_encode (array('datalist'=>$datalist,'totalpage'=>ceil($record/$pagenumber),'currentpage'=>$page,'record'=>$record), JSON_UNESCAPED_UNICODE );
		} catch ( Exception $e ) {
			$returndata ['result'] = 0;
			$returndata ['code'] = 20006;
			$returndata ['msg'] = $e->getMessage ();
		}
		return $returndata;
	}
	
	/**
	 * 商品详情页
	 * @param array $data
	 * @throws Exception
	 * @return multitype:number string NULL
	 */
	public function info($data){
		$returndata = array ();
		try {
			if(empty($data['id']) && empty($data['product_no'])){
				throw new Exception('错误的ID参数');
			}
			if(isset($data['id'])){
			    $v = $this->fetOne('clo_products','*','deleted = 0 and id ='.$data['id']);
			}else{
			    $v = $this->fetOne('clo_products','*','deleted = 0 and product_no ="'.$data['product_no'].'"');
			}
			if(!$v){
				throw new Exception('该ID不存在');
			}
			$datalist = array(
					'id'		   => $v['id'],
					'product_name' => $v['product_name'],
			        'product_no'   => $v['product_no'],
					'sales_name'   => $v['sales_name'],
					'inventory'    => $v['inventory'],
					'salequantity' => $v['initialinventory']-$v['inventory'],
					'comment'	   => intval($this->redis->get(REDIS_PREFIX.'comment_'.$v['id'])),
					'supermarket'  => number_format($v['supermarket'],2),
					'shopprice'	   => number_format($v['shopprice'],2),
					'picture'	   => $v['picture'],
					'status'	   => $v['status'],
					'published'    => date('Y-m-d H:i:s',$v['published'])					
			);
			$returndata ['result'] = 1;
			$returndata ['data'] = json_encode ($datalist, JSON_UNESCAPED_UNICODE );
		} catch ( Exception $e ) {
			$returndata ['result'] = 0;
			$returndata ['code'] = 20006;
			$returndata ['msg'] = $e->getMessage ();
		}
		return $returndata;
	}
	
	/**
	 * 商品详情页多个
	 * @param array $data
	 * @throws Exception
	 * @return multitype:number string NULL
	 */
	public function infomore($data){
	    $returndata = array ();
	    try {
	        if(empty($data['product_no'])){
	            throw new Exception('错误的ID参数');
	        }
	        $datasearcg ='';
	        foreach (explode(',', $data['product_no']) as $v){
	            $datasearcg .= '"'.$v.'",';
	        }
	        $res = $this->fetAll('clo_products','*','','deleted = 0 and product_no in('.substr($datasearcg, 0,-1).')');
	        $datalist = array();
	        foreach ($res as $v){
    	        $datalist[] = array(
    	            'id'		   => $v['id'],
    	            'product_name' => $v['product_name'],
    	            'product_no'   => $v['product_no'],
    	            'sales_name'   => $v['sales_name'],
    	            'inventory'    => $v['inventory'],
    	            'salequantity' => $v['initialinventory']-$v['inventory'],
    	            'comment'	   => intval($this->redis->get(REDIS_PREFIX.'comment_'.$v['id'])),
    	            'supermarket'  => number_format($v['supermarket'],2),
    	            'shopprice'	   => number_format($v['shopprice'],2),
    	            'picture'	   => $v['picture'],
    	            'status'	   => $v['status'],
    	            'published'    => date('Y-m-d H:i:s',$v['published'])
    	        );
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
	 * 商品详情页
	 * @param array $data
	 * @throws Exception
	 * @return multitype:number string NULL
	 */
	public function productcomment($data){
		$returndata = array ();
		try {
			if(empty($data['productid'])){
				throw new Exception('错误的productid参数');
			}
			$page = (intval($data['page'])>0)?intval($data['page']):1;
			$datarecode = $this->fetAll('clo_comments','*','published desc','deleted = 0 and productid ='.$data['productid'],($page-1)*PAGENUMBER.','.PAGENUMBER);
			$record = $this->fetRowCount('clo_comments','*','deleted = 0 and productid ='.$data['productid']);
			$record = $record['num'];
			$datalist = array();
			foreach ($datarecode as $v){
				$datalist[] = array(
					'id'	=> $v['id'],	
					'stars' => $v['stars'],
					'comment' => $v['comment'],
					'published' => date('Y-m-d H:i:s',$v['published']),
					'userinfo' => self::getuserinfo($v['userid'], 'nickname,picture')			
				);
			}
			$returndata ['result'] = 1;
			$returndata ['data'] = json_encode (array('datalist'=>$datalist,'totalpage'=>ceil($record/PAGENUMBER),'currentpage'=>$page,'record'=>$record), JSON_UNESCAPED_UNICODE );
		} catch ( Exception $e ) {
			$returndata ['result'] = 0;
			$returndata ['code'] = 20006;
			$returndata ['msg'] = $e->getMessage ();
		}
		return $returndata;
	}
	
	/**
	 * 取商品分类
	 * @param array $data
	 * @throws Exception
	 * @return multitype:number string NULL
	 */
	public function category($data){
		$returndata = array ();
		try {
			$sql = "SELECT * FROM clo_category WHERE deleted = 0";
			if(isset($data['pid'])){
				$sql .= ' and pid = '.$data['pid'];
			}
			$sql .= ' order by sorting asc';
			$data = $this->getAll($sql);
			$datalist = array();
			foreach ($data as $v){
				$datalist[] = array(
					'category_name' => $v['categoryname'],
					'pid'			=> $v['pid'],
					'picture'		=> $v['picture'],
					'info'			=> $v['info'],
					'id'			=> $v['id']				
				);
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
	 * 取分类品牌
	 * @param array $data
	 * @throws Exception
	 * @return multitype:number string NULL
	 */
	public function brand($data){
	    $returndata = array ();
	    try {
	        $sql = "SELECT * FROM clo_brand WHERE deleted = 0";
	        if(isset($data['categoryid'])){
	            $sql .= ' and categoryid = '.$data['categoryid'];
	        }
	        $sql .= ' order by sorting asc';
	        $data = $this->getAll($sql);
	        $datalist = array();
	        foreach ($data as $v){
	            $datalist[] = array(
	                'brandname' => $v['brandname'],
	                'categoryid'	=> $v['categoryid'],
	                'picture'		=> $v['picture'],
	                'info'			=> $v['info'],
	                'id'			=> $v['id']
	            );
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
	 * 通过类目ID取品牌 子类
	 * @param array $data
	 * @throws Exception
	 * @return multitype:number string NULL
	 */
	public function categorybrand($data){
	    $returndata = array ();
	    try {
	        if(intval($data['categoryid'])<=0){
	            throw new Exception('参数错误');
	        }
	        $categoryid = $data['categoryid'];
	        $sql = "SELECT * FROM clo_brand WHERE deleted = 0";
	        $sql .= ' and categoryid = '.$categoryid;
	        $sql .= ' order by sorting asc';
	        $data = $this->getAll($sql);
	        $datalist = array();
	        foreach ($data as $v){
	            $datalist['brand'][] = array(
	                'brandname' => $v['brandname'],
	                'categoryid'	=> $v['categoryid'],
	                'picture'		=> $v['picture'],
	                'info'			=> $v['info'],
	                'id'			=> $v['id']
	            );
	        }
	        if(!isset($datalist['brand'])){
	            $datalist['brand'] = array();
	        }
			$sql = "SELECT * FROM clo_category WHERE deleted = 0";
			$sql .= ' and pid = '.$categoryid;
			$sql .= ' order by sorting asc';
	        $data = $this->getAll($sql);
	        foreach ($data as $v){
	            $datalist['category'][] = array(
					'category_name' => $v['categoryname'],
					'pid'			=> $v['pid'],
					'picture'		=> $v['picture'],
					'info'			=> $v['info'],
					'id'			=> $v['id']		
	            );
	        }
	        if(!isset($datalist['category'])){
	            $datalist['category'] = array();
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

	
}

?>