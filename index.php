<?php
// +----------------------------------------------------------------------
// | Qiduoke 2015-4-19 下午1:45:54
// +----------------------------------------------------------------------
// | Copyright (c) 2014-2014 All rights reserved.
// +----------------------------------------------------------------------
// | Author: cnvj <1403729427@qq.com>
// +----------------------------------------------------------------------
error_reporting(0);

use Appclass as A; // 引用类命名空间

/**
 * 自动导入加载的类
 */
function __autoload($class) {
	$dir = './';
	set_include_path ( get_include_path () . PATH_SEPARATOR . $dir );
	$class = str_replace ( '\\', '/', $class ) . '.class.php';
	require_once ($class);
}
header("Content-type: text/html; charset=utf-8"); //utf-8 输出
header("X-Powered-By: <cnvj>1403729427@qq.com");

$config = require dirname ( __FILE__ ) . '/common.inc.php'; // 引入公共配置文档;
include dirname ( __FILE__ ) . '/function.php'; // 引入公共函数


define('REDIS_PREFIX','clofood_'); //redis前辍
define('PAGENUMBER',20); //每页条数

if(empty($_GET['appid']) || empty($_GET['mm']) || empty($_POST['data'])){ //判断传参
	echo json_encode(array('result'=>null,'error'=>array('code'=>20000,'msg'=>'传参错误')),JSON_UNESCAPED_UNICODE);
	exit();
}

$appid = (int)$_GET['appid'];

$redis = new \Redis();
$redis->connect($config['REDISCONNET']['host'], $config['REDISCONNET']['port']); //建立redis连接
if($config['REDISCONNET']['pass']){
	$redis->auth($config['REDISCONNET']['pass']);
}
$apiconnect = $redis->get($config['REDISCONNET']['prefix'].'apiconnect_'.$appid); //api连接用户
if(!$apiconnect){ //缓存里不存在
	echo json_encode(array('result'=>null,'error'=>array('code'=>20001,'msg'=>'APPID不存在')),JSON_UNESCAPED_UNICODE);
	exit();
}else{
	$api = unserialize($apiconnect);
}

$arg = explode(".",$_GET['mm']);
if(count($arg)!=2){ //判断请求方法
	echo json_encode(array('result'=>null,'error'=>array('code'=>20002,'msg'=>'MM值长度不正确')),JSON_UNESCAPED_UNICODE);
	exit();
}else{
	$model = ucfirst(strtolower($arg[0]));
	if(!file_exists($model.'.class.php')){
		echo json_encode(array('result'=>null,'error'=>array('code'=>20003,'msg'=>'MM模型不存在')),JSON_UNESCAPED_UNICODE);
		exit();
	}
	$action= $arg[1];
}

if($api['status']){ //是否是使用签名
	if(strtolower(md5($_POST['data'].$api['appkey']))!=strtolower($_POST['sign'])){
		echo json_encode(array('result'=>null,'error'=>array('code'=>20004,'msg'=>'签名不正确')),JSON_UNESCAPED_UNICODE);
		exit();
	}else{
		$data = $_POST['data'];
	}
}else{
	$psk = new A\Prpcrypt($api['appkey']); //加解密方法
	$data = $psk->decrypt($_POST['data'], $appid);
}

if(!$data){
	echo json_encode(array('result'=>null,'error'=>array('code'=>20004,'msg'=>'请检查来源数据是否正确加密')),JSON_UNESCAPED_UNICODE);
	exit();
}else{
	$data = json_decode($data,true);
	if(!is_array($data)){
		echo json_encode(array('result'=>null,'error'=>array('code'=>20005,'msg'=>'原始数据不是以json形式存在')),JSON_UNESCAPED_UNICODE);
		exit();
	}
}


$models = new $model($config,array('userid'=>@$data['userid'],'mobilecode'=>@$data['mobilecode']),$redis); //动态调用类

if(!method_exists($models,$action)){ //检查类方法是否存在
	echo json_encode(array('result'=>null,'error'=>array('code'=>20008,'msg'=>'该方法不存在')),JSON_UNESCAPED_UNICODE);
	exit();
}
$return = $models->$action($data); //动态调用类方法 $data为传入数组

if($return['result']){
	if($api['status']){ //是否是使用签名
		echo json_encode(array('result'=>json_decode($return['data'],true),'error'=>null),JSON_UNESCAPED_UNICODE);
	}else{
		echo json_encode(array('result'=>$psk->encrypt($return['data'], $appid),'error'=>null),JSON_UNESCAPED_UNICODE);
	}
}else{
	if($return['msg']=="数据库错误"){
		$return['code'] = 20009;
	}
	echo json_encode(array('result'=>null,'error'=>array('code'=>$return['code'],'msg'=>$return['msg'])),JSON_UNESCAPED_UNICODE);
}

//调试信息
file_put_contents("./tmp/accsess".date("Ymd").".log", "==============Start============== \r\n",FILE_APPEND);
file_put_contents("./tmp/accsess".date("Ymd").".log", "操作时间：".date('Y-m-d H:i:s')."\r\n",FILE_APPEND);
file_put_contents("./tmp/accsess".date("Ymd").".log", "操作方法：".$model.".".$action."\r\n传递数据：".$_POST['data']."\r\n解密数据:".json_encode($data,JSON_UNESCAPED_UNICODE)."\r\n",FILE_APPEND);
file_put_contents("./tmp/accsess".date("Ymd").".log", "返回数据：".json_encode($return,JSON_UNESCAPED_UNICODE)."\r\n",FILE_APPEND);
file_put_contents("./tmp/accsess".date("Ymd").".log", "==============End============== \r\n\r\n",FILE_APPEND);
//调试结束
?>