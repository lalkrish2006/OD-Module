<?php
require_once __DIR__ . '/includes/session_manager.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    // NOTE: Replace "root" and "" with your actual database credentials if different
    $conn = new mysqli("localhost", "root", "", "college_db");
    $conn->set_charset("utf8mb4");
} catch (Exception $e) {
    // Enhanced error message for clarity
    die("DB Connection Failed: " . $e->getMessage());
}
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    // Stop execution if not admin
    die("Access Denied: Admin privileges required.");
}

// User roles and table configurations
$user_roles = [
    'student' => ['table' => 'students', 'pk' => 'register_no', 'fields' => ['name', 'department', 'year', 'section', 'email']],
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

// --- GET Request Handling (Displaying Data) ---
if (isset($_GET['role']) && array_key_exists($_GET['role'], $user_roles)) {
    $selected_role = $_GET['role'];
    $table_config = $user_roles[$selected_role];
    $table_name = $table_config['table'];
    $pk_column = $table_config['pk'];

    // Construct SELECT query to get all data except 'password' and order by name
    $select_fields = array_merge([$pk_column], $table_config['fields']);

    $select_query = "SELECT " . implode(', ', $select_fields) . " FROM {$table_name} ORDER BY name ASC";

    $stmt = $conn->prepare($select_query);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $user_data[] = $row;
        }
    }
}

// --- POST Request Handling (Updating Data) ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['update_user'])) {

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
                    $bind_params[] = (int) $value;
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
function ref_values($arr)
{
    $refs = array();
    foreach ($arr as $key => $value)
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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin: Edit User Data</title>
    <style>
        :root {
            --primary-blue: #007bff;
            --secondary-blue: #e9ecef;
            --white: #ffffff;
            --success-green: #28a745;
            --error-red: #dc3545;
            --text-dark: #343a40;
            --text-light: #6c757d;
        }

        body {
            font-family: Arial, sans-serif;
            background-color: var(--secondary-blue);
            color: var(--text-dark);
            line-height: 1.6;
            margin: 0;
            padding: 20px;
        }

        .container {
            max-width: 1300px;
            margin: 0 auto;
            background: var(--white);
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        h2,
        h3 {
            color: var(--primary-blue);
            border-bottom: 2px solid var(--secondary-blue);
            padding-bottom: 10px;
            margin-top: 20px;
        }

        h2 {
            font-size: 1.8em;
        }

        /* --- Message Styles --- */
        .message-success,
        .message-error {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
            font-weight: bold;
        }

        .message-success {
            background-color: rgba(40, 167, 69, 0.1);
            color: var(--success-green);
            border: 1px solid var(--success-green);
        }

        .message-error {
            background-color: rgba(220, 53, 69, 0.1);
            color: var(--error-red);
            border: 1px solid var(--error-red);
        }

        /* --- Role Selection Buttons --- */
        .role-selection {
            margin-bottom: 20px;
            /* Allow buttons to wrap on smaller screens */
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            /* Spacing between buttons */
        }

        .btn-role {
            padding: 10px 18px;
            text-decoration: none;
            border: 1px solid var(--primary-blue);
            background: var(--white);
            color: var(--primary-blue);
            border-radius: 4px;
            display: inline-block;
            transition: all 0.2s;
            flex-grow: 1;
            /* Allows buttons to share space */
            text-align: center;
        }

        .btn-role:hover {
            background: var(--secondary-blue);
        }

        .btn-active {
            background: var(--primary-blue);
            color: var(--white) !important;
            border-color: var(--primary-blue);
            box-shadow: 0 2px 4px rgba(0, 123, 255, 0.4);
        }

        /* --- Search Field --- */
        #searchInput {
            width: 100%;
            /* Make it full width in the container */
            max-width: 500px;
            padding: 12px 15px;
            margin-bottom: 25px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            box-sizing: border-box;
            transition: border-color 0.2s;
        }

        #searchInput:focus {
            border-color: var(--primary-blue);
            outline: none;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }

        /* --- Table Styles --- */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            min-width: 600px;
            /* Ensure a minimum width for non-responsive tables */
        }

        th,
        td {
            padding: 12px 15px;
            border: 1px solid var(--secondary-blue);
            text-align: left;
        }

        thead th {
            background-color: var(--primary-blue);
            color: var(--white);
            font-weight: bold;
            border-color: var(--primary-blue);
        }

        tbody tr:nth-child(even) {
            background-color: #f8f9fa;
            /* Very light grey/white */
        }

        tbody tr:hover {
            background-color: var(--secondary-blue);
        }

        /* --- Input Fields in Table --- */
        input[type="text"] {
            width: 100%;
            padding: 8px;
            border: 1px solid #ced4da;
            border-radius: 3px;
            box-sizing: border-box;
            transition: border-color 0.2s;
        }

        input[type="text"]:focus {
            border-color: var(--primary-blue);
            outline: none;
        }

        /* --- Update Button --- */
        .btn-update {
            background: var(--success-green);
            color: var(--white);
            border: none;
            padding: 8px 15px;
            cursor: pointer;
            border-radius: 4px;
            font-weight: bold;
            transition: background 0.2s;
            white-space: nowrap;
        }

        .btn-update:hover {
            background: #1e7e34;
        }

        hr {
            border: 0;
            height: 1px;
            background-color: var(--secondary-blue);
            margin: 20px 0;
        }

        /* ======================================= */
        /* === MEDIA QUERY FOR RESPONSIVENESS === */
        /* ======================================= */
        @media (max-width: 768px) {
            body {
                padding: 10px;
            }

            .container {
                padding: 15px;
            }

            h2 {
                font-size: 1.5em;
            }

            /* Full width buttons on small screens */
            .role-selection {
                flex-direction: column;
            }

            .btn-role {
                margin-right: 0;
                width: 100%;
            }

            /* Responsive Table (Card View or Simplified Scrolling) */
            /* Using overflow-x:auto on the wrapper is the simplest approach for wide tables */
            .table-wrapper {
                overflow-x: auto;
            }

            /* Adjust padding and font size for dense data on mobile */
            th,
            td {
                padding: 8px 10px;
                font-size: 0.9em;
            }

            input[type="text"] {
                padding: 6px;
                font-size: 0.9em;
            }

            .btn-update {
                padding: 6px 10px;
            }
        }
    </style>
