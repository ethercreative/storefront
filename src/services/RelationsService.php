<?php
/**
 * Storefront for Craft CMS
 *
 * @link      https://ethercreative.co.uk
 * @copyright Copyright (c) 2020 Ether Creative
 */

namespace ether\storefront\services;

use Craft;
use craft\base\Component;
use craft\db\Query;
use yii\db\Exception;

/**
 * Class RelationsService
 *
 * @author  Ether Creative
 * @package ether\storefront\services
 */
class RelationsService extends Component
{

	// Consts
	// =========================================================================

	const TYPE_PRODUCT = 'Product';
	const TYPE_COLLECTION = 'Collection';

	// Methods
	// =========================================================================

	/**
	 * Stores the given relation
	 *
	 * @param int $elementId
	 * @param string $shopifyId
	 * @param string $type - ShopifyType
	 *
	 * @throws Exception
	 */
	public function store ($elementId, $shopifyId, $type)
	{
		Craft::$app->getDb()->createCommand()->insert(
			'{{%storefront_relations}}',
			[
				'id' => $elementId,
				'shopifyId' => $shopifyId,
				'type' => $type,
			],
			true
		)->execute();
	}

	/**
	 * Gets the element ID for the given Shopify ID
	 *
	 * @param string $id
	 *
	 * @return int|null
	 */
	public function getElementIdByShopifyId ($id)
	{
		return (new Query())
			->select('id')
			->from('{{%storefront_relations}}')
			->where(['shopifyId' => $id])
			->scalar();
	}

	/**
	 * Gets the Shopify ID for the given element ID
	 *
	 * @param int $id
	 *
	 * @return string|null
	 */
	public function getShopifyIdByElementId ($id)
	{
		return (new Query())
			->select('shopifyId')
			->from('{{%storefront_relations}}')
			->where(['id' => $id])
			->scalar();
	}

	public function getShopifyIdFromArray ($data, $type)
	{
		if (array_key_exists('admin_graphql_api_id', $data))
			return $this->normalizeShopifyId($data['admin_graphql_api_id'], $type);

		return $this->normalizeShopifyId($data['id'], $type);
	}

	public function normalizeShopifyId ($id, $type)
	{
		$prefix = "gid://shopify/$type/";

		if (strpos($id, $prefix) !== false)
			return $id;

		return $prefix . $id;
	}

}