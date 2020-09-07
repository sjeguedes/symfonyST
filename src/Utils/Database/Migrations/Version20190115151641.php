<?php

declare(strict_types=1);

namespace App\Utils\Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Version20190115151641 migration class.
 *
 * Create "videos" table.
 */
final class Version20190115151641 extends AbstractMigration
{
    /**
     * Create "videos" table.
     *
     * @param Schema $schema
     *
     * @return void
     */
    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE videos (uuid BINARY(16) NOT NULL COMMENT '(DC2Type:uuid_binary)', url VARCHAR(255) NOT NULL, description VARCHAR(255) NOT NULL, creation_date DATETIME NOT NULL, update_date DATETIME NOT NULL, PRIMARY KEY(uuid)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB");
    }

    /**
     * Drop "videos" table.
     *
     * @param Schema $schema
     *
     * @return void
     */
    public function down(Schema $schema): void
    {
        $this->addSql("DROP TABLE videos");
    }
}
