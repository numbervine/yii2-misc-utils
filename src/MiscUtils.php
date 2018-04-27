<?php
namespace numbervine\miscutils;

use Yii;
use yii\mongodb\Query;
use yii\helpers\VarDumper;
use yii\helpers\ArrayHelper;

class MiscUtils
{
	public static function isHome()	{
		$controller = Yii::$app->controller;
		$default_controller = Yii::$app->defaultRoute;
		$isHome = (($controller->id === $default_controller) && ($controller->action->id === $controller->defaultAction)) ? true : false;

		return $isHome;
	}

	public static function getUserRoles()
	{
		$asssignedRoles = Yii::$app->user->isGuest ? [] : array_keys(Yii::$app->authManager->getRolesByUser(Yii::$app->user->identity->id));
		$result = '['.implode('][',$asssignedRoles).']';
		return $result;
	}

	public static function isClient($candidate_user_id) {
		$roles = \Yii::$app->authManager->getRolesByUser($candidate_user_id);
		if ($roles && count($roles)==1 && isset($roles['client'])) {
			return true;
		}
		return false;
	}

	public static function isClientUser() {

		$result = false;

		if (!Yii::$app->user->isGuest) {
			$result = self::isClient(Yii::$app->user->identity->id);
		}

		return $result;
	}

	public static function getAdminUserId()
	{
		return Yii::$app->authManager->getUserIdsByRole('admin')[0];
	}

	public static function isAdmin($candidate_user_id=null) {
		if (!\Yii::$app->user->isGuest) {
			if (!$candidate_user_id) {
				$candidate_user_id = \Yii::$app->user->identity->id;
			}
			$roles = \Yii::$app->authManager->getRolesByUser($candidate_user_id);
			if ($roles && isset($roles['admin'])) {
				return true;
			}
		}
		return false;
	}

	public static function isProvider($candidate_user_id=null) {
		if (!\Yii::$app->user->isGuest) {
			if (!$candidate_user_id) {
				$candidate_user_id = \Yii::$app->user->identity->id;
			}

			if (!self::isClient($candidate_user_id) && !self::isAdmin($candidate_user_id)) {
				return true;
			}
		}
		return false;
	}

	public static function getNonClientUsers($exclude_user_ids = []) {
		$target_user_ids = [];
		$target_user_ids_tmp = array_merge(\Yii::$app->authManager->getUserIdsByRole('admin'),\Yii::$app->authManager->getUserIdsByRole('manager'),\Yii::$app->authManager->getUserIdsByRole('executive'));
		foreach ($target_user_ids_tmp as $user_tmp) {
			if (!ArrayHelper::isIn($user_tmp, $exclude_user_ids)) {
				$target_user_ids[] = $user_tmp;
			}
		}

		return $target_user_ids;
	}


	public static function debugTrace($buff) {
		file_put_contents('/tmp/debug.log', VarDumper::export('##################################\n'), FILE_APPEND);
		file_put_contents('/tmp/debug.log', VarDumper::export($buff), FILE_APPEND);
	}

	public static function dump($buff) {
		file_put_contents('/tmp/dump.log', VarDumper::export($buff));
	}

	public static function camelCase2Delimited($input_string, $delimiter='-')
	{
		$parts = preg_split("/((?<=[a-z])(?=[A-Z])|(?=[A-Z][a-z]))/", $input_string);
		$modified_parts = [];
		foreach ($parts as $part) {
			if ($part) {
				$modified_parts[] = strtolower($part);
			}
		}
		return implode($delimiter,$modified_parts);
	}

	public static function delimited2CamelCase($input_string, $delimiter_join='', $delimiter_split='-')
	{
		return implode($delimiter_join, array_map('ucwords', explode($delimiter_split, strtolower($input_string))));
	}

	public static function xmlToArray($xml)
	{
		return XML2Array::toArray($xml);
	}

	public static function curl_post_async($url, $params)
	{
		foreach ($params as $key => &$val) {
			if (is_array($val)) $val = implode(',', $val);
			$post_params[] = $key.'='.urlencode($val);
		}
		$post_string = implode('&', $post_params);

		$parts=parse_url($url);

		$fp = fsockopen($parts['host'],
				isset($parts['port'])?$parts['port']:80,
				$errno, $errstr, 30);

// 		pete_assert(($fp!=0), "Could not open a socket to ".$url." (".$errstr.")");

// 		assert(($fp!=0), "Could not open a socket to ".$url." (".$errstr.")");

		if ($fp!=0) {
			echo "ERROR: Could not open a socket to ".$url." (".$errstr.")";
			exit;
		}

		$out = "POST ".$parts['path']." HTTP/1.1\r\n";
		$out.= "Host: ".$parts['host']."\r\n";
		$out.= "Content-Type: application/x-www-form-urlencoded\r\n";
		$out.= "Content-Length: ".strlen($post_string)."\r\n";
		$out.= "Connection: Close\r\n\r\n";
		if (isset($post_string)) $out.= $post_string;

		fwrite($fp, $out);
		fclose($fp);
	}

