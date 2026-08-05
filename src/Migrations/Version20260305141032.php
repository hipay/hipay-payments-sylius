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
 * Create the hipay_transaction table.
 *
 * Built through the DBAL schema API rather than raw SQL so the DDL is generated
 * for the connected platform (MySQL, MariaDB, PostgreSQL, SQLite). See HIPASYLU003-47.
 */
final class Version20260305141032 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create hipay_transaction table';
    }

    public function up(Schema $schema): void
    {
        $table = new Table('hipay_transaction');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('payment_id', 'integer');
        $table->addColumn('transaction_reference', 'string', ['length' => 255]);
        $table->addColumn('created_at', 'datetime');
        $table->addColumn('updated_at', 'datetime', ['notnull' => false]);
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['transaction_reference'], 'UNIQ_C590D9ABED84D250');
        $table->addUniqueIndex(['payment_id'], 'UNIQ_C590D9AB4C3A3BB');
        $table->addForeignKeyConstraint('sylius_payment', ['payment_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_C590D9AB4C3A3BB');
        $table->addOption('charset', 'utf8mb4');
        $table->addOption('collation', 'utf8mb4_unicode_ci');

        foreach ($this->platform->getCreateTableSQL($table, AbstractPlatform::CREATE_INDEXES | AbstractPlatform::CREATE_FOREIGNKEYS) as $sql) {
            $this->addSql($sql);
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql($this->platform->getDropTableSQL('hipay_transaction'));
    }
}
