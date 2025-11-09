# Call Logs API - Complete API Documentation

## Base URL

```
http://localhost:8000/api
```

## Authentication

The API uses Laravel Sanctum for token-based authentication. Include the token in the Authorization header:

```
Authorization: Bearer {your-token}
```

---

## API Endpoints

### 1. User Login

Authenticate a user and receive an API token.

**Endpoint:** `POST /api/login`

**Headers:**
```
Content-Type: application/json
Accept: application/json
```

**Request Body:**
```json
{
  "email": "user@example.com",
  "password": "password123"
}
```

**Success Response (200):**
```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "user": {
      "id": 1,
      "name": "John Doe",
      "email": "user@example.com",
      "phone": "1234567890",
      "role": "user",
      "status": "active"
    },
    "token": "1|xxxxxxxxxxxxxxxxxxxxxxxxxxx"
  }
}
```

**Error Response (401):**
```json
{
  "success": false,
  "message": "Invalid credentials"
}
```

**Error Response (403):**
```json
{
  "success": false,
  "message": "Account is inactive"
}
```

---

### 2. User Logout

Revoke the current access token.

**Endpoint:** `POST /api/logout`

**Headers:**
```
Content-Type: application/json
Accept: application/json
Authorization: Bearer {token}
```

**Success Response (200):**
```json
{
  "success": true,
  "message": "Logged out successfully"
}
```

---

### 3. Create Call Log (Single)

Create a single call log entry.

**Endpoint:** `POST /api/call-logs`

**Headers:**
```
Content-Type: application/json
Accept: application/json
Authorization: Bearer {token}
```

**Request Body:**
```json
{
  "caller_name": "John Smith",
  "caller_number": "+919876543210",
  "call_type": "incoming",
  "call_duration": 125,
  "call_timestamp": "2024-11-02 14:30:00",
  "notes": "Discussed project requirements"
}
```

**Validation Rules:**
- `caller_name`: optional, string, max 255 characters
- `caller_number`: required, string, max 50 characters
- `call_type`: required, must be one of: incoming, outgoing, missed, rejected
- `call_duration`: required, integer, minimum 0
- `call_timestamp`: required, date format Y-m-d H:i:s
- `notes`: optional, text

**Success Response (201):**
```json
{
  "success": true,
  "message": "Call log(s) created successfully",
  "data": {
    "call_logs": [
      {
        "id": 1,
        "user_id": 1,
        "caller_name": "John Smith",
        "caller_number": "+919876543210",
        "call_type": "incoming",
        "call_duration": 125,
        "call_timestamp": "2024-11-02 14:30:00",
        "notes": "Discussed project requirements",
        "created_at": "2024-11-02T14:35:00.000000Z",
        "updated_at": "2024-11-02T14:35:00.000000Z"
      }
    ]
  }
}
```

