<?php
/**
 * Storefront for Craft CMS
 *
 * @link      https://ethercreative.co.uk
 * @copyright Copyright (c) 2020 Ether Creative
 */

namespace ether\storefront;

use Craft;
use Twig\Error\LoaderError as TwigLoaderError;
use Twig\Error\RuntimeError as TwigRuntimeError;
use Twig\Error\SyntaxError as TwigSyntaxError;

/**
 * Class Utility
 *
 * @author  Ether Creative
 * @package ether\storefront
 */
class Utility extends \craft\base\Utility
{

	public static function displayName (): string
	{
		return 'Storefront';
	}

	/**
	 * @inheritDoc
	 */
	public static function id (): string
	{
		return 'storefront';
	}

	public static function iconPath ()
	{
		return __DIR__ . '/icon-mask.svg';
	}

	/**
	 * @inheritDoc
	 * @throws TwigLoaderError
	 * @throws TwigRuntimeError
	 * @throws TwigSyntaxError
	 */
	public static function contentHtml (): string
	{
		return Craft::$app->getView()->renderTemplate(
			'storefront/_utility',
			[
				'settings' => Storefront::getInstance()->getSettings(),
			]
		);
	}

}