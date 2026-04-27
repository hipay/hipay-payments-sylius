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

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260424083033 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE hipay_pending_notification (id BIGINT AUTO_INCREMENT NOT NULL, event_id VARCHAR(64) NOT NULL, transaction_reference VARCHAR(64) DEFAULT NULL, status INT NOT NULL, priority SMALLINT NOT NULL, payload JSON NOT NULL, state VARCHAR(16) NOT NULL, attempts SMALLINT DEFAULT 0 NOT NULL, last_error LONGTEXT DEFAULT NULL, available_at DATETIME NOT NULL, claimed_at DATETIME DEFAULT NULL, processed_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, UNIQUE INDEX UNIQ_92FA30D971F7E88B (event_id), INDEX idx_hipay_pending_claim (state, available_at, priority, id), INDEX idx_hipay_pending_tx_ref (transaction_reference), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE hipay_pending_notification');
    }
}
