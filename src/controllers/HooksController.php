<?php
/**
 * Storefront for Craft CMS
 *
 * @link      https://ethercreative.co.uk
 * @copyright Copyright (c) 2020 Ether Creative
 */

namespace ether\storefront\controllers;

use craft\errors\ElementNotFoundException;
use craft\web\Controller;
use ether\storefront\Storefront;
use Throwable;
use yii\db\Exception;
use yii\web\BadRequestHttpException;

/**
 * Class HooksController
 *
 * @author  Ether Creative
 * @package ether\storefront\controllers
 */
class HooksController extends Controller
{

	protected $allowAnonymous = true;
	public $enableCsrfValidation = false;

	/**
	 * @return string
	 * @throws BadRequestHttpException
	 * @throws Throwable
	 * @throws ElementNotFoundException
	 * @throws \yii\base\Exception
	 * @throws Exception
	 */
	public function actionListen ()
	{
		Storefront::getInstance()->webhook->listen();

		return '';
	}

}