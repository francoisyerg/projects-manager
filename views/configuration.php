<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuration - <?= htmlspecialchars($config->get('app_title', 'Gestionnaire de Projets')) ?></title>
    <link rel="stylesheet" href="../assets/css/app.css?v=<?= time() ?>">
    <style>
        .config-page {
            max-width: 900px;
            margin: 0 auto;
            padding: var(--spacing-lg);
        }
        
        .config-card {
            background: var(--bg-card);
            border-radius: var(--radius-xl);
            padding: var(--spacing-xl);
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--color-gray-200);
        }
        
        .config-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: var(--spacing-base);
            margin-bottom: var(--spacing-lg);
            flex-wrap: wrap;
            padding-bottom: var(--spacing-md);
            border-bottom: 3px solid var(--color-primary);
        }
        
        .config-head h1 {
            margin: 0;
            color: var(--color-gray-900);
            font-size: 2rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .helper {
            font-size: 0.95rem;
            color: var(--color-gray-600);
            margin-top: 0.5rem;
            line-height: 1.5;
        }
        
        .config-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: var(--spacing-md);
        }
        
        .full-span {
            grid-column: 1 / -1;
        }
        
        .message {
            background: var(--color-success-light);
            border-color: var(--color-success);
            color: #065f46;
            border-radius: var(--radius-base);
            padding: var(--spacing-md);
            margin-bottom: var(--spacing-lg);
            font-size: 0.95rem;
            border-left: 4px solid var(--color-success);
        }
        
        .errors {
            background: var(--color-danger-light);
            border-color: var(--color-danger);
            color: #991b1b;
            border-radius: var(--radius-base);
            padding: var(--spacing-md);
            margin-bottom: var(--spacing-lg);
            font-size: 0.95rem;
            border-left: 4px solid var(--color-danger);
        }
        
        .errors ul {
            margin: 0;
            padding-left: 1.25rem;
        }
        
        .actions {
            margin-top: var(--spacing-lg);
            padding-top: var(--spacing-lg);
            border-top: 2px solid var(--color-gray-200);
            display: flex;
            justify-content: flex-end;
            gap: var(--spacing-sm);
        }
    </style>
</head>
<body>
    <div class="config-page">
        <div class="config-card">
            <div class="config-head">
                <div style="flex: 1;">
                    <h1>⚙️ Configuration</h1>
                    <p class="helper">Modifiez les réglages principaux de l'application. Vos modifications sont sauvegardées dans <code class="code-inline">config.php</code>.</p>
                </div>
                <a class="btn btn-link back-btn" href="projects.php">← Retour</a>
            </div>

        <?php if (!empty($message)): ?>
            <div class="message">✅ <?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <?php if (!empty($errors)): ?>
            <div class="errors">
                <strong>❌ Erreurs détectées :</strong>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" class="config-form">
            <div class="form-group">
                <label for="db_host">🖥️ Hôte MySQL</label>
                <input id="db_host" name="db_host" type="text" value="<?= htmlspecialchars($formValues['db_host'] ?? 'localhost') ?>" required>
            </div>
            <div class="form-group">
                <label for="db_port">🔌 Port</label>
                <input id="db_port" name="db_port" type="text" value="<?= htmlspecialchars($formValues['db_port'] ?? '') ?>" placeholder="3306 (par défaut)">
            </div>
            <div class="form-group">
                <label for="db_username">👤 Utilisateur</label>
                <input id="db_username" name="db_username" type="text" value="<?= htmlspecialchars($formValues['db_username'] ?? 'root') ?>" required>
            </div>
            <div class="form-group">
                <label for="db_password">🔒 Mot de passe</label>
                <input id="db_password" name="db_password" type="password" value="<?= htmlspecialchars($formValues['db_password'] ?? '') ?>" placeholder="(Laisser vide si aucun)">
            </div>
            <div class="form-group">
                <label for="db_schema">🗄️ Nom du schéma</label>
                <input id="db_schema" name="db_schema" type="text" value="<?= htmlspecialchars($formValues['db_schema'] ?? 'projects_manager') ?>" required>
            </div>
            <div class="form-group">
                <label for="base_projects_path">📁 Dossier des projets</label>
                <input id="base_projects_path" name="base_projects_path" type="text" value="<?= htmlspecialchars($formValues['base_projects_path'] ?? '') ?>" placeholder="C:\wamp64\www">
            </div>
            <div class="form-group">
                <label for="vhosts_file">📄 Fichier VHosts</label>
                <input id="vhosts_file" name="vhosts_file" type="text" value="<?= htmlspecialchars($formValues['vhosts_file'] ?? '') ?>" placeholder="C:\wamp64\bin\apache\apache2.4.x\conf\extra\httpd-vhosts.conf">
            </div>
            <div class="form-group">
                <label for="hosts_file">🌐 Fichier hosts système</label>
                <input id="hosts_file" name="hosts_file" type="text" value="<?= htmlspecialchars($formValues['hosts_file'] ?? '') ?>" placeholder="C:\Windows\System32\drivers\etc\hosts">
            </div>
            <div class="form-group">
                <label for="app_title">🏷️ Titre de l'application</label>
                <input id="app_title" name="app_title" type="text" value="<?= htmlspecialchars($formValues['app_title'] ?? 'Gestionnaire de Projets - WAMP') ?>">
            </div>
            <div class="form-group">
                <label for="default_php_version">🐘 Version PHP par défaut</label>
                <select id="default_php_version" name="default_php_version">
                    <?php foreach ($formValues['php_versions'] as $version => $port): ?>
                        <option value="<?= htmlspecialchars($version) ?>" <?= $version === ($formValues['default_php_version'] ?? '') ? 'selected' : '' ?>>
                            PHP <?= htmlspecialchars($version) ?> (port <?= htmlspecialchars($port) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group full-span">
                <label for="php_versions">⚙️ Versions PHP disponibles <span style="font-weight: 400; color: var(--color-gray-600);">(version=port par ligne)</span></label>
                <textarea id="php_versions" name="php_versions"><?= htmlspecialchars(implode("\n", array_map(static function ($version, $port) {
                        return sprintf('%s=%s', $version, $port);
                    }, array_keys($formValues['php_versions'] ?? []), array_values($formValues['php_versions'] ?? [])))) ?></textarea>
            </div>
            <div class="full-span actions">
                <button type="submit" class="btn btn-add">💾 Enregistrer la Configuration</button>
            </div>
        </form>
    </div>
    </div>
</body>
</html>
