<?php
namespace LandLens\Controllers;

use LandLens\Middleware\AuthMiddleware;
use PDO;

class FraudController extends BaseController {

    public function submitReport(string $propertyId) {
        $body = $this->getBody();
        $fraudId = 'fr-' . rand(100, 999);

        $this->json([
            'id' => $fraudId,
            'propertyId' => $propertyId,
            'reason' => $body['reason'] ?? 'Double Listing',
            'description' => $body['description'] ?? 'The property bounds overlap another registered claim.',
            'status' => 'UNDER_INVESTIGATION',
            'createdAt' => date('c')
        ]);
    }

    public function index() {
        $this->json([
            [
                'id' => 'fr-101',
                'propertyId' => '02a6dc7d-0ed9-4251-94cb-96185554b887',
                'reason' => 'Boundary line review requested',
                'description' => 'Cross boundary check with adjacent village plot required.',
                'status' => 'UNDER_INVESTIGATION',
                'assignedOfficer' => 'Govt Inspector Rao',
                'createdAt' => date('c', strtotime('-2 days'))
            ],
            [
                'id' => 'fr-102',
                'propertyId' => 'prop-ref-003',
                'reason' => 'Double Listing Suspected',
                'description' => 'Discrepancy flagged against cadastral registry.',
                'status' => 'FLAGGED_FRAUD_SUSPICIOUS',
                'assignedOfficer' => 'Govt Inspector Rao',
                'createdAt' => date('c', strtotime('-1 day'))
            ]
        ]);
    }

    public function getPropertyFraudReports(string $propertyId) {
        $this->json([
            [
                'id' => 'fr-' . rand(100, 999),
                'propertyId' => $propertyId,
                'reason' => 'Deed Inspection Pending',
                'status' => 'UNDER_INVESTIGATION',
                'createdAt' => date('c')
            ]
        ]);
    }

    public function assignOfficer(string $id) {
        $params = $this->getQueryParams();
        $officerId = $params['officerId'] ?? 'usr-govt-01';

        $this->json([
            'id' => $id,
            'assignedOfficerId' => $officerId,
            'status' => 'ASSIGNED_TO_OFFICER',
            'message' => 'Fraud report assigned for field investigation'
        ]);
    }

    public function resolve(string $id) {
        $params = $this->getQueryParams();
        $status = $params['status'] ?? 'RESOLVED_FRAUDULENT';

        $this->json([
            'id' => $id,
            'status' => $status,
            'resolvedAt' => date('c'),
            'message' => "Fraud report status set to {$status}"
        ]);
    }
}
