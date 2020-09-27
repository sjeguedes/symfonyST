<?php

declare(strict_types=1);

namespace App\Utils\Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Version20200726161613 migration class.
 */
final class Version20200726161613 extends AbstractMigration
{
    /**
     * Alter videos table to add name.
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
        $this->addSql('ALTER TABLE videos ADD name VARCHAR(255) NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_29AA64325E237E06 ON videos (name)');
    }

    /**
     * Cancel "alter videos table to add name".
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
        $this->addSql('DROP INDEX UNIQ_29AA64325E237E06 ON videos');
        $this->addSql('ALTER TABLE videos DROP name');
    }
}
