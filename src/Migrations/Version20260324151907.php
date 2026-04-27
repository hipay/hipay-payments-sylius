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
final class Version20260324151907 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE hipay_oney_category (id INT AUTO_INCREMENT NOT NULL, taxon_id INT NOT NULL, oney_category_id INT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, UNIQUE INDEX UNIQ_hipay_oney_category_taxon (taxon_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE hipay_oney_category ADD CONSTRAINT FK_587B92FCDE13F470 FOREIGN KEY (taxon_id) REFERENCES sylius_taxon (id) ON DELETE CASCADE');
        $this->addSql('CREATE TABLE hipay_oney_shipping_method (id INT AUTO_INCREMENT NOT NULL, shipping_method_id INT NOT NULL, oney_shipping_method VARCHAR(64) NOT NULL, oney_preparation_time INT NOT NULL, oney_delivery_time INT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, UNIQUE INDEX UNIQ_hipay_oney_shipping_method_shipping_method (shipping_method_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE hipay_oney_shipping_method ADD CONSTRAINT FK_F013C3A15F7D6850 FOREIGN KEY (shipping_method_id) REFERENCES sylius_shipping_method (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE hipay_oney_category DROP FOREIGN KEY FK_587B92FCDE13F470');
        $this->addSql('DROP TABLE hipay_oney_category');
        $this->addSql('ALTER TABLE hipay_oney_shipping_method DROP FOREIGN KEY FK_F013C3A15F7D6850');
        $this->addSql('DROP TABLE hipay_oney_shipping_method');
    }
}
