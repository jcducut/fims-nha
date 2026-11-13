# API Route & Service Guidelines

## Overview

This document provides comprehensive guidelines for creating API routes and services in the PHPMaker multi-application architecture. It covers proper service implementation, database query patterns, middleware usage, and best practices.

## API Structure

### Basic API File Structure

```php
<?php
// {SYSTEM}/app/api.php

namespace PHPMaker2024\UAC; // Change namespace to your application
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

// Middleware definitions
$jwtMiddleware = function (Request $request, RequestHandler $handler): Response {
    // JWT validation logic
};

$accessMiddleware = function (Request $request, RequestHandler $handler): Response {
    // Permission checking logic
};


// Include Service Classes
include(dirname(__DIR__, 1) . "/app/lib/YourService.php");

// Include Route Files
include(dirname(__DIR__, 1) . "/app/api/your-routes.php");
```

## Service Implementation

### Service Class Structure

```php
<?php
namespace PHPMaker2024\UAC;

class YourService
{
    /**
     * Get data with proper error handling
     * @return string JSON response
     */
    public function getData()
    {
        try {
            $sql = "SELECT * FROM your_table ORDER BY id";
            $result = ExecuteRowsAssociative($sql, "DB");
            return json_encode(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            LogError("Error in getData: " . $e->getMessage());
            return json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Save data with transaction support
     * @param array $data Input data
     * @return string JSON response
     */
    public function saveData($data)
    {
        try {
            // Start transaction
            Execute("BEGIN", "DB");
            
            try {
                // Validate input
                if (empty($data['required_field'])) {
                    throw new \Exception("Required field is missing");
                }
                
                // Insert/Update data
                $sql = "INSERT INTO your_table (field1, field2) VALUES (" . 
                       QuotedValue($data['field1'], DataType::STRING) . ", " .
                       QuotedValue($data['field2'], DataType::NUMBER) . ")";
                Execute($sql, "DB");
                
                // Commit transaction
                Execute("COMMIT", "DB");
                return json_encode(['success' => true]);
                
            } catch (\Exception $e) {
                // Rollback on error
                Execute("ROLLBACK", "DB");
                throw $e;
            }
        } catch (\Exception $e) {
            LogError("Error in saveData: " . $e->getMessage());
            return json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
```

# Database Query Patterns

### 1. ExecuteScalar - Single Values

**Use for:** COUNT, MAX, MIN, SUM, AVG, or any single value

```php
// Count records
$totalCount = App\ExecuteScalar("SELECT COUNT(*) FROM applications", "DB");

// Get maximum value
$maxId = App\ExecuteScalar("SELECT MAX(id) FROM applications", "DB");

// Check if record exists
$exists = App\ExecuteScalar(
    "SELECT COUNT(*) FROM businesses WHERE account_number = " . 
    App\QuotedValue($accountNumber, App\DataType::STRING, "DB"), 
    "DB"
);

// Get single field value
$prefix = "APP-{$year}-";
$count = App\ExecuteScalar(
    "SELECT COUNT(*) FROM applications WHERE application_number LIKE " . 
    App\QuotedValue($prefix . '%', App\DataType::STRING, "DB"), 
    "DB"
);

// Get scalar from lookup table
$statusId = App\ExecuteScalar(
    "SELECT id FROM settings_status 
     WHERE name = 'Assessment' AND status_for = 'applications' 
     LIMIT 1", 
    "DB"
);
```

> **Note:** Inside service/lib classes, always use the `App\` prefix (e.g., `App\ExecuteScalar`).  
> In custom pages (e.g., `app/pages/`) and API files (e.g., `app/api/`), use without prefix (e.g., `ExecuteScalar`).

---

### 2. ExecuteRow - Single Row

**Use for:** Getting one complete record

```php
// Get single record by ID
$application = App\ExecuteRow(
    "SELECT * FROM applications WHERE id = " . 
    App\QuotedValue($applicationId, App\DataType::NUMBER, "DB"), 
    "DB"
);

