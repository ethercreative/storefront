<?php
/**
 * Storefront for Craft CMS
 *
 * @link      https://ethercreative.co.uk
 * @copyright Copyright (c) 2020 Ether Creative
 */

namespace ether\storefront;

use Craft;
use craft\base\Element;
use craft\base\Model;
use craft\base\Plugin;
use craft\elements\db\CategoryQuery;
use craft\elements\db\ElementQuery;
use craft\elements\db\EntryQuery;
use craft\errors\MissingComponentException;
use craft\events\CancelableEvent;
use craft\events\DefineBehaviorsEvent;
use craft\events\RegisterCacheOptionsEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\fields\Categories;
use craft\fields\Tags;
use craft\services\Utilities;
use craft\utilities\ClearCaches;
use craft\web\twig\variables\CraftVariable;
use ether\storefront\behaviors\ShopifyBehavior;
use ether\storefront\helpers\CacheHelper;
use ether\storefront\models\Settings;
use ether\storefront\services\CheckoutService;
use ether\storefront\services\CollectionsService;
use ether\storefront\services\CustomersService;
use ether\storefront\services\GraphService;
use ether\storefront\services\OrdersService;
use ether\storefront\services\ProductsService;
use ether\storefront\services\RelationsService;
use ether\storefront\services\WebhookService;
use ether\storefront\web\twig\Extension;
use ether\storefront\web\Variable;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use yii\base\Event;
use yii\base\InvalidConfigException;
use yii\db\Exception;
use yii\db\Expression;

/**
 * Class Storefront
 *
 * @author  Ether Creative
 * @package ether\storefront
 * @property GraphService $graph
 * @property WebhookService $webhook
 * @property ProductsService $products
 * @property OrdersService $orders
 * @property CollectionsService $collections
 * @property RelationsService $relations
 * @property CheckoutService $checkout
 * @property CustomersService $customers
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
			'relations' => RelationsService::class,
			'products' => ProductsService::class,
			'orders' => OrdersService::class,
			'collections' => CollectionsService::class,
			'checkout' => CheckoutService::class,
			'customers' => CustomersService::class,
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
			Element::class,
			Element::EVENT_DEFINE_BEHAVIORS,
			[$this, 'onDefineElementBehaviours']
		);

		Event::on(
			ElementQuery::class,
			ElementQuery::EVENT_BEFORE_PREPARE,
			[$this, 'onBeforeElementQueryPrepare']
		);

		Event::on(
			CraftVariable::class,
			CraftVariable::EVENT_INIT,
			[$this, 'onRegisterVariable']
		);

		Event::on(
			ClearCaches::class,
			ClearCaches::EVENT_REGISTER_CACHE_OPTIONS,
			[$this, 'onRegisterClearCacheOptions']
		);

		// Edit Tabs
		// ---------------------------------------------------------------------

		// Entries

		Craft::$app->view->hook('cp.entries.edit', function(array &$context) {
			return $this->products->addShopifyTab($context);
		});

		Craft::$app->view->hook('cp.entries.edit.content', function(array &$context) {
			return $this->products->addShopifyDetails($context);
		});

		// Categories

		Craft::$app->view->hook('cp.categories.edit', function(array &$context) {
			return $this->collections->addShopifyTab($context);
		});

		Craft::$app->view->hook('cp.categories.edit.content', function(array &$context) {
			return $this->collections->addShopifyDetails($context);
		});

		// Users

		Craft::$app->view->hook('cp.users.edit', function(array &$context) {
			return $this->customers->addShopifyTab($context);
		});

		Craft::$app->view->hook('cp.users.edit.content', function(array &$context) {
			return $this->customers->addShopifyDetails($context);
		});
	}

	/**
	 * @return bool
	 * @throws MissingComponentException
	 */
	protected function beforeUninstall (): bool
	{
		$this->webhook->uninstall();

		return parent::beforeUninstall();
	}

	// TODO: Update webhooks after plugin update (just in case)
	//  (Probably have to be a migration)

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
	 * @throws \yii\base\Exception
	 */
	protected function settingsHtml ()
	{
		$sections = self::_formatSelectOptions(
			Craft::$app->getSections()->getAllSections()
		);

		$categoryGroups = self::_formatSelectOptions(
			Craft::$app->getCategories()->getAllGroups(),
			true
		);

		$fields = Craft::$app->getFields()->getAllFields();
		$categoryFields = [];
		$tagFields = [];

		foreach ($fields as $field)
		{
			if ($field instanceof Categories)
				$categoryFields[] = $field;
			else if ($field instanceof Tags)
				$tagFields[] = $field;
		}

		$categoryFields = self::_formatSelectOptions(
			$categoryFields,
			true
		);

		$tagFields = self::_formatSelectOptions(
			$tagFields,
			true
		);

		return Craft::$app->getView()->renderTemplate('storefront/_settings', [
			'settings' => $this->getSettings(),
			'sectionOptions' => $sections,
			'categoryGroupOptions' => $categoryGroups,
			'categoryFieldOptions' => $categoryFields,
			'tagFieldOptions' => $tagFields,
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

	/**
	 * @param DefineBehaviorsEvent $event
	 */
	public function onDefineElementBehaviours (DefineBehaviorsEvent $event)
	{
		$event->behaviors[] = ShopifyBehavior::class;
	}

	/**
	 * @param CancelableEvent $event
	 */
	public function onBeforeElementQueryPrepare (CancelableEvent $event)
	{
		/** @var ElementQuery $query */
		$query = $event->sender;

		if (!$this->_isShopifyQuery($query))
			return;

		$select = '[[storefront_relations_to_elements.shopifyId]]';

		if (in_array($select, $query->select))
			return;

		$query->addSelect($select);
		$query->leftJoin(
			'{{%storefront_relations_to_elements}}',
			['[[elements.id]]' => new Expression('[[storefront_relations_to_elements.elementId]]')]
		);
	}

	/**
	 * @param RegisterCacheOptionsEvent $event
	 */
	public function onRegisterClearCacheOptions (RegisterCacheOptionsEvent $event)
	{
		$event->options[] = [
			'key' => 'storefront-shopify-caches',
			'label' => 'Shopify caches',
			'action' => function () { CacheHelper::clearAllCaches(); },
		];
	}

	// Helpers
	// =========================================================================

	/**
	 * Format an array for use in a select field
	 *
	 * @param array  $array
	 * @param bool   $includeNone
	 * @param string $label
	 * @param string $key
	 *
	 * @return mixed
	 */
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

	/**
	 * Ensures the given query is one we care about
	 *
	 * @param ElementQuery $query
	 *
	 * @return bool
	 */
	private function _isShopifyQuery (ElementQuery $query)
	{
		$settings = $this->getSettings();

		if (!empty($query->select) && in_array('COUNT(*)', $query->select))
			return false;

		if ($query instanceof EntryQuery)
		{
			$section = $settings->getSection();

			if (empty($section))
				return false;

			return (
				empty($query->sectionId)
				|| $query->sectionId === $section->id
				|| (is_array($query->sectionId) && in_array($section->id, $query->sectionId))
			);
		}

		if ($query instanceof CategoryQuery)
		{
			$group = $settings->getCategoryGroup();

			if (empty($group))
				return false;

			return (
				empty($query->groupId)
				|| $query->groupId === $group->id
				|| (is_array($query->groupId) && in_array($group->id, $query->groupId))
			);
		}

		return false;
	}

}
