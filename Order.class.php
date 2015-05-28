<?php
// +----------------------------------------------------------------------
// | 云厨电商  2015-5-16 下午6:57:22
// +----------------------------------------------------------------------
// | Copyright (c) 2015-2015 http://clofood.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: cnvj <1403729427@qq.com>
// +----------------------------------------------------------------------
use Appclass\Pdomysql;

class Order extends Pdomysql{

    public function createorder($data){
        $returndata = array ();
        try {
            if(empty($data['uuid']) || $this->redis->get(REDIS_PREFIX.'uuid_mobilecode_'.$data['mobilecode']) != $data['uuid']){
                throw new Exception('页面UUID来源错误');
            }else{
                $this->redis->del(REDIS_PREFIX.'uuid_mobilecode_'.$data['mobilecode']);
            }
            if(isset($data['userid'])){//已经登录
                if($data['mobilecode'] == '' || $this->redis->get(REDIS_PREFIX.'api_mobilecode_' . $data['userid']) != $data['mobilecode']){
                    throw new Exception('登录信息不正确');
                }
                $logindata = array(); //登录信息 已经登录不用返回
            }else{//没有登录
                if(empty($data['mobile']) || !ismobile($data['mobile'])){
                    throw new Exception('手机号码不正确');
                }
                if(empty($data['code']) || $this->redis->get(REDIS_PREFIX.'api_mobile_' . $data ['mobile']) != $data['code']){
                    throw new Exception('验证码不正确');
                }
                $this->redis->del(REDIS_PREFIX.'api_mobile_' . $data ['mobile']); //删除验证码
                $userinfo = $this->fetOne('clo_user','*','mobile ='.$data['mobile']); //查找用户
                if($userinfo){ //帐号存在
                    if($userinfo['status'] === 0 || $userinfo['deleted'] === 1){ //帐号被禁用
                        throw new Exception('用户被禁用，请联系客服');
                    }
                    $data['userid'] = $userinfo['id'];
                    //登录信息
                    $logindata = array (
                        'status' => $userinfo ['status'],
                        'id' => $userinfo ['id'],
                        'mobile' => $userinfo ['mobile'],
                        'integral' => $userinfo ['integral'],
                        'money' => $userinfo ['money'],
                        'gender' => $userinfo ['gender'],
                        'both' => (($userinfo ['boths'] != null) ? date ( "Y-m-d", $userinfo ['boths'] ) : null),
                        'nickname' => $userinfo ['nickname'],
                        'picture' => $userinfo ['picture']
                    );
                    
                    $this->redis->set(REDIS_PREFIX.'api_mobilecode_' . $userinfo['id'], $data['mobilecode'] ); // 记录用户登录信息
                }else{//帐号不存在
                    //注册帐号======================================================
                    $pass = rand(100000, 999999);
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
                    $data['userid'] = $id;
                    //登录信息
                    $logindata = array (
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
                    //注册帐号结束=================================================================
                }
            }

            $balance = $this->fetOne('clo_user','integral,mobile,money,paypassword','id='.$data['userid']); //当前帐号信息
            
            
            //处理收货地址
            if(empty($data['addressinfo']) || !is_array(json_decode($data['addressinfo'],true))){
                throw new  Exception('地址详情错误错误');
            }else{
            	$address = json_decode($data['addressinfo'],true);
                if($id > 0){//是新注册的用户添加地址                    
                    $addaddress = $this->add('clo_address', array(
                        'userid' => $data['userid'],
                        'realname'  => $address['realname'],
                        'published' => time(),
                        'mobile'	=> $address['mobile'],
                        'address'	=> $address['address'],
                        'province'	=> $address['province'],
                        'city'		=> $address['city'],
                        'district'	=> $address['district'],
                        'provinceid'=> $address['provinceid'],
                        'cityid'	=> $address['cityid'],
                        'districtid'=> $address['districtid'],
                        'street'    => $address['street'],
                        'streetid'  => $address['streetid'],
                        'village'   => $address['village'],
                        'villageid' => $address['villageid'],
                        'deleted'	=> 0,
                        'status'	=> 1
                    ));
                    //把地址记录到缓存
                    $addr = $this->fetAll('clo_address','*','status desc,published desc','deleted = 0 and userid ='.$data['userid']);
                    $this->redis->set(REDIS_PREFIX.'address_'.$data['userid'],json_encode($addr,JSON_UNESCAPED_UNICODE));
                }
            }
            
            //处理订单商品
            if(empty($data['product']) || !is_array(json_decode($data['product'],true))){
                throw new  Exception('产品订单商品错误');
            }else{
                $pro = array();
                $price = 0;
                foreach (json_decode($data['product'],true) as $k=>$v){
                    if(intval($k) < 1 || intval($v) < 1){
                        throw new Exception('商品信息错误');
                    }
                    $proinfo = $this->getproudct($k,$v,$address['villageid']);
                    $price += round($proinfo['productprice'],2)*intval($v);
                    $pro[] = $proinfo;
                }
            }
            
            //邮费
            if(isset($data['postage']) && $data['postage'] > 0){
                $price += round($data['postage'],2);
            }
            
            //订单总额判断
            if(empty($data['orderprice']) || $price != $data['orderprice']){
                throw new Exception('提交的金额与系统产生的金额不符合');
            }
            
            //余额支付
            if(isset($data['balance']) && $data['balance']>0 ){
                if($balance['status'] === 0 || $balance['deleted'] ===1){
                    throw new Exception('帐号被禁用');
                }
                if(!$balance['paypassword']){
                    throw new Exception('支付密码还没有设置，请设置支付密码后再进行支付');
                }
                if(isset($data['paypassword']) && $data['paypassword'] != ""){//判断支付密码是否正确
                    if(password($data['paypassword'].$balance['mobile']) != $balance['paypassword']){
                        throw new Exception('支付密码错误');
                    }
                }else{
                    throw new Exception('支付密码没有输入');
                }
                if($data['balance'] > $balance['money']){
                    throw new Exception('余额不足,请充值后再进行支付');
                }
                $price -= round($data['balance'],2);
            }
            
            //积分支付
            if(isset($data['integral']) && $data['integral'] > 0){
                if($data['integral'] > $balance['integral']){
                    throw new Exception('帐户积分不足');
                }
                $price -= round(intval($data['integral'])/100,2);
            }
            
            //代金券支付
            if(isset($data['voucherid']) && $data['voucherid']){
               $voucher = $this->fetOne('clo_voucher','*','voucherid='.$data['voucherid']);
               if($voucher['userid'] != $data['userid'] || $voucher['status'] === 0 || $voucher['deleted'] === 1){
                   throw new Exception('代金券不可用');
               }
               if($voucher['starttime']<time() || $voucher['endtime']>time()){
                   throw new Exception('代金券不在使用期');
               }
               if($voucher['limited']){
                   if($price < $voucher['minimum']){
                       throw new Exception('代金券超过了限制值');
                   }
               }
               $price -= round($voucher['price'],2);
            }                     
            
            //判断要支付的金额
            if(isset($data['payprice']) && $data['payprice'] != $price){
               throw new Exception("要支付的金额与服务器计算的不同");
            }
            
            //拼接收货地址
            if(empty($address['address']) || empty($address['province']) || empty($address['city']) || empty($address['provinceid']) || empty($address['cityid']) || empty($address['street']) || empty($address['streetid']) || empty($address['village']) || empty($address['villageid'])){
                throw new Exception('收货地址信息不完整');
            }else{
                $realaddress = $address['province'].$address['city'].$address['district'].$address['street'].$address['village'].$address['address'];
            }
            
            //生成订单编号
            $orderno = $this->redis->get(REDIS_PREFIX.'orderno');
            $this->redis->incr(REDIS_PREFIX.'orderno');
            if(!$orderno){
                $order_no = date('Ymd').substr($address['provinceid'], 0,2).'0000000001';                
            }else{
                $this->redis->expire(REDIS_PREFIX.'orderno',strtotime(date('Y-m-d')." 23:59:59")-time());
                $order_no = date('Ymd').substr($address['provinceid'], 0,2).substr('000000000'.($orderno+1), -10);
            }
            
            //准备写入订单
            $innerorderdata = array(
                'order_no' => $order_no,
                'published'=> time(),
                'status'   => ($data['balance']>0)?1:2,
                'orderprice' => round($data['orderprice'],2),
                'poscode'  => ($data['postage']>0)?round($data['postage'],2):0.00,
                'sendtime' => $data['postage']?:"不限",
                'remark'   => $data['remark'],
                'consignee'=> $address['realname'],
                'mobile'   => $address['mobile'],
                'address'  => $realaddress,
                'voucherid'=> $data['voucherid']?:0,
                'voucherprice'=> $data['voucherid']?$voucher['price']:0.00,
                'integralprice'=>($data['integral']>0)?round(intval($data['integral'])/100,2):0.00,
                'balance'  => round($data['balance'],2),
                'userid'   => $data['userid'],
            	'payprice' => $data['payprice']	                
            );
            $innerorder = $this->add('clo_orders', $innerorderdata);
            if($innerorder){
                foreach ($pro as $v){ //向订单产品表写数据
                    $this->add('clo_orders_products', array(
                        'order_no' => $order_no,
                        'productid'=> $v['productid'],
                        'productprice'=>$v['productprice'],
                        'batch'    => $v['batch'],
                        'inventory'=> $v['inventory'],
                        'publised' => time(),
                        'deleted'  => 0,
                        'userid'   => $data['userid']
                    ));
                }
            }else{
                throw new Exception('订单生成失败');
            }            
            
            $updateuserinfo = array();
            if(isset($data['integral']) && $data['integral']>0){ //有积分支付
                $updateuserinfo['integral'] = $balance['integral']-intval($data['integral']);            
            }
            
            if(isset($data['balance']) && $data['balance']>0){ //余额支付
                $updateuserinfo['money'] = round($balance['money']-$data['balance'],2);
                $updateuserinfo['integral'] = $updateuserinfo['integral']?($updateuserinfo['integral']+intval($data['orderprice'])):($balance['integral']+intval($data['orderprice']));
                $this->add('clo_financelog', array('userid'=>$data['userid'],'price'=>round($data['balance'],2),'published'=>time(),'info'=>$order_no,'balance'=>$updateuserinfo['money'])); //添加日志
                //库存变化
                foreach ($pro as $v){
                    $this->redis->zincrby(REDIS_PREFIX.'sellerproduct_'.$v['sellerid'],-$v['inventory'],$v['productid']);
                    //TODO 暂时不对数据库写入数据
                }
            }
            
            if(count($updateuserinfo)>0){
                $this->update('clo_user', $updateuserinfo, 'id ='.$data['userid']);
            }
            
            if(isset($data['voucherid'])){ //有代金券支付
                $this->update('clo_voucher', array('status'=>0,'order_no'=>$order_no,'gone'=>date('Y-m-d H:i:s')."订单支付"), 'voucherid='.$data['voucherid']);
            }
            
                                    
			$returndata ['result'] = 1;
			$returndata ['data'] = json_encode ( array('logininfo'=>$logindata,'order_no'=>$order_no), JSON_UNESCAPED_UNICODE );
		} catch ( Exception $e ) {
			$returndata ['result'] = 0;
			$returndata ['code'] = 20006;
			$returndata ['msg'] = $e->getMessage ();
		}
		return $returndata;	
    }
    
    /**
     * 取商品信息
     * @param number $productid 商品ID
     * @param number $quantity 购买数量
     * @param number $villageid 小区ID
     * @return boolean
     */
    protected function getproudct($productid,$inventory,$villageid){
        $product = $this->redis->get(REDIS_PREFIX.'productid_'.$productid); //找到商品
        if(!$product){
        	$product = $this->fetOne('clo_products','*','id ='.$productid);
        	if(!$product){
            	throw new Exception('产品ID不存在');
        	}
        }else{
        	$product = json_decode($product,true);
        }
             $sellerid = $this->redis->get(REDIS_PREFIX.'villageid_'.$villageid); //找到商家ID
//             $int = $this->redis->zscore(REDIS_PREFIX.'sellerproduct_'.$sellerid,$productid);
//             if($inventory > $int){
//                 throw new Exception('最近商家库存不足');
//             }
            $resdata = array(
                'productid' => $product['id'],
                'productprice' => $product['shopprice'],
                'batch'     => $product['batch'],
                'inventory' => $inventory,
                'sellerid'  => $sellerid,
            );   
        return $resdata; 
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