// Get record with JOINs
$appData = App\ExecuteRow("
    SELECT 
        a.id as application_id,
        a.business_id,
        a.mode_of_payment_id,
        smp.name as mode_of_payment_name,
        b.account_number
    FROM applications a
    LEFT JOIN businesses b ON a.business_id = b.id
    LEFT JOIN settings_mode_of_payment smp ON a.mode_of_payment_id = smp.id
    WHERE a.id = " . App\QuotedValue($applicationId, App\DataType::NUMBER, "DB"), 
    "DB"
);

// Get date range or aggregate info
$dateRange = App\ExecuteRow(
    'SELECT MIN("OR_Date") as min_date, MAX("OR_Date") as max_date FROM gen_or', 
    "DB"
);
```

---

### 3. ExecuteRows - Multiple Rows

**Use for:** Getting multiple records

```php
// Get all records from a table
$statuses = App\ExecuteRows(
    "SELECT * FROM settings_status ORDER BY name", 
    "DB"
);

// Get records with conditions and JOINs
$fees = App\ExecuteRows("
    SELECT 
        f.id,
        f.fee_code,
        f.fee_name,
        bc.name as category_name
    FROM fees f
    LEFT JOIN business_categories bc ON f.business_category_id = bc.id
    WHERE f.business_category_id = " . App\QuotedValue($categoryId, App\DataType::NUMBER, "DB") . "
    ORDER BY f.fee_name", 
    "DB"
);

// Get paginated results
$limit = 50;
$offset = ($page - 1) * $limit;
$results = App\ExecuteRows("
    SELECT * FROM applications 
    ORDER BY created_at DESC 
    LIMIT $limit OFFSET $offset", 
    "DB"
);

// Get distinct values (e.g., for dropdowns or filters)
$years = App\ExecuteRows(
    'SELECT DISTINCT "Year" FROM gen_or ORDER BY "Year" DESC LIMIT 10', 
    "DB"
);
```

---

### 4. Execute - INSERT, UPDATE, DELETE

**Use for:** Data modification operations

```php
// Insert record
$sql = "INSERT INTO applications (application_number, business_id, status_id, created_at) VALUES (" .
    App\QuotedValue($applicationNumber, App\DataType::STRING, "DB") . ", " .
    App\QuotedValue($businessId, App\DataType::NUMBER, "DB") . ", " .
    App\QuotedValue($statusId, App\DataType::NUMBER, "DB") . ", NOW())";
App\Execute($sql, "DB");

// Update record
$sql = "UPDATE applications SET status_id = " . App\QuotedValue($statusId, App\DataType::NUMBER, "DB") .
    ", updated_at = NOW()" .
    " WHERE id = " . App\QuotedValue($applicationId, App\DataType::NUMBER, "DB");
App\Execute($sql, "DB");

// Delete record
$sql = "DELETE FROM business_owner_information WHERE business_id = " . 
    App\QuotedValue($businessId, App\DataType::NUMBER, "DB");
App\Execute($sql, "DB");
```

---

### 5. ExecuteStatement - Transaction Control

**Use for:** Transaction management (BEGIN, COMMIT, ROLLBACK)

```php
// Begin transaction
App\ExecuteStatement("BEGIN", "DB");

try {
    // ... perform multiple operations ...

    App\ExecuteStatement("COMMIT", "DB");
} catch (\Exception $e) {
    App\ExecuteStatement("ROLLBACK", "DB");
    App\Log("Transaction failed: " . $e->getMessage());
}
```

---

## DataType Constants and Quoting

### DataType Constants

```php
App\DataType::STRING    // For text/varchar data
App\DataType::NUMBER    // For numeric data (integers, floats/decimals)
App\DataType::BOOLEAN   // For true/false values
App\DataType::DATE      // For date values
App\DataType::TIME      // For time values
App\DataType::MEMO      // For long text
App\DataType::BLOB      // For binary data
App\DataType::GUID      // For GUID/UUID values
App\DataType::XML       // For XML data
```

### QuotedValue Usage

> Always pass the third argument `"DB"` to specify the database connection.

```php
// String values
$sql = "SELECT * FROM businesses WHERE account_number = " . 
    App\QuotedValue($accountNumber, App\DataType::STRING, "DB");

