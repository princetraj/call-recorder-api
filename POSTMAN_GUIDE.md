# Postman Testing Guide - Call Logs API

This guide will help you test the Call Logs API using Postman.

## Quick Setup

### 1. Import Collection & Environment

1. Open Postman
2. Click **Import** button (top left)
3. Select **Files** tab
4. Import both files:
   - `Call_Logs_API.postman_collection.json`
   - `Call_Logs_API.postman_environment.json`

### 2. Select Environment

In the top right corner of Postman:
1. Click the environment dropdown
2. Select **"Call Logs API - Local"**

### 3. Start Your API Server

Make sure your Laravel server is running:
```bash
cd f:\works\call-logs-api
php artisan serve
```

## Testing Flow

### Step 1: Login (Required First!)

1. Navigate to: **Authentication → Login**
2. The payload is already filled in:
   ```json
   {
     "email": "user@example.com",
     "password": "password123"
   }
   ```
3. Click **Send**
4. If successful, the **auth_token** will be automatically saved to your environment
5. Check the response - you should see:
   ```json
   {
     "success": true,
     "message": "Login successful",
     "data": {
       "user": {...},
       "token": "1|xxxxxxxxxxx"
     }
   }
   ```

**Note:** The token is automatically saved and will be used for all subsequent requests!

### Step 2: Create Call Logs

Now you can test creating call logs. Try these requests:

#### A. Create Single Incoming Call
Navigate to: **Call Logs → Create Single Call Log**
- Pre-filled with incoming call data
- Click **Send**

#### B. Create Outgoing Call
Navigate to: **Call Logs → Create Outgoing Call Log**
- Pre-filled with outgoing call data
- Click **Send**

#### C. Create Missed Call
Navigate to: **Call Logs → Create Missed Call Log**
- Pre-filled with missed call data (duration = 0)
- Click **Send**

#### D. Create Rejected Call
Navigate to: **Call Logs → Create Rejected Call Log**
- Pre-filled with rejected call data
- Click **Send**

#### E. Create Bulk Call Logs
Navigate to: **Call Logs → Create Bulk Call Logs**
- Creates 3 call logs at once
- Click **Send**

### Step 3: Retrieve Call Logs

#### Get All Call Logs
Navigate to: **Call Logs → Get All Call Logs**
- Returns paginated list (20 per page)
- Click **Send**

#### Filter by Call Type
Navigate to: **Call Logs → Get Call Logs - Filter by Type (Incoming)**
- Returns only incoming calls
- You can change `call_type` to: `outgoing`, `missed`, or `rejected`

#### Filter by Date Range
Navigate to: **Call Logs → Get Call Logs - Filter by Date Range**
- Filters calls between two dates
- Modify the query parameters:
  - `date_from`: 2024-11-01
  - `date_to`: 2024-11-02

#### Search Call Logs
Navigate to: **Call Logs → Get Call Logs - Search**
- Search by caller name or number
- Default search term: "John"
- Modify the `search` parameter as needed

#### Get Single Call Log
Navigate to: **Call Logs → Get Single Call Log**
- Replace `1` in the URL with actual call log ID
- Example: `{{base_url}}/call-logs/5`

### Step 4: Test Call Recordings (Optional)

#### Upload Recording
Navigate to: **Call Recordings → Upload Call Recording**

1. Make sure you have a call log created first (note its ID)
2. Update `call_log_id` to the actual call log ID
3. Click on the **recording** field
4. Click **Select Files** and choose an audio file (MP3, WAV, M4A, or 3GP)
5. Click **Send**

#### Get Recordings for a Call Log
Navigate to: **Call Recordings → Get Recordings for Call Log**
- Replace `1` in URL with the actual call log ID
- Click **Send**

#### Delete Recording
Navigate to: **Call Recordings → Delete Recording**
- Replace `1` in URL with the actual recording ID
- Click **Send**

## All Available Requests

### Authentication (2 requests)
- ✅ **Login** - Get authentication token
- ✅ **Logout** - Revoke current token

### Call Logs (10 requests)
- ✅ **Create Single Call Log** - Add one call log
- ✅ **Create Outgoing Call Log** - Example outgoing call
- ✅ **Create Missed Call Log** - Example missed call
- ✅ **Create Rejected Call Log** - Example rejected call
- ✅ **Create Bulk Call Logs** - Add multiple calls at once
- ✅ **Get All Call Logs** - List all with pagination
- ✅ **Get Call Logs - Filter by Type** - Filter by incoming/outgoing/missed/rejected
- ✅ **Get Call Logs - Filter by Date Range** - Filter by date
- ✅ **Get Call Logs - Search** - Search by name or number
- ✅ **Get Single Call Log** - Get specific call log with recordings

