// Project Management System JavaScript

class ProjectManager {
  constructor() {
    this.initializeModals();
    this.bindEvents();
    // editor settings for rich text (textarea | tinymce)
    this.editorType =
      document.querySelector("[data-editor-type]")?.dataset.editorType ||
      "textarea";
    this.editorPlaceholder =
      document.querySelector("[data-editor-placeholder]")?.dataset
        .editorPlaceholder || "";

    // Initialize a rich editor when configured
    this.initEditors();

    // autosave timer handle
    this._autosaveTimer = null;

    // Make checklist checkboxes in preview interactive: clicking them updates the hidden textarea
    const previewEl = document.getElementById("notesPreview");
    if (previewEl) {
      previewEl.addEventListener("change", (e) => {
        const target = e.target;
        if (
          target &&
          target.matches &&
          target.matches('input[type="checkbox"]')
        ) {
          const textarea = document.getElementById("projectNotes");
          if (!textarea) return;
          // Convert preview checkbox inputs back to canonical TinyMCE checklist HTML
          const canonical = this.convertPreviewCheckboxesToToxHtml(previewEl);
          textarea.value = canonical;
          // schedule a debounced auto-save when preview is visible
          try {
            // only auto-save when the editor textarea is hidden (we're in preview mode)
            if (textarea.style.display === "none") this.scheduleAutoSaveNotes();
          } catch (e) {
            // ignore
          }
          // show a subtle unsaved indicator
          const notesStatus = document.getElementById("notesStatus");
          if (notesStatus) {
            notesStatus.style.color = "#ff9800";
            notesStatus.textContent =
              "Modifications non-sauvegardées (case modifiée). N'oubliez pas de sauvegarder.";
            setTimeout(() => {
              notesStatus.textContent = "";
            }, 3000);
          }
        }
      });
      // Convert any existing checklist markup in the preview into checkboxes
      this.transformChecklistToCheckboxes(previewEl);
    }
  }

  // ========================================
  // INITIALIZATION
  // ========================================

  initializeModals() {
    // VHost Modal
    this.vhostModal = document.getElementById("vhostModal");
    this.vhostForm = document.getElementById("vhostForm");
    this.vhostModalMsg = document.getElementById("vhostModalMsg");

    // Task Modal
    this.taskModal = document.getElementById("taskModal");
    this.taskForm = document.getElementById("taskForm");
    this.taskModalMsg = document.getElementById("taskModalMsg");

    // Project Edit Modal
    this.projectEditModal = document.getElementById("projectEditModal");
    this.projectEditForm = document.getElementById("projectEditForm");
    this.projectEditModalMsg = document.getElementById("projectEditModalMsg");

    // Project Delete Modal
    this.projectDeleteModal = document.getElementById("projectDeleteModal");
    this.projectDeleteModalMsg = document.getElementById(
      "projectDeleteModalMsg"
    );

    // Project Export Modal
    this.projectExportModal = document.getElementById("projectExportModal");
    this.projectExportForm = document.getElementById("projectExportForm");
    this.projectExportStatus = document.getElementById("projectExportStatus");
  }

  bindEvents() {
    // Modal close events
    this.bindModalCloseEvents();

    // Form submission events
    this.bindFormEvents();

    // Auto-populate DocumentRoot
    this.bindVhostEvents();

    // Global modal close on outside click
    this.bindGlobalEvents();
  }

  bindModalCloseEvents() {
    // VHost Modal
    this.bindCloseButton("closeVhostModalBtn", this.vhostModal);
    this.bindCloseButton("cancelVhostBtn", this.vhostModal);

    this.bindCloseButton("closeTaskModalBtn", this.taskModal);
    this.bindCloseButton("cancelTaskBtn", this.taskModal);

    this.bindCloseButton("closeProjectEditModalBtn", this.projectEditModal);
    this.bindCloseButton("cancelProjectEditBtn", this.projectEditModal);

    this.bindCloseButton("closeProjectDeleteModalBtn", this.projectDeleteModal);
    this.bindCloseButton("cancelProjectDeleteBtn", this.projectDeleteModal);
    this.bindCloseButton("closeProjectExportModalBtn", this.projectExportModal);
    this.bindCloseButton("cancelProjectExportBtn", this.projectExportModal);
  }

  bindFormEvents() {
    // VHost Form
    this.vhostForm.onsubmit = (e) => this.handleVhostSubmit(e);

    // Task Form
    this.taskForm.onsubmit = (e) => this.handleTaskSubmit(e);

    // Project Edit Form
    this.projectEditForm.onsubmit = (e) => this.handleProjectEditSubmit(e);
    if (this.projectExportForm) {
      this.projectExportForm.onsubmit = (e) => this.handleExportSubmit(e);
    }
  }

