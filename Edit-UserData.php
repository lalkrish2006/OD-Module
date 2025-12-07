<?php
session_start();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli("localhost", "root", "", "college_db");
    $conn->set_charset("utf8mb4");
} catch (Exception $e) {
    die("DB Connection Failed: " . $e->getMessage());
}
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    
    die("Access Denied: Admin privileges required."); 
}
$user_roles = [
    'student' => ['table' => 'students', 'pk' => 'register_no', 'fields' => ['name', 'department', 'year', 'section','email']],
    'hod' => ['table' => 'hods', 'pk' => 'register_no', 'fields' => ['name', 'department', 'email']],
    'mentor' => ['table' => 'mentors', 'pk' => 'register_no', 'fields' => ['name', 'department', 'year', 'section', 'mentor_email']],
    'admin' => ['table' => 'admin_users', 'pk' => 'register_no', 'fields' => ['name', 'department']],
    'principal' => ['table' => 'principals', 'pk' => 'register_no', 'fields' => ['name', 'email']],
    'lab_technician' => ['table' => 'lab_technicians', 'pk' => 'register_no', 'fields' => ['name', 'department', 'email']],
    'cas' => ['table' => 'cas', 'pk' => 'register_no', 'fields' => ['name', 'department', 'year', 'section']],
    'jas' => ['table' => 'jas', 'pk' => 'register_no', 'fields' => ['name', 'department']]
];

$selected_role = '';
$table_config = null;
$user_data = []; 
$message = '';
if (isset($_GET['role']) && array_key_exists($_GET['role'], $user_roles)) {
    $selected_role = $_GET['role'];
    $table_config = $user_roles[$selected_role];
    $table_name = $table_config['table'];
    $pk_column = $table_config['pk'];
    
    // Construct SELECT query to get all data except 'password'
    $select_fields = array_merge([$pk_column], $table_config['fields']);
    
    // ⭐ MODIFICATION HERE: Add ORDER BY name
    // Note: All user tables in your schema have a 'name' column, making this consistent.
    $select_query = "SELECT " . implode(', ', $select_fields) . " FROM {$table_name} ORDER BY name ASC";
    
    $stmt = $conn->prepare($select_query);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $user_data[] = $row;
        }
    }
}if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['update_user'])) {
    
    $update_role = $_POST['role'] ?? '';
    $identifier = $_POST['identifier'] ?? ''; // Primary key value
    
    if (array_key_exists($update_role, $user_roles) && !empty($identifier)) {
        
        $config = $user_roles[$update_role];
        $update_table = $config['table'];
        $pk_column = $config['pk'];
        $fields_to_update = $config['fields'];
        
        $set_clauses = [];
        $bind_types = "";
        $bind_params = [];

        // Dynamically build the SET clause and parameters
        foreach ($fields_to_update as $field) {
            if (isset($_POST[$field])) {
                $set_clauses[] = "{$field} = ?";
                // Determine bind type: 'i' for integer (year), 's' for string (others)
                $value = $_POST[$field];
                if (in_array($field, ['year'])) {
                    $bind_types .= 'i';
                    $bind_params[] = (int)$value;
                } else {
                    $bind_types .= 's';
                    $bind_params[] = $value;
                }
            }
        }
        
        if (!empty($set_clauses)) {
            // Add the identifier/Primary Key parameter for the WHERE clause
            $bind_types .= 's';
            $bind_params[] = $identifier;

            $sql_update = "UPDATE {$update_table} SET " . implode(', ', $set_clauses) . " WHERE {$pk_column} = ?";

            $stmt = $conn->prepare($sql_update);
            // Use call_user_func_array to pass parameters dynamically to bind_param
            call_user_func_array([$stmt, 'bind_param'], array_merge([$bind_types], ref_values($bind_params)));

            if ($stmt->execute()) {
                $message = "✅ Data for **{$identifier}** in the **" . ucfirst($update_role) . "** table updated successfully!";
            } else {
                $message = "❌ Error updating data: " . $stmt->error;
            }
        }
        
    } else {
         $message = "❌ Invalid role or identifier specified for update.";
    }

    // Refresh to show updated data and clear POST state
    header("Location: Edit-UserData.php?role=" . urlencode($update_role) . "&message=" . urlencode($message));
    exit;
}

// Helper function to pass parameters by reference (required by bind_param)
function ref_values($arr){
    $refs = array();
    foreach($arr as $key => $value)
        $refs[$key] = &$arr[$key];
    return $refs;
}

