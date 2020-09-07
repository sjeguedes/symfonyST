<?php

declare(strict_types=1);

namespace App\Utils\Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Version20190115142607 migration class.
 *
 * Create "images" table.
 */
final class Version20190115142607 extends AbstractMigration
{
    /**
     * Create "images" table and add constraint with "medias" table.
     *
     * @param Schema $schema
     *
     * @return void
     */
    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE images (uuid BINARY(16) NOT NULL COMMENT '(DC2Type:uuid_binary)', name VARCHAR(255) NOT NULL, description VARCHAR(255) NOT NULL, format VARCHAR(4) NOT NULL, size INT NOT NULL, creation_date DATETIME NOT NULL, update_date DATETIME NOT NULL, UNIQUE INDEX UNIQ_E01FBE6A5E237E06 (name), PRIMARY KEY(uuid)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;");
    }
    /**
     * Drop constraint with "medias" table, and then drop "images" table.
     *
     * @param Schema $schema
     *
     * @return void
     */
    public function down(Schema $schema): void
    {
        $this->addSql("DROP TABLE images");
    }
}
