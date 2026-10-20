<?php
/**
 * Student Dashboard Header & Sidebar Include
 *
 * Shared layout for all student pages:
 * dashboard, results, analytics, profile
 *
 * Required globals/variables:
 *   $firstName     - Student first name
 *   $student       - Student data array
 *   $currentPage   - Current page name
 */

if (!isset($currentPage)) {
    $currentPage = basename($_SERVER['PHP_SELF'], '.php');
}

/*
 * Map page file names to navigation items
 */
$pageToNavMap = [
    'dashboard' => 'dashboard',
    'my-tests'  => 'my-tests' ,
    'results'   => 'results',
    'analytics' => 'analytics',
    'profile'   => 'profile',
    'test'      => 'dashboard',
];

$currentNav = $pageToNavMap[$currentPage] ?? $currentPage;
?>
<script>
(function() {
    var saved = localStorage.getItem('theme') || (document.cookie.match(/(^|; )theme=([^;]+)/) || [])[2] || 'light';
    if (saved) {
        document.documentElement.setAttribute('data-theme', saved);
    }
})();

window.updateThemeUI = function(theme) {
    var isDark = theme === 'dark';
    var label = document.getElementById('themeLabel');
    if (label) label.textContent = isDark ? 'Light Mode' : 'Dark Mode';
    var sunSvg = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-sun" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/></svg>';
    var moonSvg = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-moon" aria-hidden="true"><path d="M20.985 12.486a9 9 0 1 1-9.473-9.472c.405-.022.617.46.402.803a6 6 0 0 0 8.268 8.268c.344-.215.825-.004.803.401"/></svg>';
    document.querySelectorAll('.theme-icon').forEach(function(el) {
        el.innerHTML = isDark ? sunSvg : moonSvg;
    });

    var newAriaLabel = isDark ? 'Switch to light mode' : 'Switch to dark mode';
    document.querySelectorAll('button.topnav-icon-btn').forEach(function(btn) {
        btn.setAttribute('aria-label', newAriaLabel);
    });
    var sidebarThemeBtn = document.getElementById('themeToggle');
    if (sidebarThemeBtn) {
        sidebarThemeBtn.setAttribute('aria-label', newAriaLabel);
    }
};

window.toggleTheme = function() {
    var html = document.documentElement;
    var isDark = html.getAttribute('data-theme') === 'dark';
    var newTheme = isDark ? 'light' : 'dark';
    html.setAttribute('data-theme', newTheme);
    localStorage.setItem('theme', newTheme);
    document.cookie = 'theme=' + newTheme + ';path=/;max-age=31536000;SameSite=Lax';
    window.updateThemeUI(newTheme);
    if (typeof applyChartTheme === 'function') {
        applyChartTheme(newTheme === 'dark');
    }
};

window.toggleSidebar = function(forceState) {
    var sidebar = document.getElementById('sidebar');
    var overlay = document.getElementById('sidebarOverlay');
    if (!sidebar || !overlay) return;
    var isOpen = forceState !== undefined ? forceState : !sidebar.classList.contains('open');
    sidebar.classList.toggle('open', isOpen);
    overlay.classList.toggle('show', isOpen);
    document.body.classList.toggle('sidebar-open', isOpen);
    sidebar.setAttribute('aria-hidden', !isOpen);
};

window.closeSidebar = function() {
    window.toggleSidebar(false);
};

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        window.closeSidebar();
    }
});

document.addEventListener('DOMContentLoaded', function() {
    var saved = localStorage.getItem('theme') || (document.cookie.match(/(^|; )theme=([^;]+)/) || [])[2] || 'light';
    document.documentElement.setAttribute('data-theme', saved);
    window.updateThemeUI(saved);
});
</script>

