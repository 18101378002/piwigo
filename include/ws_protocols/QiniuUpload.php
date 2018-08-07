<?php
include_once(PHPWG_ROOT_PATH.'include/qiniu/autoload.php');
//require_once __DIR__ . "/qiniu/autoload.php";

use Qiniu\Auth;
use Qiniu\Storage\UploadManager;

/**
 * Class QiniuUpload
 * 上传文件到七牛服务器
 *
 * demo:
   QiniuUpload::setBucket(QiniuUpload::BUCKET_AVATAR);
   $avatarUrl = QiniuUpload::picture($_FILES['Filedata'], $avatarPath);
 */
class QiniuUpload
{

	const BUCKET_PC = 'spc-oil'; // pc图片上传的bucket
	//const BUCKET_AVATAR = 'face-360che-com'; // 头像上传的bucket
    const QINIU_ACCESS_KEY  = 'AwYC2lkAHxKGQrsQbWUYtivXOJdRoIxcDN-CDohF';
    const QINIU_SECRET_KEY  = '-fBQVA60w7_1N374UuJX5OYDtbNP0e_luBuDK8dp';
	private static $_bucket = 'piwigo';
	const QINIU_URL = 'http://piwigo-img.kcimg.cn/';

	/**
	 * 上传图片到七牛服务器，只能单个上传。
	 * @param $aUploadFile      array  对应$_FILES['XXXX']数组
	 * @param $uploadedFilePath string 上传到服务器上的图片相对路径，如：/data/attach/2016/07/tomcat.png
	 * @return bool|string 成功返回生成文件的绝对URL，失败返回false
	 */
	public static function picture($aUploadFile, $uploadedFilePath) {
		if ( ! self::_isUploadedFile($aUploadFile['tmp_name']) || ! self::BUCKET_PC) {
			return false;
		}
		$uploadedFilePath = ltrim($uploadedFilePath, '/');
		$auth = new Auth(self::QINIU_ACCESS_KEY, self::QINIU_SECRET_KEY);
		$token = $auth->uploadToken(self::BUCKET_PC);
		$uploadMgr = new UploadManager();
		list(, $err) = $uploadMgr->putFile($token, $uploadedFilePath, $aUploadFile['tmp_name']);
		@unlink($aUploadFile['tmp_name']);
		return is_null($err) ? self::_relative2abs($uploadedFilePath) : false;
	}

	/**
	 * 上传一段内容并生成文件
	 * @param $pictureStream    string 要上传的内容
	 * @param $uploadedFilePath string 生成文件的相对路径
	 * @return bool|string 成功返回生成文件的绝对URL，失败返回false
	 */
	public static function pictureStream($pictureStream, $uploadedFilePath) {
		$uploadedFilePath = ltrim($uploadedFilePath, '/');
		$auth = new Auth(self::QINIU_ACCESS_KEY, self::QINIU_SECRET_KEY);
		$token = $auth->uploadToken(self::$_bucket);
		$uploadMgr = new UploadManager();

		list(, $err) = $uploadMgr->put($token, $uploadedFilePath, $pictureStream);

		return is_null($err) ? self::_relative2abs($uploadedFilePath) : false;
	}

	public static function setBucket($bucket) {
		if (is_string($bucket)) {
			return self::$_bucket = trim($bucket);
		}
	}

	/**
	 * 判断一张图片是否是真实的图片
	 * @param $tmpFile string 图片的绝对路径
	 * @return bool 图片返回true，反正返回false
	 */
	public static function isValidPic($tmpFile) {
		return self::getPicExt($tmpFile) !== false;
	}

	/**
	 * 获取图片的扩展名
	 * @param $src string 图片的绝对路径
	 * @return string
	 */
	public static function getPicExt($src) {
		if ( ! $src || ! is_file($src)) {
			return '';
		}

		// 所有图片类型参考http://php.net/manual/en/function.exif-imagetype.php
		$aTypeExtMap = [
			IMAGETYPE_JPEG => 'jpg',
			IMAGETYPE_GIF => 'gif',
			IMAGETYPE_PNG => 'png',
			IMAGETYPE_BMP => 'bmp',
		];
		$imgType = @exif_imagetype($src);


		return isset($aTypeExtMap[$imgType]) ? $aTypeExtMap[$imgType] : '';
	}

	/**
	 * 根据图片内容判断图片格式
	 * @param $stream string 图片的二进制字节字符串
	 * @return string
	 */
	public static function getPicExtByStream($stream) {
		$bin = substr($stream, 0, 2);
		$strInfo = @unpack("C2chars", $bin);
		$typeCode = intval($strInfo['chars1'] . $strInfo['chars2']);
		switch ($typeCode) {
			case 255216:
				$fileType = 'jpg';
			break;
			case 7173:
				$fileType = 'gif';
			break;
			case 6677:
				$fileType = 'bmp';
			break;
			case 13780:
				$fileType = 'png';
			break;
			default:
				$fileType = '';
		}

		return $fileType;
	}

	private static function _isUploadedFile($source) {
		return $source
			&& (is_uploaded_file($source) || is_uploaded_file(str_replace('\\\\', '\\', $source)));
	}

	private static function _relative2abs($filepath) {
		$aHostBucketMap = [
			//self::BUCKET_AVATAR => 'http://face.360che.com/',
			self::BUCKET_PC => 'http://piwigo-img.kcimg.cn/',
		];
		return isset($aHostBucketMap[self::BUCKET_PC])
			? ($aHostBucketMap[self::BUCKET_PC] . $filepath)
			: false;
	}
}
