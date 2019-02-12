<?php
declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Version20190115142021 migration class.
 *
 * Create "media_types" table.
 */
final class Version20190115142021 extends AbstractMigration
{
    /**
     * Create "media_types" table.
     *
     * @param Schema $schema
     *
     * @return void
     */
    public function up(Schema $schema) : void
    {
        $this->addSql("CREATE TABLE media_types (uuid BINARY(16) NOT NULL COMMENT '(DC2Type:uuid_binary)', name VARCHAR(255) NOT NULL, description VARCHAR(255) NOT NULL, creation_date DATETIME NOT NULL, update_date DATETIME NOT NULL, UNIQUE INDEX UNIQ_46294D815E237E06 (name), PRIMARY KEY(uuid)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB");
    }

    /**
     * Drop "media_types" table.
     *
     * @param Schema $schema
     *
     * @return void
     */
    public function down(Schema $schema) : void
    {
        $this->addSql("DROP TABLE media_types");
    }
}
