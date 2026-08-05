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
 * Create the hipay_pending_notification table.
 *
 * Built through the DBAL schema API rather than raw SQL so the DDL is generated
 * for the connected platform (MySQL, MariaDB, PostgreSQL, SQLite). See HIPASYLU003-47.
 */
final class Version20260424083033 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create hipay_pending_notification table';
    }

    public function up(Schema $schema): void
    {
        $table = new Table('hipay_pending_notification');
        $table->addColumn('id', 'bigint', ['autoincrement' => true]);
        $table->addColumn('event_id', 'string', ['length' => 64]);
        $table->addColumn('transaction_reference', 'string', ['length' => 64, 'notnull' => false]);
        $table->addColumn('status', 'integer');
        $table->addColumn('priority', 'smallint');
        $table->addColumn('payload', 'json');
        $table->addColumn('state', 'string', ['length' => 16]);
        $table->addColumn('attempts', 'smallint', ['default' => 0]);
        $table->addColumn('last_error', 'text', ['notnull' => false]);
        $table->addColumn('available_at', 'datetime');
        $table->addColumn('claimed_at', 'datetime', ['notnull' => false]);
        $table->addColumn('processed_at', 'datetime', ['notnull' => false]);
        $table->addColumn('created_at', 'datetime');
        $table->addColumn('updated_at', 'datetime', ['notnull' => false]);
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['event_id'], 'UNIQ_92FA30D971F7E88B');
        $table->addIndex(['state', 'available_at', 'priority', 'id'], 'idx_hipay_pending_claim');
        $table->addIndex(['transaction_reference'], 'idx_hipay_pending_tx_ref');
        $table->addOption('charset', 'utf8mb4');
        $table->addOption('collation', 'utf8mb4_unicode_ci');

        foreach ($this->platform->getCreateTableSQL($table, AbstractPlatform::CREATE_INDEXES | AbstractPlatform::CREATE_FOREIGNKEYS) as $sql) {
            $this->addSql($sql);
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql($this->platform->getDropTableSQL('hipay_pending_notification'));
    }
}
