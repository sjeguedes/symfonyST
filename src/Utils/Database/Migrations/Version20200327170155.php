<?php

declare(strict_types=1);

namespace App\Utils\Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Version20200327170155 migration class.
 */
final class Version20200327170155 extends AbstractMigration
{
    /**
     * Add show list rank in medias table.
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
        $this->addSql('ALTER TABLE medias ADD show_list_rank SMALLINT UNSIGNED DEFAULT NULL');
    }

    /**
     * Cancel "add show list rank in medias table".
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
        $this->addSql('ALTER TABLE medias DROP show_list_rank');
    }
}
