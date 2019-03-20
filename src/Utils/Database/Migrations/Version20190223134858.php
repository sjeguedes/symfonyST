<?php

declare(strict_types=1);

namespace App\Utils\Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Version20190223134858 migration class.
 *
 * Create "medias" table.
 */
final class Version20190223134858 extends AbstractMigration
{
    /**
     * Create "medias" table and add constraints with "media_types", "images", "videos", "tricks" and "users" tables.
     *
     * @param Schema $schema
     *
     * @return void
     */
    public function up(Schema $schema) : void
    {
        $this->addSql("CREATE TABLE medias (uuid BINARY(16) NOT NULL COMMENT '(DC2Type:uuid_binary)', media_type_uuid BINARY(16) NOT NULL COMMENT '(DC2Type:uuid_binary)', image_uuid BINARY(16) DEFAULT NULL COMMENT '(DC2Type:uuid_binary)', video_uuid BINARY(16) DEFAULT NULL COMMENT '(DC2Type:uuid_binary)', trick_uuid BINARY(16) NOT NULL COMMENT '(DC2Type:uuid_binary)', user_uuid BINARY(16) NOT NULL COMMENT '(DC2Type:uuid_binary)', is_main TINYINT(1) NOT NULL, is_published TINYINT(1) NOT NULL, INDEX IDX_12D2AF8123102620 (media_type_uuid), INDEX IDX_12D2AF812345BA38 (image_uuid), INDEX IDX_12D2AF81D6E80D7A (video_uuid), INDEX IDX_12D2AF819FCC6316 (trick_uuid), INDEX IDX_12D2AF81ABFE1C6F (user_uuid), PRIMARY KEY(uuid)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB");
        $this->addSql("ALTER TABLE medias ADD CONSTRAINT FK_12D2AF8123102620 FOREIGN KEY (media_type_uuid) REFERENCES media_types (uuid)");
        $this->addSql("ALTER TABLE medias ADD CONSTRAINT FK_12D2AF812345BA38 FOREIGN KEY (image_uuid) REFERENCES images (uuid)");
        $this->addSql("ALTER TABLE medias ADD CONSTRAINT FK_12D2AF81D6E80D7A FOREIGN KEY (video_uuid) REFERENCES videos (uuid)");
        $this->addSql("ALTER TABLE medias ADD CONSTRAINT FK_12D2AF819FCC6316 FOREIGN KEY (trick_uuid) REFERENCES tricks (uuid)");
        $this->addSql("ALTER TABLE medias ADD CONSTRAINT FK_12D2AF81ABFE1C6F FOREIGN KEY (user_uuid) REFERENCES users (uuid)");
    }

    /**
     * Drop constraints with "media_types", "images", "videos" and "tricks" tables, and then drop "medias" table.
     *
     * @param Schema $schema
     *
     * @return void
     */
    public function down(Schema $schema) : void
    {
        $this->addSql("ALTER TABLE medias DROP FOREIGN KEY FK_12D2AF8123102620");
        $this->addSql("ALTER TABLE medias DROP FOREIGN KEY FK_12D2AF812345BA38");
        $this->addSql("ALTER TABLE medias DROP FOREIGN KEY FK_12D2AF81D6E80D7A");
        $this->addSql("ALTER TABLE medias DROP FOREIGN KEY FK_12D2AF819FCC6316");
        $this->addSql("DROP TABLE medias");
    }
}

