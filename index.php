<?php

/**
 * A simple, single-file PHP script to browse SQLite databases.
 * Uses the modern SQLite3 class and prepared statements for security.
 *
 * To use:
 * 1. Place this file in a web-accessible directory.
 * 2. Place your .sqlite or .db files in the same directory as this script.
 * 3. Navigate to the script in your browser.
 */

// Configuration
$db_dir = '';
$selected_db = $_GET['db'] ?? '';
$error = '';
$message = '';
$results = null;

// Optimized for encrypted database performance

// Pagination settings
$per_page = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $per_page;

// Session-based features
session_start();
if (!isset($_SESSION['query_history'])) {
    $_SESSION['query_history'] = [];
}
if (!isset($_SESSION['query_favorites'])) {
    $_SESSION['query_favorites'] = [];
}
if (!isset($_SESSION['pragma_key'])) {
    $_SESSION['pragma_key'] = '';
}

// Encryption key format caching for performance
// (Connection caching removed to avoid SQLite3 object issues)

// Handle PRAGMA key submission
if (isset($_POST['pragma_key'])) {
    $_SESSION['pragma_key'] = $_POST['pragma_key'];
}

// Helper function to sanitize user input for display
function sanitize($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

// Function to get a list of available database files in the current directory
function getDatabaseList($directory) {
    $files = glob('*.{sqlite,db,sqlite3}', GLOB_BRACE);
    $all_databases = [];

    foreach ($files as $file) {
        $all_databases[] = basename($file);
    }

    return $all_databases;
}

// Function to check if a file is a valid SQLite database
function isSQLiteDatabase($file_path) {
    if (!file_exists($file_path) || filesize($file_path) < 16) {
        return false;
    }

    $handle = fopen($file_path, 'r');
    if (!$handle) {
        return false;
    }

    $header = fread($handle, 16);
    fclose($handle);

    // Check for SQLite header
    return substr($header, 0, 16) === "SQLite format 3\000";
}

// Optimized database connection with faster encryption handling
function createDbConnection($db_path, $pragma_key = null) {
    global $error;
    
    try {
        $db = new SQLite3($db_path);
        
        // Apply PRAGMA key if provided - optimized approach
        if (!empty($pragma_key)) {
            // Store the working key format in session to avoid retrying
            $key_format_key = 'pragma_key_format_' . md5($db_path . $pragma_key);
            
            if (isset($_SESSION[$key_format_key])) {
                // Use the known working format
                $key_command = $_SESSION[$key_format_key];
                $result = $db->exec($key_command);
                if ($result) {
                    // Quick test - just check if we can access sqlite_master
                    $test_result = $db->querySingle("SELECT 1 FROM sqlite_master LIMIT 1");
                    if ($test_result !== false) {
                        // Apply performance optimizations
                        $db->exec("PRAGMA synchronous = NORMAL");
                        $db->exec("PRAGMA cache_size = 10000");
                        $db->exec("PRAGMA temp_store = MEMORY");
                        return $db;
                    }
                }
                // If cached format failed, remove it and try all formats
                unset($_SESSION[$key_format_key]);
            }
            
            // Try different PRAGMA key formats (only if no cached format worked)
            $key_commands = [
                "PRAGMA key = '" . SQLite3::escapeString($pragma_key) . "'",
                "PRAGMA hexkey = '" . bin2hex($pragma_key) . "'",
                "PRAGMA key = '" . SQLite3::escapeString($pragma_key) . "';",
                "PRAGMA hexkey = '" . bin2hex($pragma_key) . "';"
            ];

            foreach ($key_commands as $key_command) {
                $result = $db->exec($key_command);
                if ($result) {
                    // Quick test - just check if we can access sqlite_master
                    $test_result = $db->querySingle("SELECT 1 FROM sqlite_master LIMIT 1");
                    if ($test_result !== false) {
                        // Cache the working format for future use
                        $_SESSION[$key_format_key] = $key_command;
                        
                        // Apply performance optimizations for encrypted databases
                        $db->exec("PRAGMA synchronous = NORMAL");
                        $db->exec("PRAGMA cache_size = 10000");
                        $db->exec("PRAGMA temp_store = MEMORY");
                        
                        return $db;
                    }
                }
            }

            throw new Exception("Invalid encryption key or unsupported encryption format");
        }
        
        // Apply performance optimizations for unencrypted databases too
        $db->exec("PRAGMA synchronous = NORMAL");
        $db->exec("PRAGMA cache_size = 10000");
        $db->exec("PRAGMA temp_store = MEMORY");

        return $db;
    } catch (Exception $e) {
        $error = "Database connection error: " . $e->getMessage();
        if (isset($db) && $db) {
            $db->close();
        }
        return null;
    }
}

// Function to get the primary key column name for a table
function getPrimaryKey($db_path, $table_name) {
    global $error;
    $db = null;
    try {
        $pragma_key = !empty($_SESSION['pragma_key']) ? $_SESSION['pragma_key'] : null;
        $db = createDbConnection($db_path, $pragma_key);
        if (!$db) return null;

        // Use PRAGMA to get the table's column information
        $result = $db->query("PRAGMA table_info(`" . sanitize($table_name) . "`);");
        if ($result) {
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                // The 'pk' column indicates if a column is part of the primary key
                if ($row['pk'] == 1) {
                    return $row['name'];
                }
            }
        }
    } catch (Exception $e) {
        $error = "Error getting table info: " . $e->getMessage();
    } finally {
        if ($db) {
            $db->close();
        }
    }
    return null;
}

// Function to get table schema
function getTableSchema($db_path, $table_name) {
    global $error;
    $schema = [];
    $db = null;
    try {
        $pragma_key = !empty($_SESSION['pragma_key']) ? $_SESSION['pragma_key'] : null;
        $db = createDbConnection($db_path, $pragma_key);
        if (!$db) return $schema;

        $result = $db->query("PRAGMA table_info(`" . sanitize($table_name) . "`);");
        if ($result) {
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $schema[] = $row;
            }
        }
    } catch (Exception $e) {
        $error = "Error getting table schema: " . $e->getMessage();
    } finally {
        if ($db) {
            $db->close();
        }
    }
    return $schema;
}

// Function to format values for display
function formatValue($value) {
    if ($value === null) {
        return '<em>NULL</em>';
    }
    if ($value === '') {
        return '<em>empty</em>';
    }
    if (is_numeric($value)) {
        return $value;
    }
    // Truncate long text values
    if (strlen($value) > 100) {
        return htmlspecialchars(substr($value, 0, 100)) . '...';
    }
    return htmlspecialchars($value);
}

// Function to get total records count for pagination
function getTotalRecords($db_path, $query) {
    global $error;
    $db = null;
    try {
        $pragma_key = !empty($_SESSION['pragma_key']) ? $_SESSION['pragma_key'] : null;
        $db = createDbConnection($db_path, $pragma_key);
        if (!$db) return 0;

        // Convert SELECT query to COUNT query
        $count_query = preg_replace('/^SELECT\s+.*?\s+FROM/i', 'SELECT COUNT(*) FROM', $query);
        $count_query = preg_replace('/\s+ORDER\s+BY\s+.*$/i', '', $count_query);
        $count_query = preg_replace('/\s+LIMIT\s+.*$/i', '', $count_query);
        
        $result = $db->querySingle($count_query);
        return $result ? intval($result) : 0;
    } catch (Exception $e) {
        return 0;
    } finally {
        if ($db) {
            $db->close();
        }
    }
}

// Function to get database info with caching
function getDatabaseInfo($db_path) {
    global $error;
    $info = [];
    
    // Cache database info to avoid repeated queries
    $cache_key = 'db_info_' . md5($db_path . ($_SESSION['pragma_key'] ?? ''));
    if (isset($_SESSION[$cache_key]) && time() - $_SESSION[$cache_key]['timestamp'] < 30) {
        return $_SESSION[$cache_key]['data'];
    }
    
    try {
        $pragma_key = !empty($_SESSION['pragma_key']) ? $_SESSION['pragma_key'] : null;
        $db = createDbConnection($db_path, $pragma_key);
        if (!$db) return $info;

        $info['tables'] = [];

        // Get database stats
        $stats = $db->querySingle("SELECT COUNT(*) as total_tables FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%';");
        $info['total_tables'] = $stats;

        // Get table info with single query for better performance
        $result = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%';");
        if ($result) {
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $table_name = $row['name'];
                // Use a faster approach for row counting
                try {
                    $count = $db->querySingle("SELECT COUNT(*) FROM `" . sanitize($table_name) . "`");
                    $info['tables'][$table_name] = $count;
                } catch (Exception $e) {
                    // If count fails, just set to 0
                    $info['tables'][$table_name] = 0;
                }
            }
        }

        // Get database size
        $info['size'] = filesize($db_path);
        
        // Cache the result for 30 seconds
        $_SESSION[$cache_key] = [
            'data' => $info,
            'timestamp' => time()
        ];

    } catch (Exception $e) {
        $error = "Error getting database info: " . $e->getMessage();
    } finally {
        if (isset($db) && $db) {
            $db->close();
        }
    }
    return $info;
}

// Function to execute a query and return results
function executeQuery($db_path, $query, $limit = null, $offset = null) {
    global $error;
    $db = null;
    $results = [];

    try {
        $pragma_key = !empty($_SESSION['pragma_key']) ? $_SESSION['pragma_key'] : null;
        $db = createDbConnection($db_path, $pragma_key);
        if (!$db) return null;

        // Handle queries that don't return a result set (e.g., CREATE, INSERT, DELETE)
        if (stripos(trim($query), 'SELECT') !== 0) {
            $db->exec($query);
            return null; // No results to return
        }

        // Apply LIMIT and OFFSET for pagination
        if ($limit !== null && $offset !== null) {
            $query .= " LIMIT " . intval($limit) . " OFFSET " . intval($offset);
        }

        $stmt = $db->prepare($query);
        if (!$stmt) {
            throw new Exception("Could not prepare statement: " . $db->lastErrorMsg());
        }

        $result = $stmt->execute();
        if (!$result) {
            throw new Exception("Query execution failed: " . $db->lastErrorMsg());
        }

        // Fetch column names
        $columns = [];
        for ($i = 0; $i < $result->numColumns(); $i++) {
            $columns[] = $result->columnName($i);
        }
        $results['columns'] = $columns;

        // Fetch rows
        $rows = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $rows[] = $row;
        }
        $results['rows'] = $rows;

    } catch (Exception $e) {
        $error = $e->getMessage();
        return null;
    } finally {
        if ($db) {
            $db->close();
        }
    }

    return $results;
}

