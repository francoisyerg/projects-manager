<?php

return  [
    'vhosts_file' => 'C:/wamp64/bin/apache/apache2.4.62.1/conf/extra/httpd-vhosts.conf',
    'projects_file' => 'C:/Users/Francois/Projets/projects.json',
    'hosts_file' => 'C:/Windows/System32/drivers/etc/hosts',
    'base_projects_path' => 'C:/Users/Francois/Projets',
    'project_details_filename' => 'project-details.json',
    'database'
     => [
         'host' => 'localhost',
         'port' => '',
         'username' => 'root',
         'password' => '',
         'charset' => 'utf8mb4',
         'schema' => 'projects_manager',
         'auto_create_databases' => true,
     ],
    'installation'
     => [
         'is_installed' => true,
     ],
    'phpmyadmin_url' => '/phpmyadmin/',
    'editor_type' => 'tinymce',
    'editor_placeholder' => 'Ajoutez vos notes de projet ici...',
    'tinymce_api_key' => 'tafsbhd5fjvwqy3rnsk522zww6k6isqsz7t81fv47b9hmjd7',
    'tinymce_cdn_url' => 'https://cdn.tiny.cloud/1/',
    'php_versions'
     => [
         '8.3' => '983',
         '8.2' => '982',
         '8.1' => '981',
         '8.0' => '980',
         '7.4' => '974',
         '7.3' => '973',
         '7.2' => '972',
         '7.1' => '971',
         '7.0' => '970',
     ],
    'default_php_version' => '8.3',
    'vhost_settings'
     => [
         'listen_address' => '*:80',
         'directory_permissions'
          => [
              'AllowOverride' => 'All',
              'Require' => 'local',
          ],
         'auto_create_folders' => true,
         'create_index_file' => true,
     ],
    'app_title' => 'Gestionnaire de Projets - WAMP',
    'app_icon' => '🖥️',
    'footer_message' => 'Keep working hard to get success!',
    'date_format' => 'd/m/Y H:i',
    'language' => 'fr',
    'timezone' => 'Europe/Paris',
    'tasks'
     => [
         'later'
          => [
              'label' => 'Plus tard',
              'color' => '#6c757d',
          ],
         'todo'
          => [
              'label' => 'A faire',
              'color' => '#ffc107',
          ],
         'pending'
          => [
              'label' => 'En attente',
              'color' => '#ffc107',
          ],
         'in_progress'
          => [
              'label' => 'En cours',
              'color' => '#007bff',
          ],
         'waiting_for_deployment'
          => [
              'label' => 'Attente de déploiement',
              'color' => '#ff9800',
          ],
         'on_hold'
          => [
              'label' => 'En pause',
              'color' => '#6c757d',
          ],
         'done'
          => [
              'label' => 'Terminé',
              'color' => '#28a745',
          ],
     ],
    'task_priorities'
     => [
         'none'
          => [
              'label' => 'Sans priorité',
              'color' => '#adb5bd',
          ],
         'low'
          => [
              'label' => 'Faible',
              'color' => '#17a2b8',
          ],
         'medium'
          => [
              'label' => 'Moyenne',
              'color' => '#ffc107',
          ],
         'high'
          => [
              'label' => 'Haute',
              'color' => '#fd7e14',
          ],
         'critical'
          => [
              'label' => 'Critique',
              'color' => '#dc3545',
          ],
     ],
    'default_task_status' => 'later',
    'default_task_priority' => 'none',
    'directory_permissions' => 493,
    'file_permissions' => 420,
    'debug_mode' => false,
    'enable_logging' => false,
    'log_file' => 'C:\\wamp64\\www\\projects_manager/logs/projects_manager.log',
    'enable_backups' => true,
    'backup_directory' => 'C:\\wamp64\\www\\projects_manager/backups',
    'max_backups' => 10,
    'allowed_extensions'
     => [
         0 => 'php',
         1 => 'html',
         2 => 'css',
         3 => 'js',
         4 => 'txt',
         5 => 'md',
     ],
    'max_project_name_length' => 50,
    'max_description_length' => 255,
    'project_name_pattern' => '/^[a-zA-Z0-9_-]+$/',
];
