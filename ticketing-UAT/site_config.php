<?php

$SITE_CAMPAIGNS = [
    'TRIUMPH SITE' => [
        'BPI AIA',
    ],
    'PLAZA SITE' => [
        'AC Mobility',
        'BDO',
        'CBC',
        'GAOC',
        'Medicard',
    ],
    'MORATO SITE' => [
        'BDO CCC',
        'CBC CCC',
    ],
    'WORLD SITE' => [
        'AIA',
        'BPI',
        'Metrobank',
    ],
];

$SITE_OPTIONS = array_keys($SITE_CAMPAIGNS);

function getSiteCampaigns(?string $site): array {
    global $SITE_CAMPAIGNS;

    if ($site === null || $site === '') {
        return [];
    }

    return $SITE_CAMPAIGNS[$site] ?? [];
}

function getAllCampaigns(): array {
    $campaigns = [];
    foreach ($GLOBALS['SITE_CAMPAIGNS'] as $siteCampaigns) {
        foreach ($siteCampaigns as $campaign) {
            $campaigns[] = $campaign;
        }
    }

    return array_values(array_unique($campaigns));
}