// Function to handle deletion
function deleteRows($db_path, $table_name, $pks) {
    global $error, $message;
    $db = null;
    try {
        $pragma_key = !empty($_SESSION['pragma_key']) ? $_SESSION['pragma_key'] : null;
        $db = createDbConnection($db_path, $pragma_key);
        if (!$db) return;
        $pk_column = getPrimaryKey($db_path, $table_name);
        if (!$pk_column) {
            $error = "No primary key found for table `{$table_name}`. Deletion is not supported without a primary key.";
            return;
        }

        // Create placeholders for the prepared statement
        $placeholders = implode(',', array_fill(0, count($pks), '?'));
        $query = "DELETE FROM `" . sanitize($table_name) . "` WHERE `" . sanitize($pk_column) . "` IN ({$placeholders})";

        $stmt = $db->prepare($query);
        if (!$stmt) {
            throw new Exception("Could not prepare delete statement: " . $db->lastErrorMsg());
        }

        // Bind each value from the array of IDs
        foreach ($pks as $index => $pk_value) {
            $stmt->bindValue($index + 1, $pk_value, SQLITE3_TEXT);
        }

        $stmt->execute();
        $message = count($pks) . " row(s) deleted successfully from `" . sanitize($table_name) . "`.";

    } catch (Exception $e) {
        $error = "Deletion failed: " . $e->getMessage();
    } finally {
        if ($db) {
            $db->close();
        }
    }
}

// Function to update a row (single field)
function updateRow($db_path, $table_name, $pk_value, $data) {
    global $error, $message;
    $db = null;
    try {
        $pragma_key = !empty($_SESSION['pragma_key']) ? $_SESSION['pragma_key'] : null;
        $db = createDbConnection($db_path, $pragma_key);
        if (!$db) return false;
        $pk_column = getPrimaryKey($db_path, $table_name);
        if (!$pk_column) {
            $error = "No primary key found for table `{$table_name}`. Update is not supported without a primary key.";
            return false;
        }

        $set_parts = [];
        $params = [];
        foreach ($data as $column => $value) {
            if ($column !== $pk_column) {
                $set_parts[] = "`" . sanitize($column) . "` = ?";
                $params[] = $value;
            }
        }

        if (empty($set_parts)) {
            $error = "No fields to update.";
            return false;
        }

        $query = "UPDATE `" . sanitize($table_name) . "` SET " . implode(', ', $set_parts) . " WHERE `" . sanitize($pk_column) . "` = ?";
        $params[] = $pk_value;

        $stmt = $db->prepare($query);
        if (!$stmt) {
            throw new Exception("Could not prepare update statement: " . $db->lastErrorMsg());
        }

        foreach ($params as $index => $value) {
            $stmt->bindValue($index + 1, $value, SQLITE3_TEXT);
        }

        $stmt->execute();
        $message = "Row updated successfully in `" . sanitize($table_name) . "`.";
        return true;

    } catch (Exception $e) {
        $error = "Update failed: " . $e->getMessage();
        return false;
    } finally {
        if ($db) {
            $db->close();
        }
    }
}

// Function to update multiple fields in a row
function updateRowMultiple($db_path, $table_name, $pk_column, $pk_value, $updates) {
    global $error, $message;
    $db = null;
    try {
        $pragma_key = !empty($_SESSION['pragma_key']) ? $_SESSION['pragma_key'] : null;
        $db = createDbConnection($db_path, $pragma_key);
        if (!$db) return false;

        $set_parts = [];
        $params = [];
        foreach ($updates as $column => $value) {
            if ($column !== $pk_column) {
                $set_parts[] = "`" . sanitize($column) . "` = ?";
                $params[] = $value;
            }
        }

        if (empty($set_parts)) {
            $error = "No fields to update.";
            return false;
        }

        $query = "UPDATE `" . sanitize($table_name) . "` SET " . implode(', ', $set_parts) . " WHERE `" . sanitize($pk_column) . "` = ?";
        $params[] = $pk_value;

        $stmt = $db->prepare($query);
        if (!$stmt) {
            throw new Exception("Could not prepare update statement: " . $db->lastErrorMsg());
        }

        foreach ($params as $index => $value) {
            $stmt->bindValue($index + 1, $value, SQLITE3_TEXT);
        }

        $result = $stmt->execute();
        if (!$result) {
            throw new Exception("Update execution failed: " . $db->lastErrorMsg());
        }

        $message = "Record updated successfully in `" . sanitize($table_name) . "`.";
        return true;

    } catch (Exception $e) {
        $error = "Update failed: " . $e->getMessage();
        return false;
    } finally {
        if ($db) {
            $db->close();
        }
    }
}

// Function to insert a new row
function insertRow($db_path, $table_name, $data) {
    global $error, $message;
    $db = null;
    try {
        $pragma_key = !empty($_SESSION['pragma_key']) ? $_SESSION['pragma_key'] : null;
        $db = createDbConnection($db_path, $pragma_key);
        if (!$db) return false;
        $schema = getTableSchema($db_path, $table_name);

        $columns = [];
        $placeholders = [];
        $params = [];

        foreach ($schema as $col) {
            if (isset($data[$col['name']]) && $data[$col['name']] !== '') {
                $columns[] = "`" . sanitize($col['name']) . "`";
                $placeholders[] = '?';
                $params[] = $data[$col['name']];
            } elseif ($col['dflt_value'] !== null) {
                $columns[] = "`" . sanitize($col['name']) . "`";
                $placeholders[] = '?';
                $params[] = $col['dflt_value'];
            }
        }

        if (empty($columns)) {
            $error = "No data to insert.";
            return false;
        }

        $query = "INSERT INTO `" . sanitize($table_name) . "` (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";

        $stmt = $db->prepare($query);
        if (!$stmt) {
            throw new Exception("Could not prepare insert statement: " . $db->lastErrorMsg());
        }

        foreach ($params as $index => $value) {
            $stmt->bindValue($index + 1, $value, SQLITE3_TEXT);
        }

        $stmt->execute();
        $message = "Row inserted successfully into `" . sanitize($table_name) . "`.";
        return true;

    } catch (Exception $e) {
        $error = "Insert failed: " . $e->getMessage();
        return false;
    } finally {
        if ($db) {
            $db->close();
        }
    }
}

// Function to export data
function exportData($db_path, $query, $format) {
    global $error;
    $results = executeQuery($db_path, $query);

    if (!$results || empty($results['rows'])) {
        $error = "No data to export.";
        return false;
    }

    $filename = 'export_' . date('Ymd_His') . '.' . $format;

    header('Content-Type: ' . ($format === 'csv' ? 'text/csv' : ($format === 'json' ? 'application/json' : 'text/sql')));
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    switch ($format) {
        case 'csv':
            $output = fopen('php://output', 'w');
            fputcsv($output, $results['columns']);
            foreach ($results['rows'] as $row) {
                fputcsv($output, $row);
            }
            fclose($output);
            break;

        case 'json':
            echo json_encode($results, JSON_PRETTY_PRINT);
            break;

        case 'sql':
            echo "-- Export generated on " . date('Y-m-d H:i:s') . "\n";
            echo "-- Database: " . basename($db_path) . "\n";
            echo "-- Query: " . $query . "\n\n";
            foreach ($results['rows'] as $row) {
                $columns = array_keys($row);
                $values = array_map(function($val) {
                    return $val === null ? 'NULL' : "'" . addslashes($val) . "'";
                }, array_values($row));
                echo "INSERT INTO export_table (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $values) . ");\n";
            }
            break;
    }

    exit;
}

