<?php
// +----------------------------------------------------------------------
// | maxucnvj 2015-1-6 下午2:20:40
// +----------------------------------------------------------------------
// | Copyright (c) 2014-2014 http://clofood.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: cnvj <1403729427@qq.com>
// +----------------------------------------------------------------------
use Appclass\Pdomysql;

/**
 * 公共访问类
 * @author Administrator
 *
 */
class Publics extends Pdomysql {
	
	/**
	 * 取地区信息
	 */
	public function getareainfo($data) {
		$returndata = array ();
		try {
		    if(!isset($data['name']) || !isset($data['areaid'])){
		        throw new Exception('没有设定初始化一级地区');
		    }
		    switch ($data['name']){
		        case 'province': //省开始
		    		$cache = $this->redis->get(REDIS_PREFIX.'area_province_'.$data['areaid']);//缓存
            	    if(!$cache){
            	        $datas = array();
                        $dataarea = $this->getAll('Select cityID,city from clo_city where father = '.$data['areaid']); //取市
            	        foreach ($dataarea as $v){
            	            $districts = $this->getAll('Select areaID as districtid,area as district from clo_area where father = '.$v['cityID']); //取区
            	            $datas['province'][] = array(
            	                'city' => $v['city'],
            	                'cityid'=> $v['cityID'],
            	                'districts' => $districts
            	            ); 
            	        }
            	        $this->redis->set(REDIS_PREFIX.'area_province_'.$data['areaid'],json_encode($datas,JSON_UNESCAPED_UNICODE));
            	    }else{
            	        $datas = json_decode($cache,true);
            	    }
		        break;
		        case 'city': //市开始
		    	    $cache = $this->redis->get(REDIS_PREFIX.'area_city_'.$data['areaid']);
            	    if(!$cache){
            	        $datas = array();
                        $dataarea = $this->getAll('Select areaID,area from clo_area where father = '.$data['areaid']); //取区
            	        foreach ($dataarea as $v){
            	            $street = $this->getAll('Select id as streetid,street from clo_street where father = '.$v['areaID']); //取街道
            	            $datas['city'][] = array(
            	                'district' => $v['area'],
            	                'districtid'=> $v['areaID'],
            	                'street' => $street
            	            ); 
            	        }
            	        $this->redis->set(REDIS_PREFIX.'area_city_'.$data['areaid'],json_encode($datas,JSON_UNESCAPED_UNICODE));
            	    }else{
            	        $datas = json_decode($cache,true);
            	    }
		        break;
		        case 'district': //市开始
		            $cache = $this->redis->get(REDIS_PREFIX.'area_district_'.$data['areaid']);
		            if(!$cache){
		                    $datas = array();
		                    $street = $this->getAll('Select id as streetid,street from clo_street where father = '.$data['areaid']); //取街道
		                    $datas['district'][] = array(
		                        'street' => $street
		                    );
		                $this->redis->set(REDIS_PREFIX.'area_district_'.$data['areaid'],json_encode($datas,JSON_UNESCAPED_UNICODE));
		            }else{
		                $datas = json_decode($cache,true);
		            }
		        break;
		        case 'country':
		    		$cache = $this->redis->get(REDIS_PREFIX.'area_province');
		            if(!$cache){
		                $datas = array();
		                $dataarea = $this->getAll('Select provinceid,province from clo_province where deleted = 0'); //取省
		                foreach ($dataarea as $v){
		                    $datas['country'][] = array(
		                        'provinceid' => $v['provinceid'],
		                        'province'=> $v['province']
		                    );
		                }
		                $this->redis->set(REDIS_PREFIX.'area_province',json_encode($datas,JSON_UNESCAPED_UNICODE));
		            }else{
		                $datas = json_decode($cache,true);
		            }
		        break;
		        default:
		            throw new Exception('参数错误');
		        break;
		    }
			$returndata ['result'] = 1;
			$returndata ['data'] = json_encode ( array('datalist'=>$datas), JSON_UNESCAPED_UNICODE );
		} catch ( Exception $e ) {
			$returndata ['result'] = 0;
			$returndata ['code'] = 20006;
			$returndata ['msg'] = $e->getMessage ();
		}
		return $returndata;
	}

	/**
	 * 生成唯一页面UUID
	 */
	public function creatuuid($data){
	    $returndata = array ();
	    try {
    	    $uuid = md5(microtime().$data['mobilecode'].rand(10000,99999));
    	    $this->redis->setex(REDIS_PREFIX.'uuid_mobilecode_'.$data['mobilecode'],300,$uuid);
    	    $returndata ['result'] = 1;
			$returndata ['data'] = json_encode ( array('uuid'=>$uuid,'expired'=>300), JSON_UNESCAPED_UNICODE );
		} catch ( Exception $e ) {
			$returndata ['result'] = 0;
			$returndata ['code'] = 20006;
			$returndata ['msg'] = $e->getMessage ();
		}
		return $returndata;
	}
	
	/**
	 * 
	 * @param array $data
	 */
	public function getvillage($data){
	    $returndata = array ();
	    try {
	      if(empty($data['streetid']) || empty($data['villagename'])){
	           throw new Exception('提交参数错误');
	      }
	      if(isset($data['precision']) && $data['precision'] == 1){
	          $return = $this->fetAll('clo_village','id as villageid,villagename','','streetid ='.intval($data['streetid']).' and villagename = "'.$data['villagename'].'"');	           
	      }else{ 
	           $return = $this->fetAll('clo_village','id as villageid,villagename','','streetid ='.intval($data['streetid']).' and villagename like "%'.$data['villagename'].'%"');
	      }
	      $returndata ['result'] = 1;
	      $returndata ['data'] = json_encode ( array('datalist'=>$return), JSON_UNESCAPED_UNICODE );
	    } catch ( Exception $e ) {
	        $returndata ['result'] = 0;
	        $returndata ['code'] = 20006;
	        $returndata ['msg'] = $e->getMessage ();
	    }
	    return $returndata;
	}
	
	
	
}

?>