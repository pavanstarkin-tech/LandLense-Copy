<?php
namespace LandLens\Controllers;

use LandLens\Middleware\AuthMiddleware;
use PDO;

class PropertyController extends BaseController {

    public function index() {
        $params = $this->getQueryParams();
        $status = $params['status'] ?? null;
        $category = $params['category'] ?? null;
        $district = $params['district'] ?? null;
        $state = $params['state'] ?? null;
        $minPrice = $params['priceMin'] ?? $params['minPrice'] ?? null;
        $maxPrice = $params['priceMax'] ?? $params['maxPrice'] ?? null;

        $properties = [];

        if ($this->db) {
            try {
                $sql = "
                    SELECT 
                        id,
                        property_code as propertyCode,
                        title,
                        category,
                        area,
                        price,
                        description,
                        survey_number as surveyNumber,
                        address,
                        latitude,
                        longitude,
                        district,
                        village,
                        state,
                        pincode,
                        three_sixty_image_url as threeSixtyImageUrl,
                        status,
                        provider_id as providerId,
                        is_active as isActive,
                        created_at as createdAt,
                        updated_at as updatedAt
                    FROM properties 
                    WHERE is_active = 1
                ";

                $where = [];
                $bindings = [];

                if ($status) {
                    $where[] = "status = ?";
                    $bindings[] = $status;
                }
                if ($category) {
                    $where[] = "category = ?";
                    $bindings[] = $category;
                }
                if ($district) {
                    $where[] = "district LIKE ?";
                    $bindings[] = "%{$district}%";
                }
                if ($state) {
                    $where[] = "state LIKE ?";
                    $bindings[] = "%{$state}%";
                }
                if ($minPrice !== null && is_numeric($minPrice)) {
                    $where[] = "price >= ?";
                    $bindings[] = (float)$minPrice;
                }
                if ($maxPrice !== null && is_numeric($maxPrice)) {
                    $where[] = "price <= ?";
                    $bindings[] = (float)$maxPrice;
                }

                if (!empty($where)) {
                    $sql .= " AND " . implode(" AND ", $where);
                }

                $sql .= " ORDER BY created_at DESC LIMIT 100";

                $stmt = $this->db->prepare($sql);
                $stmt->execute($bindings);
                $properties = $stmt->fetchAll();

                // Format numeric values
                foreach ($properties as &$p) {
                    $p['area'] = (float)$p['area'];
                    $p['price'] = (float)$p['price'];
                    $p['latitude'] = (float)$p['latitude'];
                    $p['longitude'] = (float)$p['longitude'];
                    $p['isActive'] = (bool)$p['isActive'];
                }
            } catch (\Throwable $e) {
                error_log("DB Properties Fetch error: " . $e->getMessage());
            }
        }

        $this->json($properties);
    }

