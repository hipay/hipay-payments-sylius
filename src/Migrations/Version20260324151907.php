<?php

/*
 * HiPay payment integration for Sylius
 *
 * (c) Hipay
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace HiPay\SyliusHiPayPlugin\Migrations;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create the hipay_oney_category and hipay_oney_shipping_method tables.
 *
 * Built through the DBAL schema API rather than raw SQL so the DDL is generated
 * for the connected platform (MySQL, MariaDB, PostgreSQL, SQLite). See HIPASYLU003-47.
 */
final class Version20260324151907 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create hipay_oney_category and hipay_oney_shipping_method tables';
    }

    public function up(Schema $schema): void
    {
        $category = new Table('hipay_oney_category');
        $category->addColumn('id', 'integer', ['autoincrement' => true]);
        $category->addColumn('taxon_id', 'integer');
        $category->addColumn('oney_category_id', 'integer');
        $category->addColumn('created_at', 'datetime');
        $category->addColumn('updated_at', 'datetime', ['notnull' => false]);
        $category->setPrimaryKey(['id']);
        $category->addUniqueIndex(['taxon_id'], 'UNIQ_hipay_oney_category_taxon');
        $category->addForeignKeyConstraint('sylius_taxon', ['taxon_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_587B92FCDE13F470');
        $category->addOption('charset', 'utf8mb4');
        $category->addOption('collation', 'utf8mb4_unicode_ci');

        $shippingMethod = new Table('hipay_oney_shipping_method');
        $shippingMethod->addColumn('id', 'integer', ['autoincrement' => true]);
        $shippingMethod->addColumn('shipping_method_id', 'integer');
        $shippingMethod->addColumn('oney_shipping_method', 'string', ['length' => 64]);
        $shippingMethod->addColumn('oney_preparation_time', 'integer');
        $shippingMethod->addColumn('oney_delivery_time', 'integer');
        $shippingMethod->addColumn('created_at', 'datetime');
        $shippingMethod->addColumn('updated_at', 'datetime', ['notnull' => false]);
        $shippingMethod->setPrimaryKey(['id']);
        $shippingMethod->addUniqueIndex(['shipping_method_id'], 'UNIQ_hipay_oney_shipping_method_shipping_method');
        $shippingMethod->addForeignKeyConstraint('sylius_shipping_method', ['shipping_method_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_F013C3A15F7D6850');
        $shippingMethod->addOption('charset', 'utf8mb4');
        $shippingMethod->addOption('collation', 'utf8mb4_unicode_ci');

        foreach ([$category, $shippingMethod] as $table) {
            foreach ($this->platform->getCreateTableSQL($table, AbstractPlatform::CREATE_INDEXES | AbstractPlatform::CREATE_FOREIGNKEYS) as $sql) {
                $this->addSql($sql);
            }
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql($this->platform->getDropTableSQL('hipay_oney_category'));
        $this->addSql($this->platform->getDropTableSQL('hipay_oney_shipping_method'));
    }
}
