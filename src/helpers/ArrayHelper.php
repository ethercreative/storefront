<?php
/**
 * Storefront for Craft CMS
 *
 * @link      https://ethercreative.co.uk
 * @copyright Copyright (c) 2020 Ether Creative
 */

namespace ether\storefront\helpers;

/**
 * Class ArrayHelper
 *
 * @author  Ether Creative
 * @package ether\storefront\helpers
 */
class ArrayHelper extends \craft\helpers\ArrayHelper
{

	/**
	 * Flattens a multi-dimensional array (will loose any keys)
	 *
	 * @param array $array
	 *
	 * @return array
	 */
	public static function flatten (array $array): array
	{
		$return = [];
		array_walk_recursive(
			$array,
			function($a) use (&$return) { $return[] = $a; }
		);

		return $return;
	}

}