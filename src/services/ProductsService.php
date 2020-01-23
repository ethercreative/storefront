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
use craft\db\Table;
use craft\elements\Entry;
use craft\errors\ElementNotFoundException;
use craft\models\EntryType;
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

	public static $FRAGMENT = <<<GQL
fragment Product on Product {
	id
	title
}
GQL;

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
		$id = $this->_normalizeId($data);

		if ($fetchFresh)
		{
			$fragment = self::$FRAGMENT;
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
			$entryType = $this->_getEntryTypeByUid(
				Storefront::getInstance()->getSettings()->productEntryTypeUid
			);

			$entry = new Entry();
			$entry->sectionId = $entryType->sectionId;
			$entry->typeId = $entryType->id;
			$entry->enabled = false;
		}

		$entry->title = $data['title'];

		// TODO: Collections

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
	 * Gets the entry type for the given UID
	 *
	 * @param string $uid
	 *
	 * @return EntryType|null
	 */
	private function _getEntryTypeByUid ($uid)
	{
		$id = (new Query())
			->select('id')
			->from(Table::ENTRYTYPES)
			->where(['uid' => $uid])
			->scalar();

		return Craft::$app->getSections()->getEntryTypeById($id);
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

		if ($entry->getType()->uid !== Storefront::getInstance()->getSettings()->productEntryTypeUid)
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