// Function to delete a table
function deleteTable($db_path, $table_name) {
    global $error, $message;
    $db = null;
    try {
        $pragma_key = !empty($_SESSION['pragma_key']) ? $_SESSION['pragma_key'] : null;
        $db = createDbConnection($db_path, $pragma_key);
        if (!$db) return false;

        // Verify table exists
        $check_table = $db->querySingle("SELECT name FROM sqlite_master WHERE type='table' AND name='" . sanitize($table_name) . "'");
        if (!$check_table) {
            $error = "Table '" . sanitize($table_name) . "' does not exist.";
            return false;
        }

        // Additional safety check - don't delete system tables
        if (strpos($table_name, 'sqlite_') === 0) {
            $error = "Cannot delete system tables.";
            return false;
        }

        // Execute DROP TABLE
        $result = $db->exec("DROP TABLE IF EXISTS `" . sanitize($table_name) . "`");

        if ($result) {
            $message = "Table '" . sanitize($table_name) . "' deleted successfully.";
            return true;
        } else {
            $error = "Failed to delete table: " . $db->lastErrorMsg();
            return false;
        }

    } catch (Exception $e) {
        $error = "Error deleting table: " . $e->getMessage();
        return false;
    } finally {
        if ($db) {
            $db->close();
        }
    }
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' || (isset($_GET['page']) && isset($_SESSION['current_query']))) {
    if ((!empty($selected_db)) && (isset($_POST['query']) || isset($_SESSION['current_query']))) {
        $query = isset($_POST['query']) ? trim($_POST['query']) : $_SESSION['current_query'];
        $db_path = $db_dir . sanitize($selected_db);

        // Add to query history only on POST requests
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($query)) {
            $_SESSION['query_history'][] = $query;
            if (count($_SESSION['query_history']) > 20) {
                array_shift($_SESSION['query_history']);
            }
        }

        // Basic security check to prevent directory traversal
        if (strpos($db_path, '..') !== false || !file_exists($db_path)) {
            $error = "Invalid database selected.";
        } else {
            // Store current query in session for refresh functionality
            $_SESSION['current_query'] = $query;
            
            $results = executeQuery($db_path, $query, $per_page, $offset);
            if ($results === null && empty($error)) {
                 $message = "Query executed successfully.";
            }
        }
    }

    // Delete handler
    if (isset($_POST['action']) && $_POST['action'] === 'delete' && !empty($selected_db)) {
        if (!empty($_POST['table_name']) && !empty($_POST['ids'])) {
            $table_name = $_POST['table_name'];
            $ids = explode(',', $_POST['ids']); // Split comma-separated IDs
            $db_path = $db_dir . sanitize($selected_db);

            // Basic security check again
            if (strpos($db_path, '..') !== false || !file_exists($db_path)) {
                $error = "Invalid database selected.";
            } else {
                deleteRows($db_path, $table_name, $ids);
                // Re-run the default SELECT query to refresh the table view
                if (!empty($_POST['current_query'])) {
                    $_POST['query'] = $_POST['current_query']; // Set this to keep the table selected
                    $results = executeQuery($db_path, $_POST['current_query'], $per_page, $offset);
                }
            }
        } else {
            $error = "Invalid delete request.";
        }
    }

    // Multiple field update handler
    if (isset($_POST['action']) && $_POST['action'] === 'update_multiple' && !empty($selected_db)) {
        if (!empty($_POST['table_name']) && !empty($_POST['pk_column']) && !empty($_POST['pk_value']) && !empty($_POST['updates'])) {
            $table_name = $_POST['table_name'];
            $pk_column = $_POST['pk_column'];
            $pk_value = $_POST['pk_value'];
            $updates = json_decode($_POST['updates'], true);
            $db_path = $db_dir . sanitize($selected_db);

            if (strpos($db_path, '..') !== false || !file_exists($db_path)) {
                $error = "Invalid database selected.";
            } else {
                if (updateRowMultiple($db_path, $table_name, $pk_column, $pk_value, $updates)) {
                    $message = "Record updated successfully!";
                    // Store the current query in session to refresh the table view after page reload
                    if (!empty($_POST['query'])) {
                        $_SESSION['current_query'] = $_POST['query'];
                        $_POST['query'] = $_POST['query'];
                    }

                    // Check if this is an AJAX request
                    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                        // Return JSON response for AJAX with refresh instruction
                        header('Content-Type: application/json');
                        echo json_encode(['success' => true, 'message' => $message, 'refresh' => true]);
                        exit;
                    }
                } else {
                    $error = "Failed to update record.";

                    // Check if this is an AJAX request
                    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => false, 'error' => $error]);
                        exit;
                    }
                }
            }
        } else {
            $error = "Invalid update request.";

            // Check if this is an AJAX request
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => $error]);
                exit;
            }
        }
    }

    // Refresh table handler
    if (isset($_POST['action']) && $_POST['action'] === 'refresh_table' && !empty($selected_db)) {
        if (!empty($_POST['query'])) {
            $query = $_POST['query'];
            $db_path = $db_dir . sanitize($selected_db);

            if (strpos($db_path, '..') !== false || !file_exists($db_path)) {
                $error = "Invalid database selected.";
            } else {
                // Extract table name from query
                preg_match('/SELECT\s+.*?\s+FROM\s+`([^`]+)`/i', $query, $matches);
                $current_table = $matches[1] ?? '';

                // Get primary key column
                $pk_column = null;
                if ($current_table) {
                    $schema = getTableSchema($db_path, $current_table);
                    foreach ($schema as $col) {
                        if ($col['pk']) {
                            $pk_column = $col['name'];
                            break;
                        }
                    }
                }

                // Execute the query to get fresh data
                $results = executeQuery($db_path, $query, $per_page, $offset);
                $total_records = getTotalRecords($db_path, $query);
                $total_pages = ceil($total_records / $per_page);

                // Get column names from results structure
                $column_names = [];
                if (!empty($results) && isset($results['columns'])) {
                    $column_names = $results['columns'];
                }

                // Generate complete table container HTML
                ob_start();
                ?>
                <div class="table-container">
                    <div class="table-info">
                        <div class="table-info-left">
                            <h3><?= sanitize($current_table) ?></h3>
                            <span class="record-count"><?= $total_records ?> records found</span>
                        </div>
                        <div class="table-info-right">
                            <button class="btn btn-primary" onclick="openModal('insertModal')">+ Insert Record</button>
                        </div>
                    </div>
                    <?php
                    // Inline table template for AJAX refresh
                    if (!empty($results) && !empty($results['rows'])): 
                        $rows = $results['rows'];
                        $columns = $results['columns'];
                    ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <?php foreach ($columns as $column): ?>
                                            <th><?= sanitize($column) ?></th>
                                        <?php endforeach; ?>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($rows as $row): 
                                        // Get primary key value for this row
                                        $pk_value = isset($pk_column) && isset($row[$pk_column]) ? $row[$pk_column] : '';
                                    ?>
                                        <tr>
                                            <?php foreach ($columns as $column): ?>
                                                <td><?= formatValue($row[$column]) ?></td>
                                            <?php endforeach; ?>
                                            <td class="actions">
                                                <?php if ($pk_value): ?>
                                                    <button class="btn btn-warning btn-sm" onclick="editRecord('<?= htmlspecialchars($pk_value) ?>', <?= htmlspecialchars(json_encode($row)) ?>)">Edit</button>
                                                    <button class="btn btn-danger btn-sm" onclick="confirmDelete('<?= htmlspecialchars($pk_value) ?>')">Delete</button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if (isset($total_pages) && $total_pages > 1): ?>
                            <div class="pagination">
                                <?php if ($page > 1): ?>
                                    <a href="?db=<?= sanitize($selected_db) ?>&page=<?= $page - 1 ?>" class="btn btn-secondary">Previous</a>
                                <?php endif; ?>

                                <span class="page-info">
                                    Page <?= $page ?> of <?= $total_pages ?>
                                </span>

                                <?php if ($page < $total_pages): ?>
                                    <a href="?db=<?= sanitize($selected_db) ?>&page=<?= $page + 1 ?>" class="btn btn-secondary">Next</a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php elseif (!empty($results)): ?>
                        <p>No data found.</p>
                    <?php endif; ?>
                </div>
                <?php
                $table_html = ob_get_clean();

                // Check if this is an AJAX request
                if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => true,
                        'table_html' => $table_html
                    ]);
                    exit;
                }
            }
        } else {
            // Handle missing query for AJAX request
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'No query provided for refresh']);
                exit;
            }
        }
    }

    // Single field update handler (legacy)
    if (isset($_POST['action']) && $_POST['action'] === 'update' && !empty($selected_db)) {
        if (!empty($_POST['table_name']) && !empty($_POST['pk_value']) && !empty($_POST['data'])) {
            $table_name = $_POST['table_name'];
            $pk_value = $_POST['pk_value'];
            $data = json_decode($_POST['data'], true);
            $db_path = $db_dir . sanitize($selected_db);

            if (strpos($db_path, '..') !== false || !file_exists($db_path)) {
                $error = "Invalid database selected.";
            } else {
                if (updateRow($db_path, $table_name, $pk_value, $data)) {
                    // Re-run the query to refresh the table view
                    if (!empty($_POST['current_query'])) {
                        $_POST['query'] = $_POST['current_query'];
                        $results = executeQuery($db_path, $_POST['current_query'], $per_page, $offset);
                    }
                }
            }
        } else {
            $error = "Invalid update request.";
        }
    }

    // Insert handler
    if (isset($_POST['action']) && $_POST['action'] === 'insert' && !empty($selected_db)) {
        if (!empty($_POST['table_name']) && !empty($_POST['data'])) {
            $table_name = $_POST['table_name'];
            $data = json_decode($_POST['data'], true);
            $db_path = $db_dir . sanitize($selected_db);

            if (strpos($db_path, '..') !== false || !file_exists($db_path)) {
                $error = "Invalid database selected.";
            } else {
                if (insertRow($db_path, $table_name, $data)) {
                    // Re-run the query to refresh the table view
                    if (!empty($_POST['current_query'])) {
                        $_POST['query'] = $_POST['current_query'];
                        $results = executeQuery($db_path, $_POST['current_query'], $per_page, $offset);
                    }
                }
            }
        } else {
            $error = "Invalid insert request.";
        }
    }

    // Export handler
    if (isset($_POST['action']) && $_POST['action'] === 'export' && !empty($selected_db)) {
        if (!empty($_POST['query']) && !empty($_POST['format'])) {
            $query = $_POST['query'];
            $format = $_POST['format'];
            $db_path = $db_dir . sanitize($selected_db);

            if (strpos($db_path, '..') !== false || !file_exists($db_path)) {
                $error = "Invalid database selected.";
            } else {
                exportData($db_path, $query, $format);
            }
        } else {
            $error = "Invalid export request.";
        }
    }

    // Favorite query handler
    if (isset($_POST['action']) && $_POST['action'] === 'favorite' && !empty($_POST['query'])) {
        $_SESSION['query_favorites'][] = $_POST['query'];
        $message = "Query added to favorites.";
    }

    // Delete table handler
    if (isset($_POST['action']) && $_POST['action'] === 'delete_table' && !empty($selected_db) && !empty($_POST['table_name'])) {
        $table_name = $_POST['table_name'];
        $db_path = $db_dir . sanitize($selected_db);

        if (strpos($db_path, '..') !== false || !file_exists($db_path)) {
            $error = "Invalid database selected.";
        } else {
            deleteTable($db_path, $table_name);
        }
    }

    // Get schema handler (for AJAX requests)
    if (isset($_POST['action']) && $_POST['action'] === 'get_schema' && !empty($selected_db) && !empty($_POST['table_name'])) {
        $table_name = $_POST['table_name'];
        $db_path = $db_dir . sanitize($selected_db);

        if (strpos($db_path, '..') !== false || !file_exists($db_path)) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Invalid database selected.']);
        } else {
            $schema = getTableSchema($db_path, $table_name);
            header('Content-Type: application/json');
            echo json_encode(['schema' => $schema]);
        }
        exit;
    }
}

