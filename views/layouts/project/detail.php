<?php
$assetsBasePath = $assetsBasePath ?? '../assets';
$language = $language ?? 'fr';
$app_title = $app_title ?? 'Gestionnaire de Projets - WAMP';
$app_icon = $app_icon ?? '🖥️';
$footer_message = $footer_message ?? '';
$projectStatuses = $projectStatuses ?? [];
$projectName = $project['name'] ?? '';
$projectDescription = $project['description'] ?? '';
$projectTags = $project['tags'] ?? [];
$projectVhosts = $project['vhosts'] ?? [];
$projectTasks = $projectTasks ?? ($project['tasks'] ?? []);
$task_priorities = $task_priorities ?? [];
$taskPriorityMap = $task_priorities;
$defaultTaskPriority = $defaultTaskPriority ?? 'none';
$grouped_tasks = $grouped_tasks ?? [];
$ordered_statuses = $ordered_statuses ?? [];
$task_counts = $task_counts ?? ['pending' => 0, 'done' => 0, 'total' => 0];
$php_versions = $php_versions ?? [];
$default_php_version = $default_php_version ?? '8.3';
$editor_type = $editor_type ?? 'textarea';
$editor_placeholder = $editor_placeholder ?? '';
$date_format = $date_format ?? 'd/m/Y H:i';
$phpmyadmin_url = $phpmyadmin_url ?? '/phpmyadmin/';
$baseProjectsPath = $baseProjectsPath ?? '.';
$tinymce_script = $tinymce_script ?? '';
$default_task_status = $default_task_status ?? 'pending';
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($language) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($projectName ?: 'Projet') ?> - <?= htmlspecialchars($app_title) ?></title>
    <link rel="stylesheet" href="<?= htmlspecialchars($assetsBasePath) ?>/css/app.css">
    <?php if ($editor_type === 'tinymce' && $tinymce_script !== ''): ?>
        <script src="<?= htmlspecialchars($tinymce_script) ?>" referrerpolicy="origin"></script>
    <?php endif; ?>
