<?php
namespace numbervine\miscutils;

class XML2Array {


	public static function toArray($xml)
	{
		return json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), TRUE);
	}
}