  bindVhostEvents() {
    // Auto-populate DocumentRoot based on ServerName
    document.getElementById("servername").addEventListener("input", (e) => {
      const servername = e.target.value.trim();
      const documentrootField = document.getElementById("documentroot");
      const databaseNameField = document.getElementById("database_name");
      const editMode = document.getElementById("edit_mode").value;

      // Only auto-populate for new VHosts, not when editing
      if (editMode === "0" && servername) {
        const projectDataElement = document.querySelector(
          "[data-project-name]"
        );
        const projectSlug =
          projectDataElement.dataset.projectSlug ||
          projectDataElement.dataset.projectName;
        const baseProjectsPath =
          projectDataElement.dataset.baseProjectsPath ||
          "C:/Users/Francois/Projets";
        const suggestedPath = `${baseProjectsPath}/${projectSlug}/${servername}`;
        documentrootField.value = suggestedPath;

        // Auto-generate database name
        if (databaseNameField && !databaseNameField.value) {
          const projectName = projectDataElement.dataset.projectName;
          const dbName = this.generateDatabaseName(projectName, servername);
          databaseNameField.placeholder = `Suggéré: ${dbName}`;
        }
      }
    });

    // Handle database checkbox toggle
    const createDatabaseCheckbox = document.getElementById("create_database");
    const databaseNameGroup = document.getElementById("database_name_group");

    if (createDatabaseCheckbox && databaseNameGroup) {
      createDatabaseCheckbox.addEventListener("change", (e) => {
        databaseNameGroup.style.display = e.target.checked ? "block" : "none";
      });

      // Initial state
      databaseNameGroup.style.display = createDatabaseCheckbox.checked
        ? "block"
        : "none";
    }
  }

  bindCloseButton(buttonId, modalElement) {
    const button = document.getElementById(buttonId);
    if (!button) {
      return;
    }

    button.onclick = () => this.closeModal(modalElement);
  }

  // Generate database name from project name and server name
  generateDatabaseName(projectName, servername) {
    // Clean project name
    let cleanProject = projectName.toLowerCase();
    cleanProject = cleanProject.replace(/[^a-z0-9_]/g, "_");
    cleanProject = cleanProject.replace(/_+/g, "_");
    cleanProject = cleanProject.replace(/^_+|_+$/g, "");

    // Clean server name
    let cleanServer = servername.toLowerCase();
    cleanServer = cleanServer.replace(/^(https?:\/\/)?(www\.)?/, "");
    cleanServer = cleanServer.replace(/[^a-z0-9_]/g, "_");
    cleanServer = cleanServer.replace(/_+/g, "_");
    cleanServer = cleanServer.replace(/^_+|_+$/g, "");

    // Combine project + server name
    let name = cleanProject + "_" + cleanServer;

    // Ensure it starts with a letter or underscore
    if (/^[0-9]/.test(name)) {
      name = "db_" + name;
    }

    // Limit length to MySQL's 64 character limit
    if (name.length > 64) {
      name = name.substring(0, 64);
    }

    return name;
  }

  bindGlobalEvents() {
    // Modal click outside to close
    window.onclick = (event) => {
      if (event.target === this.vhostModal) this.closeModal(this.vhostModal);
      if (event.target === this.taskModal) this.closeModal(this.taskModal);
      if (event.target === this.projectEditModal)
        this.closeModal(this.projectEditModal);
      if (event.target === this.projectDeleteModal)
        this.closeModal(this.projectDeleteModal);
      if (event.target === this.projectExportModal)
        this.closeModal(this.projectExportModal);
    };
  }

  // Initialize rich text editors if configured (TinyMCE)

  // Initialize rich text editors if configured (TinyMCE)
  initEditors() {
    if (this.editorType !== "tinymce") return;
    if (typeof tinymce === "undefined") {
      console.warn("TinyMCE not loaded — rich editor disabled");
      return;
    }

    tinymce.init({
      selector: "#task_description",
      plugins: "lists link checklist paste autolink code autoresize",
      toolbar:
        "undo redo | formatselect | bold italic underline strikethrough | alignleft aligncenter alignright | bullist numlist | checklist | link | removeformat | code",
      menubar: false,
      branding: false,
      height: 280,
      paste_as_text: false,
      autoresize_bottom_margin: 20,
    });
  }