// Numeric values
$sql = "SELECT * FROM applications WHERE id = " . 
    App\QuotedValue($applicationId, App\DataType::NUMBER, "DB");

// Boolean values
$sql = "SELECT * FROM businesses WHERE is_active = " . 
    App\QuotedValue(true, App\DataType::BOOLEAN, "DB");

// Date values
$sql = "SELECT * FROM applications WHERE created_at >= " . 
    App\QuotedValue($dateFrom, App\DataType::DATE, "DB");

// LIKE / partial match
$sql = "SELECT * FROM applications WHERE application_number LIKE " . 
    App\QuotedValue($prefix . '%', App\DataType::STRING, "DB");

// NULL values — write directly in SQL, no QuotedValue needed
$sql = "SELECT * FROM applications WHERE deleted_at IS NULL";

// Multiple conditions
$sql = "SELECT * FROM applications WHERE " .
    "business_id = " . App\QuotedValue($businessId, App\DataType::NUMBER, "DB") . " AND " .
    "status_id = " . App\QuotedValue($statusId, App\DataType::NUMBER, "DB") . " AND " .
    "created_at >= " . App\QuotedValue($startDate, App\DataType::DATE, "DB");
```

---

## Logging

Always log SQL queries before execution for debugging.

```php
App\Log("Query SQL: " . $sql);
$result = App\ExecuteRow($sql, "DB");
```

---

## Namespace Usage Reference

| Context | Prefix Required | Example |
|---|---|---|
| `app/lib/*.php` (service classes) | `App\` | `App\ExecuteRow(...)` |
| `app/pages/*.php` (custom pages) | None | `ExecuteRow(...)` |
| `app/api/*.php` (API files) | None | `ExecuteRows(...)` |


## Route Implementation

### Basic Route Structure

```php
<?php
// {SYSTEM}/app/routes/your-routes.php
namespace PHPMaker2024\UAC;

// GET route
$app->get("/your-endpoint", function ($request, $response, $args) {
    $service = new YourService();
    return $response->write($service->getData());
})->add($accessMiddleware)->add($jwtMiddleware);

// POST route
$app->post("/your-endpoint", function ($request, $response, $args) {
    $service = new YourService();
    $data = $request->getParsedBody();
    return $response->write($service->saveData($data));
})->add($accessMiddleware)->add($jwtMiddleware);

// Route with parameters
$app->get("/your-endpoint/{id}", function ($request, $response, $args) {
    $service = new YourService();
    $id = $args['id'];
    return $response->write($service->getDataById($id));
})->add($accessMiddleware)->add($jwtMiddleware);
```

### Advanced Route Patterns

```php
// Optional parameters
$app->get("/users[/{user_id}]", function ($request, $response, $args) {
    $service = new UserService();
    $userId = isset($args['user_id']) ? $args['user_id'] : null;
    return $response->write($service->getUsers($userId));
})->add($accessMiddleware)->add($jwtMiddleware);

// Multiple parameters
$app->get("/users/{user_id}/permissions/{system_code}", function ($request, $response, $args) {
    $service = new PermissionService();
    $userId = $args['user_id'];
    $systemCode = $args['system_code'];
    return $response->write($service->getUserPermissions($userId, $systemCode));
})->add($accessMiddleware)->add($jwtMiddleware);

// Query parameters
$app->get("/users", function ($request, $response, $args) {
    $service = new UserService();
    $queryParams = $request->getQueryParams();
    $page = $queryParams['page'] ?? 1;
    $limit = $queryParams['limit'] ?? 50;
    return $response->write($service->getUsers($page, $limit));
})->add($accessMiddleware)->add($jwtMiddleware);
```

## Middleware Implementation

### JWT Middleware

```php
$jwtMiddleware = function (Request $request, RequestHandler $handler): Response {
    $authHeader = $request->getHeaderLine('Authorization');
    if (!$authHeader) {
        $authHeader = $request->getHeaderLine('X-Authorization');
    }
    if (!$authHeader) {
        $authHeader = GetJwtToken();
    }
    
    // Check for global EW_TOKEN as fallback
    if (!$authHeader && isset($_COOKIE[Config('JWT.JWT_TOKEN_NAME')])) {
        $authHeader = 'Bearer ' . $_COOKIE[Config('JWT.JWT_TOKEN_NAME')];
    }
    
    $jwt = str_replace('Bearer ', '', $authHeader);
    $payload = DecodeJwt($jwt);

    // Validate JWT
    if (isset($payload['failureMessage']) || !isset($jwt) || empty($jwt)) {
        $response = new \Nyholm\Psr7\Response();
        $response = $response->withHeader('Content-Type', 'application/json');
        $response->getBody()->write(json_encode(['error' => 'Unauthorized']));
        return $response->withStatus(401);
    }

    // Attach user_id to request
    $user_id = $payload['userid'] ?? null;
    $request = $request->withAttribute('user_id', $user_id);

    return $handler->handle($request);
};
```

### Access Middleware

```php
$accessMiddleware = function (Request $request, RequestHandler $handler): Response {
    $authHeader = $request->getHeaderLine('Authorization');
    if (!$authHeader) {
        $authHeader = $request->getHeaderLine('X-Authorization');
    }
    if (!$authHeader) {
        $authHeader = GetJwtToken();
    }
    $jwt = str_replace('Bearer ', '', $authHeader);
    
    $payload = DecodeJwt($jwt);
    $userLevel = $payload['userlevel'] ?? -2;

    // Allow system admin
    if ($userLevel == -1) return $handler->handle($request);
    
    // Get current route path
    $path = $request->getUri()->getPath();
    
    // Determine page to check based on route
    $pageName = '';
    if (strpos($path, '/access') !== false) {
        $pageName = '{UAC}UserAccess.php';
    } elseif (strpos($path, '/permission') !== false) {
        $pageName = '{UAC}UserManagement.php';
    }
    
    if ($pageName) {
        // Check permission in database
        $sql = "SELECT permission FROM user_level_permissions 
            WHERE user_level_id = ANY(STRING_TO_ARRAY(" . QuotedValue($userLevel, DataType::STRING) . ", ',')::integer[]) 
            AND table_name = " . QuotedValue($pageName, DataType::STRING) . "
            ORDER BY permission DESC 
            LIMIT 1";
        $permission = ExecuteScalar($sql, "DB");
        
        if (!$permission || $permission <= 0) {
            $response = new \Nyholm\Psr7\Response();
            $response = $response->withHeader('Content-Type', 'application/json');
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'You do not have permission to access this resource'
            ]));
            return $response->withStatus(403);
        }
    }

    return $handler->handle($request);
};
```

## Service vs Library Usage

### When to Use Services

**Use Services for:**
- Business logic
- Data processing
- Complex operations
- API endpoints
- Database operations
- External integrations

**Service Example:**
```php
class UserService
{
    public function createUser($data)
    {
        // Validate input
        $this->validateUserData($data);
        
        // Check if user exists
        if ($this->userExists($data['username'])) {
            throw new \Exception("Username already exists");
        }
        
        // Create user
        $userId = $this->insertUser($data);
        
        // Send welcome email
        $this->sendWelcomeEmail($data['email']);
        
        return $userId;
    }
    
    private function validateUserData($data)
    {
        if (empty($data['username'])) {
            throw new \Exception("Username is required");
        }
        // More validation...
    }
}
```

### When to Use Libraries

**Use Libraries for:**
- Utility functions
- Helper methods
- Reusable components
- Third-party integrations
- Common functionality

**Library Example:**
```php
class FunctionClassTools
{
    public function add($classOrInstance, string $method, string $description)
    {
        // Utility function for registering class methods
    }
    
    public function dispatch(string $name, $arguments)
    {
        // Utility function for dispatching method calls
    }
}
```

## Error Handling Patterns

### Service-Level Error Handling

```php
class YourService
{
    public function getData()
    {
        try {
            // Validate input
            if (empty($requiredParam)) {
                throw new \InvalidArgumentException("Required parameter is missing");
            }
            
            // Database operation
            $sql = "SELECT * FROM table WHERE id = " . QuotedValue($id, DataType::NUMBER);
            $result = ExecuteRowsAssociative($sql, "DB");
            
            if (empty($result)) {
                return json_encode(['success' => false, 'message' => 'No data found']);
            }
            
            return json_encode(['success' => true, 'data' => $result]);
            
        } catch (\InvalidArgumentException $e) {
            // Input validation errors
            return json_encode(['success' => false, 'message' => $e->getMessage()]);
        } catch (\Exception $e) {
            // Database or other errors
            LogError("Error in getData: " . $e->getMessage());
            return json_encode(['success' => false, 'message' => 'Internal server error']);
        }
    }
}
```

### Route-Level Error Handling

```php
$app->post("/your-endpoint", function ($request, $response, $args) {
    try {
        $service = new YourService();
        $data = $request->getParsedBody();
        
        // Validate request data
        if (empty($data)) {
            $response = new \Nyholm\Psr7\Response();
            $response = $response->withHeader('Content-Type', 'application/json');
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Request data is required'
            ]));
            return $response->withStatus(400);
        }
        
        $result = $service->processData($data);
        return $response->write($result);
        
    } catch (\Exception $e) {
        LogError("Route error: " . $e->getMessage());
        $response = new \Nyholm\Psr7\Response();
        $response = $response->withHeader('Content-Type', 'application/json');
        $response->getBody()->write(json_encode([
            'success' => false,
            'message' => 'Internal server error'
        ]));
        return $response->withStatus(500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);
```

## Transaction Management

### Basic Transaction Pattern

```php
public function saveData($data)
{
    try {
        // Start transaction
        Execute("BEGIN", "DB");
        
        try {
            // Multiple database operations
            $this->insertMainRecord($data);
            $this->insertRelatedRecords($data);
            $this->updateStatus($data);
            
            // Commit transaction
            Execute("COMMIT", "DB");
            return json_encode(['success' => true]);
            
        } catch (\Exception $e) {
            // Rollback on any error
            Execute("ROLLBACK", "DB");
            throw $e;
        }
    } catch (\Exception $e) {
        LogError("Transaction error: " . $e->getMessage());
        return json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
```

### Complex Transaction Example

```php
public function transferUser($fromUserId, $toUserId, $data)
{
    try {
        Execute("BEGIN", "DB");
        
        try {
            // Validate users exist
            $fromUser = ExecuteRowAssociative("SELECT * FROM users WHERE user_id = " . 
                QuotedValue($fromUserId, DataType::NUMBER), "DB");
            $toUser = ExecuteRowAssociative("SELECT * FROM users WHERE user_id = " . 
                QuotedValue($toUserId, DataType::NUMBER), "DB");
                
            if (!$fromUser || !$toUser) {
                throw new \Exception("One or both users not found");
            }
            
            // Update from user
            $sql = "UPDATE users SET status = 'transferred' WHERE user_id = " . 
                   QuotedValue($fromUserId, DataType::NUMBER);
            Execute($sql, "DB");
            
            // Update to user
            $sql = "UPDATE users SET status = 'active' WHERE user_id = " . 
                   QuotedValue($toUserId, DataType::NUMBER);
            Execute($sql, "DB");
            
            // Insert audit log
            $sql = "INSERT INTO audit_logs (action, user_id, details) VALUES (" .
                   QuotedValue('transfer', DataType::STRING) . ", " .
                   QuotedValue(CurrentUserID(), DataType::NUMBER) . ", " .
                   QuotedValue(json_encode($data), DataType::STRING) . ")";
            Execute($sql, "DB");
            
            Execute("COMMIT", "DB");
            return json_encode(['success' => true]);
            
        } catch (\Exception $e) {
            Execute("ROLLBACK", "DB");
            throw $e;
        }
    } catch (\Exception $e) {
        LogError("Transfer error: " . $e->getMessage());
        return json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
```

## Performance Optimization

### 1. Query Optimization

```php
// Good: Use specific columns
$users = ExecuteRowsAssociative("SELECT user_id, username, email FROM users", "DB");

// Avoid: Select all columns when not needed
$users = ExecuteRowsAssociative("SELECT * FROM users", "DB");

// Good: Use proper indexing
$user = ExecuteRowAssociative("SELECT * FROM users WHERE username = " . 
    QuotedValue($username, DataType::STRING), "DB");

// Good: Use LIMIT for large datasets
$users = ExecuteRowsAssociative("SELECT * FROM users ORDER BY created_date DESC LIMIT 100", "DB");
```

### 2. Caching

```php
class CachedService
{
    private $cache = [];
    
    public function getSystems()
    {
        $cacheKey = 'systems_list';
        
        if (!isset($this->cache[$cacheKey])) {
            $sql = "SELECT * FROM systems ORDER BY system_code";
            $this->cache[$cacheKey] = ExecuteRowsAssociative($sql, "DB");
        }
        
        return json_encode(['success' => true, 'data' => $this->cache[$cacheKey]]);
    }
}
```

### 3. Pagination

```php
public function getUsers($page = 1, $limit = 50)
{
    $offset = ($page - 1) * $limit;
    
    // Get total count
    $totalCount = ExecuteScalar("SELECT COUNT(*) FROM users", "DB");
    
    // Get paginated data
    $users = ExecuteRowsAssociative("
        SELECT * FROM users 
        ORDER BY username 
        LIMIT $limit OFFSET $offset", "DB");
    
    return json_encode([
        'success' => true,
        'data' => $users,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $totalCount,
            'pages' => ceil($totalCount / $limit)
        ]
    ]);
}
```

## Security Best Practices

### 1. Input Validation

```php
public function validateInput($data)
{
    // Required fields
    if (empty($data['username'])) {
        throw new \InvalidArgumentException("Username is required");
    }
    
    // Data type validation
    if (!is_string($data['username'])) {
        throw new \InvalidArgumentException("Username must be a string");
    }
    
    // Length validation
    if (strlen($data['username']) > 50) {
        throw new \InvalidArgumentException("Username too long");
    }
    
    // Format validation
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $data['username'])) {
        throw new \InvalidArgumentException("Username contains invalid characters");
    }
}
```

### 2. SQL Injection Prevention

```php
// Always use QuotedValue
$sql = "SELECT * FROM users WHERE username = " . QuotedValue($username, DataType::STRING);

// Never concatenate user input directly
// $sql = "SELECT * FROM users WHERE username = '$username'"; // WRONG!

// Use proper data types
$sql = "SELECT * FROM users WHERE user_id = " . QuotedValue($userId, DataType::NUMBER);
$sql = "SELECT * FROM users WHERE is_active = " . QuotedValue($isActive, DataType::BOOLEAN);
```

### 3. Permission Checking

```php
public function checkPermission($action, $resource)
{
    $userLevel = CurrentUserLevel();
    
    if ($userLevel == -1) {
        return true; // System admin
    }
    
    $sql = "SELECT permission FROM user_level_permissions 
            WHERE user_level_id = " . QuotedValue($userLevel, DataType::NUMBER) . 
            " AND table_name = " . QuotedValue($resource, DataType::STRING);
    $permission = ExecuteScalar($sql, "DB");
    
    return $permission && $permission > 0;
}
```

## Testing and Debugging

### 1. Logging

```php
// Error logging
try {
    $result = ExecuteRowsAssociative($sql, "DB");
} catch (\Exception $e) {
    LogError("Database error: " . $e->getMessage() . " SQL: " . $sql);
    throw $e;
}

// Debug logging
if (IsDevelopment()) {
    Log("Debug info: " . json_encode($debugData));
}
```

### 2. Response Formatting

```php
public function formatResponse($success, $data = null, $message = null)
{
    $response = ['success' => $success];
    
    if ($data !== null) {
        $response['data'] = $data;
    }
    
    if ($message !== null) {
        $response['message'] = $message;
    }
    
    return json_encode($response);
}
```

## Conclusion

These guidelines provide a comprehensive framework for implementing API routes and services in the PHPMaker multi-application architecture. Always prioritize security, performance, and maintainability when implementing API functionality.