// Handle GET requests
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($selected_db)) {
    // Check if database has changed - clear table selection and PRAGMA key if it has
    if (isset($_SESSION['last_db']) && $_SESSION['last_db'] !== $selected_db) {
        // Only clear PRAGMA key if database changed, but keep the current query
        unset($_SESSION['current_query']);
        unset($_POST['query']);
        // Don't clear PRAGMA key - user might be switching between databases with same key
    }
    $_SESSION['last_db'] = $selected_db;

    // Handle update action from GET request (redirect from saveEdit)
    if (isset($_GET['action']) && $_GET['action'] === 'update_multiple' && !empty($_GET['table_name']) && !empty($_GET['pk_column']) && !empty($_GET['pk_value']) && !empty($_GET['updates'])) {
        $table_name = $_GET['table_name'];
        $pk_column = $_GET['pk_column'];
        $pk_value = $_GET['pk_value'];
        $updates = json_decode($_GET['updates'], true);
        $db_path = $db_dir . sanitize($selected_db);

        if (!empty($_GET['pragma_key'])) {
            $_SESSION['pragma_key'] = $_GET['pragma_key'];
        }

        if (strpos($db_path, '..') !== false || !file_exists($db_path)) {
            $error = "Invalid database selected.";
        } else {
            if (updateRowMultiple($db_path, $table_name, $pk_column, $pk_value, $updates)) {
                $message = "Record updated successfully!";
                // Restore the query for table view and immediately execute it
                if (!empty($_GET['query'])) {
                    $_SESSION['current_query'] = $_GET['query'];
                    $_POST['query'] = $_GET['query'];
                    // Execute the query to get fresh data
                    $results = executeQuery($db_path, $_GET['query'], $per_page, $offset);
                }
            } else {
                $error = "Failed to update record.";
            }
        }
    }

    if (isset($_GET['export']) && isset($_GET['query']) && isset($_GET['format'])) {
        $query = base64_decode($_GET['query']);
        $format = $_GET['format'];
        $db_path = $db_dir . sanitize($selected_db);
        exportData($db_path, $query, $format);
    }
}

// Get the list of databases
$databases = getDatabaseList($db_dir);

// If a database is selected, get its tables and info
$tables = [];
$db_info = [];
if (!empty($selected_db)) {
    $db_path = $db_dir . sanitize($selected_db);
    if (file_exists($db_path)) {
        // Only try to connect if we have a PRAGMA key or if it's an unencrypted database
        if (!empty($_SESSION['pragma_key'])) {
            $pragma_key = $_SESSION['pragma_key'];
            $db = createDbConnection($db_path, $pragma_key);
            if ($db) {
                try {
                    $result = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%';");
                    if ($result) {
                        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                            $tables[] = $row['name'];
                        }
                    }
                    $db->close();
                    $db_info = getDatabaseInfo($db_path);
                } catch (Exception $e) {
                    $error = "Error reading database: " . $e->getMessage();
                    if ($db) {
                        $db->close();
                    }
                }
            } else {
                $error = "Failed to connect to database. Invalid encryption key?";
            }
        } elseif (isSQLiteDatabase($db_path)) {
            // Try connecting without encryption key for unencrypted databases
            $db = createDbConnection($db_path, null);
            if ($db) {
                try {
                    $result = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%';");
                    if ($result) {
                        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                            $tables[] = $row['name'];
                        }
                    }
                    $db->close();
                    $db_info = getDatabaseInfo($db_path);
                } catch (Exception $e) {
                    $error = "Error reading database: " . $e->getMessage();
                    if ($db) {
                        $db->close();
                    }
                }
            }
        } else {
            // Database appears to be encrypted but no key provided
            $error = "Database appears to be encrypted. Please enter a PRAGMA key to connect.";
        }
    } else {
        $error = "Database file not found.";
    }
}

