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
final class Version20260320161403 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE hipay_saved_card (id INT AUTO_INCREMENT NOT NULL, customer_id INT NOT NULL, token VARCHAR(255) NOT NULL, brand VARCHAR(50) NOT NULL, masked_pan VARCHAR(20) NOT NULL, expiry_month VARCHAR(2) NOT NULL, expiry_year VARCHAR(4) NOT NULL, holder VARCHAR(255) DEFAULT NULL, authorized TINYINT(1) DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, INDEX IDX_2AB2BB89395C3F3 (customer_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE hipay_saved_card ADD CONSTRAINT FK_2AB2BB89395C3F3 FOREIGN KEY (customer_id) REFERENCES sylius_customer (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE hipay_saved_card DROP FOREIGN KEY FK_2AB2BB89395C3F3');
        $this->addSql('DROP TABLE hipay_saved_card');
    }
}