<div class="dashboard-layout" id="app">

    <!-- =========================================
         MOBILE SIDEBAR OVERLAY
    ========================================== -->
    <div
        class="sidebar-overlay"
        id="sidebarOverlay"
        onclick="toggleSidebar()"
    ></div>


    <!-- =========================================
         LEFT SIDEBAR
    ========================================== -->
    <aside class="dashboard-sidebar" id="sidebar">

        <!-- Logo -->
        <a
            href="<?= BASE_URL ?>/student/dashboard"
            class="sidebar-logo"
            style="text-decoration: none;"
        >

            <?php if (!empty($student['college_logo'])): ?>

                <div
                    class="sidebar-logo-icon"
                    style="background: rgba(255,255,255,0.05); padding: 4px;"
                >

                    <img
                        src="<?= h($student['college_logo']) ?>"
                        alt="<?= h($student['college_name']) ?>"
                        style="
                            width: 36px;
                            height: 36px;
                            object-fit: contain;
                            border-radius: 8px;
                        "
                        onerror="
                            this.style.display='none';
                            this.nextElementSibling.style.display='flex';
                        "
                    >

                    <div
                        class="sidebar-logo-icon-fallback"
                        style="display: none;"
                    >
                        <svg
                            viewBox="0 0 20 20"
                            fill="currentColor"
                        >
                            <path d="M10 2.5a.5.5 0 0 1 .28-.46l7-3.5a.5.5 0 0 1 .44 0l7 3.5a.5.5 0 0 1 .28.46v12a.5.5 0 0 1-1 0V3.2l-6.5 3.25V15.5a.5.5 0 0 1-.5.5h-13a.5.5 0 0 1-.5-.5v-13z"/>
                        </svg>
                    </div>

                </div>

            <?php else: ?>

                <div class="sidebar-logo-icon">

                    <svg
                        viewBox="0 0 20 20"
                        fill="currentColor"
                    >
                        <path d="M10 2.5a.5.5 0 0 1 .28-.46l7-3.5a.5.5 0 0 1 .44 0l7 3.5a.5.5 0 0 1 .28.46v12a.5.5 0 0 1-1 0V3.2l-6.5 3.25V15.5a.5.5 0 0 1-.5.5h-13a.5.5 0 0 1-.5-.5v-13z"/>
                    </svg>

                </div>

            <?php endif; ?>

            <span class="sidebar-logo-text">
                <?= h($student['college_name'] ?? 'Test Platform') ?>
            </span>

        </a>


        <!-- =========================================
             PRIMARY NAVIGATION
        ========================================== -->
        <nav class="sidebar-nav">

            <div class="sidebar-nav-group">

                <!-- Dashboard -->
                <a
                    href="<?= BASE_URL ?>/student/dashboard"
                    class="sidebar-nav-item <?= $currentNav === 'dashboard' ? 'active' : '' ?>"
                >
                    <?= icon('dashboard', 20) ?>
                    <span>Dashboard</span>
                </a>


                <!-- My Tests -->
                <a
                    href="<?= BASE_URL ?>/student/my-tests"
                    class="sidebar-nav-item <?= $currentNav === 'my-tests' ? 'active' : '' ?>"
                >
                    <?= icon('test', 20) ?>
                    <span>My Tests</span>
                </a>


                <!-- Results -->
                <a
                    href="<?= BASE_URL ?>/student/results"
                    class="sidebar-nav-item <?= $currentNav === 'results' ? 'active' : '' ?>"
                >
                    <?= icon('chart', 20) ?>
                    <span>Results</span>
                </a>


                <!-- Analytics -->
                <a
                    href="<?= BASE_URL ?>/student/analytics"
                    class="sidebar-nav-item <?= $currentNav === 'analytics' ? 'active' : '' ?>"
                >
                    <?= icon('graph', 20) ?>
                    <span>Analytics</span>
                </a>


                <!-- Profile -->
                <a
                    href="<?= BASE_URL ?>/student/profile"
                    class="sidebar-nav-item <?= $currentNav === 'profile' ? 'active' : '' ?>"
                >
                    <?= icon('student', 20) ?>
                    <span>Profile</span>
                </a>

            </div>


            <!-- =========================================
                 THEME SWITCHER
            ========================================== -->
            <div class="sidebar-nav-group">

                <div class="sidebar-nav-label">
                    Appearance
                </div>

                <button
                    class="sidebar-nav-item theme-toggle"
                    onclick="toggleTheme()"
                    id="themeToggle"
                    type="button"
                    aria-label="Switch to dark mode"
                >
                    <span class="theme-icon" aria-hidden="true">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-moon" aria-hidden="true"><path d="M20.985 12.486a9 9 0 1 1-9.473-9.472c.405-.022.617.46.402.803a6 6 0 0 0 8.268 8.268c.344-.215.825-.004.803.401"/></svg>
                    </span>

                    <span id="themeLabel">
                        Dark Mode
                    </span>
                </button>

            </div>


            <!-- =========================================
                 SIGN OUT
            ========================================== -->
            <div
                class="sidebar-nav-group"
                style="margin-top: auto;"
            >

                <a
                    href="<?= BASE_URL ?>/logout"
                    class="sidebar-nav-item"
                >
                    <?= icon('logout', 20) ?>
                    <span>Sign Out</span>
                </a>

            </div>

        </nav>


        <!-- =========================================
             PROFILE FOOTER
        ========================================== -->
        <div class="sidebar-profile">

            <div class="sidebar-profile-avatar">

                <?= strtoupper($firstName[0] ?? '?') ?>

                <span class="online-dot"></span>

            </div>

            <div class="sidebar-profile-info">

                <div class="sidebar-profile-name">
                    <?= h($firstName ?? 'Student') ?>
                </div>

                <div class="sidebar-profile-role">
                    Student
                </div>

            </div>

        </div>

    </aside>


    <!-- =========================================
         MAIN CONTENT AREA
    ========================================== -->
    <div class="dashboard-main">


        <!-- =========================================
             TOP NAVIGATION
        ========================================== -->
        <header class="dashboard-topnav">

            <div class="topnav-left">

                <!-- Mobile hamburger -->
                <button
                    class="topnav-hamburger"
                    onclick="toggleSidebar()"
                    aria-label="Toggle sidebar"
                    type="button"
                >
                    <?= icon('menu', 20) ?>
                </button>

                <div class="topnav-brand">
                    Student Portal
                </div>

            </div>


            <div class="topnav-right">

                <!-- Theme button -->
                <button
                    class="topnav-icon-btn"
                    onclick="toggleTheme()"
                    data-tooltip="Toggle theme"
                    type="button"
                    aria-label="Switch to dark mode"
                >
                    <span class="theme-icon" aria-hidden="true">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-moon" aria-hidden="true"><path d="M20.985 12.486a9 9 0 1 1-9.473-9.472c.405-.022.617.46.402.803a6 6 0 0 0 8.268 8.268c.344-.215.825-.004.803.401"/></svg>
                    </span>
                </button>


                <!-- Profile -->
                <div class="topnav-profile">

                    <div class="topnav-avatar">

                        <?= strtoupper($firstName[0] ?? '?') ?>

                        <span class="online-dot"></span>

                    </div>

                    <div class="topnav-profile-info">

                        <div class="topnav-profile-name">
                            <?= h($firstName ?? 'Student') ?>
                        </div>

                        <div class="topnav-profile-role">
                            Student
                        </div>

                    </div>

                </div>

            </div>

        </header>


        <!-- =========================================
             AI STUDY ASSISTANT
        ========================================== -->
        <div class="chatbot-container">

            <!-- Floating AI button -->
            <button
                class="chatbot-toggle"
                id="chatbotToggle"
                type="button"
                aria-label="Open AI Assistant"
            >
                <span class="chatbot-icon-svg" aria-hidden="true">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 8V4H8"/><rect width="16" height="12" x="4" y="8" rx="2"/><path d="M2 14h2"/><path d="M20 14h2"/><path d="M15 13v2"/><path d="M9 13v2"/></svg>
                </span>
            </button>


            <!-- =====================================
                 CHAT WINDOW
            ====================================== -->
            <div
                class="chatbot-window"
                id="chatbotWindow"
                role="dialog"
                aria-label="AI Study Assistant"
            >

                <!-- Chat Header -->
                <div class="chatbot-header">

                    <div class="chatbot-header-info">

                        <div class="chatbot-avatar">

                            <span class="chatbot-icon-svg" aria-hidden="true">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 8V4H8"/><rect width="16" height="12" x="4" y="8" rx="2"/><path d="M2 14h2"/><path d="M20 14h2"/><path d="M15 13v2"/><path d="M9 13v2"/></svg>
                            </span>

                        </div>

                        <div>

                            <div class="chatbot-title">
                                AI Study Assistant
                            </div>

                            <div class="chatbot-status">
                                Academic help
                            </div>

                        </div>

                    </div>


                    <!-- Close -->
                    <button
                        class="chatbot-close"
                        id="chatbotClose"
                        type="button"
                        aria-label="Close AI Assistant"
                    >
                        <span class="chatbot-icon-svg" aria-hidden="true">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                        </span>
                    </button>

                </div>


                <!-- =====================================
                     MESSAGES
                ====================================== -->
                <div
                    class="chatbot-messages"
                    id="chatbotMessages"
                >

                    <div class="chatbot-message bot">
                        Hey! 👋 I'm your AI Study Assistant.
                        Ask me about your performance, PCI,
                        weaknesses, or what you should study next.
                    </div>

                </div>


                <!-- =====================================
                     QUICK ACTIONS
                ====================================== -->
                <div class="chatbot-actions">

                    <button
                        class="chatbot-action"
                        type="button"
                        data-message="Explain my weaknesses"
                    >
                        My Weaknesses
                    </button>


                    <button
                        class="chatbot-action"
                        type="button"
                        data-message="Explain my PCI"
                    >
                        What is my PCI?
                    </button>


                    <button
                        class="chatbot-action"
                        type="button"
                        data-message="What should I study?"
                    >
                        What should I study?
                    </button>


                    <button
                        class="chatbot-action"
                        type="button"
                        data-message="Generate my report"
                    >
                        Generate My Report
                    </button>

                </div>


                <!-- =====================================
                     INPUT AREA
                ====================================== -->
                <div class="chatbot-input-area">

                    <input
                        type="text"
                        id="chatbotInput"
                        class="chatbot-input"
                        placeholder="Ask me anything..."
                        maxlength="2000"
                        autocomplete="off"
                        aria-label="Ask the AI Study Assistant"
                    >


                    <button
                        class="chatbot-send"
                        id="chatbotSend"
                        type="button"
                        aria-label="Send message"
                    >

                        <span class="chatbot-icon-svg" aria-hidden="true">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg>
                        </span>

                    </button>

                </div>

            </div>

        </div>


        <!-- =========================================
             MAIN PAGE CONTENT
        ========================================== -->
        <main class="dashboard-content">

            <div class="dashboard-content-inner">