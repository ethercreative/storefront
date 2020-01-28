<?php
/**
 * Storefront for Craft CMS
 *
 * @link      https://ethercreative.co.uk
 * @copyright Copyright (c) 2020 Ether Creative
 */

namespace ether\storefront\helpers;

use Craft;
use craft\helpers\Json;
use ether\storefront\Storefront;
use Exception;
use yii\db\Query;

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

		self::deleteByShopifyOrCheckoutId($id);
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
		self::deleteByShopifyOrCheckoutId(base64_decode($id));
		Craft::$app->getCache()->delete($id);
		Craft::$app->getTemplateCaches()->deleteCachesByKey($id);
		Craft::debug("Clear caches for: $id", 'storefront');
	}

	/**
	 * Converts the query and variables into a unique key
	 *
	 * @param string $query
	 * @param array  $variables
	 *
	 * @return string
	 */
	public static function keyFromQuery ($query, $variables = [])
	{
		$query = preg_replace('/\s|,/m', '', $query);
		$variables = Json::encode($variables);

		return md5($query . $variables);
	}

	/**
	 * Get the cached value for the given key
	 *
	 * @param string $key
	 *
	 * @return mixed
	 */
	public static function get ($key)
	{
		$value = (new Query())
			->select('value')
			->from('{{%storefront_caches}}')
			->where(compact('key'))
			->scalar();

		return Json::decodeIfJson($value, true);
	}

	/**
	 * Cache the given value against the key
	 *
	 * @param string $key
	 * @param mixed $value
	 * @param array $variables
	 */
	public static function set ($key, $value, $variables = [])
	{
		$db = Craft::$app->getDb();

		$transaction = $db->beginTransaction();
		try {
			$db->createCommand()->upsert('{{%storefront_caches}}', [
				'key' => $key,
				'value' => Json::encode($value),
			], [
				'key' => $key,
			], [], false)->execute();

			// TODO: look for ID's in the value we're storing
			//  This means we can break the cache if, say, a product returned in
			//  the results was updated.
			// TODO: Also store checkout IDs
			// Note: for both of the above, it would be best to parse the query
			//  to find all the params / properties that are IDs and store them
			//  (since storefront IDs are base64 encoded because of course)
			preg_match_all(
				'/gid:\/\/shopify\/\w*\/((?!gid)\w)*(\?key=((?!gid)\w)*)?/m',
				Json::encode($variables),
				$matches
			);

			foreach ($matches[0] as $shopifyId)
			{
				$db->createCommand()->upsert('{{%storefront_relations_to_caches}}', [
					'shopifyId' => $shopifyId,
					'cacheKey' => $key,
				], false, [], false)->execute();
			}

			$transaction->commit();
		} catch (Exception $e) {
			Craft::error('Failed to cache query "' . $key . '"', 'storefront');
			Craft::error($e, 'storefront');
			$transaction->rollBack();
		}
	}

	/**
	 * Delete the cached value at the given key
	 *
	 * @param string $key
	 *
	 * @throws \yii\db\Exception
	 */
	public static function delete ($key)
	{
		Craft::$app->getDb()->createCommand()
			->delete('{{%storefront_caches}}', compact('key'))
			->execute();
	}

	/**
	 * Delete all caches with the given shopify ID
	 *
	 * @param string $id
	 */
	public static function deleteByShopifyOrCheckoutId ($id)
	{
		$db = Craft::$app->getDb();

		$transaction = $db->beginTransaction();
		try {
			$db->createCommand()
				->delete('{{%storefront_relations_to_caches}}', [
					'shopifyId' => $id
				])->execute();

			$transaction->commit();
		} catch (Exception $e) {
			Craft::error('Failed to delete cache by Shopify ID: "' . $id . '"', 'storefront');
			Craft::error($e, 'storefront');
			$transaction->rollBack();
		}
	}

}