// Check for refresh message from GET
if (isset($_GET['message'])) {
    $message = urldecode($_GET['message']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Admin: Edit User Data</title>
    <style>
        .btn-role { padding: 10px 15px; margin-right: 5px; text-decoration: none; border: 1px solid #ccc; background: #f0f0f0; color: #333; }
        .btn-active { background: #007bff; color: white; border-color: #007bff; }
        .message-success { color: green; font-weight: bold; }
        .message-error { color: red; font-weight: bold; }
        table { border-collapse: collapse; }
        th, td { padding: 8px; border: 1px solid #ddd; text-align: left; }
        input[type="text"] { width: 90%; padding: 5px; border: 1px solid #ccc; }
        .btn-update { background: #28a745; color: white; border: none; padding: 5px 10px; cursor: pointer; }
    </style>
</head>
<body>

    <h2>👤 Admin Edit User Data</h2>
    
    
    <?php if (!empty($message)): ?>
        <p class="<?php echo (strpos($message, '✅') !== false) ? 'message-success' : 'message-error'; ?>">
            <?php echo $message; ?>
        </p>
    <?php endif; ?>

    <h3>Select User Role to Edit:</h3>
    <div class="role-selection">
        <?php foreach ($user_roles as $role => $config): ?>
            <a href="Edit-UserData.php?role=<?php echo $role; ?>" 
               class="btn-role <?php echo ($selected_role === $role) ? 'btn-active' : ''; ?>">
                <?php echo ucwords(str_replace('_', ' ', $role)); ?>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if (!empty($selected_role) && $table_config): ?>
        <hr>
        <h3>Editing: <?php echo ucwords(str_replace('_', ' ', $selected_role)); ?> Data</h3>
        
        <input type="text" id="searchInput" onkeyup="filterTable()" 
               placeholder="Search by Identifier, Name, Dept, or Section..." 
               style="width: 50%; padding: 10px; margin-bottom: 20px; border: 1px solid #ddd;">
        <table id="userTable" style="width:100%; margin-top: 20px;">
            <thead>
                <tr>
                    <th>Identifier (<?php echo $table_config['pk']; ?>)</th>
                    <?php foreach ($table_config['fields'] as $field): ?>
                        <th><?php echo ucfirst(str_replace('_', ' ', $field)); ?></th>
                    <?php endforeach; ?>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($user_data)): ?>
                    <?php foreach ($user_data as $user): ?>
                        <tr>
                            <form method="POST" action="Edit-UserData.php">
                                
                                <input type="hidden" name="update_user" value="1">
                                <input type="hidden" name="role" value="<?php echo $selected_role; ?>">
                                
                                <?php $identifier = $user[$table_config['pk']] ?? 'N/A'; ?>
                                <td>
                                    <?php echo htmlspecialchars($identifier); ?>
                                    <input type="hidden" name="identifier" value="<?php echo htmlspecialchars($identifier); ?>">
                                </td>
                                
                                <?php foreach ($table_config['fields'] as $field): 
                                    $value = $user[$field] ?? '';
                                ?>
                                <td>
                                    <input type="text" 
                                           name="<?php echo $field; ?>" 
                                           value="<?php echo htmlspecialchars($value); ?>"
                                           required>
                                </td>
                                <?php endforeach; ?>
                                
                                <td><button type="submit" class="btn-update">Save</button></td>
                            </form>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="<?php echo count($table_config['fields']) + 2; ?>">No <?php echo ucwords(str_replace('_', ' ', $selected_role)); ?> data found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>Please select a user role above to begin editing user data.</p>
    <?php endif; ?>

    <script>
    function filterTable() {
        var input, filter, table, tr, td, i, j, txtValue;
        input = document.getElementById("searchInput");
        filter = input.value.toUpperCase();
        table = document.getElementById("userTable"); 
        tr = table.getElementsByTagName("tr");

        // Loop through all table rows (starting from 1 to skip the header row)
        for (i = 1; i < tr.length; i++) {
            tr[i].style.display = "none"; 
            td = tr[i].getElementsByTagName("td");
            
            // Loop through all cells in the row to check for a match
            // The loop runs from j=0 up to the second-to-last cell (excluding 'Action')
            for (j = 0; j < td.length - 1; j++) { 
                if (td[j]) {
                    // Extract text content from the cell (handling input fields if necessary, but this relies on the HTML structure)
                    // We assume the data is visible text content or innerText.
                    txtValue = td[j].textContent || td[j].innerText;
                    
                    // Also check the value of the input field inside the td for better accuracy
                    var inputElement = td[j].querySelector('input[type="text"]');
                    if (inputElement && inputElement.value) {
                         txtValue += " " + inputElement.value;
                    }

                    if (txtValue.toUpperCase().indexOf(filter) > -1) {
                        tr[i].style.display = ""; // Show the row if a match is found
                        break; // Stop checking cells in this row
                    }
                }
            }
        }
    }
    </script>
    </body>
</html>