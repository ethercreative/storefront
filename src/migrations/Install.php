<?php
/**
 * Storefront for Craft CMS
 *
 * @link      https://ethercreative.co.uk
 * @copyright Copyright (c) 2020 Ether Creative
 */

namespace ether\storefront\migrations;

use craft\db\Migration;
use yii\db\Exception;
use yii\db\Expression;

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
	 * @throws \yii\base\Exception
	 */
	public function safeUp()
	{
		// Webhooks
		// ---------------------------------------------------------------------

		$this->createTable('storefront_webhooks', [
			'id' => $this->string()->notNull(),
			'hook' => $this->string()->notNull(),
		]);

		$this->addPrimaryKey(
			null,
			'{{%storefront_webhooks}}',
			'id'
		);

		// Relations
		// ---------------------------------------------------------------------

		$this->createTable('storefront_relations', [
			'shopifyId' => $this->string()->notNull(),
			'type' => $this->string()->notNull(),
			'dateCreated' => $this->dateTime(),
			'dateUpdated' => $this->dateTime(),
			'uid' => $this->uid(),
		]);

		$this->addPrimaryKey(
			null,
			'{{%storefront_relations}}',
			'shopifyId'
		);

		$this->createIndex(
			null,
			'{{%storefront_relations}}',
			'type'
		);

		// Checkouts
		// ---------------------------------------------------------------------

		$this->createTable('storefront_checkouts', [
			'shopifyId' => $this->string()->notNull(),
			'dateCompleted' => $this->dateTime()->null(),
		]);

		$this->addPrimaryKey(
			null,
			'{{%storefront_checkouts}}',
			'shopifyId'
		);

		$this->addForeignKey(
			null,
			'{{%storefront_checkouts}}',
			'shopifyId',
			'{{%storefront_relations}}',
			'shopifyId',
			'CASCADE'
		);

		// Caches
		// ---------------------------------------------------------------------

		$this->createTable('storefront_caches', [
			'key' => $this->string(32)->notNull(),
			'value' => $this->longText()->notNull(),
		]);

		$this->addPrimaryKey(
			null,
			'{{%storefront_caches}}',
			'key'
		);

		// Pivot Tables
		// ---------------------------------------------------------------------

		// Storefront Relation to Elements

		$this->createTable('storefront_relations_to_elements', [
			'shopifyId' => $this->string()->notNull(),
			'elementId' => $this->integer()->notNull(),
		]);

		$this->addPrimaryKey(
			null,
			'{{%storefront_relations_to_elements}}',
			['shopifyId', 'elementId']
		);

		$this->addForeignKey(
			null,
			'{{%storefront_relations_to_elements}}',
			'shopifyId',
			'{{%storefront_relations}}',
			'shopifyId',
			'CASCADE'
		);

		$this->addForeignKey(
			null,
			'{{%storefront_relations_to_elements}}',
			'elementId',
			'{{%elements}}',
			'id',
			'CASCADE'
		);

		$this->createTrigger(
			'delete_relations_on_element_delete',
			'{{%storefront_relations_to_elements}}',
			'AFTER DELETE',
			'FOR EACH ROW',
			'DELETE FROM {{%storefront_relations}} WHERE [[storefront_relations.shopifyId]] = OLD.[[shopifyId]]'
		);

		// Storefront Relations to Caches

		$this->createTable('storefront_relations_to_caches', [
			'shopifyId' => $this->string()->notNull(),
			'cacheKey' => $this->string(32)->notNull(),
		]);

		$this->addPrimaryKey(
			null,
			'{{%storefront_relations_to_caches}}',
			['shopifyId', 'cacheKey']
		);

		$this->addForeignKey(
			null,
			'{{%storefront_relations_to_caches}}',
			'shopifyId',
			'{{%storefront_relations}}',
			'shopifyId',
			'CASCADE'
		);

		$this->addForeignKey(
			null,
			'{{%storefront_relations_to_caches}}',
			'cacheKey',
			'{{%storefront_caches}}',
			'key',
			'CASCADE'
		);

		$this->createTrigger(
			'delete_caches_on_pivot_delete',
			'{{%storefront_relations_to_caches}}',
			'AFTER DELETE',
			'FOR EACH ROW',
			'DELETE FROM {{%storefront_caches}} WHERE [[storefront_caches.key]] = OLD.[[cacheKey]]'
		);
	}

	/**
	 * @inheritdoc
	 */
	public function safeDown()
	{
		$this->dropTable('storefront_webhooks');
		$this->dropTable('storefront_relations_to_elements');
		$this->dropTable('storefront_relations_to_caches');
		$this->dropTable('storefront_caches');
		$this->dropTable('storefront_checkouts');
		$this->dropTable('storefront_relations');
	}

	// Helpers
	// =========================================================================

	/**
	 * Creates an SQL trigger
	 *
	 * @param string $name - The name of the trigger
	 * @param string $table - The table to add the trigger to
	 * @param string $event - The event to listen for (i.e. 'BEFORE INSERT', 'AFTER UPDATE')
	 * @param string $loop - The loop to use (i.e. 'FOR EACH ROW')
	 * @param string $action - The SQL to run in the loop, has access to `NEW` (if inserting / updating) and `OLD` (if updating / deleting)
	 *
	 * @throws Exception
	 */
	private function createTrigger ($name, $table, $event, $loop, $action = '')
	{
		$sql = [];

		if ($this->getDb()->getDriverName() === 'mysql')
		{
			$sql[] = <<<SQL
CREATE TRIGGER {$name} {$event} ON {$table}
{$loop} {$action}
SQL;
		}
		else
		{
			$sql[] = <<<SQL
CREATE OR REPLACE FUNCTION trigger_{$name}()
RETURNS trigger AS \$_$
BEGIN
    {$action};
    RETURN OLD;
END \$_$ LANGUAGE 'plpgsql';
SQL;
			$sql[] = <<<SQL
CREATE TRIGGER {$name} {$event} ON {$table}
{$loop} EXECUTE PROCEDURE trigger_{$name}();
SQL;
		}
		
		foreach ($sql as $cmd)
			$this->getDb()->createCommand()
				->setSql(new Expression($cmd))
				->execute();
	}

}