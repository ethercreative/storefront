<?php
/**
 * Storefront for Craft CMS
 *
 * @link      https://ethercreative.co.uk
 * @copyright Copyright (c) 2020 Ether Creative
 */

namespace ether\storefront\models;

use Craft;
use craft\base\Model;
use craft\models\CategoryGroup;
use craft\models\Section;

/**
 * Class Settings
 *
 * @author  Ether Creative
 * @package ether\storefront\models
 */
class Settings extends Model
{

	// Properties
	// =========================================================================

	/**
	 * @var string The Shopify private storefront access token
	 * @see https://help.shopify.com/en/api/storefront-api/getting-started#private-app
	 */
	public $shopifyStorefrontAccessToken = '';

	/**
	 * @var string The Shopify admin API password
	 * @see https://help.shopify.com/en/api/graphql-admin-api/getting-started#authentication
	 */
	public $shopifyAdminApiPassword = '';

	/**
	 * @var string The handle of your Shopify shop
	 */
	public $shopHandle = '';

	/**
	 * @var string The UID for the section that will be automatically
	 *             populated by Shopify products
	 */
	public $productSectionUid = '';

	// Field Mapping
	// =========================================================================

	/**
	 * @var string The UID for the category group that will be automatically
	 *             populated by Shopify collections
	 */
	public $collectionCategoryGroupUid = '';

	/**
	 * @var string The UID for the collection category field on the product
	 */
	public $collectionCategoryFieldUid = '';

	/**
	 * @var string The UID for the tag field on the product
	 */
	public $tagFieldUid = '';

	// Getters
	// =========================================================================

	/**
	 * Gets the products section
	 *
	 * @return Section|null
	 */
	public function getSection ()
	{
		static $section;

		if ($section)
			return $section;

		if (!$this->productSectionUid)
			return null;

		return $section = Craft::$app->getSections()->getSectionByUid($this->productSectionUid);
	}

	/**
	 * Gets the Collection category group
	 *
	 * @return CategoryGroup|null
	 */
	public function getCategoryGroup ()
	{
		static $group;

		if ($group)
			return $group;

		if (!$this->collectionCategoryGroupUid)
			return null;

		return $group = Craft::$app->getCategories()->getGroupByUid($this->collectionCategoryGroupUid);
	}

}