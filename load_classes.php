<?php
include('includes/config.php');


$sql = "SELECT * FROM tblclasses ORDER BY id DESC";
$query = $dbh->prepare($sql);
$query->execute();
$results = $query->fetchAll(PDO::FETCH_OBJ);
$cnt = 1;

// Check if any classes exist in the database
if ($query->rowCount() > 0) {
    foreach ($results as $result) {
        echo "<tr>
                <td>" . $cnt++ . "</td>
                <td>{$result->academic_level}</td>
                <td>{$result->class_level}</td>
                <td>
                    <button class='btn btn-warning btn-sm editClassBtn'   data-id='{$result->id}' data-academic='{$result->academic_level}' data-class='{$result->class_level}'>Edit</button>
                    <button class='btn btn-danger btn-sm deleteClassBtn' data-id='{$result->id}'>Delete</button>
                    <button class='btn btn-info btn-sm viewStreamsBtn' data-id='{$result->id}'>View Streams</button>
                    <button class='btn btn-primary btn-sm addStreamBtn' data-id='{$result->id}'>Add Stream</button>
                    <button class='btn btn-secondary btn-sm deleteStudentsBtn' data-id='{$result->id}'>Delete Students</button>
                </td>
            </tr>";
    }
} else {
    echo "<tr><td colspan='4'>No classes found.</td></tr>";
}
?>
<?php
include('includes/config.php');


$sql = "SELECT * FROM tblclasses ORDER BY id DESC";
$query = $dbh->prepare($sql);
$query->execute();
$results = $query->fetchAll(PDO::FETCH_OBJ);
$cnt = 1;

// Check if any classes exist in the database
if ($query->rowCount() > 0) {
    foreach ($results as $result) {
        echo "<tr>
                <td>" . $cnt++ . "</td>
                <td>{$result->academic_level}</td>
                <td>{$result->class_level}</td>
                <td>
                    <button class='btn btn-warning btn-sm editClassBtn'   data-id='{$result->id}' data-academic='{$result->academic_level}' data-class='{$result->class_level}'>Edit</button>
                    <button class='btn btn-danger btn-sm deleteClassBtn' data-id='{$result->id}'>Delete</button>
                    <button class='btn btn-info btn-sm viewStreamsBtn' data-id='{$result->id}'>View Streams</button>
                    <button class='btn btn-primary btn-sm addStreamBtn' data-id='{$result->id}'>Add Stream</button>
                    <button class='btn btn-secondary btn-sm deleteStudentsBtn' data-id='{$result->id}'>Delete Students</button>
                </td>
            </tr>";
    }
} else {
    echo "<tr><td colspan='4'>No classes found.</td></tr>";
}
?>
<?php
include('includes/config.php');


$sql = "SELECT * FROM tblclasses ORDER BY id DESC";
$query = $dbh->prepare($sql);
$query->execute();
$results = $query->fetchAll(PDO::FETCH_OBJ);
$cnt = 1;

// Check if any classes exist in the database
if ($query->rowCount() > 0) {
    foreach ($results as $result) {
        echo "<tr>
                <td>" . $cnt++ . "</td>
                <td>{$result->academic_level}</td>
                <td>{$result->class_level}</td>
                <td>
                    <button class='btn btn-warning btn-sm editClassBtn'   data-id='{$result->id}' data-academic='{$result->academic_level}' data-class='{$result->class_level}'>Edit</button>
                    <button class='btn btn-danger btn-sm deleteClassBtn' data-id='{$result->id}'>Delete</button>
                    <button class='btn btn-info btn-sm viewStreamsBtn' data-id='{$result->id}'>View Streams</button>
                    <button class='btn btn-primary btn-sm addStreamBtn' data-id='{$result->id}'>Add Stream</button>
                    <button class='btn btn-secondary btn-sm deleteStudentsBtn' data-id='{$result->id}'>Delete Students</button>
                </td>
            </tr>";
    }
} else {
    echo "<tr><td colspan='4'>No classes found.</td></tr>";
}
?>