**Error Response (422):**
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "caller_number": ["The caller number field is required."],
    "call_type": ["The selected call type is invalid."]
  }
}
```

---

### 4. Create Call Logs (Bulk)

Create multiple call log entries in a single request.

**Endpoint:** `POST /api/call-logs`

**Headers:**
```
Content-Type: application/json
Accept: application/json
Authorization: Bearer {token}
```

**Request Body:**
```json
{
  "call_logs": [
    {
      "caller_name": "John Smith",
      "caller_number": "+919876543210",
      "call_type": "incoming",
      "call_duration": 125,
      "call_timestamp": "2024-11-02 14:30:00",
      "notes": "Discussed project requirements"
    },
    {
      "caller_name": "Jane Doe",
      "caller_number": "+919876543211",
      "call_type": "outgoing",
      "call_duration": 85,
      "call_timestamp": "2024-11-02 15:45:00",
      "notes": null
    }
  ]
}
```

**Success Response (201):**
```json
{
  "success": true,
  "message": "Call log(s) created successfully",
  "data": {
    "call_logs": [
      {
        "id": 1,
        "user_id": 1,
        "caller_name": "John Smith",
        "caller_number": "+919876543210",
        "call_type": "incoming",
        "call_duration": 125,
        "call_timestamp": "2024-11-02 14:30:00",
        "notes": "Discussed project requirements",
        "created_at": "2024-11-02T14:35:00.000000Z",
        "updated_at": "2024-11-02T14:35:00.000000Z"
      },
      {
        "id": 2,
        "user_id": 1,
        "caller_name": "Jane Doe",
        "caller_number": "+919876543211",
        "call_type": "outgoing",
        "call_duration": 85,
        "call_timestamp": "2024-11-02 15:45:00",
        "notes": null,
        "created_at": "2024-11-02T15:46:00.000000Z",
        "updated_at": "2024-11-02T15:46:00.000000Z"
      }
    ]
  }
}
```

---

### 5. Get All Call Logs

Retrieve all call logs for the authenticated user with pagination and filters.

**Endpoint:** `GET /api/call-logs`

**Headers:**
```
Accept: application/json
Authorization: Bearer {token}
```

**Query Parameters:**
- `page`: integer, default 1
- `per_page`: integer, default 20, max 100
- `call_type`: string, filter by type (incoming, outgoing, missed, rejected)
- `date_from`: date, format Y-m-d
- `date_to`: date, format Y-m-d
- `search`: string, search in caller_name or caller_number

**Example Request:**
```
GET /api/call-logs?page=1&per_page=20&call_type=incoming&date_from=2024-11-01&date_to=2024-11-02&search=John
```

**Success Response (200):**
```json
{
  "success": true,
  "message": "Call logs retrieved successfully",
  "data": {
    "call_logs": [
      {
        "id": 1,
        "user_id": 1,
        "caller_name": "John Smith",
        "caller_number": "+919876543210",
        "call_type": "incoming",
        "call_duration": 125,
        "call_timestamp": "2024-11-02 14:30:00",
        "notes": "Discussed project requirements",
        "recordings_count": 1,
        "created_at": "2024-11-02T14:35:00.000000Z",
        "updated_at": "2024-11-02T14:35:00.000000Z"
      }
    ],
    "pagination": {
      "current_page": 1,
      "per_page": 20,
      "total": 45,
      "last_page": 3,
      "from": 1,
      "to": 20
    }
  }
}
```

---

### 6. Get Single Call Log

Retrieve a specific call log with all its recordings.

**Endpoint:** `GET /api/call-logs/{id}`

**Headers:**
```
Accept: application/json
Authorization: Bearer {token}
```

**Success Response (200):**
```json
{
  "success": true,
  "message": "Call log retrieved successfully",
  "data": {
    "call_log": {
      "id": 1,
      "user_id": 1,
      "caller_name": "John Smith",
      "caller_number": "+919876543210",
      "call_type": "incoming",
      "call_duration": 125,
      "call_timestamp": "2024-11-02 14:30:00",
      "notes": "Discussed project requirements",
      "created_at": "2024-11-02T14:35:00.000000Z",
      "updated_at": "2024-11-02T14:35:00.000000Z",
      "recordings": [
        {
          "id": 1,
          "call_log_id": 1,
          "file_name": "call_recording_20241102_143000.mp3",
          "file_path": "recordings/1730551560_call_recording_20241102_143000.mp3",
          "file_size": 2048576,
          "file_type": "audio/mpeg",
          "duration": 125,
          "created_at": "2024-11-02T14:36:00.000000Z",
          "updated_at": "2024-11-02T14:36:00.000000Z",
          "file_url": "http://localhost:8000/storage/recordings/1730551560_call_recording_20241102_143000.mp3"
        }
      ]
    }
  }
}
```

**Error Response (404):**
```json
{
  "success": false,
  "message": "Call log not found"
}
```

---

### 7. Upload Call Recording

Upload an audio recording file for a specific call log.

**Endpoint:** `POST /api/call-recordings`

**Headers:**
```
Content-Type: multipart/form-data
Accept: application/json
Authorization: Bearer {token}
```

**Request Body (Form Data):**
- `call_log_id`: integer, required
- `recording`: file, required, mimes:mp3,wav,m4a,3gp, max:51200 (50MB)
- `duration`: integer, optional, minimum 0

**Success Response (201):**
```json
{
  "success": true,
  "message": "Recording uploaded successfully",
  "data": {
    "recording": {
      "id": 1,
      "call_log_id": 1,
      "file_name": "call_recording_20241102_143000.mp3",
      "file_path": "recordings/1730551560_call_recording_20241102_143000.mp3",
      "file_size": 2048576,
      "file_type": "audio/mpeg",
      "duration": 125,
      "created_at": "2024-11-02T14:36:00.000000Z",
      "updated_at": "2024-11-02T14:36:00.000000Z",
      "file_url": "http://localhost:8000/storage/recordings/1730551560_call_recording_20241102_143000.mp3"
    }
  }
}
```

**Error Response (422):**
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "call_log_id": ["The call log id field is required."],
    "recording": ["The recording must be a file of type: mp3, wav, m4a, 3gp."]
  }
}
```

