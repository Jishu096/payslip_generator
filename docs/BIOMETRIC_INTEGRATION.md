# Biometric Attendance Integration Guide

## 📋 Overview
This system is ready to integrate with biometric devices for automated attendance tracking.

## 🔌 API Endpoint
**URL:** `http://your-domain/payslip_generator/public/api/biometric-attendance.php`
**Method:** POST
**Content-Type:** application/json

## 🔑 Authentication
Set your API key in `/public/api/biometric-attendance.php`:
```php
define('BIOMETRIC_API_KEY', 'your_secure_random_key_here');
```

## 📤 Request Format
```json
{
  "api_key": "your_secure_random_key_here",
  "employee_id": 123,
  "timestamp": "2026-01-07 09:15:30",
  "status": "present",
  "device_id": "BIO-001",
  "type": "check_in"
}
```

### Required Fields
- `api_key` - Authentication key
- `employee_id` - Employee ID from your database
- `timestamp` - Date and time of attendance (YYYY-MM-DD HH:MM:SS)

### Optional Fields
- `status` - Attendance status (default: "present")
  - Values: `present`, `absent`, `leave`, `holiday`
- `device_id` - Biometric device identifier
- `type` - Event type (default: "attendance")
  - Values: `check_in`, `check_out`, `attendance`

## 📥 Response Format

### Success Response (200)
```json
{
  "success": true,
  "message": "Attendance recorded successfully",
  "attendance_id": 456,
  "action": "created"
}
```

### Error Response (400/401/404/500)
```json
{
  "success": false,
  "error": "Error message here"
}
```

## 🔧 Database Enhancements (Optional)

If you need advanced biometric features, run this SQL:
```bash
mysql -u root -p payslip_generator < database/biometric_enhancement.sql
```

This adds:
- `device_id` column to track which device recorded attendance
- `check_in_time` and `check_out_time` for precise timing
- `biometric_devices` table to manage multiple devices
- `employee_biometric` table for enrollment data
- `attendance_sync_log` for tracking sync status

## 🛠️ Supported Devices

### ZKTeco Devices
Most ZKTeco devices support webhook/push mode or REST API.

**Example cURL from ZKTeco:**
```bash
curl -X POST http://your-server/payslip_generator/public/api/biometric-attendance.php \
  -H "Content-Type: application/json" \
  -d '{
    "api_key": "your_key",
    "employee_id": 123,
    "timestamp": "2026-01-07 09:15:30",
    "device_id": "ZKTECO-001"
  }'
```

### eSSL Devices
Configure device to send HTTP POST to the API endpoint.

### Suprema BioStar
Use BioStar SDK or configure webhook integration.

## 📊 Testing the API

### Test with cURL:
```bash
curl -X POST http://localhost/payslip_generator/public/api/biometric-attendance.php \
  -H "Content-Type: application/json" \
  -d '{
    "api_key": "CHANGE_THIS_TO_SECURE_KEY_12345",
    "employee_id": 1,
    "timestamp": "2026-01-07 09:00:00",
    "status": "present",
    "device_id": "TEST-001"
  }'
```

### Test with Postman:
1. Method: POST
2. URL: `http://localhost/payslip_generator/public/api/biometric-attendance.php`
3. Headers: `Content-Type: application/json`
4. Body (raw JSON):
```json
{
  "api_key": "CHANGE_THIS_TO_SECURE_KEY_12345",
  "employee_id": 1,
  "timestamp": "2026-01-07 09:00:00"
}
```

## 📝 Logs

The API logs all requests and errors:
- **Requests:** `/storage/logs/biometric_requests.log`
- **Errors:** `/storage/logs/biometric_errors.log`

## 🔒 Security Best Practices

1. **Change the default API key** immediately
2. Use HTTPS in production
3. Whitelist device IP addresses in `.htaccess`:
```apache
<Files "biometric-attendance.php">
    Order Deny,Allow
    Deny from all
    Allow from 192.168.1.100
    Allow from 192.168.1.101
</Files>
```
4. Store API keys in environment variables
5. Implement rate limiting for production

## 🔄 Integration Steps

### 1. Configure API Key
Edit `/public/api/biometric-attendance.php` and change:
```php
define('BIOMETRIC_API_KEY', 'your_secure_random_key_here');
```

### 2. (Optional) Run Database Enhancements
```bash
mysql -u root -p payslip_generator < database/biometric_enhancement.sql
```

### 3. Configure Your Biometric Device
- Set device to push attendance data via HTTP POST
- Configure endpoint URL to your API
- Include API key in requests
- Map device user IDs to employee IDs

### 4. Test Integration
- Use cURL or Postman to test
- Check logs in `/storage/logs/`
- Verify data appears in admin attendance reports

### 5. Monitor
- Check `attendance` table for new records
- Review sync logs if using advanced features
- Monitor error logs for issues

## 📞 Device-Specific Configuration

### ZKTeco Setup:
1. Access device web interface
2. Go to Attendance → Push Settings
3. Set URL: `http://your-server/payslip_generator/public/api/biometric-attendance.php`
4. Set Method: POST
5. Configure data format to match API

### eSSL Setup:
1. Use eSSL software to configure device
2. Enable HTTP Push/Webhook
3. Set endpoint URL
4. Map employee IDs

## 🐛 Troubleshooting

**Issue:** 401 Unauthorized
- Check API key matches in request and code

**Issue:** 404 Employee not found
- Verify employee_id exists in database
- Check employee is active

**Issue:** 500 Database error
- Check database connection
- Verify attendance table structure
- Check error logs

**Issue:** No data recorded
- Check device network connectivity
- Verify URL is accessible from device
- Check request/error logs

## 📚 Future Enhancements

When ready, you can add:
- Real-time dashboard for live attendance
- Multiple check-in/check-out tracking
- Geofencing validation
- Face recognition photo storage
- Overtime calculation
- Shift management integration
- Mobile app for remote punch
- SMS/Email notifications

---

**Current Status:** ✅ API Ready | ⏳ Database Enhancement Optional | 🔧 Device Configuration Required
