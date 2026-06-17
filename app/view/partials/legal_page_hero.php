<?php
/** @var string $legalKicker */
/** @var string $legalTitle */
/** @var string $legalLead */
$heroKicker = $legalKicker ?? '';
$heroTitle = $legalTitle ?? '';
$heroLead = $legalLead ?? '';
$heroClass = 'legal-page-hero';
include __DIR__ . '/dashboard_view_hero.php';