  // Toggle notes editing mode (preview-first UX)
  openNotesEditor() {
    const preview = document.getElementById("notesPreview");
    const textarea = document.getElementById("projectNotes");
    const editBtn = document.getElementById("editNotesBtn");
    const cancelBtn = document.getElementById("cancelNotesBtn");
    const saveBtn = document.getElementById("saveNotesBtn");

    if (!textarea) return;

    // store current content so cancel can restore
    this._notesOriginal = textarea.value;

    // hide preview, show textarea and buttons
    if (preview) preview.style.display = "none";
    textarea.style.display = "";
    if (editBtn) editBtn.style.display = "none";
    if (cancelBtn) {
      cancelBtn.style.display = "";
      cancelBtn.classList.remove("hidden");
    }
    if (saveBtn) {
      saveBtn.style.display = "";
      saveBtn.classList.remove("hidden");
    }

    // Before initializing the editor, ensure textarea contains canonical checklist markup.
    // If the live preview already contains checkbox inputs, convert them to canonical
    // TinyMCE checklist HTML and populate the textarea so the editor shows a correct
    // representation. Otherwise, convert any stray <input> tags present in the textarea.
    try {
      if (
        preview &&
        preview.querySelector &&
        preview.querySelector('input[type="checkbox"]')
      ) {
        textarea.value = this.convertPreviewCheckboxesToToxHtml(preview);
      } else if (
        textarea &&
        textarea.value &&
        textarea.value.indexOf("<input") !== -1
      ) {
        textarea.value = this.convertHtmlInputsToToxChecklist(textarea.value);
      }
    } catch (e) {
      // best effort; ignore conversion errors
    }

    // Initialize tinymce lazily for projectNotes if needed
    if (this.editorType === "tinymce" && typeof tinymce !== "undefined") {
      const ed = tinymce.get("projectNotes");
      if (!ed) {
        tinymce
          .init({
            selector: "#projectNotes",
            plugins: "lists link checklist paste autolink code autoresize",
            toolbar:
              "undo redo | formatselect | bold italic underline strikethrough | alignleft aligncenter alignright | bullist numlist | checklist | link | removeformat | code",
            menubar: false,
            branding: false,
            height: 300,
          })
          .then(() => {
            const created = tinymce.get("projectNotes");
            if (created) created.focus();
          });
      } else {
        ed.focus();
      }
    } else {
      textarea.focus();
    }
  }

  cancelNotesEdit() {
    const preview = document.getElementById("notesPreview");
    const textarea = document.getElementById("projectNotes");
    const editBtn = document.getElementById("editNotesBtn");
    const cancelBtn = document.getElementById("cancelNotesBtn");
    const saveBtn = document.getElementById("saveNotesBtn");

    if (!textarea) return;

    // If TinyMCE is active: restore original content AND destroy the editor instance
    if (this.editorType === "tinymce" && typeof tinymce !== "undefined") {
      const ed = tinymce.get("projectNotes");
      if (ed) {
        try {
          ed.setContent(this._notesOriginal || "");
          ed.remove(); // destroy editor UI
          // Ensure instance is fully removed — selector fallback
          try {
            tinymce.remove("#projectNotes");
          } catch (e) {
            /* ignore */
          }
        } catch (e) {
          textarea.value = this._notesOriginal || "";
        }
      } else {
        textarea.value = this._notesOriginal || "";
      }
    } else {
      textarea.value = this._notesOriginal || "";
    }

    // hide the editor and restore preview (show the original snapshot)
    textarea.style.display = "none";
    if (preview) {
      if (this.editorType === "tinymce") {
        preview.innerHTML = this._notesOriginal || "";
      } else {
        preview.innerHTML = (this._notesOriginal || "")
          .replace(/&/g, "&amp;")
          .replace(/</g, "&lt;")
          .replace(/>/g, "&gt;")
          .replace(/\n/g, "<br>");
      }
      this.transformChecklistToCheckboxes(preview);
      preview.style.display = "";
    }
    if (editBtn) editBtn.style.display = "";
    if (cancelBtn) {
      cancelBtn.style.display = "none";
      cancelBtn.classList.add("hidden");
    }
    if (saveBtn) {
      saveBtn.style.display = "none";
      saveBtn.classList.add("hidden");
    }
  }

  // Transform TinyMCE / other editor checklist markup (tox-checklist or mce-checklist)
  // into real interactive checkbox inputs inside the preview area.
  transformChecklistToCheckboxes(container) {
    if (!container) return;

    const selector =
      'ul.tox-checklist, ul[class*="tox-checklist"], ul.mce-checklist, ul[class*="mce-checklist"]';
    const lists = container.querySelectorAll(selector);
    lists.forEach((ul) => {
      if (ul.dataset.checkboxified) return; // avoid double-processing
      ul.dataset.checkboxified = "1";

      Array.from(ul.children).forEach((li) => {
        if (!li || li.tagName.toLowerCase() !== "li") return;
        // if a checkbox already exists, skip
        if (li.querySelector('input[type="checkbox"]')) return;

        const isChecked =
          li.classList.contains("tox-checklist--checked") ||
          li.classList.contains("mce-checklist--checked");
        const innerHTML = li.innerHTML;

        // construct checkbox + label
        li.innerHTML = "";
        const checkbox = document.createElement("input");
        checkbox.type = "checkbox";
        checkbox.checked = !!isChecked;
        checkbox.className = "notes-checklist-checkbox";

        const label = document.createElement("label");
        label.innerHTML = innerHTML;

        li.appendChild(checkbox);
        li.appendChild(label);
      });
    });
  }

