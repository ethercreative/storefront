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
	const TYPE_CHECKOUT = 'Checkout';

	// Methods
	// =========================================================================

	/**
	 * Stores the given relation
	 *
	 * @param string $shopifyId
	 * @param string $type - ShopifyType
	 * @param int|null $elementId
	 *
	 * @throws Exception
	 */
	public function store ($shopifyId, $type, $elementId = null)
	{
		Craft::$app->getDb()->createCommand()->insert(
			'{{%storefront_relations}}',
			[
				'shopifyId' => $shopifyId,
				'type' => $type,
			],
			true
		)->execute();

		if ($elementId)
		{
			Craft::$app->getDb()->createCommand()->insert(
				'{{%storefront_relations_to_elements}}',
				[
					'shopifyId' => $shopifyId,
					'elementId' => $elementId,
				],
				false
			)->execute();
		}
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
			->select('elementId')
			->from('{{%storefront_relations_to_elements}}')
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
			->from('{{%storefront_relations_to_elements}}')
			->where(['elementId' => $id])
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