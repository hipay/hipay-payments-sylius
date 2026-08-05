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
 * Create the `lock_keys` table used by the Symfony Lock DoctrineDbalStore that
 * backs the order advisory lock (HIPASYLU003-47).
 *
 * Without this table the store creates it lazily on first use. On PostgreSQL,
 * if that first acquisition happens inside an open transaction, the failed
 * "table does not exist" INSERT aborts the whole transaction (SQLSTATE 25P02) —
 * so the table must already exist. Columns mirror Symfony's DoctrineDbalStore.
 */
final class Version20260630120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create lock_keys table for the Symfony Lock DoctrineDbalStore';
    }

    public function up(Schema $schema): void
    {
        $table = new Table('lock_keys');
        $table->addColumn('key_id', 'string', ['length' => 64]);
        $table->addColumn('key_token', 'string', ['length' => 44]);
        $table->addColumn('key_expiration', 'integer', ['unsigned' => true]);
        $table->setPrimaryKey(['key_id']);

        foreach ($this->platform->getCreateTableSQL($table, AbstractPlatform::CREATE_INDEXES | AbstractPlatform::CREATE_FOREIGNKEYS) as $sql) {
            $this->addSql($sql);
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql($this->platform->getDropTableSQL('lock_keys'));
    }
}
