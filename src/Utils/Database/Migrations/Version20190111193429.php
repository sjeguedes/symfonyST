<?php

declare(strict_types=1);

namespace App\Utils\Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 *  Version20190111193429 migration class.
 *
 * Create "tricks" table.
 */
final class Version20190111193429 extends AbstractMigration
{
    /**
     * Create table "tricks" and add constraints with "trick_groups" and "users" tables.
     *
     * @param Schema $schema
     *
     * @return void
     */
    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE tricks (uuid BINARY(16) NOT NULL COMMENT '(DC2Type:uuid_binary)', trick_group_uuid BINARY(16) NOT NULL COMMENT '(DC2Type:uuid_binary)', user_uuid BINARY(16) NOT NULL COMMENT '(DC2Type:uuid_binary)', name VARCHAR(255) NOT NULL, description LONGTEXT NOT NULL, slug VARCHAR(255) NOT NULL, creation_date DATETIME NOT NULL, update_date DATETIME NOT NULL, UNIQUE INDEX UNIQ_E1D902C15E237E06 (name), UNIQUE INDEX UNIQ_E1D902C1989D9B62 (slug), INDEX IDX_E1D902C171AD9B4B (trick_group_uuid), INDEX IDX_E1D902C1ABFE1C6F (user_uuid), PRIMARY KEY(uuid)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB");
        $this->addSql("ALTER TABLE tricks ADD CONSTRAINT FK_E1D902C171AD9B4B FOREIGN KEY (trick_group_uuid) REFERENCES trick_groups (uuid)");
        $this->addSql("ALTER TABLE tricks ADD CONSTRAINT FK_E1D902C1ABFE1C6F FOREIGN KEY (user_uuid) REFERENCES users (uuid)");
    }

    /**
     * Drop constraint with "trick_groups" and "users" tables, and then drop table "tricks".
     *
     * @param Schema $schema
     *
     * @return void
     */
    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE tricks DROP FOREIGN KEY FK_E1D902C171AD9B4B");
        $this->addSql("ALTER TABLE tricks DROP FOREIGN KEY FK_E1D902C1ABFE1C6F");
        $this->addSql("DROP TABLE tricks");
    }
}