  // Convert the preview DOM (with checkbox inputs) back to canonical TinyMCE checklist HTML
  convertPreviewCheckboxesToToxHtml(container) {
    if (!container) return "";

    // Work on a clone so we don't modify the live preview
    const clone = container.cloneNode(true);

    // Accept lists that are explicitly tox/mce checklists or any ul containing checkbox inputs
    const lists = Array.from(clone.querySelectorAll("ul")).filter(
      (u) =>
        u.className.includes("tox-checklist") ||
        u.className.includes("mce-checklist") ||
        u.querySelector('input[type="checkbox"]')
    );

    lists.forEach((ul) => {
      const newUl = document.createElement("ul");
      newUl.className = "tox-checklist";

      Array.from(ul.children).forEach((li) => {
        if (!li || li.tagName.toLowerCase() !== "li") return;
        // If li contains an input checkbox, detect its state
        const cb = li.querySelector('input[type="checkbox"]');
        const checked = cb
          ? !!cb.checked
          : li.classList.contains("tox-checklist--checked") ||
            li.classList.contains("mce-checklist--checked");
        // Prefer label content if available
        const label = li.querySelector("label");
        const content = label ? label.innerHTML : li.innerHTML;

        const newLi = document.createElement("li");
        if (checked) newLi.classList.add("tox-checklist--checked");
        newLi.innerHTML = content;
        newUl.appendChild(newLi);
      });

      ul.parentNode.replaceChild(newUl, ul);
    });

    return clone.innerHTML;
  }

  // Convert a saved HTML string that contains <input> checkboxes back to tox-checklist HTML
  convertHtmlInputsToToxChecklist(htmlString) {
    const wrapper = document.createElement("div");
    wrapper.innerHTML = htmlString || "";
    return this.convertPreviewCheckboxesToToxHtml(wrapper);
  }

  // Sync rich editor content to underlying textareas (if used) before form submissions
  syncEditors() {
    if (this.editorType === "tinymce" && typeof tinymce !== "undefined") {
      try {
        tinymce.triggerSave();
      } catch (e) {
        // ignore
      }
      // Also try to convert any existing checklist markup into interactive checkboxes
      const pe = document.getElementById("notesPreview");
      if (pe) this.transformChecklistToCheckboxes(pe);
    }
  }

  // Schedule and perform autosave for notes (debounced)
  scheduleAutoSaveNotes(delay = 800) {
    if (this._autosaveTimer) clearTimeout(this._autosaveTimer);
    this._autosaveTimer = setTimeout(() => {
      this._autosaveTimer = null;
      this.autoSaveNotes();
    }, delay);
  }

  autoSaveNotes() {
    // Only auto-save when preview is visible (not editing)
    const textarea = document.getElementById("projectNotes");
    if (!textarea) return;
    if (textarea.style.display !== "none") return; // someone is editing

    // trigger save with auto flag
    this.saveNotes(true);
  }

  // ========================================
  // MODAL MANAGEMENT
  // ========================================

  closeModal(modal) {
    modal.style.display = "none";
  }

  showModal(modal) {
    modal.style.display = "block";
  }

  // ========================================
  // VHOST MANAGEMENT
  // ========================================

  openVhostModal() {
    document.getElementById("vhostModalTitle").textContent =
      "Ajouter un Virtual Host";
    this.vhostForm.reset();
    document.getElementById("edit_mode").value = "0";
    document.getElementById("original_servername").value = "";
    this.vhostModalMsg.textContent = "";
    this.showModal(this.vhostModal);
  }

  editVhost(
    displayName,
    servername,
    documentroot,
    description,
    phpVersion,
    databaseName
  ) {
    document.getElementById("vhostModalTitle").textContent =
      "Modifier le Virtual Host";
    document.getElementById("display_name").value = displayName || "";
    document.getElementById("servername").value = servername;
    document.getElementById("documentroot").value = documentroot;
    document.getElementById("description").value = description;
    // Normalize phpVersion prior to setting the <select> so numeric port values are mapped
    let normalized = phpVersion || "8.3";
    try {
      const dataEl = document.querySelector("[data-php-versions]");
      if (dataEl && dataEl.dataset && dataEl.dataset.phpVersions) {
        const mapping = JSON.parse(dataEl.dataset.phpVersions);
        // If phpVersion is a key (like "8.3"), keep. If it's a port ("983"), find the matching key.
        if (!mapping.hasOwnProperty(normalized)) {
          // find by value
          for (const ver in mapping) {
            if (String(mapping[ver]) === String(normalized)) {
              normalized = ver;
              break;
            }
          }
        }
      }
    } catch (e) {
      /* ignore parsing errors */
    }

    // Set the select value using canonical version string
    const phpVersionSelect = document.getElementById("php_version");
    if (phpVersionSelect) {
      phpVersionSelect.value = normalized;
    }
    document.getElementById("edit_mode").value = "1";
    document.getElementById("original_servername").value = servername;

    // Fill database name and enable database section if there's a database
    if (databaseName && databaseName !== "") {
      document.getElementById("create_database").checked = true;
      document.getElementById("database_name").value = databaseName;
    } else {
      document.getElementById("create_database").checked = false;
      document.getElementById("database_name").value = "";
    }

    this.vhostModalMsg.textContent = "";
    this.showModal(this.vhostModal);
  }

