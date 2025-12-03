<?php
$assetsBasePath = $assetsBasePath ?? '../assets';
$language = $language ?? 'fr';
$app_title = $app_title ?? 'Gestionnaire de projets';
$app_icon = $app_icon ?? '🖥️';
$phpmyadmin_url = $phpmyadmin_url ?? '/phpmyadmin/';
$footer_message = $footer_message ?? '';
$projectStatuses = $projectStatuses ?? [];
$baseProjectsPath = $baseProjectsPath ?? '.';
$unique_tags = $unique_tags ?? [];
$projectsJson = json_encode(array_values($projects ?? []), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($language) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($app_title) ?></title>
    <link rel="stylesheet" href="<?= htmlspecialchars($assetsBasePath) ?>/css/app.css">
</head>
<body>
    <!-- Modern Header -->
    <header class="page-header" style="margin-bottom: 1.5rem;">
        <div>
            <h1 style="margin-bottom: 0.25rem; display: flex; align-items: center; gap: 0.5rem;">
                <?= htmlspecialchars($app_icon) ?> <?= htmlspecialchars($app_title) ?>
            </h1>
            <p class="muted-text">Gérez tous vos projets locaux en un seul endroit</p>
        </div>
        <nav class="flex-row gap-1 align-center">
            <a href="<?= htmlspecialchars($phpmyadmin_url) ?>" target="_blank">📂 phpMyAdmin</a>
            <a href="configuration.php">⚙️ Paramètres</a>
            <button type="button" id="openImportBtn" class="btn btn-add">⤵️ Importer</button>
            <button type="button" id="openModalBtn" class="btn btn-add">➕ Créer un Projet</button>
        </nav>
    </header>

    <!-- Project Modal Window -->
    <div id="projectModal" class="modal">
      <div class="modal-content">
        <span class="close" id="closeModalBtn">&times;</span>
        <h2 id="modalTitle">Créer un Nouveau Projet</h2>
        <form id="projectForm">
          <div class="form-group">
            <label>Nom du projet</label>
            <input type="text" name="project_name" id="project_name" required>
          </div>
          <div class="form-group">
            <label>Description</label>
            <input type="text" name="project_description" id="project_description">
          </div>
          <div class="form-group">
            <label>Tags (séparés par des virgules)</label>
            <input type="text" name="project_tags" id="project_tags">
          </div>
          <input type="hidden" name="action" value="create_project">
          <button type="submit" class="btn btn-add">Créer le Projet</button>
          <button type="button" id="cancelBtn" class="btn btn-cancel">Annuler</button>
        </form>
        <div id="modalMsg" class="text-danger mt-1"></div>
      </div>
    </div>

        <div id="projectImportModal" class="modal">
            <div class="modal-content">
                <span class="close" id="closeProjectImportModalBtn">&times;</span>
                <h2>Importer un Projet</h2>
                <p class="muted-text">Choisissez une archive ZIP créée depuis l'export de ce gestionnaire.</p>
                <form id="projectImportForm" enctype="multipart/form-data">
                    <div class="form-group">
                        <label>Archive ZIP</label>
                        <input type="file" name="project_zip" id="projectZip" accept=".zip" required>
                    </div>
                    <div class="form-group">
                        <label>Nom du projet (optionnel)</label>
                        <input type="text" name="override_project_name" id="overrideProjectName" placeholder="Laisser vide pour reprendre l'ancien nom">
                    </div>
                    <div class="flex-row justify-end" style="gap:0.5rem;">
                        <button type="button" id="cancelProjectImportBtn" class="btn btn-cancel">Annuler</button>
                        <button type="submit" class="btn btn-add">Importer</button>
                    </div>
                </form>
                <div id="projectImportStatus" class="text-muted mt-2"></div>
            </div>
        </div>

    <!-- Project Delete Confirmation Modal -->
    <div id="projectDeleteModal" class="modal">
      <div class="modal-content">
        <span class="close" id="closeProjectDeleteModalBtn">&times;</span>
        <h2 class="text-danger">🗑️ Supprimer le Projet</h2>
        <div class="my-15">
            <p>Vous êtes sur le point de supprimer le projet <strong id="projectDeleteName"></strong>.</p>
            <p>Que souhaitez-vous faire avec les ressources associées ?</p>
            
            <div id="projectVhostsSection" class="alert-info">
                <h4 class="mt-0 mb-05 text-info">🌐 Virtual Hosts associés</h4>
                <p class="mt-0" id="projectVhostsList">Ce projet contient des Virtual Hosts qui seront également affectés.</p>
                <div class="flex-col gap-05 mt-05">
                    <label class="flex-row">
                        <input type="checkbox" id="deleteProjectVhosts" name="delete_project_vhosts" checked>
                        <span>Supprimer tous les Virtual Hosts du projet</span>
                    </label>
                </div>
            </div>

            <div class="alert-warning">
                <h4 class="mt-0 mb-05 text-warning-detail">📁 Dossier du projet</h4>
                <p class="mt-0 text-warning-detail text-small">Le dossier <code id="projectFolderPath"></code> contient peut-être des fichiers importants.</p>
                <div class="flex-col gap-05 mt-05">
                    <label class="flex-row">
                        <input type="checkbox" id="deleteProjectFolder" name="delete_project_folder">
                        <span>Supprimer le dossier et tout son contenu</span>
                    </label>
                </div>
            </div>

            <div id="projectDatabasesSection" class="alert-danger">
                <h4 class="mt-0 mb-05 text-db-danger">🗄️ Bases de données associées</h4>
                <p class="mt-0 text-db-danger text-small" id="projectDatabasesList">Ce projet contient des bases de données associées aux Virtual Hosts.</p>
                <div class="flex-col gap-05 mt-05">
                    <label class="flex-row">
                        <input type="checkbox" id="deleteProjectDatabases" name="delete_project_databases">
                        <span>Supprimer toutes les bases de données associées</span>
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

    <?php if (!empty($unique_tags)): ?>
    <div class="filters">
        <button onclick="filterProjects('all')" class="active">✨ Tous les projets</button>
        <?php foreach ($unique_tags as $tag): ?>
            <button onclick="filterProjects('<?= htmlspecialchars($tag) ?>')"><?= htmlspecialchars($tag) ?></button>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Projects Table with Modern Design -->
    <div class="projects-table">
        <table id="projectsTable">
            <thead>
                <tr>
                    <th class="width-48">★</th>
                    <th class="sortable" data-sort="name">Projet</th>
                    <th class="sortable" data-sort="tags">Étiquettes</th>
                    <th class="sortable" data-sort="vhosts">Hôtes Virtuels</th>
                    <th class="sortable" data-sort="tasks">Tâches</th>
                    <th class="sortable" data-sort="last_accessed">Dernier Accès</th>
                    <th class="width-60">Actions</th>
                </tr>
            </thead>
            <tbody id="projectsTableBody">
                <?php foreach ($projects as $project):
                    $lastAccessed = (!empty($project['last_accessed']) && $project['last_accessed'] !== '1970-01-01 00:00:00')
                        ? date('d/m/Y H:i', strtotime($project['last_accessed']))
                        : 'Jamais';

                    $tasks = $project['tasks'] ?? [];
                    $task_counts = [
                        'pending' => 0,
                        'in_progress' => 0,
                        'paused' => 0,
                        'done' => 0,
                        'total' => count($tasks)
                    ];

                    foreach ($tasks as $task) {
                        $status = $task['status'] ?? 'pending';
                        if (isset($task_counts[$status])) {
                            $task_counts[$status]++;
                        }
                    }

                    $vhost_count = count($project['vhosts'] ?? []);
                ?>
                <tr data-tags="<?= htmlspecialchars(implode(' ', array_filter(array_map('trim', $project['tags'] ?? []))), ENT_QUOTES) ?>"
                    data-name="<?= htmlspecialchars($project['name'] ?? '') ?>"
                    data-last-accessed="<?= htmlspecialchars($project['last_accessed'] ?? '') ?>"
                    data-task-total="<?= $task_counts['total'] ?>"
                    data-vhost-total="<?= $vhost_count ?>"
                    onclick="openProject('<?= htmlspecialchars($project['name'] ?? '') ?>')">
                        <td onclick="event.stopPropagation();">
                            <button class="favorite-btn btn-transparent <?= !empty($project['favorite']) ? 'is-fav' : '' ?>" data-project="<?= htmlspecialchars($project['name'] ?? '') ?>"
                                onclick="event.stopPropagation(); toggleFavorite('<?= htmlspecialchars($project['name'] ?? '') ?>', <?= !empty($project['favorite']) ? '1' : '0' ?>)">
                                <?= !empty($project['favorite']) ? '★' : '☆' ?>
                            </button>
                        </td>
                        <td class="project-name">
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <span><?= htmlspecialchars($project['name'] ?? '') ?></span>
                            </div>
                            <?php if (!empty($project['description'])): ?>
                                <p class="project-description"><?= htmlspecialchars($project['description']) ?></p>
                            <?php endif; ?>
                        </td>
                    <td>
                        <div class="tags">
                            <?php foreach ($project['tags'] ?? [] as $tag): ?>
                                <span class="tag"><?= htmlspecialchars($tag) ?></span>
                            <?php endforeach; ?>
                        </div>
                    </td>
                    <td>
                        <div class="vhost-summary">
                            <span class="vhost-count"><?= $vhost_count ?> VHost<?= $vhost_count > 1 ? 's' : '' ?></span>
                        </div>
                    </td>
                    <td>
                        <?php
                        $tasks_config = $projectStatuses;
                        $task_status_keys = array_keys($tasks_config);
                        $task_counts_dynamic = array_fill_keys($task_status_keys, 0);
                        foreach ($tasks as $t) {
                            $s = $t['status'] ?? 'pending';
                            if (!isset($task_counts_dynamic[$s])) {
                                $task_counts_dynamic[$s] = 0;
                            }
                            $task_counts_dynamic[$s]++;
                        }
                        $meta = $tasks_config;
                        ?>
                        <div class="task-summary">
                            <?php foreach ($task_counts_dynamic as $sname => $scount):
                                if ($scount === 0) continue;
                                $color = $meta[$sname]['color'] ?? '#cccccc';
                                $c = preg_match('/^#?[0-9A-Fa-f]{6}$/', $color) ? (strpos($color, '#') === 0 ? $color : '#'.$color) : '#cccccc';
                                $rgb = [
                                    hexdec(substr($c,1,2)),
                                    hexdec(substr($c,3,2)),
                                    hexdec(substr($c,5,2))
                                ];
                                $brightness = ($rgb[0]*299 + $rgb[1]*587 + $rgb[2]*114) / 1000;
                                $txt = $brightness > 186 ? '#000' : '#fff';
                            ?>
                                <span class="task-status <?= htmlspecialchars($sname) ?>" style="background: <?= htmlspecialchars($c) ?>; color: <?= htmlspecialchars($txt) ?>"><?= $scount ?></span>
                            <?php endforeach; ?>
                            <span class="text-muted text-small">(<?= $task_counts['total'] ?> total)</span>
                        </div>
                    </td>
                    <td class="last-accessed"><?= htmlspecialchars($lastAccessed) ?></td>
                    <td onclick="event.stopPropagation();">
                        <div class="actions" style="justify-content: center;">
                                <button onclick="openProject('<?= htmlspecialchars($project['name'] ?? '') ?>')" 
                                    class="btn btn-view" title="Voir le projet">
                                    👁️ Voir
                                </button>
                                <button onclick="deleteProject('<?= htmlspecialchars($project['name'] ?? '') ?>')" 
                                    class="btn btn-delete" title="Supprimer le projet">
                                    🗑️ Supprimer
                                </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <footer>
        <?= htmlspecialchars($footer_message) ?>
    </footer>

    <script src="<?= htmlspecialchars($assetsBasePath) ?>/js/project.js"></script>
    <script>
        const projectsData = <?= $projectsJson ?>;
        const projects = projectsData;
        const projectsByName = projectsData.reduce((map, project) => {
            if (project && project.name) {
                map[project.name] = project;
            }
            return map;
        }, {});

        function filterProjects(tag) {
            const rows = document.querySelectorAll('#projectsTableBody tr');
            const buttons = document.querySelectorAll('.filters button');

            buttons.forEach(btn => btn.classList.remove('active'));
            buttons.forEach(btn => {
                if (btn.textContent === tag || (tag === 'all' && btn.textContent === 'Tous')) {
                    btn.classList.add('active');
                }
            });

            rows.forEach(row => {
                if (tag === 'all') {
                    row.style.display = '';
                } else {
                    const tags = (row.dataset.tags || '').split(' ').filter(Boolean);
                    row.style.display = tags.includes(tag) ? '' : 'none';
                }
            });
        }

        let currentSort = { column: 'last_accessed', direction: 'desc' };
        
        function sortTable(column) {
            const table = document.getElementById('projectsTable');
            const tbody = table.querySelector('tbody');
            const rows = Array.from(tbody.querySelectorAll('tr'));
            
            if (currentSort.column === column) {
                currentSort.direction = currentSort.direction === 'asc' ? 'desc' : 'asc';
            } else {
                currentSort.direction = 'asc';
            }
            currentSort.column = column;
            
            document.querySelectorAll('th').forEach(th => {
                th.classList.remove('sort-asc', 'sort-desc');
            });
            const currentTh = document.querySelector(`th[data-sort="${column}"]`);
            if (currentTh) {
                currentTh.classList.add(currentSort.direction === 'asc' ? 'sort-asc' : 'sort-desc');
            }
            
            rows.sort((a, b) => {
                let aVal, bVal;
                
                switch(column) {
                    case 'name':
                        aVal = (a.dataset.name || '').toLowerCase();
                        bVal = (b.dataset.name || '').toLowerCase();
                        break;
                    case 'description':
                        aVal = (a.dataset.description || '').toLowerCase();
                        bVal = (b.dataset.description || '').toLowerCase();
                        break;
                    case 'tags':
                        aVal = (a.dataset.tags || '').toLowerCase();
                        bVal = (b.dataset.tags || '').toLowerCase();
                        break;
                    case 'vhosts':
                        aVal = parseInt(a.dataset.vhostTotal) || 0;
                        bVal = parseInt(b.dataset.vhostTotal) || 0;
                        break;
                    case 'tasks':
                        aVal = parseInt(a.dataset.taskTotal) || 0;
                        bVal = parseInt(b.dataset.taskTotal) || 0;
                        break;
                    case 'last_accessed':
                        aVal = new Date(a.dataset.lastAccessed).getTime() || 0;
                        bVal = new Date(b.dataset.lastAccessed).getTime() || 0;
                        break;
                    default:
                        return 0;
                }
                
                if (aVal < bVal) return currentSort.direction === 'asc' ? -1 : 1;
                if (aVal > bVal) return currentSort.direction === 'asc' ? 1 : -1;
                return 0;
            });
            
            rows.forEach(row => tbody.appendChild(row));
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('th.sortable').forEach(th => {
                th.addEventListener('click', () => {
                    sortTable(th.dataset.sort);
                });
            });
            
            const lastAccessedTh = document.querySelector('th[data-sort="last_accessed"]');
            if (lastAccessedTh) {
                lastAccessedTh.classList.add('sort-desc');
            }

            if (modal && openModalBtn && projectForm && modalMsg && closeModalBtn && cancelBtn) {
                const closeProjectModal = () => {
                    modal.style.display = 'none';
                };

                openModalBtn.onclick = function() {
                    projectForm.reset();
                    modalMsg.textContent = '';
                    modal.style.display = 'block';
                }

                closeModalBtn.onclick = closeProjectModal;
                cancelBtn.onclick = closeProjectModal;

                window.addEventListener('click', event => {
                    if (event.target === modal) {
                        closeProjectModal();
                    }
                    if (event.target === deleteModal) {
                        deleteModal.style.display = 'none';
                    }
                    if (event.target === importModal) {
                        importModal.style.display = 'none';
                    }
                });
            }

            if (importModal && openImportBtn && importForm) {
                const closeImportModal = () => {
                    importModal.style.display = 'none';
                };

                openImportBtn.onclick = () => {
                    importForm.reset();
                    if (importStatus) {
                        importStatus.textContent = '';
                        importStatus.style.color = '';
                    }
                    importModal.style.display = 'block';
                };

                if (closeProjectImportModalBtn) {
                    closeProjectImportModalBtn.onclick = closeImportModal;
                }

                if (cancelProjectImportBtn) {
                    cancelProjectImportBtn.onclick = closeImportModal;
                }

                importForm.onsubmit = (event) => {
                    event.preventDefault();
                    if (!importStatus) return;

                    importStatus.style.color = '#007bff';
                    importStatus.textContent = 'Import en cours...';

                    const formData = new FormData(importForm);
                    formData.append('action', 'import_project');

                    fetch(window.location.pathname, {
                        method: 'POST',
                        body: formData
                    })
                        .then(async response => {
                            const text = await response.text();
                            let data;
                            try {
                                data = JSON.parse(text);
                            } catch (err) {
                                throw new Error(text || 'Réponse invalide du serveur.');
                            }

                            if (!response.ok) {
                                const message = data.error || 'Erreur lors de l\'import.';
                                throw new Error(message);
                            }

                            return data;
                        })
                        .then(data => {
                            if (data.success) {
                                importStatus.style.color = '#28a745';
                                const projectLabel = data.project_name ? `"${data.project_name}"` : 'importé';
                                let message = `Projet ${projectLabel} importé avec succès.`;
                                if (data.vhost_errors && data.vhost_errors.length > 0) {
                                    message += ` ${data.vhost_errors.length} erreur(s) lors de la création des Virtual Hosts.`;
                                }
                                if (data.database_results && Object.keys(data.database_results).length > 0) {
                                    const failed = Object.values(data.database_results).filter(res => !res.success);
                                    if (failed.length > 0) {
                                        message += ` ${failed.length} erreur(s) lors de l'import des bases de données.`;
                                    }
                                }
                                importStatus.textContent = message;
                                setTimeout(() => {
                                    window.location.reload();
                                }, 1500);
                            } else {
                                importStatus.style.color = '#dc3545';
                                importStatus.textContent = data.error || 'Erreur lors de l\'import.';
                            }
                        })
                        .catch((error) => {
                            importStatus.style.color = '#dc3545';
                            importStatus.textContent = error.message || 'Erreur réseau lors de l\'import.';
                            console.error('Import error:', error);
                        });
                };
            }
        });

        function openProject(projectName) {
            const formData = new FormData();
            formData.append('action', 'track_access');
            formData.append('project_name', projectName);
            
            fetch(window.location.pathname, {
                method: 'POST',
                body: formData
            }).catch(() => {});
            
            window.location.href = `project.php?name=${encodeURIComponent(projectName)}`;
        }

        const modal = document.getElementById('projectModal');
        const openModalBtn = document.getElementById('openModalBtn');
        const closeModalBtn = document.getElementById('closeModalBtn');
        const cancelBtn = document.getElementById('cancelBtn');
        const projectForm = document.getElementById('projectForm');
        const modalMsg = document.getElementById('modalMsg');

        const importModal = document.getElementById('projectImportModal');
        const openImportBtn = document.getElementById('openImportBtn');
        const closeProjectImportModalBtn = document.getElementById('closeProjectImportModalBtn');
        const cancelProjectImportBtn = document.getElementById('cancelProjectImportBtn');
        const importForm = document.getElementById('projectImportForm');
        const importStatus = document.getElementById('projectImportStatus');

        const deleteModal = document.getElementById('projectDeleteModal');
        const closeDeleteModalBtn = document.getElementById('closeProjectDeleteModalBtn');
        const cancelDeleteBtn = document.getElementById('cancelProjectDeleteBtn');

        closeDeleteModalBtn.onclick = cancelDeleteBtn.onclick = function() {
            deleteModal.style.display = 'none';
        }

        function deleteProject(projectName) {
            const project = projectsByName[projectName];
            
            if (!project) {
                alert('Projet introuvable.');
                return;
            }

            document.getElementById('projectDeleteName').textContent = projectName;
            document.getElementById('projectFolderPath').textContent = `${<?= json_encode($baseProjectsPath) ?>}/${projectName}`;
            
            const vhostsSection = document.getElementById('projectVhostsSection');
            const databasesSection = document.getElementById('projectDatabasesSection');
            const vhostsList = document.getElementById('projectVhostsList');
            const databasesList = document.getElementById('projectDatabasesList');
            
            if (!project.vhosts || project.vhosts.length === 0) {
                vhostsSection.style.display = 'none';
                databasesSection.style.display = 'none';
            } else {
                vhostsSection.style.display = 'block';
                const vhostNames = project.vhosts.map(vhost => vhost.servername).join(', ');
                vhostsList.textContent = `Virtual Hosts trouvés : ${vhostNames}`;
                
                const vhostsWithDatabases = project.vhosts.filter(vhost => vhost.database_name);
                if (vhostsWithDatabases.length > 0) {
                    databasesSection.style.display = 'block';
                    const dbNames = vhostsWithDatabases.map(vhost => vhost.database_name).join(', ');
                    databasesList.textContent = `Bases de données trouvées : ${dbNames}`;
                } else {
                    databasesSection.style.display = 'none';
                }
            }
            
            document.getElementById('deleteProjectVhosts').checked = true;
            document.getElementById('deleteProjectFolder').checked = false;
            document.getElementById('deleteProjectDatabases').checked = false;
            
            window.projectToDelete = projectName;
            
            deleteModal.style.display = 'block';
        }

        function executeProjectDeletion() {
            if (!window.projectToDelete) return;
            
            const formData = new FormData();
            formData.append('action', 'delete_project');
            formData.append('project_name', window.projectToDelete);
            formData.append('delete_vhosts', document.getElementById('deleteProjectVhosts').checked ? '1' : '0');
            formData.append('delete_folder', document.getElementById('deleteProjectFolder').checked ? '1' : '0');
            formData.append('delete_databases', document.getElementById('deleteProjectDatabases').checked ? '1' : '0');
            
            const msgDiv = document.getElementById('projectDeleteModalMsg');
            msgDiv.textContent = '';
            
            fetch(window.location.pathname, {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    msgDiv.style.color = 'green';
                    msgDiv.textContent = 'Projet supprimé avec succès !';
                    setTimeout(() => { 
                        deleteModal.style.display = 'none';
                        location.reload(); 
                    }, 1000);
                } else {
                    msgDiv.style.color = 'red';
                    msgDiv.textContent = data.error || 'Erreur lors de la suppression.';
                }
            })
            .catch(() => {
                msgDiv.style.color = 'red';
                msgDiv.textContent = 'Erreur réseau lors de la suppression.';
            });
        }

        function toggleFavorite(projectName, currentState) {
            const newState = currentState === 1 ? '0' : '1';
            const formData = new FormData();
            formData.append('action', 'set_favorite');
            formData.append('project_name', projectName);
            formData.append('favorite', newState);

            fetch(window.location.pathname, {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.error || 'Impossible de modifier le favori.');
                }
            })
            .catch(() => {
                alert('Erreur réseau lors de la mise à jour du favori.');
            });
        }

        projectForm.onsubmit = function(e) {
            e.preventDefault();
            modalMsg.textContent = '';
            const formData = new FormData(projectForm);
            
            fetch(window.location.pathname, {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    modalMsg.style.color = 'green';
                    modalMsg.textContent = 'Projet créé avec succès !';
                    setTimeout(() => { location.reload(); }, 1000);
                } else {
                    modalMsg.style.color = 'red';
                    modalMsg.textContent = data.error || 'Erreur lors de la création du projet.';
                }
            })
            .catch((error) => {
                modalMsg.style.color = 'red';
                modalMsg.textContent = error?.message || 'Erreur réseau.';
            });
        }
    </script>
</body>
</html>