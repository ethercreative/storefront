<?php
/**
 * Storefront for Craft CMS
 *
 * @link      https://ethercreative.co.uk
 * @copyright Copyright (c) 2020 Ether Creative
 */

namespace ether\storefront\services;

use craft\base\Component;

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
}
GQL;
	}

	public function upsert (array $data)
	{
		// TODO: this

		return /* Craft ID */;
	}

	public function delete ($data)
	{
		// TODO: this
	}

}