	public static function addOrUpdateMongoDbDocument($collection_name,$document_identifier_config,$update_arr) {
		$result = null;
		$collection = \Yii::$app->mongodb->getCollection($collection_name);
		$aux_obj = self::findOneMongoDbDocument($collection_name, $document_identifier_config);
		$update_arr['updated_at'] =  time();
		$update_arr['updated_by'] = self::getAdminUserId();
		if ($aux_obj) {
			// screen out document_identifier_config from $update_arr
			// cannot update identifer
			foreach(array_keys($document_identifier_config) as $key) {
				unset($update_arr[$key]);
			}
			$result_tmp = $collection->update(['_id'=>$aux_obj['_id']], $update_arr);
			$result =  is_int($result_tmp) ? true : false;
		} else {
			foreach($document_identifier_config as $key=>$val) {
				$update_arr[$key]=$val;
			}
			$update_arr['created_at'] =  time();
			$update_arr['created_by'] = self::getAdminUserId();

			$result_tmp = $collection->insert($update_arr);
			$result = is_object($result_tmp) ? true : false;
		}

		return $result;
	}

	public static function findOneMongoDbDocument($collection_name,$document_identifier_config) {
		return  (new Query())->select([])->from($collection_name)->where($document_identifier_config)->one();
	}

	public static function findAllMongoDbDocuments($collection_name,$document_identifier_config) {
		// return  (new Query())->select([])->from($collection_name)->where($document_identifier_config)->all();
		return  (new Query())->from($collection_name)->where($document_identifier_config)->all();
	}

	public static function findMongoDbAggregate($collection_name,$aggregate_operator) {

		$collection = \Yii::$app->mongodb->getCollection($collection_name);

		return $collection->aggregate($aggregate_operator);

		// return  (new Query())->select([])->from($collection_name)->where($document_identifier_config)->all();
		// return  (new Query())->from($collection_name)->where($document_identifier_config)->all();
	}

// 	public static function removeMongoDbField($collection_name,$document_identifier_config,$delete_arr) {
// 		$result = null;
// 		$collection = \Yii::$app->mongodb->getCollection($collection_name);
// 		$aux_obj = self::findOneMongoDbDocument($collection_name, $document_identifier_config);
// 		if ($aux_obj) {
// 			// screen out document_identifier_config from $delete_arr
// 			// cannot update identifer
// 			foreach(array_keys($document_identifier_config) as $key) {
// 				unset($delete_arr[$key]);
// 			}
// 			$result_tmp = $collection->remove(['_id'=>$aux_obj['_id']], $delete_arr);
// 			$result =  is_int($result_tmp) ? true : false;
// 		}

// 		return $result;
// 	}

	public static function removeOneMongoDbDocument($collection_name,$document_identifier_config,$options=[]) {
		$result = null;
		$collection = \Yii::$app->mongodb->getCollection($collection_name);
		$aux_obj = self::findOneMongoDbDocument($collection_name, $document_identifier_config);
		if ($aux_obj) {
			$result_tmp = $collection->remove(['_id'=>$aux_obj['_id']], $options);
			$result =  is_int($result_tmp) ? true : false;
		}

		return $result;
	}

	public static function encodeBase36($input_decimal) {
		return str_pad(base_convert(intval($input_decimal), 10, 36), 10, '0', STR_PAD_LEFT);
	}

	public static function decodeBase36($input_base36) {
		return intval(base_convert($input_base36, 36, 10));
	}

	//returns true, if domain is availible, false if not
	public static function isDomainAvailable($domain)
	{
		//check, if a valid url is provided
		if(!filter_var($domain, FILTER_VALIDATE_URL))
		{
			return false;
		}

		//initialize curl
		$curlInit = curl_init($domain);
		curl_setopt($curlInit,CURLOPT_CONNECTTIMEOUT,10);
		curl_setopt($curlInit,CURLOPT_TIMEOUT,15);
		curl_setopt($curlInit,CURLOPT_HEADER,true);
		curl_setopt($curlInit,CURLOPT_NOBODY,true);
		curl_setopt($curlInit,CURLOPT_RETURNTRANSFER,true);

		//get answer
		$response = curl_exec($curlInit);

		curl_close($curlInit);

		if ($response) return true;

		return false;
	}

	public static function isBlog() {
		$result = preg_match('%^blog.*%', \Yii::$app->request->getPathInfo());
		return $result;
	}

	public static function mergeIntoSortedArray($data,$field_name,$newIds,$excludeId) {
    $result = ArrayHelper::getValue($data, $field_name,[]);
    if (!is_array($result)) {
      $result = [];
    }
    if (is_array($newIds)) {
      foreach ($newIds as $newId) {
        if (!in_array($newId, $result) && intval($newId)!=intval($excludeId))
        {
            $result[] = $newId;
        }
      }
    } else {
      if (!in_array($newIds, $result) && intval($newIds)!=intval($excludeId))
      {
          $result[] = $newIds;
      }
    }
    sort($result);

    return $result;
  }

	public static function getUserSocketIOKey($user_id) {
		$result = null;

		$aux_data = self::findOneMongoDbDocument('user_aux',['user_id'=>$user_id]);
		$socket_id = ArrayHelper::getValue($aux_data, 'socket_id',null);

		if ($socket_id!=null) {
			$result = 
			[
				'userId' => $user_id,
				'socketId' => $socket_id
			];
		}

		return $result;
	}

}
