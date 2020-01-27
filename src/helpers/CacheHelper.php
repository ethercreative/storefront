<?php
/**
 * Storefront for Craft CMS
 *
 * @link      https://ethercreative.co.uk
 * @copyright Copyright (c) 2020 Ether Creative
 */

namespace ether\storefront\helpers;

use Craft;
use ether\storefront\Storefront;

/**
 * Class CacheHelper
 *
 * @author  Ether Creative
 * @package ether\storefront\helpers
 */
class CacheHelper
{

	/**
	 * Clear the caches for the given Shopify ID
	 *
	 * @param string $id
	 * @param string $type - ShopifyType
	 */
	public static function clearCachesByShopifyId ($id, $type)
	{
		$id = Storefront::getInstance()->relations->normalizeShopifyId(
			$id,
			$type
		);

		Craft::$app->getCache()->delete($id);
		Craft::$app->getTemplateCaches()->deleteCachesByKey($id);
		Craft::debug("Clear caches for: $id", 'storefront');
	}

	/**
	 * Clear the caches for the given checkout ID
	 *
	 * @param string $id
	 */
	public static function clearCheckoutCaches ($id)
	{
		Craft::$app->getCache()->delete($id);
		Craft::$app->getTemplateCaches()->deleteCachesByKey($id);
		Craft::debug("Clear caches for: $id", 'storefront');
	}

}