  deleteVhost(servername, vhostPath, dbName) {
    // Show the VHost delete modal
    document.getElementById("vhostDeleteName").textContent = servername;
    document.getElementById("vhostFolderPath").textContent = vhostPath || "N/A";

    // Handle database section
    const dbSection = document.getElementById("vhostDatabaseSection");
    const dbNameElement = document.getElementById("vhostDatabaseName");

    if (dbName && dbName !== "N/A") {
      dbSection.style.display = "block";
      dbNameElement.textContent = dbName;
    } else {
      dbSection.style.display = "none";
    }

    // Store vhost data for execution
    window.currentVhostToDelete = {
      servername: servername,
      path: vhostPath,
      database: dbName,
    };

    document.getElementById("vhostDeleteModal").style.display = "block";
  }

  handleVhostSubmit(e) {
    e.preventDefault();
    this.vhostModalMsg.textContent = "";
    const formData = new FormData(this.vhostForm);

    this.submitForm(
      formData,
      (data) => {
        if (data.success) {
          this.vhostModalMsg.style.color = "green";
          this.vhostModalMsg.textContent = "Enregistré !";
          setTimeout(() => {
            location.reload();
          }, 1000);
        } else {
          this.vhostModalMsg.style.color = "red";
          this.vhostModalMsg.textContent = data.error || "Erreur.";
        }
      },
      (error) => {
        this.vhostModalMsg.style.color = "red";
        this.vhostModalMsg.textContent = error?.message || "Erreur réseau.";
      }
    );
  }

  // ========================================
  // TASK MANAGEMENT
  // ========================================

  openTaskModal() {
    document.getElementById("taskModalTitle").textContent = "Ajouter une Tâche";
    this.taskForm.reset();
    document.getElementById("task_id").value = "";
    // Clear task description in textarea and rich editor (if present)
    if (this.editorType === "tinymce" && typeof tinymce !== "undefined") {
      const ed = tinymce.get("task_description");
      if (ed) ed.setContent("");
      else document.getElementById("task_description").value = "";
    } else {
      document.getElementById("task_description").value = "";
    }
    const prioritySelect = document.getElementById("task_priority");
    if (prioritySelect) {
      const defaultValue = prioritySelect.dataset.defaultValue || "none";
      prioritySelect.value = defaultValue;
    }
    this.taskModalMsg.textContent = "";
    this.showModal(this.taskModal);
  }

  editTask(
    taskId,
    title,
    status,
    dueDate,
    priority = "none",
    description = ""
  ) {
    document.getElementById("taskModalTitle").textContent = "Modifier la Tâche";
    document.getElementById("task_id").value = taskId;
    document.getElementById("task_title").value = title;
    document.getElementById("task_status").value = status;
    document.getElementById("task_due_date").value = dueDate;
    const prioritySelect = document.getElementById("task_priority");
    if (prioritySelect) prioritySelect.value = String(priority);
    if (this.editorType === "tinymce" && typeof tinymce !== "undefined") {
      const ed = tinymce.get("task_description");
      if (ed) ed.setContent(description || "");
      else
        document.getElementById("task_description").value = description || "";
    } else {
      document.getElementById("task_description").value = description || "";
    }
    this.taskModalMsg.textContent = "";
    this.showModal(this.taskModal);
  }

  deleteTask(taskId) {
    if (confirm("Êtes-vous sûr de vouloir supprimer cette tâche ?")) {
      const formData = new FormData();
      formData.append("action", "delete_task");
      formData.append("task_id", taskId);

      this.submitForm(formData, (data) => {
        if (data.success) {
          location.reload();
        } else {
          this.taskModalMsg.style.color = "red";
          this.taskModalMsg.textContent =
            data.error || "Erreur lors de la suppression.";
        }
      });
    }
  }

  handleTaskSubmit(e) {
    e.preventDefault();
    this.taskModalMsg.textContent = "";
    // Ensure editor content is synced to textarea before creating FormData
    this.syncEditors();
    const formData = new FormData(this.taskForm);
    const prioritySelect = document.getElementById("task_priority");
    if (prioritySelect) {
      formData.append("task_priority", prioritySelect.value);
    }

    this.submitForm(
      formData,
      (data) => {
        if (data.success) {
          this.taskModalMsg.style.color = "green";
          this.taskModalMsg.textContent = "Tâche enregistrée !";
          setTimeout(() => {
            location.reload();
          }, 1000);
        } else {
          this.taskModalMsg.style.color = "red";
          this.taskModalMsg.textContent = data.error || "Erreur.";
        }
      },
      (error) => {
        this.taskModalMsg.style.color = "red";
        this.taskModalMsg.textContent = error?.message || "Erreur réseau.";
      }
    );
  }

  // ========================================
  // PROJECT MANAGEMENT
  // ========================================

  openProjectEditModal() {
    const projectName = document.querySelector("[data-project-name]").dataset
      .projectName;
    const projectDescription = document.querySelector(
      "[data-project-description]"
    ).dataset.projectDescription;
    const projectTags = document.querySelector("[data-project-tags]").dataset
      .projectTags;

    document.getElementById("new_project_name").value = projectName;
    document.getElementById("new_project_description").value =
      projectDescription;
    document.getElementById("new_project_tags").value = projectTags;

    this.projectEditModalMsg.textContent = "";
    this.showModal(this.projectEditModal);
  }

