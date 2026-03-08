<?php
include 'db_connect.php';

$sql = "SELECT transaction_id, transaction_date, description, source, created_at
        FROM `Transaction`
        ORDER BY transaction_date DESC";

$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html>
<head>
    <title>All Transactions</title>
    <style>
        table {
            border-collapse: collapse;
            width: 80%;
            margin: 40px auto;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }
        th {
            background-color: #2c3e50;
            color: white;
        }
        tr:nth-child(even){
            background-color: #f2f2f2;
        }
    </style>
</head>

<body>

<h2 style="text-align:center;">Transaction List</h2>

<table>
<tr>
    <th>ID</th>
    <th>Date</th>
    <th>Description</th>
    <th>Source</th>
    <th>Created</th>
</tr>

<?php
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row["transaction_id"] . "</td>";
        echo "<td>" . $row["transaction_date"] . "</td>";
        echo "<td>" . $row["description"] . "</td>";
        echo "<td>" . $row["source"] . "</td>";
        echo "<td>" . $row["created_at"] . "</td>";
        echo "</tr>";
    }
} else {
    echo "<tr><td colspan='5'>No transactions found</td></tr>";
}

$conn->close();
?>

</table>

</body>
</html>