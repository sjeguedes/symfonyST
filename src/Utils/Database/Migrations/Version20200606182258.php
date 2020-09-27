<?php

declare(strict_types=1);

namespace App\Utils\Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Version20200606182258 migration class.
 */
final class Version20200606182258 extends AbstractMigration
{
    /**
     * Alter media_owners and media_sources tables to add creation and update dates.
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
        $this->addSql('ALTER TABLE media_owners ADD creation_date DATETIME NOT NULL, ADD update_date DATETIME NOT NULL');
        $this->addSql('ALTER TABLE media_sources ADD creation_date DATETIME NOT NULL, ADD update_date DATETIME NOT NULL');
    }

    /**
     * Cancel "alter media_owners and media_sources tables to add creation and update dates".
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
        $this->addSql('ALTER TABLE media_owners DROP creation_date, DROP update_date');
        $this->addSql('ALTER TABLE media_sources DROP creation_date, DROP update_date');
    }
}
