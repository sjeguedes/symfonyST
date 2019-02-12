<?php
declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Version20190111193000 migration class.
 *
 * Create "trick_groups" table and add constraint with "tricks" table.
 */
final class Version20190111193000 extends AbstractMigration
{
    /**
     * Create "trick_groups" table.
     *
     * @param Schema $schema
     *
     * @return void
     */
    public function up(Schema $schema) : void
    {
        $this->addSql("CREATE TABLE trick_groups (uuid BINARY(16) NOT NULL COMMENT '(DC2Type:uuid_binary)', name VARCHAR(255) NOT NULL, description LONGTEXT NOT NULL, creation_date DATETIME NOT NULL, update_date DATETIME NOT NULL, UNIQUE INDEX UNIQ_AB78B86D5E237E06 (name), PRIMARY KEY(uuid)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB");
    }

    /**
     * Drop "trick_groups" table.
     *
     * @param Schema $schema
     *
     * @return void
     */
    public function down(Schema $schema) : void
    {
        $this->addSql("DROP TABLE trick_groups");
    }
}
