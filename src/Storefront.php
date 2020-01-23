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
use craft\errors\MissingComponentException;
use craft\events\RegisterComponentTypesEvent;
use craft\fields\Categories;
use craft\services\Utilities;
use craft\web\twig\variables\CraftVariable;
use ether\storefront\models\Settings;
use ether\storefront\services\GraphService;
use ether\storefront\services\OrdersService;
use ether\storefront\services\ProductsService;
use ether\storefront\services\WebhookService;
use ether\storefront\web\twig\Extension;
use ether\storefront\web\Variable;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use yii\base\Event;
use yii\base\InvalidConfigException;
use yii\db\Exception;

/**
 * Class Storefront
 *
 * @author  Ether Creative
 * @package ether\storefront
 * @property GraphService $graph
 * @property WebhookService $webhook
 * @property ProductsService $products
 * @property OrdersService $orders
 */
class Storefront extends Plugin
{

	// Properties
	// =========================================================================

	public $hasCpSettings = true;

	// Craft
	// =========================================================================

	public function init ()
	{
		parent::init();

		$this->setComponents([
			'graph' => GraphService::class,
			'webhook' => WebhookService::class,
			'products' => ProductsService::class,
			'orders' => OrdersService::class,
		]);

		Craft::$app->getView()->registerTwigExtension(
			new Extension()
		);

		Event::on(
			Utilities::class,
			Utilities::EVENT_REGISTER_UTILITY_TYPES,
			function (RegisterComponentTypesEvent $event) {
				$event->types[] = Utility::class;
			}
		);

		Event::on(
			CraftVariable::class,
			CraftVariable::EVENT_INIT,
			[$this, 'onRegisterVariable']
		);

		Craft::$app->view->hook('cp.entries.edit', function(array &$context) {
			return $this->products->addShopifyTab($context);
		});

		Craft::$app->view->hook('cp.entries.edit.content', function(array &$context) {
			return $this->products->addShopifyDetails($context);
		});
	}

	/**
	 * @return bool
	 * @throws MissingComponentException
	 */
	protected function beforeUninstall (): bool
	{
		if (!$this->webhook->uninstall())
			return false;

		return parent::beforeUninstall();
	}

	// TODO: Update webhooks after plugin update (just in case)

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
	 * @return bool|Model|Settings
	 */
	public function getSettings ()
	{
		return parent::getSettings();
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

	/**
	 * @throws MissingComponentException
	 * @throws Exception
	 */
	public function afterSaveSettings ()
	{
		$this->webhook->install();

		parent::afterSaveSettings();
	}

	// Events
	// =========================================================================

	/**
	 * @param Event $event
	 *
	 * @throws InvalidConfigException
	 */
	public function onRegisterVariable (Event $event)
	{
		/** @var CraftVariable $variable */
		$variable = $event->sender;
		$variable->set('storefront', Variable::class);
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