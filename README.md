# SQLite Database Browser

A powerful, single-file PHP web application for browsing and managing SQLite databases with support for encrypted databases.

## Features

### 🔍 Database Browsing
- **Multi-database support** - Browse multiple SQLite databases from a single interface
- **Table exploration** - View all tables with row counts and schema information
- **Data visualization** - Clean, responsive table display with pagination
- **Query execution** - Run custom SQL queries with syntax highlighting

### 🔐 Encryption Support
- **Encrypted database support** - Full support for SQLCipher encrypted databases
- **PRAGMA key handling** - Automatic detection of encryption key formats
- **Performance optimized** - Cached encryption key formats for faster access
- **Multiple key formats** - Supports both text and hex key formats

### ✏️ Data Management
- **Real-time editing** - Edit records directly in the browser with instant updates
- **Bulk operations** - Select and delete multiple records at once
- **Insert records** - Add new records with form validation
- **Schema viewing** - Detailed table structure and column information

### 🚀 Performance Features
- **Optimized queries** - Smart pagination and query optimization
- **Connection caching** - Efficient database connection management
- **PRAGMA optimizations** - Automatic SQLite performance tuning
- **AJAX updates** - Seamless updates without page reloads

### 📊 Additional Features
- **Export functionality** - Export data in CSV, JSON, or SQL formats
- **Query history** - Keep track of executed queries
- **Favorites** - Save frequently used queries
- **Dark mode** - Toggle between light and dark themes
- **Responsive design** - Works on desktop and mobile devices

## Installation

### Requirements
- PHP 7.4 or higher
- SQLite3 PHP extension
- Web server (Apache, Nginx, or PHP built-in server)

### Quick Setup
1. Download `index.php` to your web server directory
2. Place your SQLite database files in the same directory
3. Navigate to `index.php` in your web browser (or just the directory URL)
4. Start browsing your databases!

### Directory Structure
```
your-web-directory/
├── index.php           # Main application file
├── database1.db        # Your SQLite databases
├── database2.sqlite    # (any .db, .sqlite, .sqlite3 files)
└── encrypted.db        # Encrypted databases supported
```

## Usage

### Basic Usage
1. **Select Database**: Choose from available databases in the dropdown
2. **Browse Tables**: Click on any table to view its contents
3. **Edit Data**: Click "Edit" on any row to modify records
4. **Run Queries**: Use the custom query section for advanced operations

### Working with Encrypted Databases
1. Select an encrypted database from the dropdown
2. Enter your encryption key when prompted
3. The application will automatically detect the correct key format
4. Browse and edit data normally - encryption is handled transparently

### Supported Encryption Formats
- `PRAGMA key = 'your_key'`
- `PRAGMA hexkey = 'hex_encoded_key'`
- Automatic format detection and caching

### Query Examples
```sql
-- View all records
SELECT * FROM users;

-- Search with conditions
SELECT * FROM users WHERE age > 25;

-- Join tables
SELECT u.name, p.title 
FROM users u 
JOIN posts p ON u.id = p.user_id;

-- Update records
UPDATE users SET email = 'new@example.com' WHERE id = 1;
```

## Configuration

### Database Directory
By default, the application looks for databases in the same directory as `index.php`. To change this, modify the `$db_dir` variable:

```php
$db_dir = '/path/to/your/databases/';
```

### Pagination
Adjust the number of records per page:

```php
$per_page = 20; // Default is 10
```

### Performance Tuning
The application automatically applies these SQLite optimizations:
- `PRAGMA synchronous = NORMAL`
- `PRAGMA cache_size = 10000`
- `PRAGMA temp_store = MEMORY`

## Security Features

### Input Sanitization
- All user inputs are properly sanitized and escaped
- SQL injection protection through prepared statements
- XSS prevention with proper output encoding

### File Access Control
- Only SQLite database files are accessible
- Directory traversal protection
- Automatic file type validation

### Session Management
- Secure session handling for encryption keys
- Query history stored in sessions only
- No sensitive data stored in cookies

## Troubleshooting

### Common Issues

**Database not showing up**
- Ensure the file has a `.db`, `.sqlite`, or `.sqlite3` extension
- Check file permissions (readable by web server)
- Verify the file is a valid SQLite database

**Encrypted database won't open**
- Double-check your encryption key
- Try different key formats if needed
- Ensure the database was encrypted with SQLCipher

**Performance issues**
- Check database file size and complexity
- Consider adding indexes to frequently queried columns
- Monitor server resources (RAM, CPU)

**Edit/Update not working**
- Ensure tables have a primary key
- Check that the database file is writable
- Verify no database locks are present

### Error Messages

**"Invalid database selected"**
- File doesn't exist or isn't accessible
- Path contains invalid characters

**"No primary key found"**
- Table lacks a primary key column
- Edit/delete operations require a primary key

**"Database connection error"**
- File is corrupted or not a valid SQLite database
- Incorrect encryption key for encrypted databases

## Development

### File Structure
The application is contained in a single PHP file (`index.php`) with:
- Database connection management
- Query execution engine
- HTML/CSS/JavaScript interface
- AJAX handlers for real-time updates

### Customization
You can customize the application by modifying:
- CSS styles (embedded in the file)
- JavaScript functionality
- PHP configuration variables
- HTML templates

### Contributing
To contribute to this project:
1. Fork the repository
2. Make your changes
3. Test thoroughly with various database types
4. Submit a pull request

## License

This project is open source and available under the MIT License.

## Changelog

### Latest Version
- ✅ Real-time table updates after edits
- ✅ Optimized encrypted database performance
- ✅ Improved AJAX refresh functionality
- ✅ Enhanced error handling and user feedback
- ✅ Single-file architecture (no external dependencies)

### Previous Features
- Multi-database support
- Encrypted database compatibility
- CRUD operations with validation
- Export functionality
- Query history and favorites
- Responsive design with dark mode

## Support

For issues, questions, or feature requests:
- Check the troubleshooting section above
- Review error messages in browser console
- Ensure your PHP and SQLite versions meet requirements

---

**Note**: This application is designed for development and administrative use. For production environments, consider additional security measures and access controls.