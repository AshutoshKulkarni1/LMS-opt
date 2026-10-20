<?php
/**
 * LMS Portal - Root Entry Point & Front Controller
 * Dispatches clean URLs (/login, /signup, /student/*, /admin/*, /api/*)
 * and delegates root (/) to auth landing dispatcher via router.php.
 */
require_once __DIR__ . '/router.php';
