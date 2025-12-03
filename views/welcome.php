<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bienvenue - <?= htmlspecialchars($config->get('app_title', 'Gestionnaire de Projets')) ?></title>
    <link rel="stylesheet" href="../assets/css/app.css">
    <style>
        .welcome-wrapper {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: var(--spacing-xl) var(--spacing-base);
        }
        
        .welcome-card {
            width: min(600px, 100%);
            background: var(--bg-card);
            border-radius: var(--radius-xl);
            padding: var(--spacing-xl);
            box-shadow: var(--shadow-xl);
            border: 1px solid var(--color-gray-200);
            animation: slideIn var(--transition-slow);
        }
        
        .welcome-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-primary-light) 100%);
            border-radius: var(--radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            margin: 0 auto var(--spacing-md);
            box-shadow: var(--shadow-primary);
        }
        
        h1 {
            margin-top: 0;
            margin-bottom: 0.5rem;
            color: var(--color-gray-900);
            font-size: 2.25rem;
            text-align: center;
        }
        
        p.subtitle {
            color: var(--color-gray-600);
            margin-bottom: var(--spacing-lg);
            font-size: 1.05rem;
            text-align: center;
            line-height: 1.6;
        }
        
        .btn-primary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            border-radius: var(--radius-base);
            border: none;
            padding: 1rem;
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--color-white);
            background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-primary-light) 100%);
            cursor: pointer;
            transition: all var(--transition-base);
            box-shadow: var(--shadow-sm);
            gap: 0.5rem;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-primary);
        }
        
        .message,
        .errors {
            padding: var(--spacing-md);
            border-radius: var(--radius-base);
            margin-bottom: var(--spacing-md);
            font-size: 0.95rem;
            border-left: 4px solid;
        }
        
        .message {
            background: var(--color-success-light);
            border-color: var(--color-success);
            color: #065f46;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .errors {
            background: var(--color-danger-light);
            border-color: var(--color-danger);
            color: #991b1b;
        }
        
        .errors li {
            margin-bottom: 0.35rem;
        }
        
        .welcome-footer {
            margin-top: var(--spacing-lg);
            padding-top: var(--spacing-md);
            border-top: 2px solid var(--color-gray-200);
            color: var(--color-gray-500);
            font-size: 0.9rem;
            text-align: center;
            line-height: 1.5;
        }
        
        .welcome-form {
            display: flex;
            flex-direction: column;
            gap: var(--spacing-md);
        }
        
        .welcome-form label {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--color-gray-800);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .welcome-form input {
            width: 100%;
            padding: 0.85rem;
            border-radius: var(--radius-base);
            border: 2px solid var(--color-gray-300);
            font-size: 1rem;
            background: var(--bg-card);
            color: var(--color-gray-800);
            transition: all var(--transition-base);
        }
        
        .welcome-form input:focus {
            outline: none;
            border-color: var(--color-primary);
            box-shadow: 0 0 0 3px var(--color-primary-ultra-light);
        }
        
        .form-hint {
            font-size: 0.85rem;
            color: var(--color-gray-600);
            margin: 0;
            line-height: 1.5;
            padding: var(--spacing-sm);
            background: var(--color-gray-50);
            border-radius: var(--radius-sm);
        }
        
        .success-icon {
            font-size: 3rem;
            display: block;
            text-align: center;
            margin-bottom: var(--spacing-base);
        }
    </style>
</head>
<body>
<div class="welcome-wrapper">
    <section class="welcome-card">
        <div class="welcome-icon">🚀</div>
        <h1>Bienvenue !</h1>
        <p class="subtitle">Avant d'explorer vos projets, initialisons la base de données MySQL pour stocker vos informations.</p>

        <?php if (!empty($message)): ?>
            <div class="message">
                <span class="success-icon">✅</span>
                <div>
                    <strong>Succès !</strong><br>
                    <?= htmlspecialchars($message) ?>
                </div>
            </div>
            <a class="btn-primary" href="projects.php">🚀 Accéder à l'Application</a>
        <?php else: ?>
            <?php if (!empty($errors)): ?>
                <div class="errors">
                    <strong>⚠️ Erreurs détectées :</strong>
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="post" class="welcome-form">
                <input type="hidden" name="action" value="create_database">

                <label for="db_host">🖥️ Hôte MySQL</label>
                <input id="db_host" name="db_host" type="text" value="<?= htmlspecialchars($dbDefaults['host'] ?? 'localhost') ?>" required>

                <label for="db_port">🔌 Port (optionnel)</label>
                <input id="db_port" name="db_port" type="text" value="<?= htmlspecialchars($dbDefaults['port'] ?? '') ?>" placeholder="3306 (par défaut)">

                <label for="db_username">👤 Utilisateur</label>
                <input id="db_username" name="db_username" type="text" value="<?= htmlspecialchars($dbDefaults['username'] ?? 'root') ?>" required>

                <label for="db_password">🔒 Mot de passe</label>
                <input id="db_password" name="db_password" type="password" value="<?= htmlspecialchars($dbDefaults['password'] ?? '') ?>" placeholder="(Laisser vide si aucun)">

                <label for="db_schema">🗄️ Nom du schéma</label>
                <input id="db_schema" name="db_schema" type="text" value="<?= htmlspecialchars($dbDefaults['schema'] ?? 'projects_manager') ?>" required>

                <p class="form-hint">
                    📝 Ces informations seront sauvegardées dans <code class="code-inline">config.php</code> et permettront de créer automatiquement les tables nécessaires.
                </p>

                <button type="submit" class="btn-primary">
                    🎯 Initialiser la Base de Données
                </button>
            </form>
        <?php endif; ?>

        <p class="welcome-footer">
            Cette opération crée les tables nécessaires et prépare l'application pour la gestion de vos projets locaux.
        </p>
    </section>
</div>
</body>
</html>