  openExportModal() {
    if (!this.projectExportModal) {
      return;
    }

    if (this.projectExportForm) {
      this.projectExportForm.reset();
    }

    if (this.projectExportStatus) {
      this.projectExportStatus.textContent = "";
      this.projectExportStatus.style.color = "";
    }

    this.showModal(this.projectExportModal);
  }

  handleProjectEditSubmit(e) {
    e.preventDefault();
    this.projectEditModalMsg.textContent = "";
    const formData = new FormData(this.projectEditForm);

    this.submitForm(
      formData,
      (data) => {
        if (data.success) {
          this.projectEditModalMsg.style.color = "green";
          this.projectEditModalMsg.textContent = "Projet modifié avec succès !";
          setTimeout(() => {
            // If project name changed, redirect to new URL
            const currentProjectName = document.querySelector(
              "[data-project-name]"
            ).dataset.projectName;
            if (
              data.new_project_name &&
              data.new_project_name !== currentProjectName
            ) {
              window.location.href = `project.php?name=${encodeURIComponent(
                data.new_project_name
              )}`;
            } else {
              location.reload();
            }
          }, 1000);
        } else {
          this.projectEditModalMsg.style.color = "red";
          this.projectEditModalMsg.textContent =
            data.error || "Erreur lors de la modification.";
        }
      },
      (error) => {
        this.projectEditModalMsg.style.color = "red";
        this.projectEditModalMsg.textContent =
          error?.message || "Erreur réseau.";
      }
    );
  }

  handleExportSubmit(e) {
    e.preventDefault();
    if (!this.projectExportForm) {
      return;
    }

    const formData = new FormData(this.projectExportForm);
    const filesCheckbox = document.getElementById("exportIncludeFiles");
    const vhostsCheckbox = document.getElementById("exportIncludeVhosts");
    const databasesCheckbox = document.getElementById("exportIncludeDatabases");

    formData.set(
      "include_files",
      filesCheckbox && filesCheckbox.checked ? "1" : "0"
    );
    formData.set(
      "include_vhosts",
      vhostsCheckbox && vhostsCheckbox.checked ? "1" : "0"
    );
    formData.set(
      "include_databases",
      databasesCheckbox && databasesCheckbox.checked ? "1" : "0"
    );
    formData.append("action", "export_project");

    if (this.projectExportStatus) {
      this.projectExportStatus.style.color = "";
      this.projectExportStatus.textContent = "Préparation de l'export...";
    }

    this.submitForm(
      formData,
      (data) => {
        if (data.success && data.download_url) {
          if (this.projectExportStatus) {
            this.projectExportStatus.style.color = "#28a745";
            this.projectExportStatus.textContent =
              "Export prêt, téléchargement en cours...";
          }
          this.closeModal(this.projectExportModal);
          setTimeout(() => {
            window.location.href = data.download_url;
          }, 300);
        } else if (this.projectExportStatus) {
          this.projectExportStatus.style.color = "#dc3545";
          this.projectExportStatus.textContent =
            data.error || "Erreur lors de l'export.";
        }
      },
      (error) => {
        if (this.projectExportStatus) {
          this.projectExportStatus.style.color = "#dc3545";
          this.projectExportStatus.textContent =
            "Erreur réseau lors de l'export.";
        }
        console.error("Export error:", error);
      }
    );
  }

  confirmDeleteProject() {
    this.projectDeleteModalMsg.textContent = "";
    // Reset radio buttons to default (keep folder)
    document.querySelector(
      'input[name="delete_folder_option"][value="no"]'
    ).checked = true;
    this.showModal(this.projectDeleteModal);
  }

  executeProjectDeletion() {
    const deleteFolderOption = document.querySelector(
      'input[name="delete_folder_option"]:checked'
    ).value;

    this.projectDeleteModalMsg.textContent = "Suppression en cours...";
    this.projectDeleteModalMsg.style.color = "#007bff";

    const formData = new FormData();
    formData.append("action", "delete_project");
    formData.append("delete_folder", deleteFolderOption);

    this.submitForm(
      formData,
      (data) => {
        if (data.success) {
          this.projectDeleteModalMsg.style.color = "#28a745";
          const folderMsg = data.folder_deleted
            ? " (dossier supprimé)"
            : " (dossier conservé)";
          this.projectDeleteModalMsg.textContent =
            "✅ Projet supprimé avec succès !" + folderMsg;
          setTimeout(() => {
            window.location.href = "projects.php";
          }, 2000);
        } else {
          this.projectDeleteModalMsg.style.color = "#dc3545";
          this.projectDeleteModalMsg.textContent =
            "❌ Erreur lors de la suppression : " +
            (data.error || "Erreur inconnue");
        }
      },
      (error) => {
        this.projectDeleteModalMsg.style.color = "#dc3545";
        this.projectDeleteModalMsg.textContent =
          "❌ Erreur réseau lors de la suppression.";
        console.error("Error:", error);
      }
    );
  }

  // ========================================
  // NOTES MANAGEMENT
  // ========================================

