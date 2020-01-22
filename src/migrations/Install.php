<?php
/**
 * Storefront for Craft CMS
 *
 * @link      https://ethercreative.co.uk
 * @copyright Copyright (c) 2020 Ether Creative
 */

namespace ether\storefront\migrations;

use craft\db\Migration;

/**
 * Class Install
 *
 * @author  Ether Creative
 * @package ether\storefront\migrations
 */
class Install extends Migration
{

	/**
	 * @inheritdoc
	 */
	public function safeUp()
	{
		$this->createTable('storefront_webhooks', [
			'id' => $this->string()->notNull(),
			'hook' => $this->string()->notNull(),
		]);

		$this->addPrimaryKey(
			'storefront_webhooks_pk',
			'{{%storefront_webhooks}}',
			'id'
		);

		$this->createTable('storefront_products', [
			'id' => $this->primaryKey(),
			'shopifyId' => $this->string(),
		]);

		$this->addForeignKey(
			null,
			'{{%storefront_products}}',
			'id',
			'{{%elements}}',
			'id',
			'CASCADE'
		);
	}

	/**
	 * @inheritdoc
	 */
	public function safeDown()
	{
		$this->dropTable('storefront_webhooks');
		$this->dropTable('storefront_products');
	}

}