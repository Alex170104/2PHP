<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250508093703 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE player (id INT AUTO_INCREMENT NOT NULL, user_id INT DEFAULT NULL, pseudo VARCHAR(100) NOT NULL, age INT DEFAULT NULL, sport VARCHAR(100) DEFAULT NULL, UNIQUE INDEX UNIQ_98197A65A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE registration (id INT AUTO_INCREMENT NOT NULL, player_id INT NOT NULL, tournament_id INT DEFAULT NULL, statut VARCHAR(50) NOT NULL, UNIQUE INDEX UNIQ_62A8A7A799E6F5DF (player_id), INDEX IDX_62A8A7A733D1A3E7 (tournament_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE rencontre (id INT AUTO_INCREMENT NOT NULL, equipe1_id INT NOT NULL, equipe2_id INT NOT NULL, winner_id INT DEFAULT NULL, tournament_id INT NOT NULL, score1 INT NOT NULL, score2 INT NOT NULL, INDEX IDX_460C35ED4265900C (equipe1_id), INDEX IDX_460C35ED50D03FE2 (equipe2_id), INDEX IDX_460C35ED33D1A3E7 (tournament_id), UNIQUE INDEX unique_winner_per_match (winner_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE tournament (id INT AUTO_INCREMENT NOT NULL, organisateur_id INT DEFAULT NULL, nom VARCHAR(150) NOT NULL, sport VARCHAR(100) NOT NULL, lieu VARCHAR(255) NOT NULL, date_debut DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', date_fin DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', regles LONGTEXT DEFAULT NULL, INDEX IDX_BD5FB8D9D936B2FA (organisateur_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, nom VARCHAR(255) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL (email), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', available_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', delivered_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX IDX_75EA56E0FB7336F0 (queue_name), INDEX IDX_75EA56E0E3BD61CE (available_at), INDEX IDX_75EA56E016BA31DB (delivered_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE player ADD CONSTRAINT FK_98197A65A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE registration ADD CONSTRAINT FK_62A8A7A799E6F5DF FOREIGN KEY (player_id) REFERENCES player (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE registration ADD CONSTRAINT FK_62A8A7A733D1A3E7 FOREIGN KEY (tournament_id) REFERENCES tournament (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE rencontre ADD CONSTRAINT FK_460C35ED4265900C FOREIGN KEY (equipe1_id) REFERENCES player (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE rencontre ADD CONSTRAINT FK_460C35ED50D03FE2 FOREIGN KEY (equipe2_id) REFERENCES player (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE rencontre ADD CONSTRAINT FK_460C35ED5DFCD4B8 FOREIGN KEY (winner_id) REFERENCES player (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE rencontre ADD CONSTRAINT FK_460C35ED33D1A3E7 FOREIGN KEY (tournament_id) REFERENCES tournament (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE tournament ADD CONSTRAINT FK_BD5FB8D9D936B2FA FOREIGN KEY (organisateur_id) REFERENCES user (id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE player DROP FOREIGN KEY FK_98197A65A76ED395
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE registration DROP FOREIGN KEY FK_62A8A7A799E6F5DF
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE registration DROP FOREIGN KEY FK_62A8A7A733D1A3E7
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE rencontre DROP FOREIGN KEY FK_460C35ED4265900C
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE rencontre DROP FOREIGN KEY FK_460C35ED50D03FE2
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE rencontre DROP FOREIGN KEY FK_460C35ED5DFCD4B8
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE rencontre DROP FOREIGN KEY FK_460C35ED33D1A3E7
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE tournament DROP FOREIGN KEY FK_BD5FB8D9D936B2FA
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE player
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE registration
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE rencontre
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE tournament
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE user
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE messenger_messages
        SQL);
    }
}
