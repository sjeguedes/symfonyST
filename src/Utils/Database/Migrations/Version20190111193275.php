<?php

declare(strict_types=1);

namespace App\Utils\Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Version20190111193275 migration class.
 *
 * Create "users" table.
 */
final class Version20190111193275 extends AbstractMigration
{
    /**
     * Create "users" table.
     *
     * @param Schema $schema
     *
     * @return void
     */
    public function up(Schema $schema) : void
    {
        $this->addSql("CREATE TABLE users (uuid BINARY(16) NOT NULL COMMENT '(DC2Type:uuid_binary)', family_name VARCHAR(255) NOT NULL, first_name VARCHAR(255) NOT NULL, nick_name VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, password VARCHAR(60) NOT NULL, roles LONGTEXT NOT NULL COMMENT '(DC2Type:array)', is_activated TINYINT(1) NOT NULL, renewal_token VARCHAR(15) DEFAULT NULL, creation_date DATETIME NOT NULL, update_date DATETIME NOT NULL, renewal_request_date DATETIME DEFAULT NULL, UNIQUE INDEX UNIQ_1483A5E9A045A5E9 (nick_name), UNIQUE INDEX UNIQ_1483A5E9E7927C74 (email), UNIQUE INDEX UNIQ_1483A5E935C246D5 (password), UNIQUE INDEX UNIQ_1483A5E9F01922F3 (renewal_token), PRIMARY KEY(uuid)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB");
    }

    /**
     * Drop "users" table.
     *
     * @param Schema $schema
     *
     * @return void
     */
    public function down(Schema $schema) : void
    {
        $this->addSql("DROP TABLE users");
    }
}
