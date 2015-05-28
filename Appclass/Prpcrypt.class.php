<?php
// +----------------------------------------------------------------------
// | Qiduoke      2014-11-13 上午9:36:29
// +----------------------------------------------------------------------
// | Copyright (c) 2014-2014 http://qidor.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: cnvj <1403729427@qq.com>
// +----------------------------------------------------------------------

namespace Appclass;

/**
 * Prpcrypt class
 *
 * 加解密接口
 */

class Prpcrypt{

	public $key;
	
	public function __construct($appkey)
	{
		$this->key = base64_decode($appkey . "=");
	}
	
	/**
	 * 对明文进行加密
	 * @param string $text 需要加密的明文
	 * @return string 加密后的密文
	 */
	public function encrypt($text, $appid)
	{
	
		try {
			//获得16位随机字符串，填充到明文之前
			$random = $this->getRandomStr();
			$text = $random . pack("N", strlen($text)) . $text . $appid;
			// 网络字节序
			$size = mcrypt_get_block_size(MCRYPT_RIJNDAEL_128, MCRYPT_MODE_CBC);
			$module = mcrypt_module_open(MCRYPT_RIJNDAEL_128, '', MCRYPT_MODE_CBC, '');
			$iv = substr($this->key, 0, 16);
			//使用自定义的填充方式对明文进行补位填充
			$text = $this->pkc7encode($text);
			@mcrypt_generic_init($module, $this->key, $iv);
			//加密
			$encrypted = mcrypt_generic($module, $text);
			mcrypt_generic_deinit($module);
			mcrypt_module_close($module);
	
			//print(base64_encode($encrypted));
			//使用BASE64对加密后的字符串进行编码
			return base64_encode($encrypted);
		} catch (\Exception $e) {
			//print $e;
			return false;
		}
	}
	
	/**
	 * 对密文进行解密
	 * @param string $encrypted 需要解密的密文
	 * @return string 解密得到的明文
	 */
	public function decrypt($encrypted, $appid)
	{
		try {
			//使用BASE64对需要解密的字符串进行解码
			$ciphertext_dec = base64_decode($encrypted);
			$module = mcrypt_module_open(MCRYPT_RIJNDAEL_128, '', MCRYPT_MODE_CBC, '');
			$iv = substr($this->key, 0, 16);
			mcrypt_generic_init($module, $this->key, $iv);
	
			//解密
			$decrypted = @mdecrypt_generic($module, $ciphertext_dec);
			mcrypt_generic_deinit($module);
			mcrypt_module_close($module);
		} catch (\Exception $e) {
			return false;
		}
	
	
		try {
			//去除补位字符
			$result = $this->pkc7decode($decrypted);
			if (strlen($result) < 16)
				return "";
			$content = substr($result, 16, strlen($result));
			$len_list = @unpack("N", substr($content, 0, 4));
			$xml_len = $len_list[1];
			$xml_content = substr($content, 4, $xml_len);
			return $xml_content;

		} catch (\Exception $e) {
			//print $e;
			return false;
		}
	
	}
	
	
	/**
	 * 随机生成16位字符串
	 * @return string 生成的字符串
	 */
	function getRandomStr()
	{
	
		$str = "";
		$str_pol = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789abcdefghijklmnopqrstuvwxyz";
		$max = strlen($str_pol) - 1;
		for ($i = 0; $i < 16; $i++) {
			$str .= $str_pol[mt_rand(0, $max)];
		}
		return $str;
	}
	
	/**
	 * 提供基于PKCS7算法的加解密接口 对需要加密的明文进行填充补位
	 * @param $text 需要进行填充补位操作的明文
	 * @return 补齐明文字符串
	 */
	function pkc7encode($text)
	{
		$block_size = 32;
		$text_length = strlen($text);
		//计算需要填充的位数
		$amount_to_pad = $block_size - ($text_length % $block_size);
		if ($amount_to_pad == 0) {
			$amount_to_pad = $block_size;
		}
		//获得补位所用的字符
		$pad_chr = chr($amount_to_pad);
		$tmp = "";
		for ($index = 0; $index < $amount_to_pad; $index++) {
			$tmp .= $pad_chr;
		}
		return $text . $tmp;
	}
	
	/**
	 * 提供基于PKCS7算法的加解密接口  对解密后的明文进行补位删除
	 * @param decrypted 解密后的明文
	 * @return 删除填充补位后的明文
	 */
	function pkc7decode($text)
	{
	
		$pad = ord(substr($text, -1));
		if ($pad < 1 || $pad > 32) {
			$pad = 0;
		}
		return substr($text, 0, (strlen($text) - $pad));
	}

}

?>