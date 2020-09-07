<?php

declare(strict_types=1);

namespace App\Utils\Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Version20200822164218 migration class.
 */
final class Version20200822164218 extends AbstractMigration
{
    /**
     * Create comments table and add constraints on other tables.
     *
     * @param Schema $schema
     *
     * @return void
     *
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\DBAL\Migrations\AbortMigrationException
     */
    public function up(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');
        $this->addSql('CREATE TABLE comments (uuid BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid_binary)\', parent_comment_uuid BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid_binary)\', trick_uuid BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid_binary)\', user_uuid BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid_binary)\', content LONGTEXT NOT NULL, creation_date DATETIME NOT NULL, update_date DATETIME NOT NULL, INDEX IDX_5F9E962AA727AF64 (parent_comment_uuid), INDEX IDX_5F9E962A9FCC6316 (trick_uuid), INDEX IDX_5F9E962AABFE1C6F (user_uuid), PRIMARY KEY(uuid)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE comments ADD CONSTRAINT FK_5F9E962AA727AF64 FOREIGN KEY (parent_comment_uuid) REFERENCES comments (uuid)');
        $this->addSql('ALTER TABLE comments ADD CONSTRAINT FK_5F9E962A9FCC6316 FOREIGN KEY (trick_uuid) REFERENCES tricks (uuid)');
        $this->addSql('ALTER TABLE comments ADD CONSTRAINT FK_5F9E962AABFE1C6F FOREIGN KEY (user_uuid) REFERENCES users (uuid)');
    }

    /**
     * Cancel "create comments table and add constraints on other tables".
     *
     * @param Schema $schema
     *
     * @return void
     *
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\DBAL\Migrations\AbortMigrationException
     */
    public function down(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');
        $this->addSql('ALTER TABLE comments DROP FOREIGN KEY FK_5F9E962AA727AF64');
        $this->addSql('DROP TABLE comments');
    }
}
