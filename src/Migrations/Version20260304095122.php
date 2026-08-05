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
 * Create the hipay_account table.
 *
 * Built through the DBAL schema API rather than raw SQL so the DDL is generated
 * for the connected platform (MySQL, MariaDB, PostgreSQL, SQLite). See HIPASYLU003-47.
 */
final class Version20260304095122 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create hipay_account table';
    }

    public function up(Schema $schema): void
    {
        $table = new Table('hipay_account');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('name', 'string', ['length' => 255]);
        $table->addColumn('code', 'string', ['length' => 64]);
        $table->addColumn('api_username', 'string', ['length' => 255]);
        $table->addColumn('api_password', 'string', ['length' => 255]);
        $table->addColumn('secret_passphrase', 'string', ['length' => 255]);
        $table->addColumn('test_api_username', 'string', ['length' => 255]);
        $table->addColumn('test_api_password', 'string', ['length' => 255]);
        $table->addColumn('test_secret_passphrase', 'string', ['length' => 255]);
        $table->addColumn('public_username', 'string', ['length' => 255]);
        $table->addColumn('public_password', 'string', ['length' => 255]);
        $table->addColumn('test_public_username', 'string', ['length' => 255]);
        $table->addColumn('test_public_password', 'string', ['length' => 255]);
        $table->addColumn('environment', 'string', ['length' => 32]);
        $table->addColumn('debug_mode', 'boolean', ['default' => false]);
        $table->addColumn('created_at', 'datetime');
        $table->addColumn('updated_at', 'datetime', ['notnull' => false]);
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['code'], 'UNIQ_1E2439AB77153098');
        $table->addOption('charset', 'utf8mb4');
        $table->addOption('collation', 'utf8mb4_unicode_ci');

        foreach ($this->platform->getCreateTableSQL($table, AbstractPlatform::CREATE_INDEXES | AbstractPlatform::CREATE_FOREIGNKEYS) as $sql) {
            $this->addSql($sql);
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql($this->platform->getDropTableSQL('hipay_account'));
    }
}
