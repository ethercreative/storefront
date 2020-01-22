<?php
/**
 * Storefront for Craft CMS
 *
 * @link      https://ethercreative.co.uk
 * @copyright Copyright (c) 2020 Ether Creative
 */

namespace ether\storefront\controllers;

use craft\errors\MissingComponentException;
use craft\web\Controller;
use ether\storefront\Storefront;
use yii\db\Exception;
use yii\web\BadRequestHttpException;
use yii\web\Response;

/**
 * Class SetupController
 *
 * @author  Ether Creative
 * @package ether\storefront\controllers
 */
class SetupController extends Controller
{

	/**
	 * @return Response
	 * @throws BadRequestHttpException
	 * @throws MissingComponentException
	 * @throws Exception
	 */
	public function actionWebhooks ()
	{
		Storefront::getInstance()->webhook->install();
		return $this->redirectToPostedUrl();
	}

}