  saveNotes(auto = false) {
    // Sync rich editors to underlying textarea (if used)
    this.syncEditors();

    // Get content from textarea
    const notesTextarea = document.getElementById("projectNotes");
    const notesContent = notesTextarea.value;
    const notesStatus = document.getElementById("notesStatus");

    notesStatus.textContent = auto
      ? "Sauvegarde automatique en cours..."
      : "Sauvegarde en cours...";
    notesStatus.style.color = "#007bff";

    const formData = new FormData();
    formData.append("action", "save_notes");
    formData.append("notes", notesContent);

    this.submitForm(
      formData,
      (data) => {
        if (data.success) {
          notesStatus.style.color = "#28a745";
          notesStatus.textContent = auto
            ? "✅ Sauvegarde automatique effectuée"
            : "✅ Notes sauvegardées avec succès !";

          // Update preview and switch back to preview mode
          const preview = document.getElementById("notesPreview");
          const textarea = document.getElementById("projectNotes");
          if (preview && textarea) {
            const value = textarea.value || "";
            if (this.editorType === "tinymce") {
              // raw HTML
              preview.innerHTML = value;
            } else {
              // escape and convert newlines
              preview.innerHTML = value
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/\n/g, "<br>");
            }

            // Convert any editor checklist markup into interactive checkboxes in preview
            this.transformChecklistToCheckboxes(preview);

            // hide edit UI
            // If TinyMCE is active for this textarea, destroy the editor instance so its UI is removed
            if (
              this.editorType === "tinymce" &&
              typeof tinymce !== "undefined"
            ) {
              const ed = tinymce.get("projectNotes");
              if (ed) {
                try {
                  ed.remove();
                } catch (e) {
                  /* ignore */
                }
                try {
                  // selector fallback to ensure instance is removed
                  tinymce.remove("#projectNotes");
                } catch (e) {
                  /* ignore */
                }
              }
            }

            textarea.style.display = "none";
            const editBtn = document.getElementById("editNotesBtn");
            const cancelBtn = document.getElementById("cancelNotesBtn");
            const saveBtn = document.getElementById("saveNotesBtn");
            if (editBtn) editBtn.style.display = "";
            if (cancelBtn) {
              cancelBtn.style.display = "none";
              cancelBtn.classList.add("hidden");
            }
            if (saveBtn) {
              saveBtn.style.display = "none";
              saveBtn.classList.add("hidden");
            }
            if (preview) preview.style.display = "";
          }

          setTimeout(() => {
            notesStatus.textContent = "";
          }, 2000);
        } else {
          notesStatus.style.color = "#dc3545";
          notesStatus.textContent =
            "❌ Erreur lors de la sauvegarde : " +
            (data.error || "Erreur inconnue");
        }
      },
      (error) => {
        notesStatus.style.color = "#dc3545";
        notesStatus.textContent = auto
          ? "❌ Erreur lors de la sauvegarde automatique."
          : "❌ Erreur réseau lors de la sauvegarde.";
        console.error("Error:", error);
      }
    );
  }

  // ========================================
  // UTILITY METHODS
  // ========================================

  submitForm(formData, onSuccess, onError) {
    const projectMeta = document.getElementById("projectMetaData");
    if (
      projectMeta &&
      projectMeta.dataset.projectName &&
      !formData.has("project_name")
    ) {
      formData.append("project_name", projectMeta.dataset.projectName);
    }
    fetch(window.location.pathname + window.location.search, {
      method: "POST",
      body: formData,
    })
      .then((res) => res.json())
      .then((data) => {
        if (onSuccess) onSuccess(data);
      })
      .catch((error) => {
        if (onError) onError(error);
      });
  }
}

// Collapsible task groups (moved from project.php)
(function () {
  function initTaskGroups() {
    const projectMeta = document.querySelector("[data-project-name]");
    const projectKey = projectMeta ? projectMeta.dataset.projectName : "global";

    document.querySelectorAll(".task-group").forEach((group) => {
      const status = group.dataset.status || "_unknown";
      const toggle = group.querySelector(".task-group-toggle");
      const storageKey = `pm:${projectKey}:group:${status}`;

      // read saved state ('open'|'closed') — default: closed
      const saved = localStorage.getItem(storageKey);
      if (saved === "open") {
        group.classList.remove("collapsed");
        if (toggle) {
          toggle.setAttribute("aria-expanded", "true");
          toggle.textContent = "▾";
        }
      } else {
        group.classList.add("collapsed");
        if (toggle) {
          toggle.setAttribute("aria-expanded", "false");
          toggle.textContent = "▸";
        }
      }

      const header = group.querySelector(".task-group-header");
      const onToggle = () => {
        const nowCollapsed = !group.classList.contains("collapsed");
        group.classList.toggle("collapsed");
        const collapsedNow = group.classList.contains("collapsed");
        if (toggle) {
          toggle.setAttribute("aria-expanded", collapsedNow ? "false" : "true");
          toggle.textContent = collapsedNow ? "▸" : "▾";
        }
        localStorage.setItem(storageKey, collapsedNow ? "closed" : "open");
      };

      if (header) header.addEventListener("click", onToggle);
      if (toggle)
        toggle.addEventListener("click", function (e) {
          e.stopPropagation();
          onToggle();
        });
    });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initTaskGroups);
  } else {
    initTaskGroups();
  }
})();

