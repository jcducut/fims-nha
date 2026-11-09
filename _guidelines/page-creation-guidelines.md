# Page Creation Guidelines

## Overview

This document provides comprehensive guidelines for creating pages in the PHPMaker multi-application architecture. Pages can use direct database queries, API calls, or a combination of both approaches.

## Page Structure

### Basic Page Template

```php
<?php
namespace PHPMaker2024\UAC; // Change namespace to your application

// Page-specific PHP logic here
// Database queries, data preparation, etc.

?>
<!-- HTML content starts here -->
<div class="container-fluid">
    <!-- Page content -->
</div>

<script>
// JavaScript for page functionality
</script>

<style>
/* Page-specific styles */
</style>
```

## Data Loading Patterns

### 1. Direct Database Queries (Server-Side)

**Use when:**
- Data is needed immediately on page load
- Simple data that doesn't require complex processing
- Static or semi-static data
- Performance is critical

**Syntax:**
```php
<?php
// Single value
$totalUsers = ExecuteScalar("SELECT COUNT(*) FROM users", "DB");

// Single row
$user = ExecuteRowAssociative("SELECT * FROM users WHERE user_id = " . QuotedValue($userId, DataType::NUMBER), "DB");

// Multiple rows
$users = ExecuteRowsAssociative("SELECT * FROM users ORDER BY username", "DB");

// With parameters
$user = ExecuteRowAssociative("SELECT * FROM users WHERE user_id = " . QuotedValue($userId, DataType::NUMBER) . " AND is_active = " . QuotedValue(true, DataType::BOOLEAN), "DB");
?>
```

**Best Practices:**
- Always use `QuotedValue()` for parameters to prevent SQL injection
- Use appropriate `DataType` constants
- Use `ExecuteScalar()` for single values
- Use `ExecuteRowAssociative()` for single rows
- Use `ExecuteRowsAssociative()` for multiple rows

### 2. API Calls (Client-Side)

**Use when:**
- Data needs to be refreshed dynamically
- Complex data processing is required
- Real-time updates are needed
- Data depends on user interactions

**Syntax:**
```javascript
// Basic API call pattern
async function loadData() {
    try {
        const response = await fetch('/UAC/api/endpoint', {
            headers: { 
                'Content-Type': 'application/json',
                'X-Authorization': 'Bearer ' + ew.API_JWT_TOKEN 
            }
        });
        const result = await response.json();
        if (!result.success) throw new Error(result.message || 'Error loading data');
        
        // Process result.data
        updateUI(result.data);
    } catch (error) {
        console.error('Error:', error);
        ew.alert('Error loading data');
    }
}
```

**Best Practices:**
- Always include JWT token in headers
- Check `result.success` before processing data
- Handle errors gracefully
- Use async/await for better readability

### 3. Hybrid Approach

**Use when:**
- Initial data is needed immediately (server-side)
- Additional data is loaded dynamically (client-side)
- Complex interactions require real-time updates

**Example:**
```php
<?php
// Load initial data server-side
$initialData = ExecuteRowsAssociative("SELECT * FROM systems ORDER BY system_code", "DB");
$initialDataJson = json_encode($initialData);
?>

<script>
// Load additional data client-side
let initialData = <?php echo $initialDataJson; ?>;
let dynamicData = {};

async function loadDynamicData() {
    // API call for dynamic data
    const response = await fetch('/UAC/api/dynamic-data', {
        headers: { 
            'Content-Type': 'application/json',
            'X-Authorization': 'Bearer ' + ew.API_JWT_TOKEN 
        }
    });
    const result = await response.json();
    if (result.success) {
        dynamicData = result.data;
        updateUI();
    }
}

function updateUI() {
    // Combine initial and dynamic data
    // Update the interface
}
</script>
```

## UI Component Patterns

### 1. Cards and Containers

**Standard Card Structure:**
```html
<div class="card">
    <div class="card-header bg-primary text-white">
        <h3 class="card-title">Title</h3>
    </div>
    <div class="card-body">
        <!-- Content -->
    </div>
</div>
```

### 2. Tables

**Data Table Pattern:**
```html
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Data Table</h3>
    </div>
    <div class="card-body table-responsive">
        <table id="dataTable" class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>Column 1</th>
                    <th>Column 2</th>
                </tr>
            </thead>
            <tbody>
                <!-- Will be populated via JavaScript -->
            </tbody>
        </table>
    </div>
</div>
```

**JavaScript Population:**
```javascript
function populateTable(data) {
    const tbody = document.querySelector('#dataTable tbody');
    tbody.innerHTML = data.map(item => `
        <tr>
            <td>${item.column1}</td>
            <td>${item.column2}</td>
        </tr>
    `).join('');
}
```

### 3. Forms and Modals

**Modal Integration:**
```html
<button type="button" class="btn btn-primary" 
        data-ew-action="modal" 
        data-url="UsersAdd" 
        data-caption="Add New User">
    <i class="fas fa-plus"></i> Add User
</button>
```

**Form Handling:**
```javascript
// Handle successful form submissions
ew.on("success", function(e) {
    const data = e.detail?.data;
    if (data?.action === "modal") {
        const formName = data.form;
        if (formName?.includes("usersadd")) {
            loadUsers(); // Reload data
        }
        ew.showToast(ew.language.phrase("RecordUpdated"));
        if (data.modal) {
            ew.modalDialog.close(data.modal);
        }
    }
});
```

