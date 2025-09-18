# RSYWX API 2025

A PHP + Slim Framework API for personal library management system.

## Features

- **Book Collection Status** - Get total books, pages, keywords, and visits
- **Book Details** - Retrieve detailed book information by book ID
- **Smart Caching** - 24-hour TTL for static data, real-time for dynamic data
- **API Key Authentication** - Secure access control
- **Apache Integration** - Ready for production deployment

## API Documentation

Visit the root URL (`/`) to access the interactive API documentation with detailed endpoint specifications, request/response examples, and data models.

## API Endpoints

### Collection Status
```
GET /api/v1/books/status
```
Returns total books, pages, keywords, and visits count.

### Book Details
```
GET /api/v1/books/{bookid}
```
Returns detailed book information including:
- Basic book data (title, author, ISBN, etc.)
- Publisher and place names
- Tags and reviews
- Cover image URI
- Visit statistics (real-time)

### Authentication
All endpoints (except `/health` and `/`) require API key authentication via:
- Header: `X-API-Key: your-api-key`
- Query parameter: `?api_key=your-api-key`

## Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/taylorren/api.rsywx.2025.git
   cd api.rsywx.2025
   ```

2. **Install dependencies**
   ```bash
   composer install
   ```

3. **Configure environment**
   ```bash
   cp .env.example .env
   # Edit .env with your database credentials and API key
   ```

4. **Set up Apache virtual host**
   ```bash
   sudo cp apache-vhost.conf /etc/apache2/sites-available/api.conf
   sudo a2ensite api.conf
   sudo a2enmod rewrite headers expires deflate
   sudo systemctl restart apache2
   ```

5. **Set cache permissions**
   ```bash
   mkdir -p cache
   sudo chown -R www-data:www-data cache/
   ```

## Configuration

### Environment Variables
- `DB_HOST` - Database host
- `DB_NAME` - Database name
- `DB_USER` - Database username
- `DB_PASSWORD` - Database password
- `API_KEY` - Your secure API key

### Database Schema
The API works with the existing RSYWX database schema including:
- `book_book` - Main books table
- `book_publisher` - Publishers
- `book_place` - Storage locations
- `book_taglist` - Book tags
- `book_review` - Book reviews
- `book_visit` - Visit tracking

## Caching

The API implements intelligent caching:
- **Static data** (book details, tags, reviews) cached for 24 hours
- **Dynamic data** (visit counts, last visited) always fresh
- **Manual refresh** available with `?refresh=true` parameter
- **File-based cache** stored in `/cache` directory

## Security

- API key authentication required
- CORS headers configured
- Security headers (X-Frame-Options, X-Content-Type-Options, etc.)
- Input validation and sanitization
- Error handling without information disclosure

## Development

### Health Check
```bash
curl http://your-domain/health
```

### Testing Endpoints
```bash
# Collection status
curl -H "X-API-Key: your-key" http://your-domain/api/v1/books/status

# Book details
curl -H "X-API-Key: your-key" http://your-domain/api/v1/books/00666
```

## License

MIT License

## Author

Taylor Ren - Personal Library Management System