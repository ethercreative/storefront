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
use craft\models\EntryType;
use ether\storefront\Storefront;
use Throwable;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use yii\base\InvalidConfigException;

/**
 * Class ProductsService
 *
 * @author  Ether Creative
 * @package ether\storefront\services
 */
class ProductsService extends Component
{

	public function upsert (array $data)
	{
		$entryId = $this->_getEntryIdByShopifyId($data['id']);

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
			$entry->title = $data['title'];
		}

		// TODO: Collections

		if (Craft::$app->getElements()->saveElement($entry))
		{
			if ($entryId)
				return;

			Craft::$app->getDb()->createCommand()->insert(
				'{{%storefront_products}}',
				[
					'id' => $entry->id,
					'shopifyId' => 'gid://shopify/Product/' . $data['id'],
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
		$entryId = $this->_getEntryIdByShopifyId($data['id']);

		if (!$entryId)
			return;

		Craft::$app->getElements()->deleteElementById($entryId);
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
		/** @var Entry $entry */
		$entry = @$context['entry'];

		if (!$entry)
			return null;

		$id = $this->_getShopifyIdByEntryId($entry->id);

		if (!$id)
			return null;

		$context['tabs'][] = [
			'label' => 'Shopify',
			'url'   => '#tab-shopify',
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
	 */
	public function addShopifyDetails (array &$context)
	{
		/** @var Entry $entry */
		$entry = @$context['entry'];

		if (!$entry)
			return null;

		$id = $this->_getShopifyIdByEntryId($entry->id);

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

}