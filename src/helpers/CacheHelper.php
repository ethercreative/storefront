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
use ether\storefront\services\GraphService;
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
	 * Clears all Shopify caches
	 *
	 * @throws \yii\db\Exception
	 */
	public static function clearAllCaches ()
	{
		$db = Craft::$app->getDb();
		$transaction = $db->beginTransaction();

		$db->createCommand()
			->delete('{{%storefront_caches}}')
			->execute();

		$transaction->commit();
	}

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
	 * @param string $query
	 * @param array $variables
	 */
	public static function set ($key, $value, $query, $variables = [])
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

			$ids = GraphService::getIdsFromQuery($query, $variables, $value);
			foreach ($ids as $shopifyId)
			{
				// Ignore CheckoutLineItems & ProductVariants since we're not
				// tracking them in any way
				if (
					strpos($shopifyId, 'CheckoutLineItem') !== false ||
					strpos($shopifyId, 'ProductVariant') !== false
				) continue;

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