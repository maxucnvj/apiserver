<?php
// +----------------------------------------------------------------------
// | Qiduoke 2015-1-4 下午1:46:46
// +----------------------------------------------------------------------
// | Copyright (c) 2014-2014 All rights reserved.
// +----------------------------------------------------------------------
// | Author: cnvj <1403729427@qq.com>
// +----------------------------------------------------------------------

return array (
		// 数据库配置类
		'DBCONNET' => array (
				'dbtype' => 'mysql', // 数据库类型
				'dbhost' => '', // 数据库服务器
				'dbport' => '3306', // 端口
				'dbname' => '', // 数据库名
				'dbuser' => '', // 数据库用户
				'dbpass' => '', // 数据库密码
				
//     		    'dbhost' => 'localhost', // 数据库服务器
//     		    'dbuser' => 'root', // 数据库用户
//     		    'dbpass' => '', // 数据库密码
    		    
				'connet' => true  // 是否支持长连接
				),
		
		// redis缓存数据库
		'REDISCONNET' => array (
				'host' => '127.0.0.1',
				'port' => '6379',
				'pass' => '',
				'prefix' => 'clofood_' 
		),
		
		// 验证码接口
		'MOBILECODE' => array (
				'url' => 'http://v.juhe.cn/sms/send', // 发送接口
				'key' => '', // 发送密钥
				'code_tpl_id' => '2360', // 验证码模板号
				'password_tpl_id' => '2720', // 密码模板号
				'sessiontimes' => '600'  // 验证码生效时间
				),
				
		//第三方推送接口
		'THIRDPUSHAPP' => array(
				'AppID'	=> "",
				'AppKey'=> "",
				'AppSecret' => "",
				'MasterSecret' => "",
				'Host' => 'http://sdk.open.api.igexin.com/apiex.htm',
				'cryptid' => '',
				'cryptkey'=> ''
				),
				
		//微信公众平台接口
		'WEIXIN_APP'	=> array(
			'AppID'		=>	'',
			'AppSecret'	=>	'',
			'Token'		=>	'',
			'EncodingAESKey'	=>	'',	
		),				
		
		// 图片服务器地址
		'PICTURESERVER' => '',
		//每页LOADING长度
		'PAGESIZE'	=>	10,
		'ADPAGESIZE'	=>	10,
		//评论默认审核通过
		'COMMENTSTATUS' => 1,
		//客服接口appid
		'SERVICEID' => 2,
		//红包活动是否开启
		'HONGBAOACT' => true,
		//红包金额
		'HONGBAOPRICE' => array(100,80,50,20,10,5,3,2),
		//签名接口ID
		'SIGNID' => array(3),
				
		//提现申请
		'CASHAPPLY'	=>	array(
			'first'	=>	0, //第一次提现是否有要求 1要求 0不要求
			'share' =>	1, //是否要求分享
			'min'	=>  0, //提现最小值 0为不限制
			'max'	=>	0, //提现最大值 0为不限制
			'lastprize' => 0, //是否要求参与了上一次抽奖 1要求 0不要求
		)
);

?>