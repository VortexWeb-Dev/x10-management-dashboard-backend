<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . "/../services/CacheService.php";
require_once __DIR__ . "/../services/ResponseService.php";
require_once __DIR__ . "/BitrixController.php";

class OverallDealsController extends BitrixController
{
    private CacheService $cache;
    private ResponseService $response;
    private array $config;

    public function __construct()
    {
        parent::__construct();
        $this->config = require __DIR__ . '/../config/config.php';
        $this->cache = new CacheService($this->config['cache']['expiry']);
        $this->response = new ResponseService();
    }

    public function processRequest(string $method): void
    {
        if ($method !== 'GET') {
            $this->response->sendError(405, "Method Not Allowed");
            return;
        }

        $cacheKey = "overall_deals_" . date('Y-m-d');
        $cached = $this->cache->get($cacheKey);

        if ($cached !== false && $this->config['cache']['enabled']) {
            $this->response->sendSuccess(200, $cached);
            return;
        }

        $select = [
            "ID",
            "TITLE",
            "STAGE_ID",
            "DATE_CREATE",
            "OPPORTUNITY",
            "ASSIGNED_BY_ID",
            "UF_CRM_6800C17395A23",
            "UF_CRM_1737520005644",
            "UF_CRM_6800C177905FA",
            "UF_CRM_1737520371926",
            "UF_CRM_1737520297069",
            "UF_CRM_1737520464275",
            "UF_CRM_1743838881620",
            "UF_CRM_6800C17754DC8",
            "UF_TEAM",
            "SOURCE_ID",
            "UF_CRM_6800C1776766A",
            "UF_CRM_6800C17742B22",
            "UF_CRM_6800C17742B22",
            "UF_CRM_6800C177B1478",
        ];

        $salesDeptIds = $this->config['SALES_DEPARTMENT_IDS'];
        $salesEmployees = $this->getAllUsers(['UF_DEPARTMENT' => $salesDeptIds], [
            'ID',
            'NAME',
            'LAST_NAME',
            'WORK_POSITION',
            'UF_DEPARTMENT',
            'UF_EMPLOYMENT_DATE'
        ]);
        $salesEmployeesIds = array_column($salesEmployees, 'ID');

        $deals = $this->getDeals([
            '@ASSIGNED_BY_ID' => $salesEmployeesIds,
            '!=UF_CRM_1743838881620' => null
        ], $select, 10, ['ID' => 'desc']);

        // Create a lookup array for employees to easily find by ID
        $employeesById = [];
        foreach ($salesEmployees as $employee) {
            $employeesById[$employee['ID']] = ($employee['NAME'] ?? '') . ' ' . ($employee['LAST_NAME'] ?? '');
        }

        $formatted = [];
        foreach ($deals as $deal) {
            // Get agent name from lookup array
            $agentName = $employeesById[$deal['ASSIGNED_BY_ID']] ?? '';

            // Handle property type which appears to be an array
            $propertyType = '';
            if (!empty($deal['UF_CRM_1737520371926']) && is_array($deal['UF_CRM_1737520371926'])) {
                $propertyType = $deal['UF_CRM_1737520371926'][0];
            } elseif (!empty($deal['UF_CRM_1737520371926']) && !is_array($deal['UF_CRM_1737520371926'])) {
                $propertyType = $deal['UF_CRM_1737520371926'];
            }

            $formatted[] = [
                'date' => date('Y-m-d', strtotime($deal['DATE_CREATE'])),
                'dealType' => $deal['UF_CRM_6800C17754DC8'] ?? '',
                'projectName' => $deal['UF_CRM_1743838881620'] ?? '',
                'unitNo' => $deal['UF_CRM_1737520005644'] ?? '',
                'developerName' => $deal['UF_CRM_1737520297069'] ?? '',
                'propertyType' => $propertyType,
                'noOfBr' => (int)($deal['UF_CRM_1737520464275'] ?? 0),
                'clientName' => $deal['UF_CRM_6800C17395A23'] ?? '',
                'agentName' => $agentName,
                'propertyPrice' => (float)($deal['UF_CRM_6800C177905FA'] ?? 0),
                'grossCommissionInclVAT' => (float)($deal['UF_CRM_6800C17742B22'] ?? 0),
                'grossCommission' => (float)($deal['UF_CRM_6800C17742B22'] ?? 0),
                'vat' => (float)($deal['UF_CRM_6800C177B1478'] ?? 0),
                'agentCommission' => (float)($deal['UF_CRM_6800C1776766A'] ?? 0),
                'leadSource' => $this->mapSourceId($deal['SOURCE_ID']) ?? '',
            ];
        }

        $this->cache->set($cacheKey, $formatted);
        $this->response->sendSuccess(200, $formatted);
    }

    private function mapSourceId($id = 0)
    {
        $sources = [
            "CALL" => "Call",
            "EMAIL" => "E-Mail",
            "WEB" => "Website",
            "ADVERTISING" => "Advertising",
            "PARTNER" => "Existing Client",
            "RECOMMENDATION" => "By Recommendation",
            "TRADE_SHOW" => "Show/Exhibition",
            "WEBFORM" => "CRM form",
            "CALLBACK" => "Callback",
            "RC_GENERATOR" => "Sales boost",
            "STORE" => "Online Store",
            "2|NOTIFICATIONS" => "Bitrix24 SMS and WhatsApp - Open Channel",
            "2|FACEBOOK" => "Facebook - Open Channel",
            "2|OPENLINE" => "Live chat - Open Channel",
            "2|WZ_WHATSAPP_C50FB56CD5807D196ED649FBBBC17585F" => "WAZZUP: WhatsApp - Open Channel",
            "1|FBINSTAGRAMDIRECT" => "Instagram Direct - Open Channel",
            "1" => "LinkedIn",
            "2" => "New field",
            "3" => "Facebook",
            "4" => "Instagram",
            "6" => "X (ex-Twitter)",
            "7" => "TikTok",
            "8" => "Youtube",
            "OTHER" => "Other",
            "9" => "Bayut Call",
            "10" => "Bayut Email",
            "11" => "Bayut WhatsApp",
            "12" => "Dubizzle Call",
            "13" => "Dubizzle Email",
            "14" => "Dubizzle WhatsApp",
            "15" => "Property Finder Call",
            "16" => "Property Finder Email",
            "17" => "Property Finder WhatsApp",
        ];


        return $sources[$id] ?? 'Unknown Source';
    }
}
