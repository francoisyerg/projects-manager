# 🖥️ Projects Manager - WAMP

**Version 1.0.0**

Un gestionnaire de projets moderne et complet pour environnement WAMP, permettant de gérer vos projets web, leurs Virtual Hosts Apache, bases de données MySQL et tâches de développement.

[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D8.0-blue)](https://www.php.net/)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

---

## 📋 Table des matières

- [Fonctionnalités](#-fonctionnalités)
- [Prérequis](#-prérequis)
- [Installation](#-installation)
- [Configuration](#-configuration)
- [Utilisation](#-utilisation)
- [Structure du projet](#-structure-du-projet)
- [Technologies](#-technologies)
- [Contribuer](#-contribuer)
- [Licence](#-licence)

---

## ✨ Fonctionnalités

### 🎯 Gestion de projets

- ✅ Création, édition et suppression de projets
- 📝 Système de notes avec éditeur TinyMCE intégré
- 🏷️ Tags pour catégoriser vos projets
- 📂 Génération automatique de slugs pour les dossiers
- 🎨 Interface moderne et responsive avec design system CSS

### 🌐 Gestion des Virtual Hosts

- ➕ Création automatique de VHosts Apache
- 🔧 Configuration multi-version PHP (via FastCGI)
- 📝 Nom d'affichage personnalisé pour chaque VHost
- 🗑️ Suppression propre des VHosts
- 📄 Modification automatique des fichiers `httpd-vhosts.conf` et `hosts`

### 🗄️ Gestion des bases de données

- 🆕 Création automatique de bases MySQL lors de l'ajout d'un VHost
- 💾 Export/Import de bases de données au format SQL
- 🔗 Intégration phpMyAdmin directe
- 📦 Export de projets complets (fichiers + BDD)

### ✅ Gestion des tâches

- 📋 Système de tâches par projet avec statuts personnalisables
- 🎯 Niveaux de priorité : Aucune, Faible, Moyenne, Haute, Critique
- ⏰ Dates d'échéance avec indicateurs visuels
- 📊 Organisation par colonnes de statuts (Kanban-like)
- 🔄 Tri automatique par priorité et date

### ⚙️ Configuration

- 🎛️ Interface de configuration intuitive
- 🔌 Gestion des versions PHP et ports FastCGI
- 📁 Configuration des chemins système (WAMP, Apache, etc.)
- 💾 Sauvegarde automatique dans `config.php`

---

## 📦 Prérequis

- **WAMP Server** (Windows + Apache + MySQL + PHP)
- **PHP** >= 8.0
- **MySQL** >= 5.7
- **Apache** 2.4+ avec mod_proxy_fcgi
- **Composer** (pour l'autoloading PSR-4)

---

## 🚀 Installation

### 1. Cloner le dépôt

```bash
git clone https://github.com/francoisyerg/projects-manager.git
cd projects-manager
```

### 2. Installer les dépendances

```bash
composer install
```

### 3. Configuration initiale

Au premier lancement, accédez à l'application via votre navigateur :

```
http://localhost/projects_manager/
```

L'assistant de configuration vous guidera pour :

- Configurer la connexion MySQL
- Définir les chemins WAMP (projets, VHosts, hosts)
- Configurer les versions PHP disponibles
- Initialiser la base de données

### 4. Structure des fichiers

Le fichier `config.php` sera créé automatiquement à la racine avec vos paramètres.

---

## ⚙️ Configuration

### Fichier config.php

Exemple de configuration type :

```php
<?php
return [
    'db_host' => 'localhost',
    'db_port' => '3306',
    'db_username' => 'root',
    'db_password' => '',
    'db_schema' => 'projects_manager',

    'base_projects_path' => 'C:\wamp64\www',
    'vhosts_file' => 'C:\wamp64\bin\apache\apache2.4.x\conf\extra\httpd-vhosts.conf',
    'hosts_file' => 'C:\Windows\System32\drivers\etc\hosts',

    'php_versions' => [
        '8.3' => 9083,
        '8.2' => 9082,
        '8.1' => 9081,
        '7.4' => 9074,
    ],
    'default_php_version' => '8.3',

    'app_title' => 'Gestionnaire de Projets - WAMP',
    'phpmyadmin_url' => '/phpmyadmin/',
    'editor_type' => 'tinymce',
];
```

### Configuration Apache FastCGI

Pour utiliser plusieurs versions PHP, configurez FastCGI dans votre `httpd.conf` :

```apache
LoadModule proxy_module modules/mod_proxy.so
LoadModule proxy_fcgi_module modules/mod_proxy_fcgi.so

# Définir les handlers pour chaque version PHP
<IfModule proxy_fcgi_module>
    Define PHPFCGI_8_3 "fcgi://127.0.0.1:9083"
    Define PHPFCGI_8_2 "fcgi://127.0.0.1:9082"
    # ... autres versions
</IfModule>
```

---

## 💡 Utilisation

### Créer un projet

1. Cliquez sur **➕ Créer un Projet**
2. Renseignez le nom et la description
3. Ajoutez des tags (optionnel)
4. Le slug du dossier est généré automatiquement

### Ajouter un Virtual Host

1. Ouvrez un projet
2. Cliquez sur **➕ Ajouter VHost**
3. Configurez :
   - Nom d'affichage
   - ServerName (ex: `monsite.local`)
   - DocumentRoot (chemin du dossier)
   - Version PHP
   - Création automatique de BDD (optionnel)

Le VHost est automatiquement créé dans Apache et ajouté au fichier hosts !

### Gérer les tâches

1. Dans un projet, section **Tâches**
2. Cliquez sur **➕ Ajouter Tâche**
3. Définissez :
   - Titre de la tâche
   - Description
   - Statut (pending, in-progress, done, etc.)
   - Priorité (none, low, medium, high, critical)
   - Date d'échéance (optionnel)

Les tâches sont organisées par colonnes de statuts et triées par priorité.

### Exporter un projet

1. Ouvrez un projet
2. Cliquez sur **⬇️ Exporter**
3. Choisissez ce que vous souhaitez inclure :
   - Fichiers du projet
   - Configuration VHosts
   - Bases de données (dumps SQL)
4. Téléchargez l'archive ZIP générée

---

## 📁 Structure du projet

```
projects_manager/
├── public/                    # Point d'entrée public
│   ├── bootstrap.php          # Initialisation de l'application
│   ├── index.php              # Routeur principal
│   ├── projects.php           # Liste des projets
│   ├── project.php            # Détail d'un projet
│   ├── configuration.php      # Interface de configuration
│   ├── welcome.php            # Assistant de première installation
│   └── export.php             # Téléchargement des exports
│
├── src/                       # Architecture MVC
│   ├── Config/
│   │   ├── AppConfig.php      # Gestion de la configuration
│   │   └── ConfigPersister.php # Sauvegarde de la configuration
│   ├── Controller/
│   │   ├── ProjectController.php
│   │   └── ProjectsController.php
│   ├── Repository/
│   │   └── ProjectsRepository.php # Accès aux données (MySQL)
│   └── Service/
│       ├── ProjectService.php     # Logique métier
│       └── SystemVhostManager.php # Gestion Apache/VHosts
│
├── views/                     # Templates PHP
│   ├── layouts/
│   │   ├── project/
│   │   │   └── detail.php     # Vue détail projet
│   │   └── projects/
│   │       └── list.php       # Vue liste projets
│   ├── configuration.php      # Vue configuration
│   └── welcome.php            # Vue installation
│
├── assets/                    # Ressources statiques
│   ├── css/
│   │   └── app.css           # Design system complet
│   └── js/
│       └── project.js        # Interactions JavaScript
│
├── storage/                   # Stockage persistant
│   └── exports/              # Archives ZIP d'export
│
├── vendor/                    # Dépendances Composer
├── composer.json              # Configuration Composer
├── config.php                 # Configuration de l'application (généré)
├── index.php                  # Redirection vers public/
└── README.md                  # Ce fichier
```

---

## 🛠️ Technologies

### Backend

- **PHP 8+** - Langage principal
- **MySQL** - Base de données
- **Architecture MVC** - Organisation du code
- **PSR-4 Autoloading** - Chargement automatique des classes

### Frontend

- **HTML5 / CSS3** - Structure et design
- **JavaScript Vanilla** - Interactions dynamiques
- **TinyMCE** - Éditeur WYSIWYG pour les notes
- **CSS Variables** - Design system moderne

### Outils

- **Apache 2.4** - Serveur web
- **mod_proxy_fcgi** - Support multi-PHP
- **Composer** - Gestion des dépendances

---

## 🎨 Fonctionnalités avancées

### Système de statuts personnalisables

Les statuts des tâches sont configurables dans `config.php` :

```php
'task_statuses' => [
    'pending' => ['label' => 'À faire', 'color' => '#f59e0b'],
    'in-progress' => ['label' => 'En cours', 'color' => '#3b82f6'],
    'done' => ['label' => 'Terminé', 'color' => '#10b981'],
    'blocked' => ['label' => 'Bloqué', 'color' => '#ef4444'],
],
```

### Système de priorités

Priorités avec tri automatique :

- **Critique** - Tâches urgentes en rouge
- **Haute** - Priorité élevée en orange
- **Moyenne** - Priorité normale en jaune
- **Faible** - Faible priorité en bleu
- **Aucune** - Sans priorité (affichées en dernier)

### Import/Export de projets

Format d'export ZIP contenant :

- `project.json` - Métadonnées du projet
- `vhosts/` - Configuration Apache
- `databases/` - Dumps SQL
- `files/` - Fichiers du projet (optionnel)

---

## 🤝 Contribuer

Les contributions sont les bienvenues !

1. Forkez le projet
2. Créez une branche (`git checkout -b feature/amelioration`)
3. Committez vos changements (`git commit -m 'Ajout d'une fonctionnalité'`)
4. Pushez vers la branche (`git push origin feature/amelioration`)
5. Ouvrez une Pull Request

---

## 📝 Licence

Ce projet est sous licence MIT. Voir le fichier `LICENSE` pour plus de détails.

---

## 👤 Auteur

Développé avec ❤️ pour simplifier la gestion de projets WAMP.

---

## 🐛 Bugs connus & Limitations

- **Windows uniquement** - Chemin des fichiers système (hosts, VHosts)
- **Permissions** - Nécessite des droits administrateur pour modifier les fichiers système
- **Apache restart** - Nécessite un redémarrage manuel d'Apache après ajout de VHosts

---

## 🔮 Roadmap

- [ ] Mode dark
- [ ] Notifications en temps réel
- [ ] Gestion des utilisateurs multi-comptes
- [ ] Intégration Git
- [ ] Logs d'activité détaillés

---

## 📞 Support

Pour toute question ou problème :

- 🐛 Ouvrez une [issue](https://github.com/votre-utilisateur/projects-manager/issues)
- 💬 Consultez la [documentation](https://github.com/votre-utilisateur/projects-manager/wiki)

---

**Merci d'utiliser Projects Manager ! ⭐**
