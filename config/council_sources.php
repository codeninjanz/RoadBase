<?php

/**
 * Registry of Road Controlling Authorities that publish traffic-count points
 * (Average Daily Traffic / AADT). One entry per RCA on the mobileroad.org
 * list; CouncilTrafficCountSync ingests each into count_sites.
 *
 * Each entry:
 *   key    => DataSource.key (matches DataSourceSeeder)
 *   name   => Human label shown on /about and in the sync dashboard.
 *   url    => ArcGIS REST FeatureServer layer query URL. Read from .env so
 *             ops can fill in URLs as they verify each council's open-data
 *             portal — leaving it blank makes the sync no-op for that RCA.
 *   rca    => Display name written into count_sites.rca for every row.
 *   where  => Optional ArcGIS WHERE clause (default '1=1'); useful when a
 *             council ships historical sites in the same layer.
 *   fields => Property-name aliases for each count_sites column. The first
 *             alias that resolves to a non-null value wins. Schemas vary
 *             wildly between councils so we accept multiple spellings up
 *             front rather than chase per-council overrides later.
 *
 * To add a new council: append an entry, add a matching row to
 * DataSourceSeeder, and (if the URL ships in env) add the env var to
 * .env.example. The sync command picks the new key up automatically.
 */

$commonAliases = [
    'external_id'      => ['siteId', 'SiteID', 'SiteId', 'site_id', 'STATION_ID', 'stationId', 'GlobalID', 'globalId', 'OBJECTID'],
    'road_name'        => ['roadName', 'RoadName', 'road_name', 'siteName', 'SiteName', 'site_name', 'description', 'Description', 'descr', 'location', 'Location', 'street', 'Street'],
    'aadt'             => ['aadt', 'AADT', 'adt', 'ADT', 'averageDailyTraffic', 'AverageDailyTraffic', 'volume', 'Volume', 'avgDailyVol', 'AvgDailyVol'],
    'heavy_pct'        => ['percentHeavy', 'PercentHeavy', 'heavy_pct', 'heavyPct', 'HeavyPct', 'percent_heavy', 'hcvPct', 'HCVPct', 'heavy', 'Heavy'],
    'peak_hour_volume' => ['peakHourVolume', 'PeakHourVolume', 'peak_hour_volume', 'peakVolume', 'PeakVolume', 'maxHourVolume'],
    'speed_limit'      => ['speedLimit', 'SpeedLimit', 'speed_limit', 'speedLimitKmh', 'postedSpeed', 'PostedSpeed'],
    'count_date'       => ['countDate', 'CountDate', 'count_date', 'surveyDate', 'SurveyDate', 'survey_date', 'date', 'Date', 'recordedDate', 'RecordedDate', 'fromDate', 'FromDate'],
];

