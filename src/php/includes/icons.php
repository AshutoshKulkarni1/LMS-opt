<?php
/**
 * Enterprise Icon System — Apple SF Symbols → Lucide → Material Symbols
 * 
 * RENDER STRATEGY (solves the deferred-Lucide-not-loaded problem):
 * 
 * 1. Material Symbols font is loaded in <head> (synchronous CSS, renders immediately)
 * 2. Every icon() call outputs a <span> containing BOTH:
 *    - <i data-lucide="...">  → replaced with SVG when Lucide JS loads
 *    - <span class="icn-fallback material-symbols-outlined"> → visible IMMEDIATELY
 * 3. CSS hides the Material fallback once the Lucide <svg> sibling appears
 * 4. If Lucide never loads (JS fails/deferred), Material Symbols remain visible
 * 
 * This guarantees: NO EMPTY ICON CONTAINERS, EVER.
 * 
 * Priority: Apple SF Symbols naming → Lucide (rendered) → Material (fallback)
 * 
 * Usage:
 *   <?= icon('file-text') ?>              ← size 20, default color
 *   <?= icon('check-circle', 24) ?>        ← size 24
 *   <?= icon('clock', 20, '#F59E0B') ?>    ← custom color
 */

function icon(string $name, int $size = 20, string $color = 'currentColor', string $className = ''): string {
    $def = getIconDef($name);
    $resolvedColor = resolveIconColor($color);
    $sizeAttr = h((string)$size);
    $safeColor = h($resolvedColor);
    $lucideName = $def['lucide'] ?? 'square';

    $svgDict = getLucideSvgDictionary();
    $svgBody = $svgDict[$lucideName] ?? null;

    // Wrapper — sizes the icon container, holds the SVG or Material fallback
    $html = '<span class="icn"';
    $html .= ' style="--icn-s:' . $sizeAttr . 'px;--icn-c:' . $safeColor . ';"';
    $html .= ' role="img" aria-label="' . h($def['label']) . '"';
    if ($className) {
        $html .= ' class="' . h($className) . '"';
    }
    $html .= '>';

    if ($svgBody !== null) {
        // Native inline SVG — rendered server-side, 0 JS runtime dependency, matches existing CSS selectors
        $html .= '<svg xmlns="http://www.w3.org/2000/svg"';
        $html .= ' width="' . $sizeAttr . '" height="' . $sizeAttr . '"';
        $html .= ' viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"';
        $html .= ' stroke-linecap="round" stroke-linejoin="round"';
        $html .= ' class="lucide lucide-' . h($lucideName) . '"';
        $html .= ' data-lucide="' . h($lucideName) . '"';
        $html .= ' aria-hidden="true">';
        $html .= $svgBody;
        $html .= '</svg>';
    } else {
        // Fallback for unmapped icons: placeholder + Material Symbols fallback
        $colorAttr = $resolvedColor !== 'currentColor' ? ' color="' . $safeColor . '"' : '';
        $html .= '<i data-lucide="' . h($lucideName) . '"';
        $html .= ' width="' . $sizeAttr . '" height="' . $sizeAttr . '"';
        $html .= $colorAttr;
        $html .= '></i>';
        $html .= '<span class="icn-fallback material-symbols-outlined" aria-hidden="true">';
        $html .= h($def['material']);
        $html .= '</span>';
    }

    $html .= '</span>';
    return $html;
}

function iconSprite(): string {
    return '';
}

/**
 * Resolve CSS variable colors to hex for inline use.
 */
function resolveIconColor(string $color): string {
    static $map = [
        'var(--green)'  => '#22C55E',
        'var(--red)'    => '#EF4444',
        'var(--accent)' => '#4F8CFF',
        'var(--yellow)' => '#F59E0B',
        'var(--orange)' => '#F97316',
        'var(--purple)' => '#7C3AED',
        'var(--info)'   => '#06B6D4',
        'var(--white)'  => '#FFFFFF',
    ];
    return $map[$color] ?? $color;
}

/**
 * Get icon definition array.
 * Maps friendly/SF Symbols names → Lucide name → Material name → aria-label
 */
function getIconDef(string $name): array {
    static $defs = null;
    if ($defs === null) {
        $defs = getIconDefinitions();
    }
    return $defs[$name] ?? $defs['fallback'];
}

/**
 * Master icon definition table.
 * Key = friendly name used in icon('name') calls.
 * Can be SF Symbols style (doc.text.fill) or short form (file-text).
 */
