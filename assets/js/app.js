document.addEventListener('DOMContentLoaded', function () {
    const navLinks = document.querySelectorAll('[data-nav-target]');
    const sections = {
        dashboard: document.getElementById('dashboard-page'),
        projects: document.getElementById('projects-page'),
        personnel: document.getElementById('personnel-page'),
        attendance: document.getElementById('attendance-page'),
        documents: document.getElementById('documents-page'),
        reports: document.getElementById('reports-page'),
        settings: document.getElementById('settings-page'),
        logs: document.getElementById('logs-page')
    };

    const currentView = document.getElementById('main-content')?.dataset.currentView || window.activeView || 'dashboard';

    function setActiveNav(view) {
        navLinks.forEach((link) => {
            const isActive = link.dataset.navTarget === view;
            link.classList.toggle('active-nav', isActive);
            link.classList.toggle('text-blue-600', isActive);
            link.classList.toggle('font-medium', isActive);
            link.classList.toggle('text-gray-600', !isActive);
        });
    }

    function setActiveSection(view) {
        Object.entries(sections).forEach(([key, section]) => {
            if (!section) {
                return;
            }
            if (key === view) {
                section.classList.remove('hidden');
            } else {
                section.classList.add('hidden');
            }
        });
        setActiveNav(view);
        const mainContent = document.getElementById('main-content');
        if (mainContent) {
            mainContent.dataset.currentView = view;
        }
    }

    setActiveSection(currentView);

    function isMobileViewport() {
        return window.matchMedia('(max-width: 1023px)').matches;
    }

    const sidebarToggle = document.getElementById('sidebar-toggle');
    const sidebar = document.getElementById('sidebar');
    const sidebarOverlay = document.getElementById('sidebar-overlay');
    const mainContent = document.getElementById('main-content');

    function closeSidebar() {
        if (!sidebar) {
            return;
        }

        if (isMobileViewport()) {
            sidebar.classList.remove('is-open');
            sidebarOverlay?.classList.add('hidden');
            document.body.classList.remove('sidebar-open');
            return;
        }

        sidebar.classList.remove('w-64');
        sidebar.classList.add('w-0');
        mainContent?.classList.add('ml-0');
    }

    function openSidebar() {
        if (!sidebar) {
            return;
        }

        if (isMobileViewport()) {
            sidebar.classList.add('is-open');
            sidebarOverlay?.classList.remove('hidden');
            document.body.classList.add('sidebar-open');
            return;
        }

        sidebar.classList.remove('w-0');
        sidebar.classList.add('w-64');
        mainContent?.classList.remove('ml-0');
    }

    navLinks.forEach((link) => {
        link.addEventListener('click', function (event) {
            const view = link.dataset.navTarget;
            const href = link.getAttribute('href') || '';
            if (!view || !sections[view]) {
                return;
            }
            if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0) {
                return;
            }

            event.preventDefault();
            setActiveSection(view);
            window.activeView = view;

            if (href) {
                window.history.replaceState({}, '', href);
            }

            if (isMobileViewport()) {
                closeSidebar();
            }

            window.scrollTo({ top: 0, behavior: 'auto' });
        });
    });

    if (sidebarToggle && sidebar && mainContent) {
        sidebarToggle.addEventListener('click', function () {
            if (isMobileViewport()) {
                if (sidebar.classList.contains('is-open')) {
                    closeSidebar();
                } else {
                    openSidebar();
                }
            } else {
                if (sidebar.classList.contains('w-64')) {
                    closeSidebar();
                } else {
                    openSidebar();
                }
            }
        });
    }

    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', closeSidebar);
    }

    window.addEventListener('resize', function () {
        if (!sidebar) {
            return;
        }

        if (isMobileViewport()) {
            sidebar.classList.remove('w-0');
            sidebar.classList.add('w-64');
            mainContent?.classList.remove('ml-0');
        } else {
            sidebar.classList.remove('is-open');
            sidebarOverlay?.classList.add('hidden');
            document.body.classList.remove('sidebar-open');
        }
    });

    const clock = document.getElementById('live-clock');
    if (clock) {
        setInterval(function () {
            const now = new Date();
            const pad = (value) => String(value).padStart(2, '0');
            clock.textContent = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())} ${pad(now.getHours())}:${pad(now.getMinutes())}:${pad(now.getSeconds())}`;
        }, 1000);
    }

    const addProjectBtn = document.getElementById('add-project-btn');
    const addProjectModal = document.getElementById('add-project-modal');
    const closeModal = document.getElementById('close-modal');
    const cancelProject = document.getElementById('cancel-project');

    function closeProjectModal(event) {
        if (event) {
            event.preventDefault();
        }
        if (addProjectModal) {
            addProjectModal.classList.add('hidden');
        }
    }

    if (addProjectBtn && addProjectModal) {
        addProjectBtn.addEventListener('click', function () {
            addProjectModal.classList.remove('hidden');
        });
    }
    if (closeModal) {
        closeModal.addEventListener('click', closeProjectModal);
    }
    if (cancelProject) {
        cancelProject.addEventListener('click', closeProjectModal);
    }
    if (addProjectModal) {
        addProjectModal.addEventListener('click', function (event) {
            if (event.target === addProjectModal) {
                closeProjectModal();
            }
        });
    }

    const previewModal = document.getElementById('project-preview-modal');
    if (previewModal) {
        previewModal.addEventListener('click', function (event) {
            if (event.target === previewModal) {
                const closeLink = previewModal.querySelector('a[href]');
                if (closeLink) {
                    window.location.href = closeLink.href;
                }
            }
        });
    }

    const selectAllProjects = document.getElementById('projects-select-all');
    const projectCheckboxes = Array.from(document.querySelectorAll('.project-row-checkbox'));
    const selectedCount = document.getElementById('project-selected-count');
    const batchForm = document.getElementById('project-batch-form');
    const batchAction = document.getElementById('project-batch-action');
    const batchSubmit = document.getElementById('project-batch-submit');

    function syncProjectSelectionState() {
        const checkedCount = projectCheckboxes.filter((checkbox) => checkbox.checked).length;
        if (selectedCount) {
            selectedCount.textContent = String(checkedCount);
        }
        if (selectAllProjects) {
            selectAllProjects.checked = checkedCount > 0 && checkedCount === projectCheckboxes.length;
            selectAllProjects.indeterminate = checkedCount > 0 && checkedCount < projectCheckboxes.length;
        }
        if (batchSubmit) {
            batchSubmit.disabled = checkedCount === 0;
        }
    }

    if (selectAllProjects) {
        selectAllProjects.addEventListener('change', function () {
            projectCheckboxes.forEach((checkbox) => {
                checkbox.checked = selectAllProjects.checked;
            });
            syncProjectSelectionState();
        });
    }

    projectCheckboxes.forEach((checkbox) => {
        checkbox.addEventListener('change', syncProjectSelectionState);
    });
    syncProjectSelectionState();

    if (batchForm) {
        batchForm.addEventListener('submit', function (event) {
            const checkedCount = projectCheckboxes.filter((checkbox) => checkbox.checked).length;
            if (checkedCount === 0) {
                event.preventDefault();
                window.alert('请先选择要操作的项目');
                return;
            }
            if (!batchAction || batchAction.value !== 'delete') {
                event.preventDefault();
                window.alert('请选择批量操作');
                return;
            }
            if (!window.confirm(`确认删除选中的 ${checkedCount} 个项目吗？`)) {
                event.preventDefault();
            }
        });
    }

    const projectColumnChooser = document.getElementById('project-column-chooser');
    const projectColumnToggle = document.getElementById('project-column-toggle');
    const projectColumnMenu = document.getElementById('project-column-menu');
    const projectColumnCheckboxes = Array.from(document.querySelectorAll('.project-column-checkbox'));
    const projectEmptyCell = document.querySelector('[data-project-empty]');
    const projectColumnStorageKey = 'projectTableVisibleColumns';

    function updateProjectEmptyColspan() {
        if (!projectEmptyCell) {
            return;
        }
        const visibleColumns = projectColumnCheckboxes.filter((checkbox) => checkbox.checked).length;
        projectEmptyCell.colSpan = visibleColumns + 2;
    }

    function applyProjectColumnVisibility() {
        const visibleColumns = new Set(
            projectColumnCheckboxes.filter((checkbox) => checkbox.checked).map((checkbox) => checkbox.value)
        );

        document.querySelectorAll('[data-project-col]').forEach((cell) => {
            const columnKey = cell.getAttribute('data-project-col');
            cell.classList.toggle('hidden', !visibleColumns.has(columnKey));
        });

        updateProjectEmptyColspan();

        try {
            window.localStorage.setItem(projectColumnStorageKey, JSON.stringify(Array.from(visibleColumns)));
        } catch (error) {
            // Ignore storage write failures.
        }
    }

    if (projectColumnCheckboxes.length > 0) {
        let storedColumns = null;
        try {
            storedColumns = JSON.parse(window.localStorage.getItem(projectColumnStorageKey) || 'null');
        } catch (error) {
            storedColumns = null;
        }

        if (Array.isArray(storedColumns) && storedColumns.length > 0) {
            projectColumnCheckboxes.forEach((checkbox) => {
                checkbox.checked = storedColumns.includes(checkbox.value);
            });
        }

        projectColumnCheckboxes.forEach((checkbox) => {
            checkbox.addEventListener('change', function () {
                const checkedCount = projectColumnCheckboxes.filter((item) => item.checked).length;
                if (checkedCount === 0) {
                    checkbox.checked = true;
                    return;
                }
                applyProjectColumnVisibility();
            });
        });

        applyProjectColumnVisibility();
    }

    if (projectColumnToggle && projectColumnMenu) {
        projectColumnToggle.addEventListener('click', function () {
            const willOpen = projectColumnMenu.classList.contains('hidden');
            projectColumnMenu.classList.toggle('hidden', !willOpen);
            projectColumnToggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
        });

        document.addEventListener('click', function (event) {
            if (!projectColumnChooser || projectColumnChooser.contains(event.target)) {
                return;
            }
            projectColumnMenu.classList.add('hidden');
            projectColumnToggle.setAttribute('aria-expanded', 'false');
        });
    }

    const startInput = document.querySelector('[data-duration-start]');
    const endInput = document.querySelector('[data-duration-end]');
    const output = document.querySelector('[data-duration-output]');
    const updateDuration = function () {
        if (!startInput || !endInput || !output || !startInput.value || !endInput.value) {
            return;
        }
        const start = new Date(startInput.value);
        const end = new Date(endInput.value);
        const diff = Math.floor((end - start) / 86400000) + 1;
        output.value = diff > 0 ? diff : 0;
    };
    if (startInput && endInput) {
        startInput.addEventListener('change', updateDuration);
        endInput.addEventListener('change', updateDuration);
        updateDuration();
    }

    const dashboard = window.dashboardPayload || null;
    if (!dashboard || typeof Chart === 'undefined') {
        return;
    }

    const regionEntries = Object.entries(dashboard.charts?.regions || {});
    const monthlyEntries = Object.entries(dashboard.charts?.monthly || {});
    const recentProjects = dashboard.recent_projects || [];
    const projectNames = recentProjects.map((item) => item.project_name);
    const projectDurations = recentProjects.map((item) => Number(item.duration_days || 0));

    const attendanceRows = dashboard.attendance?.rows || [];
    const personnelNames = attendanceRows.map((item) => item.name);
    const loadDays = attendanceRows.map((item) => Number(item.load_days || 0));

    function drawChart(id, config) {
        const canvas = document.getElementById(id);
        if (!canvas) {
            return;
        }
        new Chart(canvas.getContext('2d'), config);
    }

    const commonLegend = {
        position: 'bottom',
        labels: {
            padding: 20,
            usePointStyle: true
        }
    };

    const regionConfig = {
        type: 'doughnut',
        data: {
            labels: regionEntries.map((item) => item[0]),
            datasets: [{
                data: regionEntries.map((item) => item[1]),
                backgroundColor: ['#3B82F6', '#10B981', '#F59E0B', '#8B5CF6', '#EF4444'],
                borderWidth: 2,
                borderColor: '#ffffff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: commonLegend }
        }
    };

    drawChart('region-chart', regionConfig);
    drawChart('region-chart-report', regionConfig);

    drawChart('hours-chart', {
        type: 'bar',
        data: {
            labels: projectNames,
            datasets: [{
                label: '项目工期（天）',
                data: projectDurations,
                backgroundColor: '#3B82F6',
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
        }
    });

    const monthlyConfig = {
        type: 'line',
        data: {
            labels: monthlyEntries.map((item) => item[0]),
            datasets: [{
                label: '计划项目',
                data: monthlyEntries.map((item) => item[1]),
                borderColor: '#3B82F6',
                backgroundColor: 'rgba(59, 130, 246, 0.12)',
                tension: 0.35,
                fill: true
            }, {
                label: '已完成项目',
                data: monthlyEntries.map((item) => {
                    const month = item[0];
                    return recentProjects.filter((project) => project.start_date?.slice(0, 7) === month && Number(project.completion_flag || 0) === 1).length;
                }),
                borderColor: '#10B981',
                backgroundColor: 'rgba(16, 185, 129, 0.08)',
                tension: 0.35,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'top' } },
            scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
        }
    };

    drawChart('completion-trend-chart', monthlyConfig);
    drawChart('completion-trend-chart-report', monthlyConfig);

    drawChart('workload-chart', {
        type: 'bar',
        data: {
            labels: personnelNames,
            datasets: [{
                label: '本周占用天数',
                data: loadDays,
                backgroundColor: '#10B981',
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'top' } },
            scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
        }
    });

});