</head>

<body>

    <div class="container">
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
                placeholder="🔍 Search by Identifier, Name, Dept, or Section...">

            <div class="table-wrapper" style="overflow-x:auto;">
                <table id="userTable">
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
                                            <input type="hidden" name="identifier"
                                                value="<?php echo htmlspecialchars($identifier); ?>">
                                        </td>

                                        <?php foreach ($table_config['fields'] as $field):
                                            $value = $user[$field] ?? '';
                                            ?>
                                            <td>
                                                <input type="text" name="<?php echo $field; ?>"
                                                    value="<?php echo htmlspecialchars($value); ?>" required>
                                            </td>
                                        <?php endforeach; ?>

                                        <td><button type="submit" class="btn-update">Save</button></td>
                                    </form>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="<?php echo count($table_config['fields']) + 2; ?>">No
                                    <?php echo ucwords(str_replace('_', ' ', $selected_role)); ?> data found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p>Please select a user role above to begin editing user data.</p>
        <?php endif; ?>
    </div>

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

                        // Prioritize getting the value from the editable text input
                        var inputElement = td[j].querySelector('input[type="text"]');
                        var identifierInput = td[j].querySelector('input[type="hidden"][name="identifier"]');

                        if (inputElement) {
                            txtValue = inputElement.value; // Use current input value
                        } else if (identifierInput) {
                            // Check the primary key for the identifier column
                            txtValue = identifierInput.value;
                        } else {
                            // Fallback to visible text content
                            txtValue = td[j].textContent || td[j].innerText;
                        }

                        if (txtValue && txtValue.toUpperCase().indexOf(filter) > -1) {
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