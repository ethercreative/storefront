<?php
/**
 * Storefront for Craft CMS
 *
 * @link      https://ethercreative.co.uk
 * @copyright Copyright (c) 2020 Ether Creative
 */

namespace ether\storefront;

use Craft;
use craft\base\Field;
use craft\base\Model;
use craft\base\Plugin;
use craft\fields\Categories;
use craft\models\EntryType;
use ether\storefront\models\Settings;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

/**
 * Class Storefront
 *
 * @author  Ether Creative
 * @package ether\storefront
 */
class Storefront extends Plugin
{

	// Properties
	// =========================================================================

	public $hasCpSettings = true;

	// Settings
	// =========================================================================

	/**
	 * @return Model|Settings|null
	 */
	protected function createSettingsModel ()
	{
		return new Settings();
	}

	/**
	 * @return string|null
	 * @throws LoaderError
	 * @throws RuntimeError
	 * @throws SyntaxError
	 */
	protected function settingsHtml ()
	{
		$entryTypes = self::_formatSelectOptions(
			Craft::$app->getSections()->getAllEntryTypes()
		);

		$categoryGroups = self::_formatSelectOptions(
			Craft::$app->getCategories()->getAllGroups(),
			true
		);

		$categoryFields = self::_formatSelectOptions(
			array_filter(
				Craft::$app->getFields()->getAllFields(),
				function (Field $field) {
					return $field instanceof Categories;
				}
			),
			true
		);

		return Craft::$app->getView()->renderTemplate('storefront/_settings', [
			'settings' => $this->getSettings(),
			'entryTypeOptions' => $entryTypes,
			'categoryGroupOptions' => $categoryGroups,
			'categoryFieldOptions' => $categoryFields,
		]);
	}

	// Helpers
	// =========================================================================

	private static function _formatSelectOptions (
		array $array,
		$includeNone = false,
		$label = 'name',
		$key = 'uid'
	) {
		return array_reduce(
			$array,
			function ($carry, Model $entryType) use ($key, $label) {
				$carry[$entryType->{$key}] = $entryType->{$label};
				return $carry;
			},
			[
				[
					'label' => $includeNone ? 'None' : 'Please select',
					'value' => '',
					'disabled' => !$includeNone,
				],
			]
		);
	}

}