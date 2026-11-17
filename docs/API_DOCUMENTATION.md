# ServiceFlow Versioned REST API (v1)

**Base URL:** `/api/v1`  
**Authentication:** HTTP Bearer Token via Laravel Sanctum  
**Rate Limiting:** 60 requests per minute per IP/user (`throttle:60,1`)  
**Tenant Scoping Header:** `X-Organization-Id: <tenant-id>`

---

## 1. Authentication Endpoints

### 1.1 Login
- **Method / Endpoint:** `POST /api/v1/auth/login`
- **Payload:**
  ```json
  {
    "email": "technician@serviceflow.test",
    "password": "StrongSecretPassword!",
    "device_name": "Field iPhone 15",
    "organization_id": 1
  }
  ```
- **Success Response (200 OK):**
  ```json
  {
    "success": true,
    "message": "Login successful.",
    "data": {
      "token": "1|ABC123PlainTextBearerToken...",
      "user": {
        "id": 5,
        "name": "Leo Mobile Tech",
        "email": "technician@serviceflow.test"
      },
      "organization": {
        "id": 1,
        "name": "Apex HVAC Solutions",
        "slug": "apex-hvac-solutions"
      }
    }
  }
  ```

### 1.2 Current User Profile & Memberships
- **Method / Endpoint:** `GET /api/v1/auth/me`
- **Headers:** `Authorization: Bearer <token>`
- **Success Response (200 OK):**
  ```json
  {
    "success": true,
    "data": {
      "user": { "id": 5, "name": "Leo Mobile Tech", "email": "technician@serviceflow.test" },
      "memberships": [
        {
          "organization_id": 1,
          "organization_name": "Apex HVAC Solutions",
          "roles": ["technician"]
        }
      ]
    }
  }
  ```

### 1.3 Logout
- **Method / Endpoint:** `POST /api/v1/auth/logout`
- **Headers:** `Authorization: Bearer <token>`
- **Success Response (200 OK):**
  ```json
  {
    "success": true,
    "message": "Successfully logged out and revoked current access token."
  }
  ```

---

## 2. Work Order Execution Endpoints

All endpoints below require:
1. `Authorization: Bearer <token>`
2. `X-Organization-Id: <tenant_id>` (or query `organization_id`)

### 2.1 List Assigned Work Orders
- **Method / Endpoint:** `GET /api/v1/work-orders?status=scheduled&page=1`
- **Success Response (200 OK):**
  ```json
  {
    "success": true,
    "data": [
      {
        "id": 12,
        "work_order_number": "WO-01K8...",
        "title": "Quarterly Chiller Maintenance",
        "status": "scheduled",
        "scheduled_start_at": "2026-11-17T09:00:00Z",
        "scheduled_end_at": "2026-11-17T11:00:00Z",
        "customer": { "id": 2, "name": "Prestige Heights", "phone": "+91 98765 43210" },
        "branch": { "id": 1, "name": "Downtown Service Hub" }
      }
    ],
    "meta": {
      "current_page": 1,
      "last_page": 1,
      "per_page": 15,
      "total": 1
    }
  }
  ```

### 2.2 Work Order Details
- **Method / Endpoint:** `GET /api/v1/work-orders/{id}`
- **Success Response (200 OK):** returns complete work order model including `customer`, `address`, `equipment`, `checklists`, and `entries`.

### 2.3 Status Transition
- **Method / Endpoint:** `POST /api/v1/work-orders/{id}/status`
- **Payload:**
  ```json
  {
    "status": "completed",
    "reason": null,
    "completion_notes": "Completed inspection and replaced air filters."
  }
  ```

### 2.4 Toggle Checklist Item
- **Method / Endpoint:** `POST /api/v1/work-orders/{id}/checklists/{checklistId}/toggle`
- **Success Response (200 OK):**
  ```json
  {
    "success": true,
    "message": "Checklist item toggled.",
    "data": { "id": 4, "is_completed": true, "completed_at": "2026-11-17T12:00:00Z" }
  }
  ```

### 2.5 Record Labour or Part Entry
- **Method / Endpoint:** `POST /api/v1/work-orders/{id}/entries`
- **Payload:**
  ```json
  {
    "type": "labor",
    "description": "Cleaned condenser and replaced intake filters",
    "minutes": 90
  }
  ```

### 2.6 Customer Sign-off
- **Method / Endpoint:** `POST /api/v1/work-orders/{id}/sign`
- **Payload:**
  ```json
  {
    "customer_signature_name": "Rohan Facility Manager"
  }
  ```

---

## 3. Error Handling Contract

| HTTP Code | Condition | JSON Structure |
|---|---|---|
| **401 Unauthorized** | Missing or revoked Bearer token | `{ "success": false, "message": "Unauthenticated." }` |
| **403 Forbidden** | User not a member of target tenant | `{ "success": false, "message": "Forbidden. You are not an active member of this organization." }` |
| **404 Not Found** | Record does not exist in tenant | `{ "success": false, "message": "Work order not found." }` |
| **422 Unprocessable** | Validation error or invalid state transition | `{ "success": false, "message": "Error details", "errors": { "field": ["validation message"] } }` |
| **429 Too Many Requests** | Rate limit exceeded | `{ "message": "Too Many Attempts." }` |