// Global functions for backwards compatibility
let projectManager;

function openVhostModal() {
  projectManager.openVhostModal();
}

function editVhost(
  displayName,
  servername,
  documentroot,
  description,
  phpVersion,
  databaseName
) {
  projectManager.editVhost(
    displayName,
    servername,
    documentroot,
    description,
    phpVersion,
    databaseName
  );
}

function deleteVhost(servername) {
  projectManager.deleteVhost(servername);
}

function openTaskModal() {
  projectManager.openTaskModal();
}

function editTask(
  taskId,
  title,
  status,
  dueDate,
  priority = 0,
  description = ""
) {
  projectManager.editTask(
    taskId,
    title,
    status,
    dueDate,
    priority,
    description
  );
}

function deleteTask(taskId) {
  projectManager.deleteTask(taskId);
}

function editTaskFromButton(button) {
  const taskId = button.dataset.taskId;
  const title = button.dataset.taskTitle;
  const status = button.dataset.taskStatus;
  const dueDate = button.dataset.taskDueDate;
  const priority = button.dataset.taskPriority || "none";
  const description = button.dataset.taskDescription;

  projectManager.editTask(
    taskId,
    title,
    status,
    dueDate,
    priority,
    description
  );
}

function deleteTaskFromButton(button) {
  const taskId = button.dataset.taskId;
  projectManager.deleteTask(taskId);
}

function openProjectEditModal() {
  projectManager.openProjectEditModal();
}

function confirmDeleteProject() {
  projectManager.confirmDeleteProject();
}

function openExportModal() {
  projectManager.openExportModal();
}

function executeProjectDeletion() {
  projectManager.executeProjectDeletion();
}

function executeVhostDeletion() {
  const vhost = window.currentVhostToDelete;
  if (!vhost) return;

  const deleteFolder = document.getElementById("deleteVhostFolder").checked;
  const deleteDatabase = document.getElementById("deleteVhostDatabase").checked;

  const formData = new FormData();
  formData.append("action", "delete_vhost");
  formData.append("servername", vhost.servername);
  formData.append("delete_folder", deleteFolder ? "1" : "0");
  formData.append("delete_database", deleteDatabase ? "1" : "0");

  projectManager.submitForm(formData, (data) => {
    if (data.success) {
      // Show success message with details
      let message = "VHost supprimé avec succès.";
      if (data.database_deleted) {
        message += `\nBase de données "${data.database_deleted}" supprimée.`;
      }
      if (data.folder_deleted) {
        message += `\nDossier "${data.folder_deleted}" supprimé.`;
      }
      if (data.folder_error) {
        message += `\nErreur lors de la suppression du dossier: ${data.folder_error}`;
      }

      // Show debug info if available
      if (data.debug) {
        console.log("Debug VHost deletion:", data.debug);
      }

      alert(message);
      location.reload();
    } else {
      document.getElementById("vhostDeleteModalMsg").textContent =
        "Erreur lors de la suppression : " + (data.error || "Erreur inconnue");
    }
  });
}

function saveNotes() {
  projectManager.saveNotes();
}

// Initialize when DOM is ready
document.addEventListener("DOMContentLoaded", function () {
  projectManager = new ProjectManager();

  // Hook edit/cancel notes buttons (preview-first UX)
  const editNotesBtn = document.getElementById("editNotesBtn");
  const cancelNotesBtn = document.getElementById("cancelNotesBtn");
  if (editNotesBtn)
    editNotesBtn.onclick = () => projectManager.openNotesEditor();
  if (cancelNotesBtn)
    cancelNotesBtn.onclick = () => projectManager.cancelNotesEdit();

  // Modal close handlers for VHost delete modal
  const vhostDeleteModal = document.getElementById("vhostDeleteModal");
  const closeVhostDeleteModalBtn = document.getElementById(
    "closeVhostDeleteModalBtn"
  );
  const cancelVhostDeleteBtn = document.getElementById("cancelVhostDeleteBtn");

  if (closeVhostDeleteModalBtn) {
    closeVhostDeleteModalBtn.onclick = function () {
      vhostDeleteModal.style.display = "none";
    };
  }

  if (cancelVhostDeleteBtn) {
    cancelVhostDeleteBtn.onclick = function () {
      vhostDeleteModal.style.display = "none";
    };
  }

  // Close modal when clicking outside
  window.onclick = function (event) {
    if (event.target === vhostDeleteModal) {
      vhostDeleteModal.style.display = "none";
    }
  };
});

// Toggle task description visibility
function toggleTaskDescription(button) {
  const taskItem = button.closest(".task-item");
  const descriptionContent = taskItem.querySelector(
    ".task-description-content"
  );

  if (descriptionContent) {
    descriptionContent.classList.toggle("hidden");
    button.classList.toggle("expanded");
  }
}