// Set current page URL for pagination
$current_url = '?db=' . urlencode($selected_db);
if (isset($_POST['query']) && !empty($_POST['query'])) {
    // Store the current query in session for pagination
    $_SESSION['current_query'] = $_POST['query'];
}
// If we have a stored query and no new query is posted, use the stored one
if (isset($_SESSION['current_query']) && !isset($_POST['query'])) {
    $_POST['query'] = $_SESSION['current_query'];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PHP SQLite Browser</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            margin: 0;
            background-color: #f5f5f5;
            color: #333;
            transition: background-color 0.3s ease, color 0.3s ease;
            overflow-x: hidden;
        }
        body::-webkit-scrollbar {
            width: 12px;
            height: 12px;
        }
        body::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 6px;
        }
        body::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 6px;
        }
        body::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }
        .dark-mode body::-webkit-scrollbar-track {
            background: #2a2a2a;
        }
        .dark-mode body::-webkit-scrollbar-thumb {
            background: #555;
        }
        .dark-mode body::-webkit-scrollbar-thumb:hover {
            background: #777;
        }
        body.dark-mode {
            background-color: #1a1a1a;
            color: #e0e0e0;
        }
        .container {
            width: 100%;
            padding: 20px;
            box-sizing: border-box;
            max-width: none;
        }
        h1, h2, h3 {
            color: #2c3e50;
            margin-bottom: 20px;
        }
        .dark-mode h1, .dark-mode h2, .dark-mode h3 {
            color: #ecf0f1;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
        }
        .dark-mode .header {
            background: #2a2a2a;
            border-color: #555;
        }
        .header-left {
            flex: 1;
        }
        .header-right {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .selectors {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .table-selector-form {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 0;
        }
        .table-selector-form label {
            font-weight: 500;
            color: #666;
            white-space: nowrap;
        }
        .table-selector-form select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            background: white;
            color: #333;
            font-size: 14px;
            min-width: 150px;
        }
        .dark-mode .table-selector-form label {
            color: #ccc;
        }
        .dark-mode .table-selector-form select {
            background: #3a3a3a;
            border-color: #555;
            color: #e0e0e0;
        }
        .pragma-form {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 0;
        }
        .pragma-form label {
            font-weight: 500;
            color: #666;
            white-space: nowrap;
        }
        .pragma-form input {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            background: white;
            color: #333;
            font-size: 14px;
            min-width: 150px;
        }
        .dark-mode .pragma-form label {
            color: #ccc;
        }
        .dark-mode .pragma-form input {
            background: #3a3a3a;
            border-color: #555;
            color: #e0e0e0;
        }
        .db-selector-form {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 0;
        }
        .db-selector-form label {
            font-weight: 500;
            color: #666;
            white-space: nowrap;
        }
        .db-selector-form select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            background: white;
            color: #333;
            font-size: 14px;
            min-width: 150px;
        }
        .dark-mode .db-selector-form label {
            color: #ccc;
        }
        .dark-mode .db-selector-form select {
            background: #3a3a3a;
            border-color: #555;
            color: #e0e0e0;
        }
        .header .tab-buttons {
            display: flex;
            gap: 5px;
            border-bottom: none;
            margin-bottom: 0;
        }
        .header .tab-button {
            padding: 10px 20px;
            border: 1px solid #ddd;
            background: white;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            color: #666;
            transition: all 0.3s ease;
        }
        .header .tab-button:hover {
            background: #f8f9fa;
            color: #333;
            border-color: #3498db;
        }
        .header .tab-button.active {
            background: #3498db;
            color: white;
            border-color: #3498db;
        }
        .dark-mode .header .tab-button {
            background: #3a3a3a;
            border-color: #555;
            color: #ccc;
        }
        .dark-mode .header .tab-button:hover {
            background: #4a4a4a;
            color: #e0e0e0;
            border-color: #74b9ff;
        }
        .dark-mode .header .tab-button.active {
            background: #74b9ff;
            color: white;
            border-color: #74b9ff;
        }
        .theme-toggle {
            background: none;
            border: 2px solid #3498db;
            color: #3498db;
            padding: 8px 16px;
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .theme-toggle:hover {
            background-color: #3498db;
            color: white;
        }
        .sidebar {
            margin-bottom: 30px;
        }
        .tabs {
            display: flex;
            flex-direction: column;
        }
        .tab-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid #dee2e6;
            padding-bottom: 0;
        }
        .tab-button {
            padding: 12px 24px;
            border: none;
            background: none;
            border-bottom: 3px solid transparent;
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            color: #666;
            transition: all 0.3s ease;
            border-radius: 8px 8px 0 0;
        }
        .tab-button:hover {
            background-color: #f8f9fa;
            color: #333;
        }
        .tab-button.active {
            color: #3498db;
            border-bottom-color: #3498db;
            background-color: #f8f9fa;
        }
        .dark-mode .tab-button {
            color: #ccc;
        }
        .dark-mode .tab-button:hover {
            background-color: #3a3a3a;
            color: #e0e0e0;
        }
        .dark-mode .tab-button.active {
            color: #74b9ff;
            background-color: #3a3a3a;
            border-bottom-color: #74b9ff;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        .panel {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .dark-mode .panel {
            background: #2c2c2c;
            box-shadow: 0 2px 10px rgba(0,0,0,0.3);
        }
        select, textarea, input[type="text"], input[type="number"], input[type="submit"] {
            padding: 10px;
            margin-top: 10px;
            border-radius: 5px;
            border: 1px solid #ddd;
            width: 100%;
            box-sizing: border-box;
            font-family: inherit;
            transition: border-color 0.3s ease;
        }
        .dark-mode select, .dark-mode textarea, .dark-mode input {
            background-color: #3a3a3a;
            border-color: #555;
            color: #e0e0e0;
        }
        select:focus, textarea:focus, input:focus {
            outline: none;
            border-color: #3498db;
        }
        textarea {
            resize: vertical;
            min-height: 100px;
            font-family: 'Consolas', 'Monaco', monospace;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
            margin: 5px;
        }
        .btn-primary {
            background-color: #3498db;
            color: white;
        }
        .btn-primary:hover {
            background-color: #2980b9;
            transform: translateY(-1px);
        }
        .btn-success {
            background-color: #2ecc71;
            color: white;
        }
        .btn-success:hover {
            background-color: #27ae60;
        }
        .btn-danger {
            background-color: #e74c3c;
            color: white;
        }
        .btn-danger:hover {
            background-color: #c0392b;
        }
        .btn-warning {
            background-color: #f39c12;
            color: white;
        }
        .btn-warning:hover {
            background-color: #e67e22;
        }
        .btn-secondary {
            background-color: #95a5a6;
            color: white;
        }
        .btn-secondary:hover {
            background-color: #7f8c8d;
        }
        .btn-sm {
            padding: 5px 10px;
            font-size: 0.875rem;
        }
        .message {
            padding: 12px;
            margin-bottom: 15px;
            border-radius: 5px;
            animation: slideIn 0.3s ease;
        }
        @keyframes slideIn {
            from { transform: translateY(-10px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .message.error {
            background-color: #e74c3c;
            color: white;
        }
        .message.success {
            background-color: #2ecc71;
            color: white;
        }
        .message.info {
            background-color: #3498db;
            color: white;
        }
        .table-container {
            width: 100%;
            overflow-x: auto;
            margin-top: 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .table-container::-webkit-scrollbar {
            height: 12px;
        }
        .table-container::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 6px;
        }
        .table-container::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 6px;
        }
        .table-container::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }
        .dark-mode .table-container::-webkit-scrollbar-track {
            background: #2a2a2a;
        }
        .dark-mode .table-container::-webkit-scrollbar-thumb {
            background: #555;
        }
        .dark-mode .table-container::-webkit-scrollbar-thumb:hover {
            background: #777;
        }
        .dark-mode .table-container {
            background: #2c2c2c;
        }
        #editFieldsContainer textarea {
            transition: height 0.2s ease;
            line-height: 1.4;
        }
        #editFieldsContainer textarea:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 5px rgba(52, 152, 219, 0.3);
        }
        .dark-mode #editFieldsContainer textarea {
            background: #3a3a3a;
            border-color: #555;
            color: #e0e0e0;
        }
        .dark-mode #editFieldsContainer textarea:focus {
            border-color: #74b9ff;
            box-shadow: 0 0 5px rgba(116, 185, 255, 0.3);
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        table thead th {
            background-color: #34495e;
            color: white;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        table tbody tr {
            transition: background-color 0.2s ease;
        }
        table tbody tr:hover {
            background-color: #f8f9fa;
        }
        .dark-mode table tbody tr:hover {
            background-color: #3a3a3a;
        }
        table tbody tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        .dark-mode table tbody tr:nth-child(even) {
            background-color: #2c2c2c;
        }
        table tbody td {
            padding: 12px;
            border: 1px solid #dee2e6;
            white-space: nowrap;
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .editable {
            cursor: pointer;
            position: relative;
        }
        .editable:hover {
            background-color: #e3f2fd;
        }
        .dark-mode .editable:hover {
            background-color: #3a3a3a;
        }
        .edit-input {
            width: 100%;
            padding: 4px;
            border: 1px solid #3498db;
            border-radius: 3px;
        }
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 10px 0 20px 0;
            gap: 10px;
        }
        .pagination .btn {
            margin: 0 2px;
        }
        .pagination .page-info {
            margin: 0 15px;
            color: #666;
        }
        .dark-mode .pagination .page-info {
            color: #ccc;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .bottom-stats {
            background: #f8f9fa;
            border-top: 1px solid #e0e0e0;
            padding: 20px;
            margin-top: 40px;
        }
        .bottom-stats .stats-grid {
            margin-bottom: 0;
            max-width: 1200px;
            margin: 0 auto;
        }
        .dark-mode .bottom-stats {
            background: #2a2a2a;
            border-top-color: #555;
        }
        .dark-mode .stat-card {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        .query-history {
            max-height: 200px;
            overflow-y: auto;
            background: #f8f9fa;
            border-radius: 5px;
            padding: 10px;
        }
        .dark-mode .query-history {
            background: #3a3a3a;
        }
        .query-item {
            padding: 8px;
            margin: 5px 0;
            background: white;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s ease;
            font-family: 'Consolas', 'Monaco', monospace;
            font-size: 0.9rem;
        }
        .dark-mode .query-item {
            background: #2c2c2c;
        }
        .query-item:hover {
            background: #e3f2fd;
            transform: translateX(5px);
        }
        .dark-mode .query-item:hover {
            background: #3a3a3a;
        }
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 10px;
            margin-bottom: 15px;
        }
        .schema-viewer {
            background: #f8f9fa;
            border-radius: 5px;
            padding: 15px;
            margin-top: 15px;
        }
        .dark-mode .schema-viewer {
            background: #3a3a3a;
        }
        .schema-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }
        .schema-table th, .schema-table td {
            padding: 8px;
            border: 1px solid #dee2e6;
        }
        .schema-table th {
            background: #e9ecef;
        }
        .dark-mode .schema-table th {
            background: #495057;
        }
        .export-options {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.8);
        }
        .modal-content {
            background-color: white;
            margin: 0;
            padding: 0;
            border-radius: 0;
            width: 100%;
            height: 100%;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
        }
        .dark-mode .modal-content {
            background-color: #2c2c2c;
        }
        .modal-body {
            padding: 30px;
            flex: 1;
            overflow-y: auto;
            overflow-x: auto;
            max-height: calc(100vh - 160px); /* Account for header and footer */
        }
        .modal-body::-webkit-scrollbar {
            width: 12px;
        }
        .modal-body::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 6px;
        }
        .modal-body::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 6px;
        }
        .modal-body::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }
        .dark-mode .modal-body::-webkit-scrollbar-track {
            background: #2a2a2a;
        }
        .dark-mode .modal-body::-webkit-scrollbar-thumb {
            background: #555;
        }
        .dark-mode .modal-body::-webkit-scrollbar-thumb:hover {
            background: #777;
        }
        .modal-footer {
            padding: 20px 30px;
            border-top: 1px solid #dee2e6;
            background-color: #f8f9fa;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        .dark-mode .modal-footer {
            border-top-color: #495057;
            background-color: #1a1a1a;
        }
        .dark-mode .modal-content {
            background-color: #2c2c2c;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 30px;
            border-bottom: 1px solid #dee2e6;
            background-color: #f8f9fa;
        }
        .dark-mode .modal-header {
            border-bottom-color: #495057;
            background-color: #1a1a1a;
        }
        .modal-header h2 {
            margin: 0;
        }
        .modal-header .close {
            margin: 0;
        }
        .close {
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            color: #aaa;
        }
        .close:hover {
            color: #000;
        }
        .dark-mode .close:hover {
            color: #fff;
        }
        pre {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            font-family: 'Consolas', 'Monaco', monospace;
            border: 1px solid #dee2e6;
        }
        .dark-mode pre {
            background-color: #2c2c2c;
            border-color: #495057;
        }
        @media (max-width: 768px) {
            .sidebar {
                grid-template-columns: 1fr;
            }
            .header {
                flex-direction: column;
                gap: 15px;
            }
            .export-options {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <div class="header-left">
            <div class="tabs">
                <div class="tab-buttons">
                    <button class="tab-button active" onclick="showTab('table-select')">Table Selection</button>
                    <button class="tab-button" onclick="showTab('table-manage')">Table Management</button>
                    <button class="tab-button" onclick="showTab('table-schema')">Table Schema</button>
                    <button class="tab-button" onclick="showTab('query')">Custom Query</button>
                    <button class="tab-button" onclick="showTab('history')">Query History</button>
                </div>
            </div>
        </div>
        <div class="header-right">
            <div class="selectors">
                <form method="get" action="" class="db-selector-form">
                    <label for="db_selector">Database:</label>
                    <select name="db" id="db_selector" onchange="showDatabaseOptions(this.value)">
                        <option value="">-- Choose --</option>
                        <?php foreach ($databases as $db_file): ?>
                            <option value="<?= sanitize($db_file) ?>" <?= $db_file === $selected_db ? 'selected' : '' ?>>
                                <?= sanitize($db_file) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
                <?php if (!empty($selected_db)): ?>
                    <form method="post" action="?db=<?= sanitize($selected_db) ?>" class="pragma-form">
                        <label for="pragma_key">PRAGMA Key:</label>
                        <input type="password" name="pragma_key" id="pragma_key" value="<?= sanitize($_SESSION['pragma_key']) ?>" placeholder="Enter encryption key" />
                        <button type="submit" class="btn btn-primary btn-sm">Connect</button>
                    </form>
                <?php endif; ?>
                <?php if (!empty($selected_db) && !empty($tables)): ?>
                    <form method="post" action="?db=<?= sanitize($selected_db) ?>" class="table-selector-form">
                        <label for="header_table_selector">Table:</label>
                        <select name="query" id="header_table_selector" onchange="this.form.submit()">
                            <option value="">-- Select --</option>
                            <?php foreach ($tables as $table_name): ?>
                                <?php $option_value = "SELECT * FROM `" . sanitize($table_name) . "`"; ?>
                                <option value="<?= $option_value ?>" <?= (isset($_POST['query']) && $_POST['query'] === $option_value) ? 'selected' : '' ?>>
                                    <?= sanitize($table_name) ?> <?= !empty($db_info['tables'][$table_name]) ? '(' . $db_info['tables'][$table_name] . ' rows)' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                <?php endif; ?>
            </div>
            <button class="theme-toggle" onclick="toggleDarkMode()">🌙 Dark Mode</button>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="message error"><?= sanitize($error) ?></div>
    <?php endif; ?>

    <?php if ($message): ?>
        <div class="message success"><?= sanitize($message) ?></div>
    <?php endif; ?>

    
    
        <?php if (!empty($selected_db)): ?>
        <div class="sidebar">

            <div id="table-select-tab" class="tab-content active">
                <?php if (!empty($current_table) && !empty($results)): ?>
                    <div class="table-container">
                        <div class="table-info">
                            <div class="table-info-left">
                                <h3><?= sanitize($current_table) ?></h3>
                                <span class="record-count"><?= $total_records ?> records found</span>
                            </div>
                            <div class="table-info-right">
                                <button class="btn btn-primary" onclick="openModal('insertModal')">+ Insert Record</button>
                            </div>
                        </div>
                        <?php
                        // Inline table template for main display
                        if (!empty($results) && !empty($results['rows'])): 
                            $rows = $results['rows'];
                            $columns = $results['columns'];
                        ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <?php foreach ($columns as $column): ?>
                                                <th><?= sanitize($column) ?></th>
                                            <?php endforeach; ?>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($rows as $row): 
                                            // Get primary key value for this row
                                            $pk_value = isset($pk_column) && isset($row[$pk_column]) ? $row[$pk_column] : '';
                                        ?>
                                            <tr>
                                                <?php foreach ($columns as $column): ?>
                                                    <td><?= formatValue($row[$column]) ?></td>
                                                <?php endforeach; ?>
                                                <td class="actions">
                                                    <?php if ($pk_value): ?>
                                                        <button class="btn btn-warning btn-sm" onclick="editRecord('<?= htmlspecialchars($pk_value) ?>', <?= htmlspecialchars(json_encode($row)) ?>)">Edit</button>
                                                        <button class="btn btn-danger btn-sm" onclick="confirmDelete('<?= htmlspecialchars($pk_value) ?>')">Delete</button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <?php if (isset($total_pages) && $total_pages > 1): ?>
                                <div class="pagination">
                                    <?php if ($page > 1): ?>
                                        <a href="?db=<?= sanitize($selected_db) ?>&page=<?= $page - 1 ?>" class="btn btn-secondary">Previous</a>
                                    <?php endif; ?>

                                    <span class="page-info">
                                        Page <?= $page ?> of <?= $total_pages ?>
                                    </span>

                                    <?php if ($page < $total_pages): ?>
                                        <a href="?db=<?= sanitize($selected_db) ?>&page=<?= $page + 1 ?>" class="btn btn-secondary">Next</a>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        <?php elseif (!empty($results)): ?>
                            <p>No data found.</p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                </div>

                <div id="table-manage-tab" class="tab-content">
                    <div class="panel">
                                <h3>Table Management</h3>
                                <div style="max-height: 400px; overflow-y: auto; border: 1px solid #ddd; border-radius: 5px; padding: 10px; background: #f9f9f9;">
                                    <?php if (empty($tables)): ?>
                                        <p>No tables found in this database.</p>
                                    <?php else: ?>
                                        <?php foreach ($tables as $table_name): ?>
                                            <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px; margin: 8px 0; background: white; border-radius: 6px; border: 1px solid #e0e0e0; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                                <div>
                                                    <strong style="font-size: 1.1em;"><?= sanitize($table_name) ?></strong>
                                                    <?php if (!empty($db_info['tables'][$table_name])): ?>
                                                        <div style="color: #666; font-size: 0.9em; margin-top: 4px;">
                                                            📊 <?= $db_info['tables'][$table_name] ?> rows
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div style="display: flex; gap: 8px;">
                                                    <button type="button" class="btn btn-primary btn-sm" onclick="quickViewTable('<?= sanitize($table_name) ?>')">View</button>
                                                    <button type="button" class="btn btn-danger btn-sm" onclick="confirmDeleteTable('<?= sanitize($table_name) ?>')"
                                                            <?php if (strpos($table_name, 'sqlite_') === 0): ?>disabled title="Cannot delete system tables"<?php endif; ?>>
                                                        Delete
                                                    </button>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                <div style="margin-top: 15px; padding: 15px; background: #e8f4fd; border-radius: 6px; border-left: 4px solid #3498db;">
                                    <strong>💡 Table Management Tips:</strong>
                                    <ul style="margin: 8px 0 0 20px; color: #555;">
                                        <li>Use the <strong>View</strong> button to quickly open a table</li>
                                        <li><strong>Delete</strong> permanently removes tables and all their data</li>
                                        <li>System tables (starting with 'sqlite_') cannot be deleted</li>
                                    </ul>
                                </div>
                    </div>
                </div>

                <div id="table-schema-tab" class="tab-content">
                    <div class="panel">
                                <h3>Table Schema</h3>
                                <?php if (empty($tables)): ?>
                                    <p>No tables found in this database.</p>
                                <?php else: ?>
                                    <div style="margin-bottom: 15px;">
                                        <label for="schema_table_selector">Select a table to view its schema:</label>
                                        <select id="schema_table_selector" onchange="showTableSchema(this.value)" style="width: 100%; padding: 10px;">
                                            <option value="">-- Choose a table --</option>
                                            <?php foreach ($tables as $table_name): ?>
                                                <option value="<?= sanitize($table_name) ?>"><?= sanitize($table_name) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div id="schema-container">
                                        <?php if (!empty($tables) && isset($_POST['query']) && preg_match('/SELECT\s+\*\s+FROM\s+`([^`]+)`/i', $_POST['query'], $matches)): ?>
                                            <?php $current_table = $matches[1]; ?>
                                            <?php $schema = getTableSchema($db_dir . sanitize($selected_db), $current_table); ?>
                                            <?php if (!empty($schema)): ?>
                                                <div class="schema-viewer">
                                                    <h5>Schema for: <strong><?= sanitize($current_table) ?></strong></h5>
                                                    <table class="schema-table">
                                                        <thead>
                                                            <tr>
                                                                <th>Column</th>
                                                                <th>Type</th>
                                                                <th>PK</th>
                                                                <th>Nullable</th>
                                                                <th>Default</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach ($schema as $col): ?>
                                                                <tr>
                                                                    <td><strong><?= sanitize($col['name']) ?></strong></td>
                                                                    <td><code><?= sanitize($col['type']) ?></code></td>
                                                                    <td><?= $col['pk'] ? '🔑 Yes' : 'No' ?></td>
                                                                    <td><?= $col['notnull'] ? '❌ No' : '✅ Yes' ?></td>
                                                                    <td><?= $col['dflt_value'] !== null ? '<code>' . sanitize($col['dflt_value']) . '</code>' : '-' ?></td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <div style="text-align: center; padding: 40px; color: #666;">
                                                <div style="font-size: 3em; margin-bottom: 10px;">📋</div>
                                                <p>Select a table from the dropdown above to view its schema structure.</p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                    </div>
                </div>

                <div id="query-tab" class="tab-content">
                    <div class="panel">
                        <h3>Run Custom Query</h3>
                        <form method="post" action="?db=<?= sanitize($selected_db) ?>">
                            <textarea name="query" placeholder="Enter your SQL query here..."><?php echo isset($_POST['query']) ? sanitize($_POST['query']) : ''; ?></textarea>
                            <div style="display: flex; gap: 10px;">
                                <button type="submit" class="btn btn-primary">Execute Query</button>
                                <button type="button" class="btn btn-secondary" onclick="openInsertModal()">Insert Record</button>
                                <button type="button" class="btn btn-warning" onclick="openExportModal()">Export Data</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div id="history-tab" class="tab-content">
                    <div class="panel">
                        <h3>Query History</h3>
                        <?php if (!empty($_SESSION['query_history'])): ?>
                            <div class="query-history">
                                <?php foreach (array_reverse($_SESSION['query_history']) as $index => $query): ?>
                                    <div class="query-item" onclick="executeQueryFromHistory('<?= htmlspecialchars($query) ?>')">
                                        <strong><?= htmlspecialchars(substr($query, 0, 60)) . (strlen($query) > 60 ? '...' : '') ?></strong>
                                        <div style="margin-top: 5px; font-size: 0.8em; color: #666;">
                                            Click to execute
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p>No query history yet. Run some queries to see them here.</p>
                        <?php endif; ?>

                        <?php if (!empty($_SESSION['query_favorites'])): ?>
                            <h4 style="margin-top: 30px;">Favorite Queries</h4>
                            <div class="query-history">
                                <?php foreach ($_SESSION['query_favorites'] as $index => $query): ?>
                                    <div class="query-item" onclick="executeQueryFromHistory('<?= htmlspecialchars($query) ?>')">
                                        <strong><?= htmlspecialchars(substr($query, 0, 60)) . (strlen($query) > 60 ? '...' : '') ?></strong>
                                        <div style="margin-top: 5px; font-size: 0.8em; color: #666;">
                                            Click to execute
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($results && !empty($results['rows'])): ?>
        <?php
            // Extract the table name from the query to find the primary key
            $query_parts = explode('FROM', $_POST['query'] ?? 'FROM');
            $current_table = trim(explode('LIMIT', $query_parts[1] ?? '')[0], '` ');
            $pk_column = getPrimaryKey($db_dir . sanitize($selected_db), $current_table);

            // Get total count for pagination
            $count_query = preg_replace('/SELECT\s+.*?\s+FROM/i', 'SELECT COUNT(*) as total FROM', $_POST['query']);
            $count_query = preg_replace('/\s+LIMIT\s+\d+\s*(?:OFFSET\s*\d+)?/i', '', $count_query);
            $count_query = preg_replace('/\s+ORDER\s+BY\s+.*$/i', '', $count_query);
            $count_result = executeQuery($db_dir . sanitize($selected_db), $count_query);
            $total_rows = $count_result['rows'][0]['total'] ?? 0;
            $total_pages = ceil($total_rows / $per_page);
        ?>

        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="<?= $current_url ?>&page=1" class="btn btn-secondary btn-sm">First</a>
                <a href="<?= $current_url ?>&page=<?= $page - 1 ?>" class="btn btn-secondary btn-sm">Previous</a>
            <?php endif; ?>

            <span class="page-info">Page <?= $page ?> of <?= $total_pages ?> (<?= $total_rows ?> total rows)</span>

            <?php if ($page < $total_pages): ?>
                <a href="<?= $current_url ?>&page=<?= $page + 1 ?>" class="btn btn-secondary btn-sm">Next</a>
                <a href="<?= $current_url ?>&page=<?= $total_pages ?>" class="btn btn-secondary btn-sm">Last</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="table-container">
            <form method="post" action="?db=<?= sanitize($selected_db) ?>" id="deleteForm">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="table_name" value="<?= sanitize($current_table) ?>">
                <input type="hidden" name="current_query" value="<?= sanitize($_POST['query']) ?>">
                <input type="hidden" name="ids" id="idsToDelete">
                <table>
                    <thead>
                        <tr>
                            <?php if ($pk_column): ?>
                                <th><input type="checkbox" id="selectAll"></th>
                            <?php endif; ?>
                            <?php foreach ($results['columns'] as $col): ?>
                                <th><?= sanitize($col) ?></th>
                            <?php endforeach; ?>
                            <?php if ($pk_column): ?>
                                <th>Action</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results['rows'] as $row): ?>
                            <tr>
                                <?php if ($pk_column): ?>
                                    <td><input type="checkbox" name="row_ids[]" value="<?= sanitize($row[$pk_column]) ?>"></td>
                                <?php endif; ?>
                                <?php foreach ($row as $col_name => $value): ?>
                                    <td class="editable" data-pk="<?= sanitize($pk_column) ?>" data-pk-value="<?= sanitize($row[$pk_column] ?? '') ?>" data-row-data='<?= htmlspecialchars(json_encode($row)) ?>' title="<?= sanitize($value) ?> (Click to edit record)">
                                        <?php
                                        $display_value = $value;
                                        if (mb_strlen($value) > 50) {
                                            $display_value = mb_substr($value, 0, 50) . '...';
                                        }
                                        echo sanitize($display_value);
                                        ?>
                                    </td>
                                <?php endforeach; ?>
                                <?php if ($pk_column): ?>
                                    <td>
                                        <button type="button" class="btn btn-danger btn-sm" onclick="singleDelete('<?= sanitize($row[$pk_column]) ?>')">Delete</button>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </form>
        </div>

        <div style="margin-top: 15px; display: flex; gap: 10px;">
            <?php if ($pk_column): ?>
                <button type="button" class="btn btn-danger" id="bulkDeleteBtn">Delete Selected</button>
            <?php endif; ?>
            <button type="button" class="btn btn-warning" onclick="openExportModal()">Export Results</button>
            <button type="button" class="btn btn-secondary" onclick="openInsertModal()">Add Record</button>
        </div>

    <?php elseif ($results && empty($results['rows'])): ?>
        <div class="message info">No results found for this query.</div>
    <?php endif; ?>

</div>

<script>
    // Global variables for AJAX refresh
    window.currentQuery = '<?= sanitize($_POST['query'] ?? '') ?>';
    window.currentDb = '<?= sanitize($selected_db) ?>';
    
    // Performance monitoring for encrypted databases
    <?php if (!empty($_SESSION['pragma_key'])): ?>
    console.log('Using encrypted database with optimized key handling');
    <?php endif; ?>
    
    // Dark mode toggle
    function toggleDarkMode() {
        document.body.classList.toggle('dark-mode');
        localStorage.setItem('darkMode', document.body.classList.contains('dark-mode'));
    }

    // Load dark mode preference
    if (localStorage.getItem('darkMode') === 'true') {
        document.body.classList.add('dark-mode');
    }

    // Query history execution
    function executeQueryFromHistory(query) {
        const textarea = document.querySelector('textarea[name="query"]');
        textarea.value = query;
        window.currentQuery = query;
        textarea.form.submit();
    }
    
    // Update global variables when query form is submitted
    document.addEventListener('DOMContentLoaded', function() {
        const queryForms = document.querySelectorAll('form');
        queryForms.forEach(form => {
            form.addEventListener('submit', function(e) {
                const queryInput = form.querySelector('textarea[name="query"], select[name="query"]');
                if (queryInput) {
                    window.currentQuery = queryInput.value;
                }
            });
        });
    });

    // Single delete
    function singleDelete(id) {
        document.getElementById('idsToDelete').value = id;
        document.getElementById('deleteForm').submit();
    }

    // Select all checkboxes
    if (document.getElementById('selectAll')) {
        document.getElementById('selectAll').addEventListener('change', function(e) {
            const checkboxes = document.querySelectorAll('input[name="row_ids[]"]');
            checkboxes.forEach(cb => cb.checked = e.target.checked);
        });
    }

    // Bulk delete
    if (document.getElementById('bulkDeleteBtn')) {
        document.getElementById('bulkDeleteBtn').addEventListener('click', function() {
            const checkboxes = document.querySelectorAll('input[name="row_ids[]"]:checked');
            const ids = Array.from(checkboxes).map(cb => cb.value);
            if (ids.length > 0) {
                document.getElementById('idsToDelete').value = ids.join(',');
                document.getElementById('deleteForm').submit();
            }
        });
    }

    // Modal editing
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('editable')) {
            const cell = e.target;
            const pk = cell.getAttribute('data-pk');
            const pkValue = cell.getAttribute('data-pk-value');
            const rowData = JSON.parse(cell.getAttribute('data-row-data'));

            openEditModal(pkValue, rowData);
        }
    });

    function openEditModal(pkValue, rowData) {
        const modal = document.getElementById('editModal');
        if (modal) {
            document.getElementById('editPkValue').value = pkValue;
            document.getElementById('editPkValueDisplay').value = pkValue;

            // Clear existing fields
            const fieldsContainer = document.getElementById('editFieldsContainer');
            fieldsContainer.innerHTML = '';

            // Create fields for each column
            Object.keys(rowData).forEach(column => {
                const fieldDiv = document.createElement('div');
                fieldDiv.style.marginBottom = '20px';

                const label = document.createElement('label');
                label.textContent = column + ':';
                label.style.display = 'block';
                label.style.marginBottom = '5px';
                label.style.fontWeight = 'bold';

                const input = document.createElement('textarea');
                input.name = 'field_' + column;
                input.value = rowData[column];
                input.style.width = '100%';
                input.style.padding = '8px';
                input.style.border = '1px solid #ddd';
                input.style.borderRadius = '4px';
                input.style.minHeight = '60px';
                input.style.resize = 'vertical';
                input.style.fontFamily = "'Consolas', 'Monaco', monospace";

                fieldDiv.appendChild(label);
                fieldDiv.appendChild(input);
                fieldsContainer.appendChild(fieldDiv);
            });

            // Open modal first, then auto-expand fields after they're visible
            openModal('editModal');

            // Auto-expand all textareas after modal is visible
            setTimeout(() => {
                const textareas = fieldsContainer.querySelectorAll('textarea');
                textareas.forEach(textarea => {
                    // Auto-expand textarea based on content
                    textarea.style.height = 'auto';
                    textarea.style.height = Math.max(60, textarea.scrollHeight) + 'px';

                    // Auto-expand on input
                    textarea.addEventListener('input', function() {
                        this.style.height = 'auto';
                        this.style.height = Math.max(60, this.scrollHeight) + 'px';
                    });
                });
            }, 100);
        }
    }

    function saveEdit() {
        const pkValue = document.getElementById('editPkValue').value;
        const fieldsContainer = document.getElementById('editFieldsContainer');
        const textareas = fieldsContainer.querySelectorAll('textarea[name^="field_"]');

        const updates = {};
        let hasChanges = false;

        textareas.forEach(textarea => {
            const column = textarea.name.replace('field_', '');
            const newValue = textarea.value.trim();
            updates[column] = newValue;
            hasChanges = true;
        });

        if (hasChanges) {
            // First update the record via AJAX
            const formData = new FormData();
            formData.append('action', 'update_multiple');
            formData.append('table_name', '<?= sanitize($current_table ?? '') ?>');
            formData.append('pk_column', '<?= sanitize($pk_column ?? '') ?>');
            formData.append('pk_value', pkValue);
            formData.append('updates', JSON.stringify(updates));
            formData.append('query', '<?= sanitize($_POST['query'] ?? '') ?>');

            fetch('?db=<?= sanitize($selected_db) ?>', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Close modal
                    closeModal('editModal');
                    // Show success message
                    showMessage(data.message, 'success');
                    
                    // If server requests refresh or AJAX refresh fails, reload page
                    if (data.refresh) {
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    } else {
                        // Try AJAX refresh first, fallback to page reload
                        try {
                            refreshTableContent();
                        } catch (error) {
                            console.error('AJAX refresh failed, reloading page:', error);
                            setTimeout(() => {
                                window.location.reload();
                            }, 1000);
                        }
                    }
                } else {
                    alert('Error updating record: ' + (data.error || 'Unknown error'));
                    closeModal('editModal');
                }
            })
            .catch(error => {
                console.error('Error updating record:', error);
                alert('Error updating record: ' + error.message);
                closeModal('editModal');
            });
        } else {
            closeModal('editModal');
        }
    }

    function refreshTableContent() {
        // Get current query and database from global variables or form
        const currentQuery = window.currentQuery || document.querySelector('input[name="query"]')?.value || '';
        const currentDb = window.currentDb || '<?= sanitize($selected_db) ?>';
        
        console.log('Refreshing table with query:', currentQuery, 'and db:', currentDb);
        
        if (!currentQuery) {
            console.error('No current query available for refresh, reloading page instead');
            window.location.reload();
            return;
        }

        const formData = new FormData();
        formData.append('action', 'refresh_table');
        formData.append('query', currentQuery);

        fetch('?db=' + encodeURIComponent(currentDb), {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Replace the table content
                const tableContainer = document.querySelector('.table-container');
                if (tableContainer) {
                    tableContainer.innerHTML = data.table_html;
                } else {
                    console.warn('Table container not found, reloading page');
                    window.location.reload();
                }
            } else {
                console.error('Error refreshing table:', data.error);
                // Fallback to page reload
                window.location.reload();
            }
        })
        .catch(error => {
            console.error('Error refreshing table:', error);
            // Fallback to page reload
            window.location.reload();
        });
    }

    function showMessage(message, type) {
        const messageDiv = document.createElement('div');
        messageDiv.className = `alert alert-${type}`;
        messageDiv.textContent = message;
        messageDiv.style.position = 'fixed';
        messageDiv.style.top = '20px';
        messageDiv.style.right = '20px';
        messageDiv.style.zIndex = '9999';
        messageDiv.style.padding = '10px 20px';
        messageDiv.style.borderRadius = '4px';
        messageDiv.style.backgroundColor = type === 'success' ? '#d4edda' : '#f8d7da';
        messageDiv.style.color = type === 'success' ? '#155724' : '#721c24';
        messageDiv.style.border = `1px solid ${type === 'success' ? '#c3e6cb' : '#f5c6cb'}`;

        document.body.appendChild(messageDiv);

        // Remove the message after 3 seconds
        setTimeout(() => {
            if (messageDiv.parentNode) {
                messageDiv.parentNode.removeChild(messageDiv);
            }
        }, 3000);
    }

    // Modal functions
    function openModal(modalId) {
        document.getElementById(modalId).style.display = 'block';
    }

    function closeModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
    }

    // Tab switching function
    function showTab(tabName) {
        // Hide all tab contents
        const tabContents = document.querySelectorAll('.tab-content');
        tabContents.forEach(tab => {
            tab.classList.remove('active');
        });

        // Remove active class from all tab buttons
        const tabButtons = document.querySelectorAll('.tab-button');
        tabButtons.forEach(button => {
            button.classList.remove('active');
        });

        // Show selected tab content
        const selectedTab = document.getElementById(tabName + '-tab');
        if (selectedTab) {
            selectedTab.classList.add('active');
        }

        // Add active class to clicked button
        const clickedButton = Array.from(tabButtons).find(button =>
            button.textContent.toLowerCase().includes(tabName)
        );
        if (clickedButton) {
            clickedButton.classList.add('active');
        }
    }

    
    // Quick view table function
    function quickViewTable(tableName) {
        const query = "SELECT * FROM `" + tableName + "`";
        const form = document.createElement('form');
        form.method = 'post';
        form.action = '?db=<?= sanitize($selected_db) ?>';

        const queryInput = document.createElement('input');
        queryInput.type = 'hidden';
        queryInput.name = 'query';
        queryInput.value = query;

        form.appendChild(queryInput);
        document.body.appendChild(form);
        form.submit();
    }

    // Show table schema function
    function showTableSchema(tableName) {
        if (!tableName) {
            document.getElementById('schema-container').innerHTML = `
                <div style="text-align: center; padding: 40px; color: #666;">
                    <div style="font-size: 3em; margin-bottom: 10px;">📋</div>
                    <p>Select a table from the dropdown above to view its schema structure.</p>
                </div>
            `;
            return;
        }

        // Fetch schema via AJAX
        fetch('?db=<?= sanitize($selected_db) ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=get_schema&table_name=' + encodeURIComponent(tableName)
        })
        .then(response => response.text())
        .then(html => {
            // Extract schema from the response (this is a simplified approach)
            // In a real app, you'd return JSON
            const schema = getTableSchemaFromCache(tableName);
            if (schema) {
                displayTableSchema(tableName, schema);
            } else {
                document.getElementById('schema-container').innerHTML = `
                    <div style="text-align: center; padding: 40px; color: #e74c3c;">
                        <div style="font-size: 3em; margin-bottom: 10px;">❌</div>
                        <p>Could not load schema for table: ${tableName}</p>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error loading schema:', error);
            document.getElementById('schema-container').innerHTML = `
                <div style="text-align: center; padding: 40px; color: #e74c3c;">
                    <div style="font-size: 3em; margin-bottom: 10px;">❌</div>
                    <p>Error loading schema for table: ${tableName}</p>
                </div>
            `;
        });
    }

    // Cache table schemas on page load
    let tableSchemaCache = {};

    // Initialize schema cache
    document.addEventListener('DOMContentLoaded', function() {
        <?php foreach ($tables as $table_name): ?>
            tableSchemaCache['<?= sanitize($table_name) ?>'] = <?= json_encode(getTableSchema($db_dir . sanitize($selected_db), $table_name)) ?>;
        <?php endforeach; ?>
    });

    function getTableSchemaFromCache(tableName) {
        return tableSchemaCache[tableName] || null;
    }

    function displayTableSchema(tableName, schema) {
        if (!schema || schema.length === 0) {
            document.getElementById('schema-container').innerHTML = `
                <div style="text-align: center; padding: 40px; color: #e74c3c;">
                    <div style="font-size: 3em; margin-bottom: 10px;">❌</div>
                    <p>No schema found for table: ${tableName}</p>
                </div>
            `;
            return;
        }

        let schemaHtml = `
            <div class="schema-viewer">
                <h5>Schema for: <strong>${tableName}</strong></h5>
                <table class="schema-table">
                    <thead>
                        <tr>
                            <th>Column</th>
                            <th>Type</th>
                            <th>PK</th>
                            <th>Nullable</th>
                            <th>Default</th>
                        </tr>
                    </thead>
                    <tbody>
        `;

        schema.forEach(col => {
            schemaHtml += `
                <tr>
                    <td><strong>${col.name}</strong></td>
                    <td><code>${col.type}</code></td>
                    <td>${col.pk ? '🔑 Yes' : 'No'}</td>
                    <td>${col.notnull ? '❌ No' : '✅ Yes'}</td>
                    <td>${col.dflt_value !== null ? '<code>' + col.dflt_value + '</code>' : '-'}</td>
                </tr>
            `;
        });

        schemaHtml += `
                    </tbody>
                </table>
            </div>
        `;

        document.getElementById('schema-container').innerHTML = schemaHtml;
    }

    // Table deletion confirmation
    function confirmDeleteTable(tableName) {
        if (confirm('Are you sure you want to delete the table "' + tableName + '"?\n\nThis action cannot be undone and all data in the table will be permanently lost!')) {
            const form = document.createElement('form');
            form.method = 'post';
            form.action = '?db=<?= sanitize($selected_db) ?>';

            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'delete_table';

            const tableNameInput = document.createElement('input');
            tableNameInput.type = 'hidden';
            tableNameInput.name = 'table_name';
            tableNameInput.value = tableName;

            form.appendChild(actionInput);
            form.appendChild(tableNameInput);
            document.body.appendChild(form);
            form.submit();
        }
    }

    // Insert modal
    function openInsertModal() {
        const modal = document.getElementById('insertModal');
        if (modal) {
            openModal('insertModal');
        }
    }

    // Export modal
    function openExportModal() {
        const modal = document.getElementById('exportModal');
        if (modal) {
            openModal('exportModal');
        }
    }

    // Close modals when clicking outside
    window.onclick = function(event) {
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        });
    }

    // Show database options and auto-submit if database is selected
    function showDatabaseOptions(dbValue) {
        if (dbValue) {
            // Auto-submit the form to show PRAGMA key field
            document.getElementById('db_selector').form.submit();
        }
    }

    </script>

<!-- Edit Record Modal -->
<?php if (!empty($selected_db) && !empty($current_table)): ?>
<div id="editModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Edit Record</h2>
            <span class="close" onclick="closeModal('editModal')">&times;</span>
        </div>
        <div class="modal-body">
            <form onsubmit="event.preventDefault(); saveEdit();">
                <input type="hidden" id="editPkValue" name="pk_value">

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 30px;">
                    <div>
                        <label>Table:</label>
                        <input type="text" value="<?= sanitize($current_table) ?>" readonly>
                    </div>
                    <div>
                        <label>Primary Key:</label>
                        <input type="text" id="editPkValueDisplay" readonly>
                    </div>
                </div>

                <div style="margin-bottom: 30px;">
                    <label><strong>Edit Record Fields:</strong></label>
                    <div id="editFieldsContainer">
                        <!-- Fields will be dynamically generated here -->
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('editModal')">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="saveEdit()">Save Changes</button>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Insert Record Modal -->
<?php if (!empty($selected_db) && !empty($current_table)): ?>
<div id="insertModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Insert New Record</h2>
            <span class="close" onclick="closeModal('insertModal')">&times;</span>
        </div>
        <div class="modal-body">
            <form method="post" action="?db=<?= sanitize($selected_db) ?>" id="insertForm">
                <input type="hidden" name="action" value="insert">
                <input type="hidden" name="table_name" value="<?= sanitize($current_table) ?>">
                <input type="hidden" name="current_query" value="<?= sanitize($_POST['query'] ?? '') ?>">

                <?php
                $schema = getTableSchema($db_dir . sanitize($selected_db), $current_table);
                if (!empty($schema)):
                ?>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
                        <?php foreach ($schema as $col): ?>
                            <?php if ($col['name'] !== $pk_column): ?>
                                <div>
                                    <label><?= sanitize($col['name']) ?> <?= $col['notnull'] ? '*' : '' ?>:
                                    <small style="color: #666; display: block; margin-bottom: 5px;">
                                        Type: <?= sanitize($col['type']) ?>
                                        <?php if ($col['dflt_value'] !== null): ?> | Default: <?= sanitize($col['dflt_value']) ?><?php endif; ?>
                                    </small></label>
                                    <input type="text" name="fields[<?= sanitize($col['name']) ?>]" placeholder="<?= sanitize($col['name']) ?>" <?= $col['notnull'] ? 'required' : '' ?> style="width: 100%;">
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p>Could not load table schema.</p>
                <?php endif; ?>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('insertModal')">Cancel</button>
            <button type="submit" form="insertForm" class="btn btn-success">Insert Record</button>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Export Modal -->
<?php if (!empty($selected_db) && isset($_POST['query'])): ?>
<div id="exportModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Export Data</h2>
            <span class="close" onclick="closeModal('exportModal')">&times;</span>
        </div>
        <div class="modal-body">
            <form method="post" action="?db=<?= sanitize($selected_db) ?>" id="exportForm">
                <input type="hidden" name="action" value="export">
                <input type="hidden" name="query" value="<?= sanitize($_POST['query']) ?>">

                <div style="max-width: 600px; margin: 0 auto;">
                    <label>Export Format:</label>
                    <select name="format" style="width: 100%; font-size: 16px; padding: 15px;">
                        <option value="csv">CSV (Comma Separated Values)</option>
                        <option value="json">JSON</option>
                        <option value="sql">SQL (INSERT Statements)</option>
                    </select>

                    <div style="margin-top: 30px; text-align: center;">
                        <button type="submit" class="btn btn-primary" style="padding: 15px 30px; font-size: 16px;">Export Data</button>
                    </div>
                </div>

                <div style="margin-top: 50px; padding-top: 30px; border-top: 2px solid #dee2e6;">
                    <h3>Direct Export Links:</h3>
                    <div style="display: flex; justify-content: center; gap: 20px; margin-top: 20px;">
                        <a href="?db=<?= sanitize($selected_db) ?>&export=1&query=<?= base64_encode($_POST['query']) ?>&format=csv" class="btn btn-success" style="padding: 15px 25px; font-size: 16px;">Download CSV</a>
                        <a href="?db=<?= sanitize($selected_db) ?>&export=1&query=<?= base64_encode($_POST['query']) ?>&format=json" class="btn btn-success" style="padding: 15px 25px; font-size: 16px;">Download JSON</a>
                        <a href="?db=<?= sanitize($selected_db) ?>&export=1&query=<?= base64_encode($_POST['query']) ?>&format=sql" class="btn btn-success" style="padding: 15px 25px; font-size: 16px;">Download SQL</a>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('exportModal')">Cancel</button>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($selected_db)): ?>
    <?php if (!empty($db_info)): ?>
        <div class="bottom-stats">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?= sanitize($db_info['total_tables']) ?></div>
                    <div class="stat-label">Tables</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?= number_format($db_info['size'] / 1024, 2) ?> KB</div>
                    <div class="stat-label">Database Size</div>
                </div>
                <?php
                $total_rows = array_sum($db_info['tables']);
                if ($total_rows > 0): ?>
                <div class="stat-card">
                    <div class="stat-value"><?= number_format($total_rows) ?></div>
                    <div class="stat-label">Total Rows</div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>

</body>
</html>