### 4. Search and Filter

**Search Input:**
```html
<div class="input-group mb-3">
    <input type="text" id="searchInput" class="form-control" placeholder="Search...">
    <button class="btn btn-outline-secondary" type="button" onclick="filterData()">
        <i class="fas fa-search"></i>
    </button>
</div>
```

**Filter Function:**
```javascript
function filterData() {
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
    const rows = document.querySelectorAll('#dataTable tbody tr');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchTerm) ? '' : 'none';
    });
}
```

## Data Types and Quoting

### DataType Constants

```php
// Use these constants with QuotedValue()
DataType::STRING    // For text data
DataType::NUMBER    // For numeric data
DataType::BOOLEAN   // For true/false values
DataType::DATE      // For date values
DataType::TIME      // For time values
DataType::MEMO      // For long text
DataType::BLOB      // For binary data
```

### QuotedValue Usage

```php
// Correct usage
$userId = 123;
$username = "john_doe";
$isActive = true;
$createdDate = "2024-01-01";

$sql = "SELECT * FROM users WHERE user_id = " . QuotedValue($userId, DataType::NUMBER) . 
       " AND username = " . QuotedValue($username, DataType::STRING) . 
       " AND is_active = " . QuotedValue($isActive, DataType::BOOLEAN) . 
       " AND created_date >= " . QuotedValue($createdDate, DataType::DATE);

// Wrong usage (SQL injection risk)
$sql = "SELECT * FROM users WHERE user_id = $userId AND username = '$username'";
```

## Error Handling

### Server-Side Error Handling

```php
<?php
try {
    $data = ExecuteRowsAssociative("SELECT * FROM users", "DB");
    $dataJson = json_encode($data);
} catch (Exception $e) {
    LogError("Error loading users: " . $e->getMessage());
    $dataJson = json_encode([]);
}
?>
```

### Client-Side Error Handling

```javascript
async function loadData() {
    try {
        const response = await fetch('/UAC/api/users', {
            headers: { 
                'Content-Type': 'application/json',
                'X-Authorization': 'Bearer ' + ew.API_JWT_TOKEN 
            }
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const result = await response.json();
        if (!result.success) {
            throw new Error(result.message || 'Unknown error');
        }
        
        return result.data;
    } catch (error) {
        console.error('Error loading data:', error);
        ew.alert('Error loading data: ' + error.message);
        return [];
    }
}
```
## Security Best Practices

### 1. Input Validation

```php
// Validate user input
$userId = Get("user_id");
if (!is_numeric($userId) || $userId <= 0) {
    throw new Exception("Invalid user ID");
}
```

### 2. Permission Checks

```php
// Check user permissions
if (!AllowView("users")) {
    throw new Exception("Access denied");
}
```

### 3. XSS Prevention

```php
// Use HtmlEncode for output
echo HtmlEncode($userData['username']);

// In JavaScript, be careful with innerHTML
element.textContent = userData.username; // Safe
// element.innerHTML = userData.username; // Potentially unsafe
```

## Common Patterns

### 2. Management Pages

```php
<?php
// Load initial data
$systems = ExecuteRowsAssociative("SELECT * FROM systems ORDER BY system_code", "DB");
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-4">
            <!-- Selection panel -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Systems</h3>
                </div>
                <div class="card-body">
                    <select id="systemSelect" class="form-select" onchange="loadSystemData(this.value)">
                        <?php foreach ($systems as $system): ?>
                        <option value="<?php echo $system['system_code']; ?>">
                            <?php echo HtmlEncode($system['system_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
        
        <div class="col-md-8">
            <!-- Data panel -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">System Data</h3>
                </div>
                <div class="card-body" id="systemData">
                    <!-- Will be populated via API -->
                </div>
            </div>
        </div>
    </div>
</div>

<script>
async function loadSystemData(systemCode) {
    try {
        const response = await fetch(`/UAC/api/system-data/${systemCode}`, {
            headers: { 
                'Content-Type': 'application/json',
                'X-Authorization': 'Bearer ' + ew.API_JWT_TOKEN 
            }
        });
        const result = await response.json();
        if (result.success) {
            document.getElementById('systemData').innerHTML = formatSystemData(result.data);
        }
    } catch (error) {
        console.error('Error:', error);
        ew.alert('Error loading system data');
    }
}
</script>
```

## Testing and Debugging

### 1. Debug Output

```php
<?php
// Development debugging
if (IsDevelopment()) {
    echo "<!-- Debug info: " . json_encode($debugData) . " -->";
}
?>
```

### 2. Console Logging

```javascript
// Client-side debugging
console.log('Loading data...', { userId: currentUserId });
console.log('Data loaded:', data);
```

### 3. Error Logging

```php
// Server-side error logging
try {
    $result = ExecuteRowsAssociative($sql, "DB");
} catch (Exception $e) {
    LogError("Database error: " . $e->getMessage() . " SQL: " . $sql);
    throw $e;
}
```

## Conclusion

These guidelines provide a comprehensive framework for creating pages in the PHPMaker multi-application architecture. Always prioritize security, performance, and maintainability when implementing page functionality.
