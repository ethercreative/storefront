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
use craft\errors\ElementNotFoundException;
use craft\errors\MissingComponentException;
use craft\helpers\ArrayHelper;
use craft\helpers\UrlHelper;
use craft\web\assets\d3\D3Asset;
use ether\storefront\Storefront;
use Throwable;
use yii\db\Exception;
use yii\db\Query;
use yii\helpers\Json;
use yii\web\BadRequestHttpException;

/**
 * Class WebhookService
 *
 * @author  Ether Creative
 * @package ether\storefront\services
 */
class WebhookService extends Component
{

	// Public
	// =========================================================================

	/**
	 * Install / Update the webhooks
	 *
	 * @throws MissingComponentException
	 * @throws Exception
	 */
	public function install ()
	{
		$session = Craft::$app->getSession();

		$hooks = [
			'PRODUCTS_CREATE',
			'PRODUCTS_UPDATE',
			'PRODUCTS_DELETE',

			'COLLECTIONS_CREATE',
			'COLLECTIONS_UPDATE',
			'COLLECTIONS_DELETE',

			'ORDERS_CREATE',

			'CHECKOUTS_UPDATE',
			'CHECKOUTS_DELETE',
		];

		$existingHooks = $this->_getSavedWebhooks('hook');
		$query = '';

		foreach ($hooks as $hook)
		{
			$url = $this->_getPublicActionUrl('storefront/hooks/listen', [
				'hook' => $hook,
			]);

			if (array_key_exists($hook, $existingHooks)) {
				$method = 'Update';
				$identifier = 'id: "' . $existingHooks[$hook] . '"';
			} else {
				$method = 'Create';
				$identifier = 'topic: ' . $hook;
			}

			$query .= <<<GQL
$hook: webhookSubscription$method (
	$identifier
	webhookSubscription: { callbackUrl: "$url", format: JSON }
) {
	webhookSubscription {
		id
	}
	userErrors {
		message
	}
}
GQL;
		}

		$res = Storefront::getInstance()->graph->admin(
			'mutation { ' . $query . ' }'
		);

		if (@$res['errors'])
		{
			Craft::error($res['errors'][0]['message'], 'storefront');
			$session->setError('Failed to save webhooks, see logs for more info.');
		}
		else
		{
			foreach ($hooks as $hook)
			{
				$r = $res['data'][$hook];

				if (!empty($r['userErrors']))
				{
					Craft::error($r['userErrors'][0]['message'], 'storefront');
					$session->setError("Failed to save '$hook' webhook, see logs for more info.");
					continue;
				}

				if (!array_key_exists($hook, $existingHooks))
				{
					$id = $r['webhookSubscription']['id'];

					Craft::$app->getDb()->createCommand()->insert(
						'{{%storefront_webhooks}}',
						compact('id', 'hook'),
						false
					)->execute();
				}
			}

			$session->setNotice('Webhooks saved!');
		}
	}

	/**
	 * Will uninstall all of the saved webhooks
	 *
	 * @return bool
	 * @throws MissingComponentException
	 */
	public function uninstall ()
	{
		$session = Craft::$app->getSession();
		$hooks = $this->_getSavedWebhooks();
		$query = '';

		if (empty($hooks))
			return true;

		foreach ($hooks as $id => $hook)
		{
			$query .= <<<GQL
$hook: webhookSubscriptionDelete (id: "$id") {
	userErrors {
		message
	}
}
GQL;
		}

		$res = Storefront::getInstance()->graph->admin(
			'mutation { ' . $query . ' }'
		);

		if (@$res['errors'])
		{
			Craft::error($res['errors'], 'storefront');
			$session->setError('Failed to delete webhooks, see logs for more info.');
			return false;
		}

		foreach ($hooks as $hook)
		{
			$r = $res['data'][$hook];

			if (!empty(@$r['userErrors']))
			{
				Craft::error($r['userErrors'], 'storefront');
				$session->setError("Failed to delete '$hook' webhook, see logs for more info.");
				return false;
			}
		}

		return true;
	}

	/**
	 * Handles webhook events
	 *
	 * @throws BadRequestHttpException
	 * @throws Exception
	 * @throws Throwable
	 * @throws ElementNotFoundException
	 * @throws \yii\base\Exception
	 */
	public function listen ()
	{
		$request = Craft::$app->getRequest();

		$hook = $request->getRequiredQueryParam('hook');
		$json = Json::decode($request->getRawBody(), true);

		switch ($hook)
		{
			case 'PRODUCTS_CREATE':
			case 'PRODUCTS_UPDATE':
				Storefront::getInstance()->products->upsert($json, true);
				break;
			case 'PRODUCTS_DELETE':
				Storefront::getInstance()->products->delete($json);
				break;
			case 'COLLECTIONS_CREATE':
			case 'COLLECTIONS_UPDATE':
				Storefront::getInstance()->collections->upsert($json, true);
				break;
			case 'COLLECTIONS_DELETE':
				Storefront::getInstance()->collections->delete($json);
				break;
			case 'ORDERS_CREATE':
				Storefront::getInstance()->orders->onCreate($json);
				break;
			case 'CHECKOUTS_UPDATE':
				Storefront::getInstance()->checkout->onUpdate($json);
				break;
			case 'CHECKOUT_DELETE':
				Storefront::getInstance()->checkout->delete($json);
				break;
		}
	}

	// Helpers
	// =========================================================================

	/**
	 * Get the saved webhooks as key-pairs
	 *
	 * @param string $key - Pair key
	 * @return array
	 */
	private function _getSavedWebhooks ($key = 'id')
	{
		$existingHooks = (new Query())
			->select('id, hook')
			->from('{{%storefront_webhooks}}')
			->all();

		if ($key === 'id') {
			$from = 'id';
			$to = 'hook';
		} else {
			$from = 'hook';
			$to = 'id';
		}

		return ArrayHelper::map($existingHooks, $from, $to);
	}

	/**
	 * @param string $path
	 * @param null|array $params
	 *
	 * @return string
	 */
	private function _getPublicActionUrl ($path, $params = null)
	{
		$url = UrlHelper::actionUrl($path, $params);

		return str_replace(
			Craft::$app->getConfig()->getGeneral()->cpTrigger . '/',
			'',
			$url
		);
	}

}