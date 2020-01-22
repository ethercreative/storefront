<?php
/**
 * Storefront for Craft CMS
 *
 * @link      https://ethercreative.co.uk
 * @copyright Copyright (c) 2020 Ether Creative
 */

namespace ether\storefront\web\twig;

use ether\storefront\web\twig\tokenparsers\ShopifyTokenParser;
use Twig\Extension\AbstractExtension;

/**
 * Class Extension
 *
 * @author  Ether Creative
 * @package ether\storefront\web\twig
 */
class Extension extends AbstractExtension
{

	public function getTokenParsers ()
	{
		return [
			new ShopifyTokenParser(),
		];
	}

}