</head>
<body>
    <div id="projectMetaData" class="hidden"
         data-project-name="<?= htmlspecialchars($projectName) ?>"
         data-project-slug="<?= htmlspecialchars($projectSlug) ?>"
         data-project-description="<?= htmlspecialchars($projectDescription) ?>"
         data-project-tags="<?= htmlspecialchars(implode(', ', $projectTags)) ?>"
         data-base-projects-path="<?= htmlspecialchars($baseProjectsPath) ?>"
         data-php-versions="<?= htmlspecialchars(json_encode($php_versions, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP)) ?>"
         data-editor-type="<?= htmlspecialchars($editor_type) ?>"
         data-editor-placeholder="<?= htmlspecialchars($editor_placeholder) ?>">
    </div>

    <!-- Modern Header -->
    <header class="page-header">
        <div>
            <h1 style="margin-bottom: 0.25rem; display: flex; align-items: center; gap: 0.5rem;">
                <?= htmlspecialchars($app_icon) ?> <?= htmlspecialchars($projectName) ?>
            </h1>
            <p class="muted-text">Gérez les détails de votre projet</p>
        </div>
        <nav class="flex-row gap-1 align-center">
            <a href="projects.php" class="btn btn-link back-btn">← Retour aux Projets</a>
            <a href="<?= htmlspecialchars($phpmyadmin_url) ?>" target="_blank">📂 phpMyAdmin</a>
            <a href="configuration.php">⚙️ Paramètres</a>
        </nav>
    </header>

    <main class="project-detail" role="main">
        <!-- Project Info Card -->
        <section class="project-meta-section">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; flex-wrap: wrap;">
                <div style="flex: 1;">
                    <h2 style="margin-bottom: 0.5rem; color: var(--color-primary); font-size: 1.5rem;">📋 Informations du Projet</h2>
                    <p style="margin: 0.5rem 0; color: var(--color-gray-700); line-height: 1.6;"><?= htmlspecialchars($projectDescription) ?></p>
                    <?php if (!empty($projectTags)): ?>
                        <div class="tags" style="margin-top: 1rem;">
                            <?php foreach ($projectTags as $tag): ?>
                                <span class="tag"><?= htmlspecialchars($tag) ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="actions mt-1" style="padding-top: 1rem; border-top: 2px solid var(--color-gray-200);">
                <button onclick="openProjectEditModal()" class="btn btn-edit">✏️ Modifier</button>
                <button type="button" onclick="openExportModal()" class="btn btn-add">⬇️ Exporter</button>
                <button onclick="confirmDeleteProject()" class="btn btn-delete">🗑️ Supprimer</button>
            </div>
        </section>

        <!-- Virtual Hosts Section -->
        <section class="section vhosts-section">
            <div class="section-header">
                <h2>🌐 Virtual Hosts</h2>
                <button onclick="openVhostModal()" class="btn btn-add">➕ Ajouter VHost</button>
            </div>
            <div class="section-content">
                <?php if (empty($projectVhosts)): ?>
                    <div class="empty-state">
                        <p style="font-size: 1.1rem; margin-bottom: 1rem;">🌐 Aucun virtual host configuré</p>
                        <p style="color: var(--color-gray-500);">Créez un virtual host pour accéder à votre projet via un nom de domaine local</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($projectVhosts as $vhost): ?>
                        <div class="vhost-item">
                            <div class="vhost-header">
                                <div style="flex: 1;">
                                    <div class="vhost-name" style="display: flex; align-items: center; gap: 0.5rem;">
                                        🏷️ <?= htmlspecialchars($vhost['display_name'] ?: $vhost['servername']) ?>
                                    </div>
                                    <?php $actualServerName = $vhost['actual_servername'] ?? ($vhost['servername'] ?? ''); ?>
                                    <?php if ($actualServerName !== ''): ?>
                                        <a href="http://<?= htmlspecialchars($actualServerName) ?>" target="_blank" class="vhost-url">
                                            🔗 http://<?= htmlspecialchars($actualServerName) ?>
                                        </a>
                                    <?php endif; ?>
                                </div>
                                <div class="actions">
                                    <button onclick="editVhost('<?= htmlspecialchars($vhost['display_name'] ?? '') ?>', '<?= htmlspecialchars($vhost['servername'] ?? '') ?>', '<?= htmlspecialchars($vhost['documentroot'] ?? '') ?>', '<?= htmlspecialchars($vhost['description'] ?? '') ?>', '<?= htmlspecialchars($vhost['php_version'] ?? $default_php_version) ?>', '<?= htmlspecialchars($vhost['database_name'] ?? '') ?>')" class="btn btn-edit">✏️ Modifier</button>
                                    <button onclick="projectManager.deleteVhost('<?= htmlspecialchars($vhost['servername'] ?? '') ?>', '<?= htmlspecialchars($vhost['documentroot'] ?? '') ?>', '<?= htmlspecialchars($vhost['database_name'] ?? '') ?>')" class="btn btn-delete">🗑️</button>
                                </div>
                            </div>
                            <?php if (!empty($vhost['description'])): ?>
                                <div class="vhost-description"><?= htmlspecialchars($vhost['description']) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($vhost['documentroot'])): ?>
                                <div class="vhost-path" style="margin-top: 0.75rem;">📁 <?= htmlspecialchars($vhost['documentroot']) ?></div>
                            <?php endif; ?>
                            <div class="vhost-meta mt-05 flex-row gap-05 wrap" style="margin-top: 0.75rem;">
                                <span class="badge badge-info">
                                    🐘 PHP <?= htmlspecialchars($vhost['php_version'] ?? $default_php_version) ?>
                                </span>
                                <?php if (!empty($vhost['database_name'])): ?>
                                    <span class="badge badge-success">
                                        🗄️ DB: <?= htmlspecialchars($vhost['database_name']) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>

        <div class="two-column-layout">
            <!-- Tasks Section -->
            <div class="tasks-column">
                <div class="section">
                    <div class="section-header">
                        <h2>📋 Tâches <span style="font-size: 0.9rem; opacity: 0.9;">(<?= (int)($task_counts['total'] ?? 0) ?>)</span></h2>
                        <button onclick="openTaskModal()" class="btn btn-add">➕ Ajouter Tâche</button>
                    </div>
                    <div class="section-content">
                        <?php if (empty($projectTasks)): ?>
                            <div class="empty-state">
                                <p style="font-size: 1.1rem; margin-bottom: 1rem;">📝 Aucune tâche définie</p>
                                <p style="color: var(--color-gray-500);">Ajoutez des tâches pour suivre l'avancement de votre projet</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($ordered_statuses as $status): ?>
                                <?php
                                    $tasksForStatus = $grouped_tasks[$status] ?? [];
                                    if (empty($tasksForStatus)) {
                                        continue;
                                    }
                                    if ($status === '_unknown') {
                                        $statusLabel = 'Autres';
                                        $statusColor = '#888888';
                                    } else {
                                        $meta = $projectStatuses[$status] ?? ['label' => ucfirst($status), 'color' => '#cccccc'];
                                        $statusLabel = $meta['label'] ?? ucfirst($status);
                                        $statusColor = $meta['color'] ?? '#cccccc';
                                    }
                                ?>
                                <div class="task-group collapsed" data-status="<?= htmlspecialchars($status) ?>">
                                    <div class="task-group-header" aria-controls="tasks-for-<?= htmlspecialchars($status) ?>">
                                        <button type="button" class="task-group-toggle" aria-expanded="false">▸</button>
                                        <span class="status-pill" style="background: <?= htmlspecialchars($statusColor) ?>;"></span>
                                        <strong><?= htmlspecialchars($statusLabel) ?> (<?= count($tasksForStatus) ?>)</strong>
                                    </div>
                                    <div id="tasks-for-<?= htmlspecialchars($status) ?>">
                                        <?php foreach ($tasksForStatus as $task): ?>
                                            <?php $taskPriorityKey = $task['priority'] ?? 'none'; ?>
                                            <?php $priorityMeta = $taskPriorityMap[$taskPriorityKey] ?? null; ?>
                                            <div class="task-item">
                                                <div class="task-header">
                                                    <div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                                                        <div class="task-title"><?= htmlspecialchars($task['title'] ?? 'Sans titre') ?></div>
                                                        <?php if (!empty($task['description'])): ?>
                                                            <button type="button" class="task-description-toggle" onclick="toggleTaskDescription(this)" title="Afficher/masquer la description">
                                                                <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                                                                    <path d="M8 10.5l-4-4h8l-4 4z"/>
                                                                </svg>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                    <?php if (!empty($task['description'])): ?>
                                                        <div class="task-description-content hidden">
                                                            <div class="task-description-text"><?= $task['description'] ?></div>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div class="task-meta-row">
                                                        <div class="task-info">
                                                            <?php
                                                                $meta = $projectStatuses[$task['status'] ?? ''] ?? ['label' => ucfirst($task['status'] ?? ''), 'color' => '#cccccc'];
                                                                $statusColor = $meta['color'] ?? '#cccccc';
                                                                $rgb = [
                                                                    hexdec(substr($statusColor, 1, 2)),
                                                                    hexdec(substr($statusColor, 3, 2)),
                                                                    hexdec(substr($statusColor, 5, 2))
                                                                ];
                                                                $brightness = ($rgb[0] * 299 + $rgb[1] * 587 + $rgb[2] * 114) / 1000;
                                                                $textColor = $brightness > 186 ? '#000' : '#fff';
                                                            ?>
                                                            <span class="task-status" style="background: <?= htmlspecialchars($statusColor) ?>; color: <?= htmlspecialchars($textColor) ?>;">
                                                                <?= htmlspecialchars($meta['label'] ?? ($task['status'] ?? '')) ?>
                                                            </span>
                                                            <?php if ($priorityMeta): ?>
                                                                <span class="task-priority-badge" style="background: <?= htmlspecialchars($priorityMeta['color'] ?? '#adb5bd') ?>;">
                                                                    <?= htmlspecialchars($priorityMeta['label']) ?>
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="actions">
                                                            <button 
                                                                class="btn btn-edit"
                                                                data-task-id="<?= htmlspecialchars($task['id'] ?? '') ?>"
                                                                data-task-title="<?= htmlspecialchars($task['title'] ?? '') ?>"
                                                                data-task-status="<?= htmlspecialchars($task['status'] ?? '') ?>"
                                                                data-task-due-date="<?= htmlspecialchars($task['due_date'] ?? '') ?>"
                                                                data-task-priority="<?= htmlspecialchars($task['priority'] ?? 'none') ?>"
                                                                data-task-description="<?= htmlspecialchars($task['description'] ?? '') ?>"
                                                                onclick="editTaskFromButton(this)">✏️</button>
                                                            <button 
                                                                class="btn btn-delete"
                                                                data-task-id="<?= htmlspecialchars($task['id'] ?? '') ?>"
                                                                onclick="deleteTaskFromButton(this)">🗑️</button>
                                                        </div>
                                                    </div>
                                                </div>
                                                <?php if (!empty($task['due_date'])): ?>
                                                    <div class="task-due">Échéance: <?= htmlspecialchars(date($date_format, strtotime($task['due_date']))) ?></div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <!-- Notes Section -->
            <div class="notes-column">
                <div class="notes-section">
                    <div class="section-header">
                        <h2>📝 Notes du Projet</h2>
                        <div class="header-hint" style="font-size: 0.85rem; opacity: 0.95;">
                            <?= (int)($task_counts['pending'] ?? 0) ?> en attente • <?= (int)($task_counts['done'] ?? 0) ?> terminées
                        </div>
                    </div>
                    <div class="notes-content">
                        <div id="notesPreview" class="notes-preview" aria-live="polite" style="min-height: 200px;">
                            <?php if ($editor_type === 'tinymce'): ?>
                                <?= $project['notes'] ?? '<p style="color: var(--color-gray-400); font-style: italic;">Aucune note pour le moment...</p>' ?>
                            <?php else: ?>
                                <?= nl2br(htmlspecialchars($project['notes'] ?? '')) ?: '<p style="color: var(--color-gray-400); font-style: italic;">Aucune note pour le moment...</p>' ?>
                            <?php endif; ?>
                        </div>
                        <textarea id="projectNotes" class="hidden" placeholder="<?= htmlspecialchars($editor_placeholder) ?>"><?php
                            if ($editor_type === 'tinymce') {
                                echo $project['notes'] ?? '';
                            } else {
                                echo htmlspecialchars($project['notes'] ?? '');
                            }
                        ?></textarea>
                        <div class="notes-actions">
                            <button id="editNotesBtn" class="btn btn-edit" type="button">✏️ Modifier</button>
                            <button id="cancelNotesBtn" class="btn btn-cancel hidden" type="button">Annuler</button>
                            <button id="saveNotesBtn" onclick="saveNotes()" class="btn btn-add hidden">💾 Sauvegarder</button>
                            <div id="notesStatus" class="notes-status"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <div id="vhostModal" class="modal">
        <div class="modal-content">
            <span class="close" id="closeVhostModalBtn">&times;</span>
            <h2 id="vhostModalTitle">Ajouter un Virtual Host</h2>
            <form id="vhostForm">
                <div class="form-group">
                    <label>🏷️ Nom du Virtual Host</label>
                    <input type="text" name="display_name" id="display_name" placeholder="Ex: Site Principal, API Backend..." required>
                    <small class="text-muted">Nom d'affichage pour identifier facilement ce vhost</small>
                </div>
                <div class="form-group">
                    <label>🌐 Hostname (nom de domaine)</label>
                    <input type="text" name="servername" id="servername" placeholder="Ex: monsite.local" required>
                    <small class="text-muted">Nom d'hôte pour accéder au site (sera ajouté au fichier hosts)</small>
                </div>
                <div class="form-group">
                    <label>📁 DocumentRoot</label>
                    <input type="text" name="documentroot" id="documentroot" required>
                    <small class="text-muted">Chemin vers le dossier racine du site</small>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <input type="text" name="description" id="description">
                </div>
                <div class="form-group">
                    <label>Version PHP</label>
                    <select name="php_version" id="php_version">
                        <?php foreach ($php_versions as $version => $port): ?>
                            <option value="<?= htmlspecialchars($version) ?>" <?= $version === $default_php_version ? 'selected' : '' ?>>
                                PHP <?= htmlspecialchars($version) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="create_database" id="create_database" checked>
                        Créer une base de données automatiquement
                    </label>
                    <div id="database_name_group" class="mt-05">
                        <label>Nom de la base de données</label>
                        <input type="text" name="database_name" id="database_name" placeholder="Sera généré automatiquement basé sur le ServerName">
                    </div>
                </div>
                <input type="hidden" name="action" value="save_vhost">
                <input type="hidden" name="edit_mode" id="edit_mode" value="0">
                <input type="hidden" name="original_servername" id="original_servername" value="">
                <button type="submit" class="btn btn-add">Enregistrer</button>
                <button type="button" id="cancelVhostBtn" class="btn btn-cancel">Annuler</button>
            </form>
            <div id="vhostModalMsg" class="text-danger mt-1"></div>
        </div>
    </div>

    <div id="taskModal" class="modal">
        <div class="modal-content">
            <span class="close" id="closeTaskModalBtn">&times;</span>
            <h2 id="taskModalTitle">Ajouter une Tâche</h2>
            <form id="taskForm">
                <div class="form-group">
                    <label>Titre de la tâche</label>
                    <input type="text" name="task_title" id="task_title" required>
                </div>
                    <div class="form-group">
                    <label>Statut</label>
                    <select name="task_status" id="task_status">
                        <?php if (empty($projectStatuses)): ?>
                            <option value="<?= htmlspecialchars($default_task_status) ?>" selected><?= htmlspecialchars(ucfirst($default_task_status)) ?></option>
                        <?php else: ?>
                            <?php foreach ($projectStatuses as $statusKey => $meta): ?>
                                <option value="<?= htmlspecialchars($statusKey) ?>" <?= $statusKey === $default_task_status ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($meta['label'] ?? ucfirst($statusKey)) ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
                    <div class="form-group">
                        <label>Priorité</label>
                        <select name="task_priority" id="task_priority" data-default-value="<?= htmlspecialchars($defaultTaskPriority) ?>">
                            <?php if (empty($task_priorities)): ?>
                                <option value="none">Sans priorité</option>
                            <?php else: ?>
                                <?php foreach ($task_priorities as $priorityKey => $priorityMeta): ?>
                                    <option value="<?= htmlspecialchars($priorityKey) ?>" <?= $priorityKey === $defaultTaskPriority ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($priorityMeta['label'] ?? ucfirst($priorityKey)) ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                <div class="form-group">
                    <label>Date d'échéance</label>
                    <input type="date" name="task_due_date" id="task_due_date">
                </div>
                <div class="form-group">
                    <label for="task_description">Description (optionnelle):</label>
                    <textarea id="task_description" name="task_description" rows="3" placeholder="Description détaillée de la tâche..."></textarea>
                </div>
                <input type="hidden" name="action" value="save_task">
                <input type="hidden" name="task_id" id="task_id" value="">
                <button type="submit" class="btn btn-add">Enregistrer</button>
                <button type="button" id="cancelTaskBtn" class="btn btn-cancel">Annuler</button>
            </form>
            <div id="taskModalMsg" class="text-danger mt-1"></div>
        </div>
    </div>

    <div id="projectEditModal" class="modal">
        <div class="modal-content">
            <span class="close" id="closeProjectEditModalBtn">&times;</span>
            <h2 id="projectEditModalTitle">Modifier le Projet</h2>
            <form id="projectEditForm">
                <div class="form-group">
                    <label>Nom du projet</label>
                    <input type="text" name="new_project_name" id="new_project_name" required>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <input type="text" name="new_project_description" id="new_project_description">
                </div>
                <div class="form-group">
                    <label>Tags (séparés par des virgules)</label>
                    <input type="text" name="new_project_tags" id="new_project_tags">
                </div>
                <div class="form-group">
                    <label class="flex-row" style="gap: 0.5rem; align-items: center;">
                        <input type="checkbox" name="rename_folder" id="rename_folder" value="1">
                        <span>📁 Renommer le dossier du projet pour qu'il corresponde au nouveau nom</span>
                    </label>
                    <small class="text-muted">Si coché, le dossier sera renommé avec le slug du nouveau nom (ex: "mon-projet-2025")</small>
                </div>
                <input type="hidden" name="action" value="edit_project">
                <button type="submit" class="btn btn-add">Enregistrer</button>
                <button type="button" id="cancelProjectEditBtn" class="btn btn-cancel">Annuler</button>
            </form>
            <div id="projectEditModalMsg" class="text-danger mt-1"></div>
        </div>
    </div>

    <div id="projectExportModal" class="modal">
        <div class="modal-content">
            <span class="close" id="closeProjectExportModalBtn">&times;</span>
            <h2>📦 Exporter le Projet</h2>
            <p class="muted-text">Créez une archive ZIP contenant les fichiers du projet, la configuration des Virtual Hosts et les bases de données associées.</p>
            <form id="projectExportForm">
                <div class="form-group">
                    <label>
                        <input id="exportIncludeFiles" type="checkbox" name="include_files" value="1" checked>
                        Inclure les fichiers du projet
                    </label>
                </div>
                <div class="form-group">
                    <label>
                        <input id="exportIncludeVhosts" type="checkbox" name="include_vhosts" value="1" checked>
                        Inclure la configuration des Virtual Hosts
                    </label>
                </div>
                <div class="form-group">
                    <label>
                        <input id="exportIncludeDatabases" type="checkbox" name="include_databases" value="1" checked>
                        Inclure les bases de données (mysqldump requis)
                    </label>
                </div>
                <div class="flex-row justify-end" style="gap:0.5rem;">
                    <button type="button" id="cancelProjectExportBtn" class="btn btn-cancel">Annuler</button>
                    <button type="submit" class="btn btn-add">Générer et télécharger</button>
                </div>
            </form>
            <div id="projectExportStatus" class="text-muted mt-2"></div>
        </div>
    </div>

    <div id="projectDeleteModal" class="modal">
        <div class="modal-content">
            <span class="close" id="closeProjectDeleteModalBtn">&times;</span>
            <h2 class="text-danger">🗑️ Supprimer le Projet</h2>
            <div class="my-15">
                <p><strong>Attention !</strong> Vous êtes sur le point de supprimer le projet <strong>"<?= htmlspecialchars($projectName) ?>"</strong>.</p>
                <p>Cette action supprimera également les Virtual Hosts, tâches et notes associées.</p>
                <div class="alert-warning">
                    <h4 class="mt-0 mb-05 text-warning-detail">📁 Que faire du dossier du projet ?</h4>
                    <p class="mt-0 text-warning-detail">Le dossier <code><?= htmlspecialchars(rtrim($baseProjectsPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $projectName) ?></code> contient peut-être des fichiers importants.</p>
                    <div class="flex-col gap-05">
                        <label class="flex-row">
                            <input type="radio" name="delete_folder_option" value="no" checked>
                            <span><strong>Conserver le dossier</strong> - Seul le projet sera supprimé de la base de données</span>
                        </label>
                        <label class="flex-row">
                            <input type="radio" name="delete_folder_option" value="yes">
                            <span class="text-danger"><strong>Supprimer le dossier et tout son contenu</strong> - ⚠️ Action irréversible !</span>
                        </label>
                    </div>
                </div>
            </div>
            <div class="flex-row justify-end">
                <button type="button" id="cancelProjectDeleteBtn" class="btn btn-cancel">Annuler</button>
                <button type="button" onclick="executeProjectDeletion()" class="btn btn-delete">🗑️ Confirmer la suppression</button>
            </div>
            <div id="projectDeleteModalMsg" class="text-danger mt-1"></div>
        </div>
    </div>

    <div id="vhostDeleteModal" class="modal">
        <div class="modal-content">
            <span class="close" id="closeVhostDeleteModalBtn">&times;</span>
            <h2 class="text-danger">🗑️ Supprimer le Virtual Host</h2>
            <div class="my-15">
                <p>Vous êtes sur le point de supprimer le Virtual Host <strong id="vhostDeleteName"></strong>.</p>
                <p>Que souhaitez-vous faire avec les ressources associées ?</p>
                <div class="alert-warning">
                    <h4 class="mt-0 mb-05 text-warning-detail">📁 Dossier du Virtual Host</h4>
                    <div class="flex-col gap-05 mt-05">
                        <label class="flex-row">
                            <input type="checkbox" id="deleteVhostFolder" name="delete_vhost_folder">
                            <span>Supprimer le dossier <code id="vhostFolderPath"></code></span>
                        </label>
                    </div>
                </div>
                <div class="alert-danger" id="vhostDatabaseSection">
                    <h4 class="mt-0 mb-05 text-db-danger">🗄️ Base de données associée</h4>
                    <div class="flex-col gap-05 mt-05">
                        <label class="flex-row">
                            <input type="checkbox" id="deleteVhostDatabase" name="delete_vhost_database">
                            <span>Supprimer la base de données <code id="vhostDatabaseName"></code></span>
                        </label>
                    </div>
                </div>
                <input type="hidden" id="vhostDeleteProjectName" name="project_name" value="<?= htmlspecialchars($projectName) ?>">
                <input type="hidden" id="vhostDeleteServerName" name="servername" value="">
            </div>
            <div class="flex-row justify-end">
                <button type="button" id="cancelVhostDeleteBtn" class="btn btn-cancel">Annuler</button>
                <button type="button" onclick="executeVhostDeletion()" class="btn btn-delete">🗑️ Confirmer la suppression</button>
            </div>
            <div id="vhostDeleteModalMsg" class="text-danger mt-1"></div>
        </div>
    </div>

    <footer>
        <?= htmlspecialchars($footer_message) ?>
    </footer>

    <script src="<?= htmlspecialchars($assetsBasePath) ?>/js/project.js"></script>
</body>
</html>
