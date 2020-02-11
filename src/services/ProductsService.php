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
use craft\elements\Entry;
use craft\elements\Tag;
use craft\errors\ElementNotFoundException;
use craft\helpers\StringHelper;
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
 * Class ProductsService
 *
 * @author  Ether Creative
 * @package ether\storefront\services
 */
class ProductsService extends Component
{

	public static function FRAGMENT () {
		return <<<GQL
fragment Product on Product {
	id
	title
	handle
	tags
	# TODO: It would be nice to get all collections, but setting first to 250 
	#  exceeds the query cost :(
	collections (first: \$collectionLimit) {
		edges {
			node {
				id
			}
		}
	}
}
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
		$storefront = Storefront::getInstance();
		$settings = $storefront->getSettings();
		$relations = $storefront->relations;
		$collections = $storefront->collections;

		$fields = Craft::$app->getFields();
		$elements = Craft::$app->getElements();

		$id = $relations->getShopifyIdFromArray($data, ShopifyType::Product);

		if ($fetchFresh)
		{
			$fragment = self::FRAGMENT();
			$query = <<<GQL
query GetProduct (
	\$id: ID!
	\$collectionLimit: Int = 250
) {
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

		$entryId = $relations->getElementIdByShopifyId($id);

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

		// Content
		// ---------------------------------------------------------------------

		$entry->title = $data['title'];
		$entry->slug = $data['handle'];

		// Content: Collections
		// ---------------------------------------------------------------------

		if ($settings->collectionCategoryGroupUid && $settings->collectionCategoryFieldUid)
		{
			/** @var Field $collectionField */
			$collectionField = $fields->getFieldByUid(
				$settings->collectionCategoryFieldUid
			);
			
			$ids = [];
			
			// TODO: Handle pagination?
			foreach ($data['collections']['edges'] as $edge)
			{
				$_id = $relations->normalizeShopifyId(
					$edge['node']['id'],
					ShopifyType::Collection
				);
				$collection = $collections->getCollectionById($_id);
				$_id = $collections->upsert($collection);
				if ($_id) $ids[] = $_id;
			}

			$entry->setFieldValue($collectionField->handle, $ids);
		}

		// Content: Tags
		// ---------------------------------------------------------------------

		if ($settings->tagFieldUid && !empty($data['tags']))
		{
			/** @var Field $tagsField */
			$tagsField = $fields->getFieldByUid(
				$settings->tagFieldUid
			);

			if (empty(@$tagsField->getSettings()['source']))
				goto skipTags;

			$tagGroup = Craft::$app->getTags()->getTagGroupByUid(
				explode(':', $tagsField->getSettings()['source'])[1]
			);

			$ids = [];
			$existingTags = Tag::find()->groupId($tagGroup->id)->select('elements_sites.slug, elements.id')->pairs();

			foreach ($data['tags'] as $tag)
			{
				$slug = StringHelper::slugify($tag);

				if (array_key_exists($slug, $existingTags))
				{
					$ids[] = $existingTags[$slug];
					continue;
				}

				$t = new Tag();
				$t->groupId = $tagGroup->id;
				$t->title = $tag;

				if ($elements->saveElement($t))
					$ids[] = $t->id;
			}

			$entry->setFieldValue($tagsField->handle, $ids);
		}
		skipTags:

		// Save
		// ---------------------------------------------------------------------

		if ($elements->saveElement($entry))
		{
			CacheHelper::clearCachesByShopifyId($id, ShopifyType::Product);

			if ($entryId)
				return;

			$relations->store(
				$id,
				ShopifyType::Product,
				$entry->id
			);
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
		$relations = Storefront::getInstance()->relations;

		$id = $relations->getShopifyIdFromArray($data, ShopifyType::Product);
		$entryId = $relations->getElementIdByShopifyId($id);

		if (!$entryId)
			return;

		Craft::$app->getElements()->deleteElementById($entryId);
		CacheHelper::clearCachesByShopifyId($id, ShopifyType::Product);
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
	 * @throws \yii\base\Exception
	 */
	public function addShopifyDetails (array &$context)
	{
		$id = $this->_validateAndGetId(@$context['entry']);

		if (!$id)
			return null;

		return Craft::$app->getView()->renderTemplate(
			'storefront/_product',
			[
				'id' => $id,
				'visible' => count($context['tabs'] ?: [0]) === 1
			]
		);
	}

	// Helpers
	// =========================================================================

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

		return Storefront::getInstance()->relations->getShopifyIdByElementId(
			$entry->id,
			ShopifyType::Product
		);
	}

}