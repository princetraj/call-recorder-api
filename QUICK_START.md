# Quick Start Guide

Get your Call Logs API up and running in minutes!

## Prerequisites

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Composer installed

## Installation Steps

### 1. Install Dependencies

```bash
composer install
```

### 2. Configure Environment

```bash
copy .env.example .env
```

Edit `.env` and update database credentials:
```env
DB_DATABASE=call_logs_db
DB_USERNAME=root
DB_PASSWORD=your_password
```

### 3. Generate Application Key

```bash
php artisan key:generate
```

### 4. Create Database

Create a MySQL database named `call_logs_db`:
```sql
CREATE DATABASE call_logs_db;
```

### 5. Run Migrations

```bash
php artisan migrate
```

### 6. Seed Test Data (Optional)

```bash
php artisan db:seed
```

This creates two test users:
- **Admin:** `admin@example.com` / `password123`
- **User:** `user@example.com` / `password123`

### 7. Create Storage Link

```bash
php artisan storage:link
```

### 8. Start Development Server

```bash
php artisan serve
```

API is now available at: `http://localhost:8000`

## Quick Test

### 1. Login

```bash
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "user@example.com",
    "password": "password123"
  }'
```

Copy the `token` from the response.

### 2. Create a Call Log

```bash
curl -X POST http://localhost:8000/api/call-logs \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "caller_name": "John Doe",
    "caller_number": "+1234567890",
    "call_type": "incoming",
    "call_duration": 120,
    "call_timestamp": "2024-11-02 10:30:00"
  }'
```

### 3. Get All Call Logs

```bash
curl -X GET http://localhost:8000/api/call-logs \
  -H "Authorization: Bearer YOUR_TOKEN"
```

## What's Next?

- Read the full [README.md](README.md) for detailed documentation
- Check [API_DOCUMENTATION.md](API_DOCUMENTATION.md) for complete API reference
- Test endpoints using Postman or Insomnia

## Common Issues

### Permission Errors
```bash
chmod -R 775 storage bootstrap/cache
```

### Storage Link Not Working
```bash
php artisan storage:link
```

### Database Connection Failed
- Verify MySQL is running
- Check database credentials in `.env`
- Ensure database exists

## Support

For issues or questions, please refer to the documentation or create an issue in the repository.
