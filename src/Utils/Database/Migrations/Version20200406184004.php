<?php

declare(strict_types=1);

namespace App\Utils\Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Version20200406184004 migration class.
 */
final class Version20200406184004 extends AbstractMigration
{
    /**
     * Add unique indexes for image and video uuid in medias table.
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
        $this->addSql('ALTER TABLE medias DROP INDEX IDX_12D2AF812345BA38, ADD UNIQUE INDEX UNIQ_12D2AF812345BA38 (image_uuid)');
        $this->addSql('ALTER TABLE medias DROP INDEX IDX_12D2AF81D6E80D7A, ADD UNIQUE INDEX UNIQ_12D2AF81D6E80D7A (video_uuid)');
    }

    /**
     * Cancel "add unique indexes for image and video uuid in medias table".
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
        $this->addSql('ALTER TABLE medias DROP INDEX UNIQ_12D2AF812345BA38, ADD INDEX IDX_12D2AF812345BA38 (image_uuid)');
        $this->addSql('ALTER TABLE medias DROP INDEX UNIQ_12D2AF81D6E80D7A, ADD INDEX IDX_12D2AF81D6E80D7A (video_uuid)');
    }
}