return [

    // ---- Already stubbed in DataSourceSeeder ---------------------------------

    'at_adt' => [
        'name' => 'Auckland Transport Average Daily Traffic',
        'url' => env('AT_ADT_FEATURE_URL'),
        'rca' => 'Auckland Transport',
        'fields' => $commonAliases,
    ],
    'hcc' => [
        'name' => 'Hamilton City Traffic Counts',
        'url' => env('HCC_FEATURE_URL'),
        'rca' => 'Hamilton City Council',
        'fields' => $commonAliases,
    ],
    'ccc' => [
        'name' => 'Christchurch City Traffic Counts',
        'url' => env('CCC_FEATURE_URL'),
        'rca' => 'Christchurch City Council',
        'fields' => $commonAliases,
    ],

    // ---- Other RCAs from the mobileroad.org list -----------------------------
    // Each one is registered so the markers layer covers the whole network as
    // soon as the operator drops a published FeatureServer URL into .env.

    'wellington_cc' => [
        'name' => 'Wellington City Traffic Counts',
        'url' => env('WELLINGTON_CC_FEATURE_URL'),
        'rca' => 'Wellington City Council',
        'fields' => $commonAliases,
    ],
    'hutt_cc' => [
        'name' => 'Hutt City Traffic Counts',
        'url' => env('HUTT_CC_FEATURE_URL'),
        'rca' => 'Hutt City Council',
        'fields' => $commonAliases,
    ],
    'upper_hutt_cc' => [
        'name' => 'Upper Hutt City Traffic Counts',
        'url' => env('UPPER_HUTT_CC_FEATURE_URL'),
        'rca' => 'Upper Hutt City Council',
        'fields' => $commonAliases,
    ],
    'porirua_cc' => [
        'name' => 'Porirua City Traffic Counts',
        'url' => env('PORIRUA_CC_FEATURE_URL'),
        'rca' => 'Porirua City Council',
        'fields' => $commonAliases,
    ],
    'kapiti_coast_dc' => [
        'name' => 'Kapiti Coast Traffic Counts',
        'url' => env('KAPITI_COAST_DC_FEATURE_URL'),
        'rca' => 'Kapiti Coast District Council',
        'fields' => $commonAliases,
    ],
    'tauranga_cc' => [
        'name' => 'Tauranga City Traffic Counts',
        'url' => env('TAURANGA_CC_FEATURE_URL'),
        'rca' => 'Tauranga City Council',
        'fields' => $commonAliases,
    ],
    'western_bop_dc' => [
        'name' => 'Western Bay of Plenty Traffic Counts',
        'url' => env('WESTERN_BOP_DC_FEATURE_URL'),
        'rca' => 'Western Bay of Plenty District Council',
        'fields' => $commonAliases,
    ],
    'rotorua_dc' => [
        'name' => 'Rotorua District Traffic Counts',
        'url' => env('ROTORUA_DC_FEATURE_URL'),
        'rca' => 'Rotorua District Council',
        'fields' => $commonAliases,
    ],
    'whakatane_dc' => [
        'name' => 'Whakatane District Traffic Counts',
        'url' => env('WHAKATANE_DC_FEATURE_URL'),
        'rca' => 'Whakatane District Council',
        'fields' => $commonAliases,
    ],
    'kawerau_dc' => [
        'name' => 'Kawerau District Traffic Counts',
        'url' => env('KAWERAU_DC_FEATURE_URL'),
        'rca' => 'Kawerau District Council',
        'fields' => $commonAliases,
    ],
    'opotiki_dc' => [
        'name' => 'Opotiki District Traffic Counts',
        'url' => env('OPOTIKI_DC_FEATURE_URL'),
        'rca' => 'Opotiki District Council',
        'fields' => $commonAliases,
    ],
    'taupo_dc' => [
        'name' => 'Taupo District Traffic Counts',
        'url' => env('TAUPO_DC_FEATURE_URL'),
        'rca' => 'Taupo District Council',
        'fields' => $commonAliases,
    ],
    'gisborne_dc' => [
        'name' => 'Gisborne District Traffic Counts',
        'url' => env('GISBORNE_DC_FEATURE_URL'),
        'rca' => 'Gisborne District Council',
        'fields' => $commonAliases,
    ],
    'wairoa_dc' => [
        'name' => 'Wairoa District Traffic Counts',
        'url' => env('WAIROA_DC_FEATURE_URL'),
        'rca' => 'Wairoa District Council',
        'fields' => $commonAliases,
    ],
    'hastings_dc' => [
        'name' => 'Hastings District Traffic Counts',
        'url' => env('HASTINGS_DC_FEATURE_URL'),
        'rca' => 'Hastings District Council',
        'fields' => $commonAliases,
    ],
    'napier_cc' => [
        'name' => 'Napier City Traffic Counts',
        'url' => env('NAPIER_CC_FEATURE_URL'),
        'rca' => 'Napier City Council',
        'fields' => $commonAliases,
    ],
    'central_hawkes_bay_dc' => [
        'name' => 'Central Hawkes Bay Traffic Counts',
        'url' => env('CENTRAL_HAWKES_BAY_DC_FEATURE_URL'),
        'rca' => 'Central Hawkes Bay District Council',
        'fields' => $commonAliases,
    ],
    'new_plymouth_dc' => [
        'name' => 'New Plymouth Traffic Counts',
        'url' => env('NEW_PLYMOUTH_DC_FEATURE_URL'),
        'rca' => 'New Plymouth District Council',
        'fields' => $commonAliases,
    ],
    'south_taranaki_dc' => [
        'name' => 'South Taranaki Traffic Counts',
        'url' => env('SOUTH_TARANAKI_DC_FEATURE_URL'),
        'rca' => 'South Taranaki District Council',
        'fields' => $commonAliases,
    ],
    'stratford_dc' => [
        'name' => 'Stratford District Traffic Counts',
        'url' => env('STRATFORD_DC_FEATURE_URL'),
        'rca' => 'Stratford District Council',
        'fields' => $commonAliases,
    ],
    'whanganui_dc' => [
        'name' => 'Whanganui District Traffic Counts',
        'url' => env('WHANGANUI_DC_FEATURE_URL'),
        'rca' => 'Whanganui District Council',
        'fields' => $commonAliases,
    ],
    'palmerston_north_cc' => [
        'name' => 'Palmerston North Traffic Counts',
        'url' => env('PALMERSTON_NORTH_CC_FEATURE_URL'),
        'rca' => 'Palmerston North City Council',
        'fields' => $commonAliases,
    ],
    'manawatu_dc' => [
        'name' => 'Manawatu District Traffic Counts',
        'url' => env('MANAWATU_DC_FEATURE_URL'),
        'rca' => 'Manawatu District Council',
        'fields' => $commonAliases,
    ],
    'horowhenua_dc' => [
        'name' => 'Horowhenua District Traffic Counts',
        'url' => env('HOROWHENUA_DC_FEATURE_URL'),
        'rca' => 'Horowhenua District Council',
        'fields' => $commonAliases,
    ],
    'tararua_dc' => [
        'name' => 'Tararua District Traffic Counts',
        'url' => env('TARARUA_DC_FEATURE_URL'),
        'rca' => 'Tararua District Council',
        'fields' => $commonAliases,
    ],
    'rangitikei_dc' => [
        'name' => 'Rangitikei District Traffic Counts',
        'url' => env('RANGITIKEI_DC_FEATURE_URL'),
        'rca' => 'Rangitikei District Council',
        'fields' => $commonAliases,
    ],
    'ruapehu_dc' => [
        'name' => 'Ruapehu District Traffic Counts',
        'url' => env('RUAPEHU_DC_FEATURE_URL'),
        'rca' => 'Ruapehu District Council',
        'fields' => $commonAliases,
    ],
    'masterton_dc' => [
        'name' => 'Masterton District Traffic Counts',
        'url' => env('MASTERTON_DC_FEATURE_URL'),
        'rca' => 'Masterton District Council',
        'fields' => $commonAliases,
    ],
    'carterton_dc' => [
        'name' => 'Carterton District Traffic Counts',
        'url' => env('CARTERTON_DC_FEATURE_URL'),
        'rca' => 'Carterton District Council',
        'fields' => $commonAliases,
    ],
    'south_wairarapa_dc' => [
        'name' => 'South Wairarapa Traffic Counts',
        'url' => env('SOUTH_WAIRARAPA_DC_FEATURE_URL'),
        'rca' => 'South Wairarapa District Council',
        'fields' => $commonAliases,
    ],
    'far_north_dc' => [
        'name' => 'Far North Traffic Counts',
        'url' => env('FAR_NORTH_DC_FEATURE_URL'),
        'rca' => 'Far North District Council',
        'fields' => $commonAliases,
    ],
    'whangarei_dc' => [
        'name' => 'Whangarei District Traffic Counts',
        'url' => env('WHANGAREI_DC_FEATURE_URL'),
        'rca' => 'Whangarei District Council',
        'fields' => $commonAliases,
    ],
    'kaipara_dc' => [
        'name' => 'Kaipara District Traffic Counts',
        'url' => env('KAIPARA_DC_FEATURE_URL'),
        'rca' => 'Kaipara District Council',
        'fields' => $commonAliases,
    ],
    'thames_coromandel_dc' => [
        'name' => 'Thames-Coromandel Traffic Counts',
        'url' => env('THAMES_COROMANDEL_DC_FEATURE_URL'),
        'rca' => 'Thames-Coromandel District Council',
        'fields' => $commonAliases,
    ],
    'hauraki_dc' => [
        'name' => 'Hauraki District Traffic Counts',
        'url' => env('HAURAKI_DC_FEATURE_URL'),
        'rca' => 'Hauraki District Council',
        'fields' => $commonAliases,
    ],
    'matamata_piako_dc' => [
        'name' => 'Matamata-Piako Traffic Counts',
        'url' => env('MATAMATA_PIAKO_DC_FEATURE_URL'),
        'rca' => 'Matamata-Piako District Council',
        'fields' => $commonAliases,
    ],
    'waikato_dc' => [
        'name' => 'Waikato District Traffic Counts',
        'url' => env('WAIKATO_DC_FEATURE_URL'),
        'rca' => 'Waikato District Council',
        'fields' => $commonAliases,
    ],
    'waipa_dc' => [
        'name' => 'Waipa District Traffic Counts',
        'url' => env('WAIPA_DC_FEATURE_URL'),
        'rca' => 'Waipa District Council',
        'fields' => $commonAliases,
    ],
    'south_waikato_dc' => [
        'name' => 'South Waikato Traffic Counts',
        'url' => env('SOUTH_WAIKATO_DC_FEATURE_URL'),
        'rca' => 'South Waikato District Council',
        'fields' => $commonAliases,
    ],
    'otorohanga_dc' => [
        'name' => 'Otorohanga District Traffic Counts',
        'url' => env('OTOROHANGA_DC_FEATURE_URL'),
        'rca' => 'Otorohanga District Council',
        'fields' => $commonAliases,
    ],
    'waitomo_dc' => [
        'name' => 'Waitomo District Traffic Counts',
        'url' => env('WAITOMO_DC_FEATURE_URL'),
        'rca' => 'Waitomo District Council',
        'fields' => $commonAliases,
    ],
    'nelson_cc' => [
        'name' => 'Nelson City Traffic Counts',
        'url' => env('NELSON_CC_FEATURE_URL'),
        'rca' => 'Nelson City Council',
        'fields' => $commonAliases,
    ],
    'tasman_dc' => [
        'name' => 'Tasman District Traffic Counts',
        'url' => env('TASMAN_DC_FEATURE_URL'),
        'rca' => 'Tasman District Council',
        'fields' => $commonAliases,
    ],
    'marlborough_dc' => [
        'name' => 'Marlborough District Traffic Counts',
        'url' => env('MARLBOROUGH_DC_FEATURE_URL'),
        'rca' => 'Marlborough District Council',
        'fields' => $commonAliases,
    ],
    'kaikoura_dc' => [
        'name' => 'Kaikoura District Traffic Counts',
        'url' => env('KAIKOURA_DC_FEATURE_URL'),
        'rca' => 'Kaikoura District Council',
        'fields' => $commonAliases,
    ],
    'hurunui_dc' => [
        'name' => 'Hurunui District Traffic Counts',
        'url' => env('HURUNUI_DC_FEATURE_URL'),
        'rca' => 'Hurunui District Council',
        'fields' => $commonAliases,
    ],
    'waimakariri_dc' => [
        'name' => 'Waimakariri District Traffic Counts',
        'url' => env('WAIMAKARIRI_DC_FEATURE_URL'),
        'rca' => 'Waimakariri District Council',
        'fields' => $commonAliases,
    ],
    'selwyn_dc' => [
        'name' => 'Selwyn District Traffic Counts',
        'url' => env('SELWYN_DC_FEATURE_URL'),
        'rca' => 'Selwyn District Council',
        'fields' => $commonAliases,
    ],
    'ashburton_dc' => [
        'name' => 'Ashburton District Traffic Counts',
        'url' => env('ASHBURTON_DC_FEATURE_URL'),
        'rca' => 'Ashburton District Council',
        'fields' => $commonAliases,
    ],
    'timaru_dc' => [
        'name' => 'Timaru District Traffic Counts',
        'url' => env('TIMARU_DC_FEATURE_URL'),
        'rca' => 'Timaru District Council',
        'fields' => $commonAliases,
    ],
    'mackenzie_dc' => [
        'name' => 'Mackenzie District Traffic Counts',
        'url' => env('MACKENZIE_DC_FEATURE_URL'),
        'rca' => 'Mackenzie District Council',
        'fields' => $commonAliases,
    ],
    'waimate_dc' => [
        'name' => 'Waimate District Traffic Counts',
        'url' => env('WAIMATE_DC_FEATURE_URL'),
        'rca' => 'Waimate District Council',
        'fields' => $commonAliases,
    ],
    'waitaki_dc' => [
        'name' => 'Waitaki District Traffic Counts',
        'url' => env('WAITAKI_DC_FEATURE_URL'),
        'rca' => 'Waitaki District Council',
        'fields' => $commonAliases,
    ],
    'central_otago_dc' => [
        'name' => 'Central Otago Traffic Counts',
        'url' => env('CENTRAL_OTAGO_DC_FEATURE_URL'),
        'rca' => 'Central Otago District Council',
        'fields' => $commonAliases,
    ],
    'queenstown_lakes_dc' => [
        'name' => 'Queenstown Lakes Traffic Counts',
        'url' => env('QUEENSTOWN_LAKES_DC_FEATURE_URL'),
        'rca' => 'Queenstown Lakes District Council',
        'fields' => $commonAliases,
    ],
    'dunedin_cc' => [
        'name' => 'Dunedin City Traffic Counts',
        'url' => env('DUNEDIN_CC_FEATURE_URL'),
        'rca' => 'Dunedin City Council',
        'fields' => $commonAliases,
    ],
    'clutha_dc' => [
        'name' => 'Clutha District Traffic Counts',
        'url' => env('CLUTHA_DC_FEATURE_URL'),
        'rca' => 'Clutha District Council',
        'fields' => $commonAliases,
    ],
    'gore_dc' => [
        'name' => 'Gore District Traffic Counts',
        'url' => env('GORE_DC_FEATURE_URL'),
        'rca' => 'Gore District Council',
        'fields' => $commonAliases,
    ],
    'invercargill_cc' => [
        'name' => 'Invercargill City Traffic Counts',
        'url' => env('INVERCARGILL_CC_FEATURE_URL'),
        'rca' => 'Invercargill City Council',
        'fields' => $commonAliases,
    ],
    'southland_dc' => [
        'name' => 'Southland District Traffic Counts',
        'url' => env('SOUTHLAND_DC_FEATURE_URL'),
        'rca' => 'Southland District Council',
        'fields' => $commonAliases,
    ],
    'west_coast_rc' => [
        'name' => 'West Coast Regional Traffic Counts',
        'url' => env('WEST_COAST_RC_FEATURE_URL'),
        'rca' => 'West Coast Regional Council',
        'fields' => $commonAliases,
    ],
    'chatham_islands' => [
        'name' => 'Chatham Islands Traffic Counts',
        'url' => env('CHATHAM_ISLANDS_FEATURE_URL'),
        'rca' => 'Chatham Islands Council',
        'fields' => $commonAliases,
    ],
    'doc' => [
        'name' => 'Department of Conservation Traffic Counts',
        'url' => env('DOC_FEATURE_URL'),
        'rca' => 'Department of Conservation',
        'fields' => $commonAliases,
    ],
];
