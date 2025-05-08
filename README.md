# 2PHPD

## 🎯 Objectif

2PHPD est un site web de **gestion de tournoi**, utilisant une **API** pour permettre des interactions simples et efficaces avec les données du tournoi.

## 🛠️ Technologies

- [PHP 8.4.0](https://www.php.net/)
- [Symfony](https://symfony.com/)
- [Doctrine ORM]
- [WAMP](https://www.wampserver.com/) (ou tout autre serveur local compatible PHP/MySQL)

## 🚀 Installation

1. **Cloner le projet ou dézipper le dossier :**

   ```bash
   git clone https://github.com/Alex170104/2PHP.git
   ```

2. **Démarrer WAMP et s'assurer que le serveur MySQL est actif.**

3. **Lancer les commandes suivantes dans le terminal :**
   ```bash
   php bin/console doctrine:database:create
   php bin/console doctrine:migrations:migrate
   php bin/console doctrine:fixtures:load
   symfony server:start
   ```
4. **Accéder à l'application via :**
http://localhost:8000

## 👨💻 Développeurs 

- Tanguy BONFITTO _(tanguy.bonfitto@supinfo.com)_
- Alexandre DISCHAMPS _(alexandre.dischamps@supinfo.com)_