**Error Response (403):**
```json
{
  "success": false,
  "message": "You do not have permission to upload recording for this call log"
}
```

---

### 8. Get Recordings for Call Log

Retrieve all recordings for a specific call log.

**Endpoint:** `GET /api/call-logs/{id}/recordings`

**Headers:**
```
Accept: application/json
Authorization: Bearer {token}
```

**Success Response (200):**
```json
{
  "success": true,
  "message": "Recordings retrieved successfully",
  "data": {
    "recordings": [
      {
        "id": 1,
        "call_log_id": 1,
        "file_name": "call_recording_20241102_143000.mp3",
        "file_path": "recordings/1730551560_call_recording_20241102_143000.mp3",
        "file_size": 2048576,
        "file_type": "audio/mpeg",
        "duration": 125,
        "created_at": "2024-11-02T14:36:00.000000Z",
        "updated_at": "2024-11-02T14:36:00.000000Z",
        "file_url": "http://localhost:8000/storage/recordings/1730551560_call_recording_20241102_143000.mp3"
      }
    ]
  }
}
```

**Error Response (404):**
```json
{
  "success": false,
  "message": "Call log not found"
}
```

---

### 9. Delete Recording

Delete a specific call recording.

**Endpoint:** `DELETE /api/call-recordings/{id}`

**Headers:**
```
Accept: application/json
Authorization: Bearer {token}
```

**Success Response (200):**
```json
{
  "success": true,
  "message": "Recording deleted successfully"
}
```

**Error Response (404):**
```json
{
  "success": false,
  "message": "Recording not found"
}
```

**Error Response (403):**
```json
{
  "success": false,
  "message": "You do not have permission to delete this recording"
}
```

---

## Error Codes

| Status Code | Description |
|-------------|-------------|
| 200 | OK - Request successful |
| 201 | Created - Resource created successfully |
| 400 | Bad Request - Invalid request |
| 401 | Unauthorized - Invalid or missing token |
| 403 | Forbidden - No permission to access resource |
| 404 | Not Found - Resource not found |
| 422 | Unprocessable Entity - Validation failed |
| 500 | Internal Server Error - Server error |

---

## Testing with cURL

### Login
```bash
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "email": "user@example.com",
    "password": "password123"
  }'
```

### Create Call Log
```bash
curl -X POST http://localhost:8000/api/call-logs \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -d '{
    "caller_name": "John Smith",
    "caller_number": "+919876543210",
    "call_type": "incoming",
    "call_duration": 125,
    "call_timestamp": "2024-11-02 14:30:00",
    "notes": "Test call"
  }'
```

### Get Call Logs
```bash
curl -X GET "http://localhost:8000/api/call-logs?page=1&per_page=20" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

### Upload Recording
```bash
curl -X POST http://localhost:8000/api/call-recordings \
  -H "Accept: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -F "call_log_id=1" \
  -F "recording=@/path/to/audio.mp3" \
  -F "duration=125"
```

---

## Notes

- All timestamps are in UTC
- File uploads support: mp3, wav, m4a, 3gp formats
- Maximum file size: 50MB
- Pagination is available on the call logs listing endpoint
- All authenticated endpoints require a valid Bearer token
- Users can only access their own call logs and recordings
