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
use craft\elements\Category;
use craft\elements\Entry;
use craft\errors\MissingComponentException;
use craft\events\DefineBehaviorsEvent;
use craft\events\DefineEagerLoadingMapEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\fields\Categories;
use craft\fields\Tags;
use craft\helpers\ArrayHelper;
use craft\services\Utilities;
use craft\web\twig\variables\CraftVariable;
use ether\storefront\behaviors\ShopifyBehavior;
use ether\storefront\models\Settings;
use ether\storefront\services\CollectionsService;
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
use yii\db\Query;

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
			Element::class,
			Element::EVENT_DEFINE_EAGER_LOADING_MAP,
			[$this, 'onDefineElementEagerLoadingMap']
		);

		Event::on(
			CraftVariable::class,
			CraftVariable::EVENT_INIT,
			[$this, 'onRegisterVariable']
		);

		// Edit Tabs
		// ---------------------------------------------------------------------

		Craft::$app->view->hook('cp.entries.edit', function(array &$context) {
			return $this->products->addShopifyTab($context);
		});

		Craft::$app->view->hook('cp.entries.edit.content', function(array &$context) {
			return $this->products->addShopifyDetails($context);
		});

		Craft::$app->view->hook('cp.categories.edit', function(array &$context) {
			return $this->collections->addShopifyTab($context);
		});

		Craft::$app->view->hook('cp.categories.edit.content', function(array &$context) {
			return $this->collections->addShopifyDetails($context);
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
	 * Automatically eager-load any Shopify related stuff, no .with() required
	 *
	 * FIXME: This only works if you are eager-loading something (it doesn't
	 *   even have to exist) i.e. ..ries.with(['whatever']).al.. will work,
	 *   but ..ries.al.. won't.
	 * TODO: Try to find a workaround where this will always be called after
	 *   we've gotten the elements. Perhaps injecting our own join code into
	 *   ElementQuery::afterPrepare()?
	 *
	 * @param DefineEagerLoadingMapEvent $event
	 *
	 * @throws InvalidConfigException
	 */
	public function onDefineElementEagerLoadingMap (DefineEagerLoadingMapEvent $event)
	{
		/** @var Element[] $elements */
		$elements = $event->sourceElements;

		// Note: if we switch to injecting into the element query, we should
		//   check the Query class to see what element we're querying, and the
		//   already defined properties to see if it's a section or group we
		//   care about
		if (empty($elements) || !$this->_isShopifyElement($elements[0]))
			return;

		$elementIds = [];

		foreach ($elements as $element)
			$elementIds[] = $element->id;

		$map = (new Query())
			->select('id, shopifyId')
			->from('{{%storefront_relations}}')
			->where(['id' => $elementIds])
			->all();

		$map = ArrayHelper::map($map, 'id', 'shopifyId');

		foreach ($elements as $element)
			if (array_key_exists($element->id, $map))
				$element->shopifyId = $map[$element->id];
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

	/**
	 * Ensures the given element is one we care about
	 *
	 * @param Element $element
	 *
	 * @return bool
	 * @throws InvalidConfigException
	 */
	private function _isShopifyElement (Element $element)
	{
		$settings = $this->getSettings();

		if ($element instanceof Entry)
			return $element->getSection()->uid === $settings->productSectionUid;

		if ($element instanceof Category)
			return $element->getGroup()->uid === $settings->collectionCategoryGroupUid;

		return false;
	}

}