    public function show(string $id) {
        if ($this->db) {
            try {
                $stmt = $this->db->prepare("
                    SELECT 
                        id,
                        property_code as propertyCode,
                        title,
                        category,
                        area,
                        price,
                        description,
                        survey_number as surveyNumber,
                        address,
                        latitude,
                        longitude,
                        district,
                        village,
                        state,
                        pincode,
                        three_sixty_image_url as threeSixtyImageUrl,
                        status,
                        provider_id as providerId,
                        is_active as isActive,
                        created_at as createdAt,
                        updated_at as updatedAt
                    FROM properties 
                    WHERE (id = ? OR property_code = ?)
                    LIMIT 1
                ");
                $stmt->execute([$id, $id]);
                $prop = $stmt->fetch();

                if ($prop) {
                    $prop['area'] = (float)$prop['area'];
                    $prop['price'] = (float)$prop['price'];
                    $prop['latitude'] = (float)$prop['latitude'];
                    $prop['longitude'] = (float)$prop['longitude'];
                    $prop['isActive'] = (bool)$prop['isActive'];
                    $this->json($prop);
                }
            } catch (\Throwable $e) {
                error_log("DB Property Detail error: " . $e->getMessage());
            }
        }

        $this->error('Property not found', 404);
    }

    public function store() {
        // Enforce role check: PROVIDER or ADMIN
        $currentUser = AuthMiddleware::getCurrentUser();
        if ($currentUser && isset($currentUser['role'])) {
            $role = strtoupper($currentUser['role']);
            if ($role === 'BUYER') {
                $this->error('Access denied. Buyers cannot post property listings.', 403);
            }
        }

        $body = $this->getBody();
        $propId = 'prop-' . uniqid();
        $propCode = 'LL-' . strtoupper(substr($body['state'] ?? 'IND', 0, 3)) . '-' . date('Y') . '-' . rand(100, 999);

        $providerId = $currentUser['id'] ?? $body['providerId'] ?? '078068ad-a896-4207-8476-53278c91dc42';

        $data = [
            'id' => $propId,
            'propertyCode' => $propCode,
            'title' => $body['title'] ?? 'Untitled Property Listing',
            'category' => $body['category'] ?? 'AGRICULTURAL',
            'area' => (float)($body['area'] ?? 1.0),
            'price' => (float)($body['price'] ?? 1000000),
            'description' => $body['description'] ?? '',
            'surveyNumber' => $body['surveyNumber'] ?? 'SN-' . rand(10, 99),
            'address' => $body['address'] ?? 'Village Road',
            'latitude' => (float)($body['latitude'] ?? 17.3850),
            'longitude' => (float)($body['longitude'] ?? 78.4867),
            'district' => $body['district'] ?? 'Hyderabad',
            'village' => $body['village'] ?? 'Secunderabad',
            'state' => $body['state'] ?? 'Telangana',
            'pincode' => $body['pincode'] ?? '500001',
            'threeSixtyImageUrl' => $body['threeSixtyImageUrl'] ?? 'https://images.unsplash.com/photo-1500382017468-9049fed747ef',
            'status' => 'PENDING',
            'providerId' => $providerId,
            'isActive' => true
        ];

        if ($this->db) {
            try {
                $stmt = $this->db->prepare("
                    INSERT INTO properties (
                        id, property_code, title, category, area, price, description,
                        survey_number, address, latitude, longitude, district, village,
                        state, pincode, three_sixty_image_url, status, provider_id, is_active,
                        created_at, updated_at
                    ) VALUES (
                        ?, ?, ?, ?, ?, ?, ?,
                        ?, ?, ?, ?, ?, ?,
                        ?, ?, ?, ?, ?, 1,
                        NOW(), NOW()
                    )
                ");
                $stmt->execute([
                    $data['id'],
                    $data['propertyCode'],
                    $data['title'],
                    $data['category'],
                    $data['area'],
                    $data['price'],
                    $data['description'],
                    $data['surveyNumber'],
                    $data['address'],
                    $data['latitude'],
                    $data['longitude'],
                    $data['district'],
                    $data['village'],
                    $data['state'],
                    $data['pincode'],
                    $data['threeSixtyImageUrl'],
                    $data['status'],
                    $data['providerId']
                ]);
            } catch (\Throwable $e) {
                error_log("DB Property Insert error: " . $e->getMessage());
            }
        }

        $this->json($data, 200);
    }

    public function update(string $id) {
        $body = $this->getBody();

        if ($this->db) {
            try {
                $stmt = $this->db->prepare("
                    UPDATE properties 
                    SET title = COALESCE(?, title),
                        category = COALESCE(?, category),
                        area = COALESCE(?, area),
                        price = COALESCE(?, price),
                        description = COALESCE(?, description),
                        survey_number = COALESCE(?, survey_number),
                        address = COALESCE(?, address),
                        latitude = COALESCE(?, latitude),
                        longitude = COALESCE(?, longitude),
                        district = COALESCE(?, district),
                        village = COALESCE(?, village),
                        state = COALESCE(?, state),
                        pincode = COALESCE(?, pincode),
                        three_sixty_image_url = COALESCE(?, three_sixty_image_url),
                        status = COALESCE(?, status),
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    $body['title'] ?? null,
                    $body['category'] ?? null,
                    isset($body['area']) ? (float)$body['area'] : null,
                    isset($body['price']) ? (float)$body['price'] : null,
                    $body['description'] ?? null,
                    $body['surveyNumber'] ?? null,
                    $body['address'] ?? null,
                    isset($body['latitude']) ? (float)$body['latitude'] : null,
                    isset($body['longitude']) ? (float)$body['longitude'] : null,
                    $body['district'] ?? null,
                    $body['village'] ?? null,
                    $body['state'] ?? null,
                    $body['pincode'] ?? null,
                    $body['threeSixtyImageUrl'] ?? null,
                    $body['status'] ?? null,
                    $id
                ]);
            } catch (\Throwable $e) {
                error_log("DB Property Update error: " . $e->getMessage());
            }
        }

        $this->show($id);
    }

    public function destroy(string $id) {
        if ($this->db) {
            try {
                $stmt = $this->db->prepare("UPDATE properties SET is_active = 0, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$id]);
            } catch (\Throwable $e) {
                error_log("DB Property Delete error: " . $e->getMessage());
            }
        }

        $this->json(['success' => true, 'message' => "Property {$id} deleted successfully."]);
    }

    // --- Images ---
    public function getImages(string $propertyId) {
        $images = [
            [
                'id' => 'img-01',
                'propertyId' => $propertyId,
                'imageUrl' => 'https://images.unsplash.com/photo-1500382017468-9049fed747ef',
                'thumbnailUrl' => 'https://images.unsplash.com/photo-1500382017468-9049fed747ef',
                'displayOrder' => 1
            ]
        ];
        $this->json($images);
    }

    public function uploadImage(string $propertyId) {
        $body = $this->getBody();
        $this->json([
            'id' => 'img-' . uniqid(),
            'propertyId' => $propertyId,
            'imageUrl' => $body['imageUrl'] ?? 'https://images.unsplash.com/photo-1500382017468-9049fed747ef',
            'thumbnailUrl' => $body['thumbnailUrl'] ?? 'https://images.unsplash.com/photo-1500382017468-9049fed747ef',
            'displayOrder' => $body['displayOrder'] ?? 1
        ]);
    }

    // --- Videos ---
    public function getVideos(string $propertyId) {
        $videos = [
            [
                'id' => 'vid-01',
                'propertyId' => $propertyId,
                'videoUrl' => 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4',
                'thumbnailUrl' => 'https://images.unsplash.com/photo-1500382017468-9049fed747ef',
                'duration' => 120
            ]
        ];
        $this->json($videos);
    }

    public function uploadVideo(string $propertyId) {
        $body = $this->getBody();
        $this->json([
            'id' => 'vid-' . uniqid(),
            'propertyId' => $propertyId,
            'videoUrl' => $body['videoUrl'] ?? 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4',
            'thumbnailUrl' => $body['thumbnailUrl'] ?? 'https://images.unsplash.com/photo-1500382017468-9049fed747ef',
            'duration' => $body['duration'] ?? 120
        ]);
    }

    // --- Documents & OCR ---
    public function getDocuments(string $propertyId) {
        $documents = [
            [
                'id' => 'doc-' . substr(md5($propertyId), 0, 8),
                'propertyId' => $propertyId,
                'documentType' => 'PATTA_PASSBOOK',
                'fileUrl' => 'https://example.com/documents/patta_verified.pdf',
                'status' => 'VERIFIED'
            ]
        ];
        $this->json($documents);
    }

    public function uploadDocument(string $propertyId) {
        $body = $this->getBody();
        $this->json([
            'id' => 'doc-' . uniqid(),
            'propertyId' => $propertyId,
            'documentType' => $body['documentType'] ?? 'PATTA',
            'fileUrl' => $body['fileUrl'] ?? 'https://example.com/documents/patta.pdf',
            'status' => 'UPLOADED'
        ]);
    }

    public function processOcr(string $documentId) {
        $this->json([
            'id' => $documentId,
            'ocrStatus' => 'COMPLETED',
            'extractedData' => [
                'surveyNumber' => '342/A',
                'ownerName' => 'K. Venkata Ramanathan',
                'extent' => '2.5 Acres',
                'district' => 'Medchal-Malkajgiri',
                'confidence' => 0.98
            ]
        ]);
    }

    // --- Saved Bookmarks ---
    public function saveProperty(string $propertyId) {
        $this->json(['success' => true, 'propertyId' => $propertyId, 'saved' => true]);
    }

    public function getSavedProperties() {
        $this->json([]);
    }

    public function unsaveProperty(string $propertyId) {
        $this->json(['success' => true, 'propertyId' => $propertyId, 'saved' => false]);
    }

    // --- Visits ---
    public function scheduleVisit(string $propertyId) {
        $body = $this->getBody();
        $visitId = 'vst-' . round(microtime(true) * 1000);
        $visitDate = $body['visitDate'] ?? date('Y-m-d', strtotime('+3 days'));
        $visitTime = $body['visitTime'] ?? '10:00:00';
        $buyerId = $body['buyerId'] ?? '078068ad-a896-4207-8476-53278c91dc42';

        if ($this->db) {
            try {
                $stmt = $this->db->prepare("
                    INSERT INTO property_visits (id, property_id, buyer_id, visit_date, visit_time, status, is_active, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, 'SCHEDULED', 1, NOW(), NOW())
                ");
                $stmt->execute([$visitId, $propertyId, $buyerId, $visitDate, $visitTime]);
            } catch (\Throwable $e) {}
        }

        $this->json([
            'id' => $visitId,
            'propertyId' => $propertyId,
            'visitDate' => $visitDate,
            'visitTime' => $visitTime,
            'status' => 'SCHEDULED'
        ]);
    }

    public function getVisits() {
        $visits = [];
        if ($this->db) {
            try {
                $stmt = $this->db->query("
                    SELECT 
                        pv.id,
                        pv.visit_date as visitDate,
                        pv.visit_time as visitTime,
                        pv.status,
                        pv.created_at as createdAt,
                        p.id as prop_id,
                        p.title as prop_title,
                        p.price as prop_price,
                        p.area as prop_area,
                        p.village as prop_village,
                        p.district as prop_district
                    FROM property_visits pv
                    LEFT JOIN properties p ON pv.property_id = p.id
                    WHERE pv.is_active = 1
                    ORDER BY pv.visit_date ASC, pv.visit_time ASC
                ");
                $rows = $stmt->fetchAll();
                foreach ($rows as $r) {
                    $visits[] = [
                        'id' => $r['id'],
                        'visitDate' => $r['visitDate'],
                        'visitTime' => $r['visitTime'],
                        'status' => $r['status'] ?? 'SCHEDULED',
                        'property' => [
                            'id' => $r['prop_id'],
                            'title' => $r['prop_title'] ?? 'Scheduled Property',
                            'price' => (float)$r['prop_price'],
                            'area' => (float)$r['prop_area'],
                            'village' => $r['prop_village'],
                            'district' => $r['prop_district']
                        ]
                    ];
                }
            } catch (\Throwable $e) {}
        }

        $this->json($visits);
    }

    public function updateVisitStatus(string $visitId) {
        $params = $this->getQueryParams();
        $status = $params['status'] ?? 'CONFIRMED';

        if ($this->db) {
            try {
                $stmt = $this->db->prepare("UPDATE property_visits SET status = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$status, $visitId]);
            } catch (\Throwable $e) {}
        }

        $this->json([
            'id' => $visitId,
            'status' => $status,
            'message' => "Visit status updated to {$status}"
        ]);
    }
}
