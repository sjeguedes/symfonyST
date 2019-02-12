<?php declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Version20190115151641 migration class.
 *
 * Create "images" table.
 */
final class Version20190115151641 extends AbstractMigration
{
    /**
     * Create "images" table and add constraint with "medias" table.
     *
     * @param Schema $schema
     *
     * @return void
     */
    public function up(Schema $schema) : void
    {
        $this->addSql(" CREATE TABLE images (uuid BINARY(16) NOT NULL COMMENT '(DC2Type:uuid_binary)', name VARCHAR(255) NOT NULL, description VARCHAR(255) NOT NULL, format VARCHAR(4) NOT NULL, size INT NOT NULL, dimensions VARCHAR(9) NOT NULL, creation_date DATETIME NOT NULL, update_date DATETIME NOT NULL, UNIQUE INDEX UNIQ_E01FBE6A5E237E06 (name), PRIMARY KEY(uuid)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB");
        // Add constraint with "medias" table to manage "Class Table Inheritance":
        //$this->addSql("ALTER TABLE images ADD CONSTRAINT FK_E01FBE6AD17F50A6 FOREIGN KEY (uuid) REFERENCES medias (uuid) ON DELETE CASCADE");
    }

    /**
     * Drop constraint with "medias" table, and then drop "images" table.
     *
     * @param Schema $schema
     *
     * @return void
     */
    public function down(Schema $schema) : void
    {
        // Remove constraint with "medias" table for "Class Table Inheritance":
        //$this->addSql("ALTER TABLE images DROP FOREIGN KEY FK_E01FBE6AD17F50A6");
        $this->addSql("DROP TABLE images");
    }
}
