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
use craft\base\Field;
use craft\db\Query;
use craft\elements\Entry;
use craft\errors\ElementNotFoundException;
use ether\storefront\Storefront;
use Throwable;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use yii\base\InvalidConfigException;
use yii\db\Exception;

/**
 * Class ProductsService
 *
 * @author  Ether Creative
 * @package ether\storefront\services
 */
class ProductsService extends Component
{

	public static function FRAGMENT () {
		$collectionFragment = CollectionsService::FRAGMENT();

		return <<<GQL
fragment Product on Product {
	id
	title
	collections (first: 250) {
		edges {
			node {
				...Collection
			}
		}
	}
}
$collectionFragment
GQL;
	}

	/**
	 * @param array $data
	 * @param bool  $fetchFresh - Should we query fresh data from graph?
	 *
	 * @throws ElementNotFoundException
	 * @throws Exception
	 * @throws Throwable
	 * @throws \yii\base\Exception
	 */
	public function upsert (array $data, $fetchFresh = false)
	{
		$settings = Storefront::getInstance()->getSettings();
		$id = $this->_normalizeId($data);

		if ($fetchFresh)
		{
			$fragment = self::FRAGMENT();
			$query = <<<GQL
query GetProduct (\$id: ID!) {
	product (id: \$id) {
		...Product
	}
}
$fragment
GQL;
			$res = Storefront::getInstance()->graph->admin($query, compact('id'));

			if (array_key_exists('errors', $res))
			{
				Craft::error('Failed to import product: ' . $id, 'storefront');
				Craft::error($res['errors'], 'storefront');
				return;
			}

			$data = $res['data']['product'];
		}

		$entryId = $this->_getEntryIdByShopifyId($id);

		if ($entryId)
		{
			$entry = Craft::$app->getEntries()->getEntryById($entryId);
		}
		else
		{
			$section = Craft::$app->getSections()->getSectionByUid(
				$settings->productSectionUid
			);

			$entry = new Entry();
			$entry->sectionId = $section->id;
			$entry->typeId = $section->getEntryTypes()[0]->id;
			$entry->enabled = false;
		}

		$entry->title = $data['title'];

		if ($settings->collectionCategoryFieldUid)
		{
			/** @var Field $collectionField */
			$collectionField = Craft::$app->getFields()->getFieldByUid(
				$settings->collectionCategoryFieldUid
			);
			
			$ids = [];
			
			// TODO: Handle pagination?
			foreach ($data['collections']['edges'] as $edge)
				$ids[] = Storefront::getInstance()->collections->upsert($edge['node']);

			$entry->setFieldValue($collectionField->handle, $ids);
		}

		if (Craft::$app->getElements()->saveElement($entry))
		{
			$this->clearCaches($id);

			if ($entryId)
				return;

			Craft::$app->getDb()->createCommand()->insert(
				'{{%storefront_products}}',
				[
					'id' => $entry->id,
					'shopifyId' => $id,
				],
				false
			)->execute();
		}
		else
		{
			Craft::error('Failed to upsert product', 'storefront');
			Craft::error($entry->getErrors(), 'storefront');
		}
	}

	/**
	 * Delete the product by its Shopify ID
	 *
	 * @param array $data
	 *
	 * @throws Throwable
	 */
	public function delete (array $data)
	{
		$id = $this->_normalizeId($data);

		$entryId = $this->_getEntryIdByShopifyId($id);

		if (!$entryId)
			return;

		Craft::$app->getElements()->deleteElementById($entryId);
		$this->clearCaches($id);
	}

	// Edit
	// =========================================================================

	/**
	 * @param array $context
	 *
	 * @return null
	 * @throws InvalidConfigException
	 */
	public function addShopifyTab (array &$context)
	{
		$id = $this->_validateAndGetId(@$context['entry']);

		if (!$id)
			return null;

		$context['tabs'][] = [
			'label' => 'Shopify',
			'url'   => '#storefront-tab-shopify',
			'class' => null,
		];

		return null;
	}

	/**
	 * @param array $context
	 *
	 * @return string|null
	 * @throws LoaderError
	 * @throws RuntimeError
	 * @throws SyntaxError
	 * @throws InvalidConfigException
	 */
	public function addShopifyDetails (array &$context)
	{
		$id = $this->_validateAndGetId(@$context['entry']);

		if (!$id)
			return null;

		return Craft::$app->getView()->renderTemplate(
			'storefront/_product',
			[ 'id' => $id ]
		);
	}

	// Helpers
	// =========================================================================

	/**
	 * Clear the caches for the given ID
	 *
	 * @param string $id
	 */
	public function clearCaches ($id)
	{
		$id = $this->_normalizeId(compact('id'));

		Craft::$app->getCache()->delete($id);
		Craft::$app->getTemplateCaches()->deleteCachesByKey($id);
		Craft::debug("Clear caches for: $id", 'storefront');
	}

	/**
	 * Gets an entry ID by the given product ID
	 *
	 * @param string $id
	 *
	 * @return int|null
	 */
	private function _getEntryIdByShopifyId ($id)
	{
		return (new Query())
			->select('id')
			->from('{{%storefront_products}}')
			->where(['shopifyId' => $id])
			->scalar();
	}

	/**
	 * Gets a Shopify product ID by the given entry ID
	 *
	 * @param string|int $id
	 *
	 * @return string|null
	 */
	private function _getShopifyIdByEntryId ($id)
	{
		return (new Query())
			->select('shopifyId')
			->from('{{%storefront_products}}')
			->where(['id' => $id])
			->scalar();
	}

	/**
	 * Validate that the given entry exists and is a Shopify generated entry
	 *
	 * @param Entry|null $entry
	 *
	 * @return string|null
	 * @throws InvalidConfigException
	 */
	private function _validateAndGetId ($entry)
	{
		if (!$entry)
			return null;

		if ($entry->getSection()->uid !== Storefront::getInstance()->getSettings()->productSectionUid)
			return null;

		return $this->_getShopifyIdByEntryId($entry->id);
	}

	/**
	 * Normalize the product ID
	 *
	 * @param array $data
	 *
	 * @return string
	 */
	private function _normalizeId ($data)
	{
		if (array_key_exists('admin_graphql_api_id', $data))
			return $data['admin_graphql_api_id'];

		$id = $data['id'];

		if (strpos($id, 'gid://shopify/Product/') !== false)
			return $id;

		return 'gid://shopify/Product/' . $id;
	}

}