<?php declare(strict_types=1);

namespace App\Utils\Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200601123004 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE media_owners (uuid BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid_binary)\', trick_uuid BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid_binary)\', user_uuid BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid_binary)\', UNIQUE INDEX UNIQ_FE0B08249FCC6316 (trick_uuid), UNIQUE INDEX UNIQ_FE0B0824ABFE1C6F (user_uuid), PRIMARY KEY(uuid)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE media_sources (uuid BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid_binary)\', image_uuid BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid_binary)\', video_uuid BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid_binary)\', UNIQUE INDEX UNIQ_B38AE3BB2345BA38 (image_uuid), UNIQUE INDEX UNIQ_B38AE3BBD6E80D7A (video_uuid), PRIMARY KEY(uuid)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE media_owners ADD CONSTRAINT FK_FE0B08249FCC6316 FOREIGN KEY (trick_uuid) REFERENCES tricks (uuid)');
        $this->addSql('ALTER TABLE media_owners ADD CONSTRAINT FK_FE0B0824ABFE1C6F FOREIGN KEY (user_uuid) REFERENCES users (uuid)');
        $this->addSql('ALTER TABLE media_sources ADD CONSTRAINT FK_B38AE3BB2345BA38 FOREIGN KEY (image_uuid) REFERENCES images (uuid)');
        $this->addSql('ALTER TABLE media_sources ADD CONSTRAINT FK_B38AE3BBD6E80D7A FOREIGN KEY (video_uuid) REFERENCES videos (uuid)');
        $this->addSql('ALTER TABLE medias DROP FOREIGN KEY FK_12D2AF812345BA38');
        $this->addSql('ALTER TABLE medias DROP FOREIGN KEY FK_12D2AF819FCC6316');
        $this->addSql('ALTER TABLE medias DROP FOREIGN KEY FK_12D2AF81D6E80D7A');
        $this->addSql('DROP INDEX UNIQ_12D2AF81D6E80D7A ON medias');
        $this->addSql('DROP INDEX IDX_12D2AF819FCC6316 ON medias');
        $this->addSql('DROP INDEX UNIQ_12D2AF812345BA38 ON medias');
        $this->addSql('ALTER TABLE medias ADD media_source_uuid BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid_binary)\', ADD media_owner_uuid BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid_binary)\', DROP image_uuid, DROP video_uuid, DROP trick_uuid');
        $this->addSql('ALTER TABLE medias ADD CONSTRAINT FK_12D2AF81279DA736 FOREIGN KEY (media_source_uuid) REFERENCES media_sources (uuid)');
        $this->addSql('ALTER TABLE medias ADD CONSTRAINT FK_12D2AF814DFCFDA2 FOREIGN KEY (media_owner_uuid) REFERENCES media_owners (uuid)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_12D2AF81279DA736 ON medias (media_source_uuid)');
        $this->addSql('CREATE INDEX IDX_12D2AF814DFCFDA2 ON medias (media_owner_uuid)');
        $this->addSql('ALTER TABLE media_types ADD source_type VARCHAR(45) NOT NULL, CHANGE type type VARCHAR(45) NOT NULL');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE medias DROP FOREIGN KEY FK_12D2AF814DFCFDA2');
        $this->addSql('ALTER TABLE medias DROP FOREIGN KEY FK_12D2AF81279DA736');
        $this->addSql('DROP TABLE media_owners');
        $this->addSql('DROP TABLE media_sources');
        $this->addSql('ALTER TABLE media_types DROP source_type, CHANGE type type VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('DROP INDEX UNIQ_12D2AF81279DA736 ON medias');
        $this->addSql('DROP INDEX IDX_12D2AF814DFCFDA2 ON medias');
        $this->addSql('ALTER TABLE medias ADD image_uuid BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid_binary)\', ADD video_uuid BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid_binary)\', ADD trick_uuid BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid_binary)\', DROP media_source_uuid, DROP media_owner_uuid');
        $this->addSql('ALTER TABLE medias ADD CONSTRAINT FK_12D2AF812345BA38 FOREIGN KEY (image_uuid) REFERENCES images (uuid)');
        $this->addSql('ALTER TABLE medias ADD CONSTRAINT FK_12D2AF819FCC6316 FOREIGN KEY (trick_uuid) REFERENCES tricks (uuid)');
        $this->addSql('ALTER TABLE medias ADD CONSTRAINT FK_12D2AF81D6E80D7A FOREIGN KEY (video_uuid) REFERENCES videos (uuid)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_12D2AF81D6E80D7A ON medias (video_uuid)');
        $this->addSql('CREATE INDEX IDX_12D2AF819FCC6316 ON medias (trick_uuid)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_12D2AF812345BA38 ON medias (image_uuid)');
    }
}
