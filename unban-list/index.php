<?php
include '../config.php';

$searchQuery = "";
$search = "";
if (isset($_GET['search'])) {
	$search = "%" . $_GET['search'] . "%";
	$searchQuery = "WHERE nick LIKE ? OR reason LIKE ?";
}

$limit = 50;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$countSql = "SELECT COUNT(*) AS total FROM amx_unban " . ($searchQuery ? $searchQuery : "");
$countStmt = $conn->prepare($countSql);
if ($countStmt === false) {
	echo "Error preparing count statement: " . htmlspecialchars($conn->error, ENT_QUOTES, 'UTF-8');
	exit();
}

if ($searchQuery) {
	$countStmt->bind_param("ss", $search, $search);
}
$countStmt->execute();
$countStmt->bind_result($totalRows);
$countStmt->fetch();
$countStmt->close();

$totalPages = ceil($totalRows / $limit);

$sql = "SELECT id, nick, reason, date 
	FROM amx_unban 
	" . ($searchQuery ? $searchQuery : "") . " 
	ORDER BY id DESC 
	LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
if ($stmt === false) {
	echo "Error preparing select statement: " . htmlspecialchars($conn->error, ENT_QUOTES, 'UTF-8');
	exit();
}

function bindSearchParams($stmt, $searchQuery, $search, $limit, $offset) {
	if ($searchQuery) {
		$stmt->bind_param("ssii", $search, $search, $limit, $offset);
	} else {
		$stmt->bind_param("ii", $limit, $offset);
	}
}

bindSearchParams($stmt, $searchQuery, $search, $limit, $offset);

$stmt->execute();
$result = $stmt->get_result();

?>

<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=1200, initial-scale=1.0">
	<title>UGC-Gaming - CS 1.6 Unban List</title>
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
	<link rel="stylesheet" href="../assets/css/styles.css">
	<link rel="stylesheet" href="../assets/css/navbar.css">
</head>
<body>
	<?php include '../navbar.php'; ?>
	<div class="container" style="margin-top: 80px;">
		<h1><a href="index.php"><i class="fa fa-ban"></i> UGC-Gaming - CS 1.6 Unban List</a></h1>
		<form method="GET" class="search-form">
			<input type="text" name="search" placeholder="Search by Player Nick or Reason" value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search'], ENT_QUOTES, 'UTF-8') : ''; ?>">
			<button type="submit" class="search-button">Search</button>
		</form>
		<div class="pagination-info">Showing page <?php echo htmlspecialchars($page, ENT_QUOTES, 'UTF-8'); ?> of <?php echo htmlspecialchars($totalPages, ENT_QUOTES, 'UTF-8'); ?> pages.</div>
		<table>
			<thead>
				<tr>
					<th>Date</th>
					<th>Nick</th>
					<th>Reason</th>
				</tr>
			</thead>
			<tbody>
				<?php
				if ($result->num_rows > 0) {
					while($row = $result->fetch_assoc()) {

						echo "<tr>
							<td>" . htmlspecialchars($row["date"], ENT_QUOTES, 'UTF-8') . "</td>
							<td>" . htmlspecialchars($row["nick"], ENT_QUOTES, 'UTF-8') . "</td>
							<td>" . htmlspecialchars($row["reason"], ENT_QUOTES, 'UTF-8') . "</td>
						</tr>";
					}
				} else {
					echo "<tr><td colspan='3'>No results found</td></tr>";
				}
				?>
			</tbody>
		</table>
		<div class="pagination">
			<?php
			$maxDisplayPages = 5;
			$startPage = max(1, $page - floor($maxDisplayPages / 2));
			$endPage = min($totalPages, $startPage + $maxDisplayPages - 1);

			if ($startPage > 1) {
				echo "<a href='?page=1&search=" . urlencode(isset($_GET['search']) ? $_GET['search'] : '') . "'>First</a>";
			}

			if ($page > 1) {
				echo "<a href='?page=" . ($page - 1) . "&search=" . urlencode(isset($_GET['search']) ? $_GET['search'] : '') . "'>Prev</a>";
			}

			for ($i = $startPage; $i <= $endPage; $i++) {
				$activeClass = ($i == $page) ? 'active' : '';
				echo "<a href='?page=$i&search=" . urlencode(isset($_GET['search']) ? $_GET['search'] : '') . "' class='$activeClass'>$i</a>";
			}

			if ($page < $totalPages) {
				echo "<a href='?page=" . ($page + 1) . "&search=" . urlencode(isset($_GET['search']) ? $_GET['search'] : '') . "'>Next</a>";
			}

			if ($endPage < $totalPages) {
				echo "<a href='?page=$totalPages&search=" . urlencode(isset($_GET['search']) ? $_GET['search'] : '') . "'>Last</a>";
			}
			?>
		</div>
	</div>

	<script src="../assets/js/scripts.js"></script>
</body>
</html>

<?php
$stmt->close();
$conn->close();
?>
