<?php

declare(strict_types=1);

namespace App\Utils\Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 *
 * @see integrity constraint violation with self referencing issue and work around:
 * https://github.com/doctrine/data-fixtures/issues/159
 * https://stackoverflow.com/questions/39358986/disable-doctrine-foreign-key-constraint
 */
final class Version20200825125111 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE comments DROP FOREIGN KEY FK_5F9E962AA727AF64');
        $this->addSql('ALTER TABLE comments ADD CONSTRAINT FK_5F9E962AA727AF64 FOREIGN KEY (parent_comment_uuid) REFERENCES comments (uuid) ON DELETE SET NULL');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE comments DROP FOREIGN KEY FK_5F9E962AA727AF64');
        $this->addSql('ALTER TABLE comments ADD CONSTRAINT FK_5F9E962AA727AF64 FOREIGN KEY (parent_comment_uuid) REFERENCES comments (uuid)');
    }
}