### Call Recordings (3 requests)
- ✅ **Upload Call Recording** - Upload audio file
- ✅ **Get Recordings for Call Log** - Get all recordings for a call
- ✅ **Delete Recording** - Remove a recording

**Total: 15 API requests ready to test!**

## Sample Test Payloads

### Incoming Call (Long Duration)
```json
{
  "caller_name": "Important Client",
  "caller_number": "+919123456789",
  "call_type": "incoming",
  "call_duration": 1800,
  "call_timestamp": "2024-11-02 09:00:00",
  "notes": "Discussed new contract terms"
}
```

### Outgoing Call (Quick Follow-up)
```json
{
  "caller_name": "Team Member",
  "caller_number": "+919987654321",
  "call_type": "outgoing",
  "call_duration": 45,
  "call_timestamp": "2024-11-02 10:30:00",
  "notes": "Quick status update"
}
```

### Missed Call (No Duration)
```json
{
  "caller_name": "Unknown",
  "caller_number": "+919555555555",
  "call_type": "missed",
  "call_duration": 0,
  "call_timestamp": "2024-11-02 14:20:00"
}
```

### Rejected Call (Spam)
```json
{
  "caller_name": null,
  "caller_number": "+911234567890",
  "call_type": "rejected",
  "call_duration": 0,
  "call_timestamp": "2024-11-02 16:45:00",
  "notes": "Suspected spam call"
}
```

## Tips & Tricks

### 1. Auto-Save Token
The Login request has a test script that automatically saves your token to the environment. You don't need to copy/paste it manually!

### 2. Check Environment Variables
Click the eye icon (👁️) next to the environment dropdown to see:
- `base_url` - Your API URL
- `auth_token` - Your current authentication token
- `user_id` - Your user ID

### 3. Modify Timestamps
All timestamps use format: `YYYY-MM-DD HH:MM:SS`
Example: `2024-11-02 14:30:00`

Update to current date/time for realistic testing.

### 4. Call Types
Valid call types:
- `incoming`
- `outgoing`
- `missed`
- `rejected`

### 5. Pagination
For "Get All Call Logs" request, you can modify:
- `page` - Page number (default: 1)
- `per_page` - Results per page (default: 20, max: 100)

Example: `{{base_url}}/call-logs?page=2&per_page=50`

### 6. Combine Filters
You can combine multiple filters:
```
{{base_url}}/call-logs?call_type=incoming&date_from=2024-11-01&search=John&per_page=10
```

## Troubleshooting

### "Unauthenticated" Error (401)
- You need to login first!
- Your token may have expired - login again
- Check that the environment is selected

### "Validation failed" Error (422)
- Check the payload format
- Ensure required fields are present:
  - `caller_number` (required)
  - `call_type` (required, must be: incoming/outgoing/missed/rejected)
  - `call_duration` (required, integer)
  - `call_timestamp` (required, format: Y-m-d H:i:s)

### "Not Found" Error (404)
- Check the call log ID exists
- Make sure you created call logs first

### Connection Error
- Ensure Laravel server is running: `php artisan serve`
- Check `base_url` is correct: `http://localhost:8000/api`
- Verify firewall isn't blocking port 8000

## Testing Checklist

Use this checklist to ensure everything works:

- [ ] Login successfully
- [ ] Create incoming call log
- [ ] Create outgoing call log
- [ ] Create missed call log
- [ ] Create rejected call log
- [ ] Create bulk call logs (3 at once)
- [ ] Get all call logs (verify pagination)
- [ ] Filter by call type (incoming)
- [ ] Filter by date range
- [ ] Search by name/number
- [ ] Get single call log by ID
- [ ] Upload call recording (if you have audio file)
- [ ] Get recordings for a call log
- [ ] Delete a recording
- [ ] Logout

## Verify in Database

After creating call logs, verify in your database:

```bash
cd f:\works\call-logs-api
php artisan tinker
```

Then run:
```php
// Get all call logs
\App\Models\CallLog::all();

// Get latest call log
\App\Models\CallLog::latest()->first();

// Count total call logs
\App\Models\CallLog::count();

// Get call logs by type
\App\Models\CallLog::where('call_type', 'incoming')->get();
```

## Ready to Test!

1. ✅ Import the Postman collection
2. ✅ Import the environment
3. ✅ Select the environment
4. ✅ Start Laravel server
5. ✅ Run Login request first
6. ✅ Test all other endpoints

**Happy Testing! 🚀**
