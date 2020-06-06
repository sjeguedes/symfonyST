<?php

declare(strict_types = 1);

namespace App\Utils\Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200606182258 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE media_owners ADD creation_date DATETIME NOT NULL, ADD update_date DATETIME NOT NULL');
        $this->addSql('ALTER TABLE media_sources ADD creation_date DATETIME NOT NULL, ADD update_date DATETIME NOT NULL');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE media_owners DROP creation_date, DROP update_date');
        $this->addSql('ALTER TABLE media_sources DROP creation_date, DROP update_date');
    }
}
