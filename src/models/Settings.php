<?php
/**
 * Storefront for Craft CMS
 *
 * @link      https://ethercreative.co.uk
 * @copyright Copyright (c) 2020 Ether Creative
 */

namespace ether\storefront\models;

use craft\base\Model;

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
	 * @var string The UID for the entry type that will be automatically
	 *             populated by Shopify products
	 */
	public $productEntryTypeUid = '';

	/**
	 * @var string The UID for the category group that will be automatically
	 *             populated by Shopify collections
	 */
	public $collectionCategoryGroupUid = '';

	/**
	 * @var string The UID for the collection category field on the product
	 */
	public $collectionCategoryFieldUid = '';

}