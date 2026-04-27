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
final class Version20260304095122 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE hipay_account (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, code VARCHAR(64) NOT NULL, api_username VARCHAR(255) NOT NULL, api_password VARCHAR(255) NOT NULL, secret_passphrase VARCHAR(255) NOT NULL, test_api_username VARCHAR(255) NOT NULL, test_api_password VARCHAR(255) NOT NULL, test_secret_passphrase VARCHAR(255) NOT NULL, public_username VARCHAR(255) NOT NULL, public_password VARCHAR(255) NOT NULL, test_public_username VARCHAR(255) NOT NULL, test_public_password VARCHAR(255) NOT NULL, environment VARCHAR(32) NOT NULL, debug_mode TINYINT(1) DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, UNIQUE INDEX UNIQ_1E2439AB77153098 (code), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE hipay_account');
    }
}