function getIconDefinitions(): array {
    return [

        // ── FALLBACK (when name not found) ────────────────
        'fallback' => [
            'lucide'   => 'square',
            'material' => 'square',
            'label'    => 'icon',
        ],

        // ── SIDEBAR / NAVIGATION ──────────────────────────
        'dashboard' => [
            'lucide'   => 'layout-dashboard',
            'material' => 'dashboard',
            'label'    => 'Dashboard',
        ],
        'layout-dashboard' => [
            'lucide'   => 'layout-dashboard',
            'material' => 'dashboard',
            'label'    => 'Dashboard',
        ],
        'college' => [
            'lucide'   => 'building2',
            'material' => 'business',
            'label'    => 'College',
        ],
        'building2' => [
            'lucide'   => 'building2',
            'material' => 'business',
            'label'    => 'College',
        ],
        'course' => [
            'lucide'   => 'book-open',
            'material' => 'book',
            'label'    => 'Course',
        ],
        'book-open' => [
            'lucide'   => 'book-open',
            'material' => 'book',
            'label'    => 'Course',
        ],
        'batch' => [
            'lucide'   => 'layers',
            'material' => 'layers',
            'label'    => 'Batch',
        ],
        'layers' => [
            'lucide'   => 'layers',
            'material' => 'layers',
            'label'    => 'Batch',
        ],
        'student' => [
            'lucide'   => 'graduation-cap',
            'material' => 'school',
            'label'    => 'Student',
        ],
        'graduation-cap' => [
            'lucide'   => 'graduation-cap',
            'material' => 'school',
            'label'    => 'Student',
        ],
        'test' => [
            'lucide'   => 'file-text',
            'material' => 'description',
            'label'    => 'Test',
        ],
        'file-text' => [
            'lucide'   => 'file-text',
            'material' => 'description',
            'label'    => 'File',
        ],
        'doc.text.fill' => [
            'lucide'   => 'file-text',
            'material' => 'description',
            'label'    => 'Document',
        ],
        'grading' => [
            'lucide'   => 'clipboard-check',
            'material' => 'fact_check',
            'label'    => 'Grading',
        ],
        'clipboard-check' => [
            'lucide'   => 'clipboard-check',
            'material' => 'fact_check',
            'label'    => 'Graded',
        ],
        'reports' => [
            'lucide'   => 'chart-bar-big',
            'material' => 'bar_chart',
            'label'    => 'Reports',
        ],
        'chart-bar-big' => [
            'lucide'   => 'chart-bar-big',
            'material' => 'bar_chart',
            'label'    => 'Chart',
        ],
        'settings' => [
            'lucide'   => 'settings',
            'material' => 'settings',
            'label'    => 'Settings',
        ],
        'notifications' => [
            'lucide'   => 'bell',
            'material' => 'notifications',
            'label'    => 'Notifications',
        ],
        'bell' => [
            'lucide'   => 'bell',
            'material' => 'notifications',
            'label'    => 'Notifications',
        ],
        'search' => [
            'lucide'   => 'search',
            'material' => 'search',
            'label'    => 'Search',
        ],
        'menu' => [
            'lucide'   => 'menu',
            'material' => 'menu',
            'label'    => 'Menu',
        ],

        // ── ACTIONS ───────────────────────────────────────
        'plus' => [
            'lucide'   => 'circle-plus',
            'material' => 'add_circle',
            'label'    => 'Add',
        ],
        'circle-plus' => [
            'lucide'   => 'circle-plus',
            'material' => 'add_circle',
            'label'    => 'Add',
        ],
        'edit' => [
            'lucide'   => 'square-pen',
            'material' => 'edit',
            'label'    => 'Edit',
        ],
        'square-pen' => [
            'lucide'   => 'square-pen',
            'material' => 'edit',
            'label'    => 'Edit',
        ],
        'trash' => [
            'lucide'   => 'trash-2',
            'material' => 'delete',
            'label'    => 'Delete',
        ],
        'trash-2' => [
            'lucide'   => 'trash-2',
            'material' => 'delete',
            'label'    => 'Delete',
        ],
        'copy' => [
            'lucide'   => 'copy',
            'material' => 'content_copy',
            'label'    => 'Copy',
        ],
        'filter' => [
            'lucide'   => 'filter',
            'material' => 'filter_list',
            'label'    => 'Filter',
        ],
        'refresh' => [
            'lucide'   => 'refresh-cw',
            'material' => 'refresh',
            'label'    => 'Refresh',
        ],
        'refresh-cw' => [
            'lucide'   => 'refresh-cw',
            'material' => 'refresh',
            'label'    => 'Refresh',
        ],

        // ── NAVIGATION ────────────────────────────────────
        'arrow.right' => [
            'lucide'   => 'arrow-right',
            'material' => 'arrow_forward',
            'label'    => 'Next',
        ],
        'arrow-down' => [
            'lucide'   => 'arrow-down',
            'material' => 'arrow_downward',
            'label'    => 'Down',
        ],
        'arrow-up' => [
            'lucide'   => 'arrow-up',
            'material' => 'arrow_upward',
            'label'    => 'Up',
        ],
        'arrow-right-circle-fill' => [
            'lucide'   => 'arrow-right-circle',
            'material' => 'arrow_forward',
            'label'    => 'Go',
        ],
        'chevron.right' => [
            'lucide'   => 'chevron-right',
            'material' => 'chevron_right',
            'label'    => 'Next',
        ],
        'chevron.down' => [
            'lucide'   => 'chevron-down',
            'material' => 'expand_more',
            'label'    => 'Expand',
        ],
        'checkmark' => [
            'lucide'   => 'check',
            'material' => 'check',
            'label'    => 'Check',
        ],
        'more-vertical' => [
            'lucide'   => 'more-vertical',
            'material' => 'more_vert',
            'label'    => 'More',
        ],
        'arrow-right' => [
            'lucide'   => 'arrow-right',
            'material' => 'arrow_forward',
            'label'    => 'Next',
        ],
        'arrow-left' => [
            'lucide'   => 'arrow-left',
            'material' => 'arrow_back',
            'label'    => 'Back',
        ],
        'chevron-right' => [
            'lucide'   => 'chevron-right',
            'material' => 'chevron_right',
            'label'    => 'Next',
        ],
        'chevron-left' => [
            'lucide'   => 'chevron-left',
            'material' => 'chevron_left',
            'label'    => 'Back',
        ],
        'chevron-down' => [
            'lucide'   => 'chevron-down',
            'material' => 'expand_more',
            'label'    => 'Expand',
        ],
        'external-link' => [
            'lucide'   => 'external-link',
            'material' => 'open_in_new',
            'label'    => 'Open',
        ],
        'logout' => [
            'lucide'   => 'log-out',
            'material' => 'logout',
            'label'    => 'Sign Out',
        ],
        'log-out' => [
            'lucide'   => 'log-out',
            'material' => 'logout',
            'label'    => 'Sign Out',
        ],
        'login' => [
            'lucide'   => 'log-in',
            'material' => 'login',
            'label'    => 'Sign In',
        ],
        'log-in' => [
            'lucide'   => 'log-in',
            'material' => 'login',
            'label'    => 'Sign In',
        ],
        'arrow.right.circle.fill' => [
            'lucide'   => 'arrow-right-circle',
            'material' => 'arrow_forward',
            'label'    => 'Go',
        ],
        'arrow-right-circle' => [
            'lucide'   => 'arrow-right-circle',
            'material' => 'arrow_forward',
            'label'    => 'Go',
        ],

        // ── MEDIA / PLAYER ────────────────────────────────
        'play' => [
            'lucide'   => 'play',
            'material' => 'play_arrow',
            'label'    => 'Play',
        ],
        'pause' => [
            'lucide'   => 'pause',
            'material' => 'pause',
            'label'    => 'Pause',
        ],
        'stop' => [
            'lucide'   => 'square',
            'material' => 'stop',
            'label'    => 'Stop',
        ],

        // ── STATUS / ALERTS ───────────────────────────────
        'check' => [
            'lucide'   => 'check',
            'material' => 'check',
            'label'    => 'Check',
        ],
        'check-circle' => [
            'lucide'   => 'check-circle',
            'material' => 'check_circle',
            'label'    => 'Success',
        ],
        'checkmark.circle.fill' => [
            'lucide'   => 'check-circle',
            'material' => 'check_circle',
            'label'    => 'Completed',
        ],
        'badge-check' => [
            'lucide'   => 'badge-check',
            'material' => 'verified',
            'label'    => 'Verified',
        ],
        'x' => [
            'lucide'   => 'x',
            'material' => 'close',
            'label'    => 'Close',
        ],
        'info' => [
            'lucide'   => 'info',
            'material' => 'info',
            'label'    => 'Info',
        ],
        'warning' => [
            'lucide'   => 'triangle-alert',
            'material' => 'warning',
            'label'    => 'Warning',
        ],
        'triangle-alert' => [
            'lucide'   => 'triangle-alert',
            'material' => 'warning',
            'label'    => 'Warning',
        ],
        'question-circle' => [
            'lucide'   => 'circle-help',
            'material' => 'help',
            'label'    => 'Help',
        ],
        'circle-help' => [
            'lucide'   => 'circle-help',
            'material' => 'help',
            'label'    => 'Help',
        ],
        'help' => [
            'lucide'   => 'circle-help',
            'material' => 'help',
            'label'    => 'Help',
        ],
        'shield' => [
            'lucide'   => 'shield',
            'material' => 'shield',
            'label'    => 'Shield',
        ],

        // ── DATA / CHARTS ─────────────────────────────────
        'chart' => [
            'lucide'   => 'chart-bar-big',
            'material' => 'bar_chart',
            'label'    => 'Chart',
        ],
        'graph' => [
            'lucide'   => 'chart-line',
            'material' => 'show_chart',
            'label'    => 'Graph',
        ],
        'chart-line' => [
            'lucide'   => 'chart-line',
            'material' => 'show_chart',
            'label'    => 'Chart',
        ],
        'activity' => [
            'lucide'   => 'activity',
            'material' => 'pulse',
            'label'    => 'Activity',
        ],
        'status' => [
            'lucide'   => 'circle-dot',
            'material' => 'circle',
            'label'    => 'Status',
        ],
        'circle-dot' => [
            'lucide'   => 'circle-dot',
            'material' => 'circle',
            'label'    => 'Status',
        ],
        'data-usage' => [
            'lucide'   => 'hard-drive',
            'material' => 'storage',
            'label'    => 'Data',
        ],
        'database' => [
            'lucide'   => 'database',
            'material' => 'database',
            'label'    => 'Database',
        ],
        'globe' => [
            'lucide'   => 'globe',
            'material' => 'public',
            'label'    => 'Global',
        ],
        'code' => [
            'lucide'   => 'code',
            'material' => 'code',
            'label'    => 'Code',
        ],
        'flag' => [
            'lucide'   => 'flag',
            'material' => 'flag',
            'label'    => 'Flag',
        ],
        'tag' => [
            'lucide'   => 'tag',
            'material' => 'label',
            'label'    => 'Tag',
        ],
        'folder' => [
            'lucide'   => 'folder',
            'material' => 'folder',
            'label'    => 'Folder',
        ],
        'file' => [
            'lucide'   => 'file',
            'material' => 'insert_drive_file',
            'label'    => 'File',
        ],
        'document' => [
            'lucide'   => 'file-text',
            'material' => 'description',
            'label'    => 'Document',
        ],
        'clipboard' => [
            'lucide'   => 'clipboard',
            'material' => 'content_paste',
            'label'    => 'Clipboard',
        ],
        'timer' => [
            'lucide'   => 'timer',
            'material' => 'timer',
            'label'    => 'Timer',
        ],
        'clock' => [
            'lucide'   => 'clock',
            'material' => 'schedule',
            'label'    => 'Time',
        ],
        'clock3' => [
            'lucide'   => 'clock-3',
            'material' => 'schedule',
            'label'    => 'Clock',
        ],
        'clock.badge.exclamationmark.fill' => [
            'lucide'   => 'clock-3',
            'material' => 'pending_actions',
            'label'    => 'Pending',
        ],
        'pending_actions' => [
            'lucide'   => 'clock-3',
            'material' => 'pending_actions',
            'label'    => 'Pending',
        ],
        'calendar' => [
            'lucide'   => 'calendar',
            'material' => 'calendar_month',
            'label'    => 'Calendar',
        ],
        'calendar-days' => [
            'lucide'   => 'calendar-days',
            'material' => 'calendar_month',
            'label'    => 'Calendar',
        ],
        'calendar.circle.fill' => [
            'lucide'   => 'calendar-days',
            'material' => 'calendar_month',
            'label'    => 'Date',
        ],
        'star' => [
            'lucide'   => 'star',
            'material' => 'star',
            'label'    => 'Star',
        ],

        // ── SYSTEM ────────────────────────────────────────
        'user' => [
            'lucide'   => 'user',
            'material' => 'person',
            'label'    => 'User',
        ],
        'users' => [
            'lucide'   => 'users',
            'material' => 'group',
            'label'    => 'Users',
        ],
        'mail' => [
            'lucide'   => 'mail',
            'material' => 'mail',
            'label'    => 'Email',
        ],
        'phone' => [
            'lucide'   => 'phone',
            'material' => 'phone',
            'label'    => 'Phone',
        ],
        'lock' => [
            'lucide'   => 'lock',
            'material' => 'lock',
            'label'    => 'Lock',
        ],
        'eye' => [
            'lucide'   => 'eye',
            'material' => 'visibility',
            'label'    => 'View',
        ],
        'eye-off' => [
            'lucide'   => 'eye-off',
            'material' => 'visibility_off',
            'label'    => 'Hide',
        ],
        'download' => [
            'lucide'   => 'download',
            'material' => 'download',
            'label'    => 'Download',
        ],
        'upload' => [
            'lucide'   => 'upload',
            'material' => 'upload',
            'label'    => 'Upload',
        ],
        'lightbulb' => [
            'lucide'   => 'lightbulb',
            'material' => 'lightbulb',
            'label'    => 'Tip',
        ],
        'moon' => [
            'lucide'   => 'moon',
            'material' => 'dark_mode',
            'label'    => 'Dark Mode',
        ],

        // ── LAYOUT ────────────────────────────────────────
        'grid' => [
            'lucide'   => 'grid-3x3',
            'material' => 'grid_view',
            'label'    => 'Grid',
        ],
        'grid-3x3' => [
            'lucide'   => 'grid-3x3',
            'material' => 'grid_view',
            'label'    => 'Grid',
        ],
        'list' => [
            'lucide'   => 'list',
            'material' => 'list',
            'label'    => 'List',
        ],

        // ── PROFILE / MISC ────────────────────────────────
        'id-card' => [
            'lucide'   => 'badge-check',
            'material' => 'badge',
            'label'    => 'ID',
        ],
        'badge' => [
            'lucide'   => 'badge',
            'material' => 'badge',
            'label'    => 'Badge',
        ],
        'git-branch' => [
            'lucide'   => 'git-branch',
            'material' => 'alt_route',
            'label'    => 'Branch',
        ],
        'tray' => [
            'lucide'   => 'tray',
            'material' => 'inbox',
            'label'    => 'Inbox',
        ],
        'tray.fill' => [
            'lucide'   => 'tray',
            'material' => 'inbox',
            'label'    => 'Inbox',
        ],

        // ── EXTRA DASHBOARD ICONS ─────────────────────────
        'circle' => [
            'lucide'   => 'circle',
            'material' => 'circle',
            'label'    => 'Circle',
        ],
        'circle.fill' => [
            'lucide'   => 'circle',
            'material' => 'circle',
            'label'    => 'Status',
        ],
        'progress.indicator' => [
            'lucide'   => 'circle-dashed',
            'material' => 'donut_small',
            'label'    => 'Progress',
        ],
        'circle-dashed' => [
            'lucide'   => 'circle-dashed',
            'material' => 'donut_small',
            'label'    => 'In Progress',
        ],
        'donut_small' => [
            'lucide'   => 'circle-dashed',
            'material' => 'donut_small',
            'label'    => 'Progress',
        ],

        // ── TEST TAKING ───────────────────────────────────
        'warning' => [
            'lucide'   => 'triangle-alert',
            'material' => 'warning',
            'label'    => 'Warning',
        ],
    ];
}

