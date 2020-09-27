<?php

declare(strict_types=1);

namespace App\Utils\Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Version20200825125111 migration class.
 *
 * @see integrity constraint violation with self referencing issue and work around:
 * https://github.com/doctrine/data-fixtures/issues/159
 * https://stackoverflow.com/questions/39358986/disable-doctrine-foreign-key-constraint
 */
final class Version20200825125111 extends AbstractMigration
{
    /**
     * Alter comments table to add constraint on parent comment uuid.
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
        $this->addSql('ALTER TABLE comments DROP FOREIGN KEY FK_5F9E962AA727AF64');
        $this->addSql('ALTER TABLE comments ADD CONSTRAINT FK_5F9E962AA727AF64 FOREIGN KEY (parent_comment_uuid) REFERENCES comments (uuid) ON DELETE SET NULL');
    }

    /**
     * Cancel "alter comments table to add constraint on parent comment uuid".
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
        $this->addSql('ALTER TABLE comments ADD CONSTRAINT FK_5F9E962AA727AF64 FOREIGN KEY (parent_comment_uuid) REFERENCES comments (uuid)');
    }
}
