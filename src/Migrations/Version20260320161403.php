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
 * Create the hipay_saved_card table.
 *
 * Built through the DBAL schema API rather than raw SQL so the DDL is generated
 * for the connected platform (MySQL, MariaDB, PostgreSQL, SQLite). See HIPASYLU003-47.
 */
final class Version20260320161403 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create hipay_saved_card table';
    }

    public function up(Schema $schema): void
    {
        $table = new Table('hipay_saved_card');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('customer_id', 'integer');
        $table->addColumn('token', 'string', ['length' => 255]);
        $table->addColumn('brand', 'string', ['length' => 50]);
        $table->addColumn('masked_pan', 'string', ['length' => 20]);
        $table->addColumn('expiry_month', 'string', ['length' => 2]);
        $table->addColumn('expiry_year', 'string', ['length' => 4]);
        $table->addColumn('holder', 'string', ['length' => 255, 'notnull' => false]);
        $table->addColumn('authorized', 'boolean', ['default' => true]);
        $table->addColumn('created_at', 'datetime');
        $table->addColumn('updated_at', 'datetime', ['notnull' => false]);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['customer_id'], 'IDX_2AB2BB89395C3F3');
        $table->addForeignKeyConstraint('sylius_customer', ['customer_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_2AB2BB89395C3F3');
        $table->addOption('charset', 'utf8mb4');
        $table->addOption('collation', 'utf8mb4_unicode_ci');

        foreach ($this->platform->getCreateTableSQL($table, AbstractPlatform::CREATE_INDEXES | AbstractPlatform::CREATE_FOREIGNKEYS) as $sql) {
            $this->addSql($sql);
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql($this->platform->getDropTableSQL('hipay_saved_card'));
    }
}
