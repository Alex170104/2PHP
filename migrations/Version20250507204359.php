<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250507204359 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE registration DROP INDEX IDX_62A8A7A799E6F5DF, ADD UNIQUE INDEX UNIQ_62A8A7A799E6F5DF (player_id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE registration DROP FOREIGN KEY FK_62A8A7A799E6F5DF
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE registration CHANGE player_id player_id INT NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE registration ADD CONSTRAINT FK_62A8A7A799E6F5DF FOREIGN KEY (player_id) REFERENCES player (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE rencontre DROP INDEX IDX_460C35ED5DFCD4B8, ADD UNIQUE INDEX unique_winner_per_match (winner_id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user CHANGE nom nom VARCHAR(255) NOT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE user CHANGE nom nom VARCHAR(100) NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE registration DROP INDEX UNIQ_62A8A7A799E6F5DF, ADD INDEX IDX_62A8A7A799E6F5DF (player_id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE registration DROP FOREIGN KEY FK_62A8A7A799E6F5DF
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE registration CHANGE player_id player_id INT DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE registration ADD CONSTRAINT FK_62A8A7A799E6F5DF FOREIGN KEY (player_id) REFERENCES player (id) ON UPDATE NO ACTION ON DELETE NO ACTION
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE rencontre DROP INDEX unique_winner_per_match, ADD INDEX IDX_460C35ED5DFCD4B8 (winner_id)
        SQL);
    }
}