/**
 * Master Lucide SVG Bodies Dictionary (server-rendered inline SVGs)
 */
function getLucideSvgDictionary(): array {
    static $dict = null;
    if ($dict !== null) return $dict;
    $dict = [
        'activity' => '<path d="M22 12h-2.48a2 2 0 0 0-1.93 1.46l-2.35 8.36a.25.25 0 0 1-.48 0L9.24 2.18a.25.25 0 0 0-.48 0l-2.35 8.36A2 2 0 0 1 4.49 12H2" />',
        'arrow-down' => '<path d="M12 5v14" /> <path d="m19 12-7 7-7-7" />',
        'arrow-left' => '<path d="m12 19-7-7 7-7" /> <path d="M19 12H5" />',
        'arrow-right' => '<path d="M5 12h14" /> <path d="m12 5 7 7-7 7" />',
        'arrow-right-circle' => '<circle cx="12" cy="12" r="10" /> <path d="m12 16 4-4-4-4" /> <path d="M8 12h8" />',
        'arrow-up' => '<path d="m5 12 7-7 7 7" /> <path d="M12 19V5" />',
        'badge' => '<path d="M3.85 8.62a4 4 0 0 1 4.78-4.77 4 4 0 0 1 6.74 0 4 4 0 0 1 4.78 4.78 4 4 0 0 1 0 6.74 4 4 0 0 1-4.77 4.78 4 4 0 0 1-6.75 0 4 4 0 0 1-4.78-4.77 4 4 0 0 1 0-6.76Z" />',
        'badge-check' => '<path d="M3.85 8.62a4 4 0 0 1 4.78-4.77 4 4 0 0 1 6.74 0 4 4 0 0 1 4.78 4.78 4 4 0 0 1 0 6.74 4 4 0 0 1-4.77 4.78 4 4 0 0 1-6.75 0 4 4 0 0 1-4.78-4.77 4 4 0 0 1 0-6.76Z" /> <path d="m16 9-5.5 5.5L8 12" />',
        'bell' => '<path d="M10.268 21a2 2 0 0 0 3.464 0" /> <path d="M3.262 15.326A1 1 0 0 0 4 17h16a1 1 0 0 0 .74-1.673C19.41 13.956 18 12.499 18 8A6 6 0 0 0 6 8c0 4.499-1.411 5.956-2.738 7.326" />',
        'book-open' => '<path d="M12 5v16" /> <path d="M20.001 19A2 2 0 0022 17V5a2 2 0 00-1.999-2L16 3.002A5 5 0 0012 5a5 5 0 00-4-2H4a2 2 0 00-2 2v12a2 2 0 001.999 2H8a5 5 0 014 2 5 5 0 014-2z" />',
        'building-2' => '<path d="M10 12h4" /> <path d="M10 8h4" /> <path d="M14 21v-3a2 2 0 0 0-4 0v3" /> <path d="M6 10H4a2 2 0 0 0-2 2v7a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-2" /> <path d="M6 21V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v16" />',
        'building2' => '<path d="M10 12h4" /> <path d="M10 8h4" /> <path d="M14 21v-3a2 2 0 0 0-4 0v3" /> <path d="M6 10H4a2 2 0 0 0-2 2v7a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-2" /> <path d="M6 21V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v16" />',
        'calendar' => '<path d="M8 2v3" /> <path d="M16 2v3" /> <rect x="3" y="3" width="18" height="18" rx="2" /> <path d="M3 9h18" />',
        'calendar-days' => '<path d="M8 2v3" /> <path d="M16 2v3" /> <rect x="3" y="3" width="18" height="18" rx="2" /> <path d="M3 9h18" /> <path d="M8 13h.01" /> <path d="M12 13h.01" /> <path d="M16 13h.01" /> <path d="M8 17h.01" /> <path d="M12 17h.01" /> <path d="M16 17h.01" />',
        'chart-bar-big' => '<path d="M3 3v16a2 2 0 0 0 2 2h16" /> <rect x="7" y="13" width="9" height="4" rx="1" /> <rect x="7" y="5" width="12" height="4" rx="1" />',
        'chart-line' => '<path d="M3 3v16a2 2 0 0 0 2 2h16" /> <path d="m19 9-5 5-4-4-3 3" />',
        'check' => '<path d="M20 6 9 17l-5-5" />',
        'check-circle' => '<path d="M21.801 10A10 10 0 1 1 17 3.335" /> <path d="m9 11 3 3L22 4" />',
        'chevron-down' => '<path d="m6 9 6 6 6-6" />',
        'chevron-left' => '<path d="m15 18-6-6 6-6" />',
        'chevron-right' => '<path d="m9 18 6-6-6-6" />',
        'circle' => '<circle cx="12" cy="12" r="10" />',
        'circle-dashed' => '<path d="M10.1 2.182a10 10 0 0 1 3.8 0" /> <path d="M13.9 21.818a10 10 0 0 1-3.8 0" /> <path d="M17.609 3.721a10 10 0 0 1 2.69 2.7" /> <path d="M2.182 13.9a10 10 0 0 1 0-3.8" /> <path d="M20.279 17.609a10 10 0 0 1-2.7 2.69" /> <path d="M21.818 10.1a10 10 0 0 1 0 3.8" /> <path d="M3.721 6.391a10 10 0 0 1 2.7-2.69" /> <path d="M6.391 20.279a10 10 0 0 1-2.69-2.7" />',
        'circle-dot' => '<circle cx="12" cy="12" r="1" /> <circle cx="12" cy="12" r="10" />',
        'circle-help' => '<circle cx="12" cy="12" r="10" /> <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3" /> <path d="M12 17h.01" />',
        'circle-plus' => '<circle cx="12" cy="12" r="10" /> <path d="M8 12h8" /> <path d="M12 8v8" />',
        'clipboard' => '<rect width="8" height="4" x="8" y="2" rx="1" ry="1" /> <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2" />',
        'clipboard-check' => '<rect width="8" height="4" x="8" y="2" rx="1" ry="1" /> <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2" /> <path d="m9 14 2 2 4-4" />',
        'clock' => '<circle cx="12" cy="12" r="10" /> <path d="M12 6v6l4 2" />',
        'clock-3' => '<circle cx="12" cy="12" r="10" /> <path d="M12 6v6h4" />',
        'code' => '<path d="m16 18 6-6-6-6" /> <path d="m8 6-6 6 6 6" />',
        'copy' => '<rect width="14" height="14" x="8" y="8" rx="2" ry="2" /> <path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2" />',
        'database' => '<ellipse cx="12" cy="5" rx="9" ry="3" /> <path d="M3 5V19A9 3 0 0 0 21 19V5" /> <path d="M3 12A9 3 0 0 0 21 12" />',
        'download' => '<path d="M12 15V3" /> <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" /> <path d="m7 10 5 5 5-5" />',
        'external-link' => '<path d="M15 3h6v6" /> <path d="M10 14 21 3" /> <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6" />',
        'eye' => '<path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0" /> <circle cx="12" cy="12" r="3" />',
        'eye-off' => '<path d="M10.733 5.076a10.744 10.744 0 0 1 11.205 6.575 1 1 0 0 1 0 .696 10.747 10.747 0 0 1-1.444 2.49" /> <path d="M14.084 14.158a3 3 0 0 1-4.242-4.242" /> <path d="M17.479 17.499a10.75 10.75 0 0 1-15.417-5.151 1 1 0 0 1 0-.696 10.75 10.75 0 0 1 4.446-5.143" /> <path d="m2 2 20 20" />',
        'file' => '<path d="M6 22a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h8a2.4 2.4 0 0 1 1.704.706l3.588 3.588A2.4 2.4 0 0 1 20 8v12a2 2 0 0 1-2 2z" /> <path d="M14 2v5a1 1 0 0 0 1 1h5" />',
        'file-text' => '<path d="M6 22a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h8a2.4 2.4 0 0 1 1.704.706l3.588 3.588A2.4 2.4 0 0 1 20 8v12a2 2 0 0 1-2 2z" /> <path d="M14 2v5a1 1 0 0 0 1 1h5" /> <path d="M10 9H8" /> <path d="M16 13H8" /> <path d="M16 17H8" />',
        'filter' => '<path d="M10 20a1 1 0 0 0 .553.895l2 1A1 1 0 0 0 14 21v-7a2 2 0 0 1 .517-1.341L21.74 4.67A1 1 0 0 0 21 3H3a1 1 0 0 0-.742 1.67l7.225 7.989A2 2 0 0 1 10 14z" />',
        'flag' => '<path d="M4 22V4a1 1 0 0 1 .4-.8A6 6 0 0 1 8 2c3 0 5 2 7.333 2q2 0 3.067-.8A1 1 0 0 1 20 4v10a1 1 0 0 1-.4.8A6 6 0 0 1 16 16c-3 0-5-2-8-2a6 6 0 0 0-4 1.528" />',
        'folder' => '<path d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z" />',
        'git-branch' => '<path d="M15 6a9 9 0 0 0-9 9V3" /> <circle cx="18" cy="6" r="3" /> <circle cx="6" cy="18" r="3" />',
        'globe' => '<circle cx="12" cy="12" r="10" /> <path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20" /> <path d="M2 12h20" />',
        'graduation-cap' => '<path d="M21.42 10.922a1 1 0 0 0-.019-1.838L12.83 5.18a2 2 0 0 0-1.66 0L2.6 9.08a1 1 0 0 0 0 1.832l8.57 3.908a2 2 0 0 0 1.66 0z" /> <path d="M22 10v6" /> <path d="M6 12.5V16a6 3 0 0 0 12 0v-3.5" />',
        'grid-3x3' => '<rect width="18" height="18" x="3" y="3" rx="2" /> <path d="M3 9h18" /> <path d="M3 15h18" /> <path d="M9 3v18" /> <path d="M15 3v18" />',
        'hard-drive' => '<path d="M10 16h.01" /> <path d="M2.212 11.577a2 2 0 0 0-.212.896V18a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-5.527a2 2 0 0 0-.212-.896L18.55 5.11A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z" /> <path d="M21.946 12.013H2.054" /> <path d="M6 16h.01" />',
        'inbox' => '<polyline points="22 12 16 12 14 15 10 15 8 12 2 12" /> <path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z" />',
        'info' => '<circle cx="12" cy="12" r="10" /> <path d="M12 16v-4" /> <path d="M12 8h.01" />',
        'layers' => '<path d="M12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.83z" /> <path d="M2 12a1 1 0 0 0 .58.91l8.6 3.91a2 2 0 0 0 1.65 0l8.58-3.9A1 1 0 0 0 22 12" /> <path d="M2 17a1 1 0 0 0 .58.91l8.6 3.91a2 2 0 0 0 1.65 0l8.58-3.9A1 1 0 0 0 22 17" />',
        'layout-dashboard' => '<rect width="7" height="9" x="3" y="3" rx="1" /> <rect width="7" height="5" x="14" y="3" rx="1" /> <rect width="7" height="9" x="14" y="12" rx="1" /> <rect width="7" height="5" x="3" y="16" rx="1" />',
        'lightbulb' => '<path d="M15 14c.2-1 .7-1.7 1.5-2.5 1-.9 1.5-2.2 1.5-3.5A6 6 0 0 0 6 8c0 1 .2 2.2 1.5 3.5.7.7 1.3 1.5 1.5 2.5" /> <path d="M9 18h6" /> <path d="M10 22h4" />',
        'list' => '<path d="M3 5h.01" /> <path d="M3 12h.01" /> <path d="M3 19h.01" /> <path d="M8 5h13" /> <path d="M8 12h13" /> <path d="M8 19h13" />',
        'lock' => '<rect width="18" height="11" x="3" y="11" rx="2" ry="2" /> <path d="M7 11V7a5 5 0 0 1 10 0v4" />',
        'log-in' => '<path d="m10 17 5-5-5-5" /> <path d="M15 12H3" /> <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4" />',
        'log-out' => '<path d="m16 17 5-5-5-5" /> <path d="M21 12H9" /> <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />',
        'mail' => '<path d="m22 7-8.991 5.727a2 2 0 0 1-2.009 0L2 7" /> <rect x="2" y="4" width="20" height="16" rx="2" />',
        'menu' => '<path d="M4 5h16" /> <path d="M4 12h16" /> <path d="M4 19h16" />',
        'moon' => '<path d="M20.985 12.486a9 9 0 1 1-9.473-9.472c.405-.022.617.46.402.803a6 6 0 0 0 8.268 8.268c.344-.215.825-.004.803.401" />',
        'more-vertical' => '<circle cx="12" cy="12" r="1" /> <circle cx="12" cy="5" r="1" /> <circle cx="12" cy="19" r="1" />',
        'pause' => '<rect x="14" y="3" width="5" height="18" rx="1" /> <rect x="5" y="3" width="5" height="18" rx="1" />',
        'phone' => '<path d="M13.832 16.568a1 1 0 0 0 1.213-.303l.355-.465A2 2 0 0 1 17 15h3a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2A18 18 0 0 1 2 4a2 2 0 0 1 2-2h3a2 2 0 0 1 2 2v3a2 2 0 0 1-.8 1.6l-.468.351a1 1 0 0 0-.292 1.233 14 14 0 0 0 6.392 6.384" />',
        'play' => '<path d="M5 5a2 2 0 0 1 3.008-1.728l11.997 6.998a2 2 0 0 1 .003 3.458l-12 7A2 2 0 0 1 5 19z" />',
        'refresh-cw' => '<path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8" /> <path d="M21 3v5h-5" /> <path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16" /> <path d="M8 16H3v5" />',
        'search' => '<path d="m21 21-4.34-4.34" /> <circle cx="11" cy="11" r="8" />',
        'settings' => '<path d="M9.671 4.136a2.34 2.34 0 0 1 4.659 0 2.34 2.34 0 0 0 3.319 1.915 2.34 2.34 0 0 1 2.33 4.033 2.34 2.34 0 0 0 0 3.831 2.34 2.34 0 0 1-2.33 4.033 2.34 2.34 0 0 0-3.319 1.915 2.34 2.34 0 0 1-4.659 0 2.34 2.34 0 0 0-3.32-1.915 2.34 2.34 0 0 1-2.33-4.033 2.34 2.34 0 0 0 0-3.831A2.34 2.34 0 0 1 6.35 6.051a2.34 2.34 0 0 0 3.319-1.915" /> <circle cx="12" cy="12" r="3" />',
        'shield' => '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z" />',
        'square' => '<rect width="18" height="18" x="3" y="3" rx="2" />',
        'square-pen' => '<path d="M12 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" /> <path d="M18.375 2.625a1 1 0 0 1 3 3l-9.013 9.014a2 2 0 0 1-.853.505l-2.873.84a.5.5 0 0 1-.62-.62l.84-2.873a2 2 0 0 1 .506-.852z" />',
        'star' => '<path d="M11.525 2.295a.53.53 0 0 1 .95 0l2.31 4.679a2.123 2.123 0 0 0 1.595 1.16l5.166.756a.53.53 0 0 1 .294.904l-3.736 3.638a2.123 2.123 0 0 0-.611 1.878l.882 5.14a.53.53 0 0 1-.771.56l-4.618-2.428a2.122 2.122 0 0 0-1.973 0L6.396 21.01a.53.53 0 0 1-.77-.56l.881-5.139a2.122 2.122 0 0 0-.611-1.879L2.16 9.795a.53.53 0 0 1 .294-.906l5.165-.755a2.122 2.122 0 0 0 1.597-1.16z" />',
        'tag' => '<path d="M12.586 2.586A2 2 0 0 0 11.172 2H4a2 2 0 0 0-2 2v7.172a2 2 0 0 0 .586 1.414l8.704 8.704a2.426 2.426 0 0 0 3.42 0l6.58-6.58a2.426 2.426 0 0 0 0-3.42z" /> <circle cx="7.5" cy="7.5" r=".5" fill="currentColor" />',
        'timer' => '<line x1="10" x2="14" y1="2" y2="2" /> <line x1="12" x2="15" y1="14" y2="11" /> <circle cx="12" cy="14" r="8" />',
        'trash-2' => '<path d="M10 11v6" /> <path d="M14 11v6" /> <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6" /> <path d="M3 6h18" /> <path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />',
        'tray' => '<polyline points="22 12 16 12 14 15 10 15 8 12 2 12" /> <path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z" />',
        'triangle-alert' => '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3" /> <path d="M12 9v4" /> <path d="M12 17h.01" />',
        'upload' => '<path d="M12 3v12" /> <path d="m17 8-5-5-5 5" /> <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />',
        'user' => '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2" /> <circle cx="12" cy="7" r="4" />',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" /> <path d="M16 3.128a4 4 0 0 1 0 7.744" /> <path d="M22 21v-2a4 4 0 0 0-3-3.87" /> <circle cx="9" cy="7" r="4" />',
        'x' => '<path d="M18 6 6 18" /> <path d="m6 6 12 12" />',
    ];
    return $dict;
}
