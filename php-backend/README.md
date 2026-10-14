# 🌟 LandLens Enterprise PHP Backend API

This directory contains the **complete, native PHP backend** converted from Spring Boot REST controllers. It provides 100% endpoint parity, high performance, robust PDO database connectivity, JWT/Bearer token authentication, and NVIDIA AI vision/chat integration.

---

## 🚀 Quick Start

### 1. Run Locally
To run the PHP API server locally on port **5000**:

```bash
# Using PHP CLI Built-in Server
php -S 0.0.0.0:5000 router.php
```

Or double-click:
`start_server.bat`

The API will be available at: **`http://localhost:5000`**

### 2. Verify Health
Open [http://localhost:5000/api/health](http://localhost:5000/api/health) in your browser:
```json
{
  "status": "UP",
  "service": "LandLens PHP Backend Service",
  "runtime": "PHP 8.2.12",
  "database": "CONNECTED",
  "aiModel": "meta/llama-3.2-11b-vision-instruct",
  "timestamp": "2026-10-09T16:40:00+05:30",
  "region": "ap-south-1"
}
```

---

## 📂 Project Architecture

```
php-backend/
├── config/
│   ├── Config.php          # Environment configs, NVIDIA API Keys, JWT secrets
│   └── Database.php        # High-performance PDO connection pool & failover
├── controllers/
│   ├── BaseController.php       # JSON output, request parsing, HTTP status helpers
│   ├── AuthController.php       # Login, Register, Token Refresh, Logout, Health
│   ├── UserController.php       # Profiles (/api/users/me), user administration
│   ├── PropertyController.php   # Property CRUD, filters, images, videos, docs, visits
│   ├── VerificationController.php # Dual-layer AI verification, Govt officer sign-off
│   ├── FraudController.php      # Fraud reports, officer assignments, resolutions
│   ├── NotificationController.php# User notifications and alerts
│   ├── DeveloperController.php  # API Key generation, external partner verify (x-api-key)
│   ├── AnalyticsController.php  # Dashboard KPIs, property counts, citizen stats
│   └── AiController.php         # NVIDIA Llama 3.2 Vision chat & land valuation
├── middleware/
│   ├── AuthMiddleware.php       # JWT / ll-b64 token decoder & role permissions
│   └── CorsMiddleware.php       # Universal cross-origin preflight handling
├── routes/
│   └── Router.php               # Dynamic regex REST route dispatcher
├── public/
│   └── index.php                # Master Front Controller & route table
├── .htaccess                    # Production Apache rewrite rules (Hostinger / cPanel)
├── router.php                   # CLI web server routing handler
├── start_server.bat             # 1-click startup script for Windows
└── test_php_backend.php         # Automated unit & integration test runner
```

---

## 📋 Converted Spring Boot to PHP Endpoints Directory

### 1. Authentication & System (`AuthController.php`)
| HTTP Method | Endpoint | Spring Boot Equivalent | Description |
| :--- | :--- | :--- | :--- |
| `GET` | `/api/health` | `HealthController.getHealth()` | Service health check |
| `POST` | `/api/auth/login` | `AuthController.authenticateUser()` | Login user & generate JWT/Bearer token |
| `POST` | `/api/auth/register` | `AuthController.registerUser()` | Register user with role (BUYER, PROVIDER, etc.) |
| `POST` | `/api/auth/refresh` | `AuthController.refreshToken()` | Refresh active access token |
| `POST` | `/api/auth/logout` | `AuthController.logoutUser()` | Revoke session |
| `POST` | `/api/auth/forgot-password` | `AuthController.resetPassword()` | Reset user credentials |

### 2. Users & Profiles (`UserController.php`)
| HTTP Method | Endpoint | Spring Boot Equivalent | Description |
| :--- | :--- | :--- | :--- |
| `GET` | `/api/users/me` | `UserController.getCurrentUser()` | Get authenticated user profile |
| `GET` | `/api/users` | `UserController.getAllUsers()` | List users (ADMIN restricted) |
| `GET` | `/api/users/{id}` | `UserController.getUserById()` | Get user by ID |
| `PUT` | `/api/users/{id}` | `UserController.updateUser()` | Update user profile |

### 3. Properties Management (`PropertyController.php`)
| HTTP Method | Endpoint | Spring Boot Equivalent | Description |
| :--- | :--- | :--- | :--- |
| `GET` | `/api/properties` | `PropertyController.getProperties()` | List & filter properties by district, category, price |
| `GET` | `/api/properties/{id}` | `PropertyController.getPropertyById()` | Get single property listing details |
| `POST` | `/api/properties` | `PropertyController.createProperty()` | Create property (PROVIDER / ADMIN restricted) |
| `PUT` | `/api/properties/{id}` | `PropertyController.updateProperty()` | Update property listing |
| `DELETE`| `/api/properties/{id}` | `PropertyController.deleteProperty()` | Delete / deactivate property |
| `POST` | `/api/properties/{id}/save` | `PropertyController.saveProperty()` | Bookmark property for buyer |
| `GET` | `/api/properties/saved` | `PropertyController.getSaved()` | List buyer's bookmarked properties |
| `DELETE`| `/api/properties/{id}/save` | `PropertyController.unsaveProperty()` | Remove bookmark |
| `POST` | `/api/properties/{id}/visit` | `VisitController.scheduleVisit()` | Schedule physical site visit |
| `GET` | `/api/properties/visits` | `VisitController.getVisits()` | List scheduled visits |
| `PUT` | `/api/properties/visits/{id}`| `VisitController.updateVisitStatus()`| Confirm / complete visit |
| `GET` | `/api/properties/{id}/images`| `PropertyController.getImages()` | Get property gallery images |
| `POST` | `/api/properties/{id}/images`| `PropertyController.uploadImage()` | Upload property photo |
| `GET` | `/api/properties/{id}/videos`| `PropertyController.getVideos()` | Get property walkthrough videos |
| `POST` | `/api/properties/{id}/videos`| `PropertyController.uploadVideo()` | Upload video walkthrough |
| `GET` | `/api/properties/{id}/documents`| `DocumentController.getDocuments()`| Get uploaded legal documents |
| `POST` | `/api/properties/{id}/documents`| `DocumentController.uploadDoc()` | Upload Patta / Title Deed |
| `POST` | `/api/documents/{id}/ocr` | `DocumentController.processOcr()` | Trigger mock OCR document extraction |

### 4. Verification & Government Audit (`VerificationController.php`)
| HTTP Method | Endpoint | Spring Boot Equivalent | Description |
| :--- | :--- | :--- | :--- |
| `POST` | `/api/properties/{id}/ai-verify` | `VerificationController.aiVerify()` | Run AI dual-layer cross-check |
| `GET` | `/api/properties/{id}/ai-verification` | `VerificationController.getReport()` | Retrieve AI Trust Score & forgery analysis |
| `POST` | `/api/properties/{id}/government-verify`| `VerificationController.govtSignOff()` | Revenue officer digital sign-off |
| `GET` | `/api/properties/{id}/timeline` | `AuditController.getPropertyTimeline()` | Property audit timeline logs |
| `GET` | `/api/verification/timeline` | `AuditController.getGeneralTimeline()` | Global verification stages |
| `POST` | `/api/verification/verify` | `VerificationController.verifyDeed()` | Instant survey match & overlap scan |

### 5. Fraud Investigation (`FraudController.php`)
| HTTP Method | Endpoint | Spring Boot Equivalent | Description |
| :--- | :--- | :--- | :--- |
| `GET` | `/api/fraud-reports` | `FraudController.getAllReports()` | List active fraud alerts |
| `GET` | `/api/properties/{id}/fraud-reports`| `FraudController.getByProperty()` | Get dispute history for property |
| `POST` | `/api/properties/{id}/fraud-reports`| `FraudController.submitReport()` | Citizen dispute / fake-seller report |
| `PUT` | `/api/fraud-reports/{id}/assign` | `FraudController.assignOfficer()` | Assign case to revenue officer |
| `PUT` | `/api/fraud-reports/{id}/resolve` | `FraudController.resolveReport()` | Mark dispute resolved or fraudulent |

### 6. Developer & External API (`DeveloperController.php`)
| HTTP Method | Endpoint | Spring Boot Equivalent | Description |
| :--- | :--- | :--- | :--- |
| `GET` | `/api/developer/keys` | `ApiKeyController.getKeys()` | List developer API keys |
| `POST` | `/api/developer/keys` | `ApiKeyController.createKey()` | Generate new `LL_LIVE` API key |
| `GET` | `/api/developer/keys/{id}/logs` | `ApiKeyController.getKeyLogs()` | View rate limit & request audit logs |
| `DELETE`| `/api/developer/keys/{id}` | `ApiKeyController.revokeKey()` | Revoke developer API key |
| `GET` | `/api/v1/external/properties/{code}/verify` | `ExternalApiController.verify()` | Partner API verification (using `x-api-key`) |

### 7. AI Engine & Valuation (`AiController.php`)
| HTTP Method | Endpoint | Spring Boot Equivalent | Description |
| :--- | :--- | :--- | :--- |
| `POST` | `/api/ai/chat` | `AiAssistantController.chat()` | Live NVIDIA Llama 3.2 Vision Chat |
| `POST` | `/api/ai/estimate-price` | `ValuationController.estimatePrice()` | ML cadastral rate estimation |
| `GET` | `/api/ai/conversations` | `AiAssistantController.getThreads()` | List user consultation threads |
| `POST` | `/api/ai/conversations` | `AiAssistantController.createThread()`| Start new AI consultation thread |
| `POST` | `/api/ai/conversations/{id}/messages` | `AiAssistantController.postMessage()` | Stream response to conversation thread |

---

## 🌐 Production Deployment Guide

### Deploying to Hostinger / cPanel / Shared Hosting
1. Upload the contents of `php-backend/` into `public_html/` (or a subdirectory like `public_html/api/`).
2. Ensure `.htaccess` is uploaded to enable URL rewrites.
3. Configure your database credentials in `config/Config.php` or as server environment variables.
4. Ensure PHP 8.1+ is selected with `pdo_mysql` and `curl` extensions enabled.

### Running with React Frontend
The React frontend connects directly to `http://localhost:5000` (or your production URL).
Configure `frontend-react/.env`:
```env
VITE_API_BASE_URL=http://localhost:5000
```
