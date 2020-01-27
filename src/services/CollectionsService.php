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
use craft\elements\Category;
use craft\errors\ElementNotFoundException;
use ether\storefront\enums\ShopifyType;
use ether\storefront\helpers\CacheHelper;
use ether\storefront\Storefront;
use Throwable;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use yii\base\InvalidConfigException;
use yii\db\Exception;

/**
 * Class CollectionsService
 *
 * @author  Ether Creative
 * @package ether\storefront\services
 */
class CollectionsService extends Component
{

	public static function FRAGMENT () {
		return <<<GQL
fragment Collection on Collection {
	id
	title
	handle
}
GQL;
	}

	/**
	 * Get a Collection for the given Shopify ID
	 *
	 * @param string $id
	 *
	 * @return array|null
	 */
	public function getCollectionById ($id)
	{
		static $cache = [];

		if (array_key_exists($id, $cache))
			return $cache[$id];

		$fragment = self::FRAGMENT();
		$query = <<<GQL
query GetCollection (\$id: ID!) {
	collection (id: \$id) {
		...Collection
	}
}
$fragment
GQL;

		$res = Storefront::getInstance()->graph->admin($query, compact('id'));

		if (array_key_exists('errors', $res))
		{
			Craft::error('Failed to import collection: ' . $id, 'storefront');
			Craft::error($res['errors'], 'storefront');
			return $cache[$id] = null;
		}

		return $cache[$id] = $res['data']['collection'];
	}

	/**
	 * Store collection
	 *
	 * @param array $data
	 * @param bool  $fetchFresh - Should we query fresh data from graph?
	 *
	 * @return int|null
	 * @throws ElementNotFoundException
	 * @throws Exception
	 * @throws Throwable
	 * @throws \yii\base\Exception
	 */
	public function upsert (array $data, $fetchFresh = false)
	{
		$storefront = Storefront::getInstance();
		$relations = $storefront->relations;
		$settings = $storefront->getSettings();

		$id = $relations->getShopifyIdFromArray($data, ShopifyType::Collection);

		if ($fetchFresh)
		{
			$fragment = self::FRAGMENT();
			$query = <<<GQL
query GetCollection (\$id: ID!) {
	collection (id: \$id) {
		...Collection
	}
}
$fragment
GQL;
			$res = Storefront::getInstance()->graph->admin($query, compact('id'));

			if (array_key_exists('errors', $res))
			{
				Craft::error('Failed to import collection: ' . $id, 'storefront');
				Craft::error($res['errors'], 'storefront');
				return null;
			}

			$data = $res['data']['collection'];
		}

		$categoryId = $relations->getElementIdByShopifyId($id);

		if ($categoryId)
		{
			$category = Craft::$app->getCategories()->getCategoryById($categoryId);
		}
		else
		{
			$category = new Category();
			$category->enabled = false;
			$group = Craft::$app->getCategories()->getGroupByUid(
				$settings->collectionCategoryGroupUid
			);
			$category->groupId = $group->id;
		}

		$category->title = $data['title'];
		$category->slug = $data['handle'];

		if (Craft::$app->getElements()->saveElement($category))
		{
			CacheHelper::clearCachesByShopifyId($id, ShopifyType::Collection);

			if ($categoryId)
				return $categoryId;

			$relations->store(
				$category->id,
				$id,
				ShopifyType::Collection
			);
		}
		else
		{
			Craft::error('Failed to upsert collection', 'storefront');
			Craft::error($category->getErrors(), 'storefront');
		}

		return $category->id;
	}

	/**
	 * Delete the collection by its Shopify ID
	 *
	 * @param array $data
	 *
	 * @throws Throwable
	 */
	public function delete (array $data)
	{
		$relations = Storefront::getInstance()->relations;

		$id = $relations->getShopifyIdFromArray($data, ShopifyType::Collection);
		$categoryId = $relations->getElementIdByShopifyId($id);

		if (!$categoryId)
			return;

		Craft::$app->getElements()->deleteElementById($categoryId);
		CacheHelper::clearCachesByShopifyId($id, ShopifyType::Collection);
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
		$id = $this->_validateAndGetId(@$context['category']);

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
		$id = $this->_validateAndGetId(@$context['category']);

		if (!$id)
			return null;

		return Craft::$app->getView()->renderTemplate(
			'storefront/_category',
			[
				'id' => $id,
				'visible' => count($context['tabs']) === 1
			]
		);
	}

	// Helpers
	// =========================================================================

	/**
	 * Validate that the given entry exists and is a Shopify generated entry
	 *
	 * @param Category|null $category
	 *
	 * @return string|null
	 * @throws InvalidConfigException
	 */
	private function _validateAndGetId ($category)
	{
		if (!$category)
			return null;

		if ($category->getGroup()->uid !== Storefront::getInstance()->getSettings()->collectionCategoryGroupUid)
			return null;

		return Storefront::getInstance()->relations->getShopifyIdByElementId($category->id);
	}

}