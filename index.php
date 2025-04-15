<?php
require 'vendor/autoload.php';
use GeoIp2\Database\Reader;

$reader = new Reader('images/GeoLite2-Country.mmdb');

include 'config.php';

$visitor_ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

$banCheckSql = "SELECT player_ip, ban_created, ban_length, expired FROM acp_bans WHERE player_ip = ?";
$banCheckStmt = $conn->prepare($banCheckSql);
if ($banCheckStmt === false) {
	echo "Error preparing statement: " . htmlspecialchars($conn->error, ENT_QUOTES, 'UTF-8');
	exit();
}
$banCheckStmt->bind_param("s", $visitor_ip);
$banCheckStmt->execute();
$banCheckStmt->bind_result($player_ip, $ban_created, $ban_length, $expired);
$banCheckStmt->fetch();
$banCheckStmt->close();

$isBanned = false;

if ($player_ip && $expired == 0) {
	$current_time = time();
	$ban_expiration = $ban_created + ($ban_length * 60);
	if ($ban_length == 0 || $current_time < $ban_expiration) {
		$isBanned = true;
	}
}

$searchQuery = "";
$search = "";
if (isset($_GET['search'])) {
	$search = "%" . $_GET['search'] . "%";
	$searchQuery = "WHERE player_nick LIKE ? OR player_id LIKE ? OR player_ip LIKE ? OR ban_reason LIKE ?";
}

$limit = 50;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$countSql = "SELECT COUNT(*) AS total FROM acp_bans " . ($searchQuery ? $searchQuery : "");
$countStmt = $conn->prepare($countSql);
if ($countStmt === false) {
	echo "Error preparing count statement: " . htmlspecialchars($conn->error, ENT_QUOTES, 'UTF-8');
	exit();
}

if ($searchQuery) {
	$countStmt->bind_param("ssss", $search, $search, $search, $search);
}
$countStmt->execute();
$countStmt->bind_result($totalRows);
$countStmt->fetch();
$countStmt->close();

$totalPages = ceil($totalRows / $limit);

$sql = "SELECT bid, player_ip, player_id, player_nick, admin_id, admin_nick, ban_reason, ban_created, ban_length, server_name, expired 
	FROM acp_bans 
	" . ($searchQuery ? $searchQuery : "") . " 
	ORDER BY bid DESC 
	LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
if ($stmt === false) {
	echo "Error preparing select statement: " . htmlspecialchars($conn->error, ENT_QUOTES, 'UTF-8');
	exit();
}

function bindSearchParams($stmt, $searchQuery, $search, $limit, $offset) {
	if ($searchQuery) {
		$stmt->bind_param("ssssii", $search, $search, $search, $search, $limit, $offset);
	} else {
		$stmt->bind_param("ii", $limit, $offset);
	}
}

bindSearchParams($stmt, $searchQuery, $search, $limit, $offset);

$stmt->execute();
$result = $stmt->get_result();

function isValidSteamID($steamID, $url = false, $xml = false) {
	$rightSteam = "/^STEAM_0:[01]:\d+$/";

	if (preg_match($rightSteam, $steamID)) {
		list($prefix, $y, $z) = explode(':', $steamID);
		$steam64 = bcadd('76561197960265728', bcmul($z, '2'));
		if ($y == 1) {
			$steam64 = bcadd($steam64, '1');
		}

		if ($xml) {
			return htmlspecialchars('http://steamcommunity.com/profiles/' . $steam64 . '?xml=1', ENT_QUOTES, 'UTF-8');
		}

		if ($url) {
			return '<a href="http://steamcommunity.com/profiles/' . htmlspecialchars($steam64, ENT_QUOTES, 'UTF-8') . '" target="_blank">' . htmlspecialchars($steam64, ENT_QUOTES, 'UTF-8') . '</a>';
		}

		return $steam64;
	}

	return false;
}

function formatBanLength($minutes) {
	if ($minutes == 0) {
		return 'Permanent';
	}

	$time = [
		'week(s)' => floor($minutes / 10080),
		'day(s)' => floor(($minutes % 10080) / 1440),
		'hour(s)' => floor(($minutes % 1440) / 60),
		'min(s)' => $minutes % 60,
	];

	$result = [];
	foreach ($time as $unit => $value) {
		if ($value > 0) {
			$result[] = "$value $unit";
		}
	}

	return implode(' ', $result);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=1200, initial-scale=1.0">
	<title>UGC-Gaming - CS 1.6 Bans</title>
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
	<link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
	<?php include 'navbar.php'; ?>
	<div class="container" style="margin-top: 80px;">
		<h1><a href="index.php"><i class="fa fa-ban"></i> UGC-Gaming - CS 1.6 Bans</a></h1>
		<form method="GET" class="search-form">
			<input type="text" name="search" placeholder="Search by Player Nick, SteamID, IP or Reason" value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search'], ENT_QUOTES, 'UTF-8') : ''; ?>">
			<button type="submit" class="search-button">Search</button>
		</form>
		<?php if ($isBanned): ?>
			<div class="ban-status banned">Your IP address (<?php echo htmlspecialchars($visitor_ip, ENT_QUOTES, 'UTF-8'); ?>) is banned.</div>
		<?php else: ?>
			<div class="ban-status not-banned">Your IP address (<?php echo htmlspecialchars($visitor_ip, ENT_QUOTES, 'UTF-8'); ?>) is not banned.</div>
		<?php endif; ?>
		<div class="pagination-info">Showing page <?php echo htmlspecialchars($page, ENT_QUOTES, 'UTF-8'); ?> of <?php echo htmlspecialchars($totalPages, ENT_QUOTES, 'UTF-8'); ?> pages.</div>
		<table>
			<thead>
				<tr>
					<th>Date</th>
					<th>Nick</th>
					<th>Admin</th>
					<th>Reason</th>
					<th>Length</th>
					<th>Status</th>
					<th>Actions</th>
				</tr>
			</thead>
			<tbody>
				<?php
				if ($result->num_rows > 0) {
					while($row = $result->fetch_assoc()) {
						try {
							$record = $reader->country($row['player_ip']);
							$countryCode = $record->country->isoCode;
							$flag = !empty($countryCode) ? "<img src='images/flags/" . strtolower($countryCode) . ".png' alt='" . htmlspecialchars($countryCode, ENT_QUOTES, 'UTF-8') . "' style='width:20px;height:15px;'>" : "<i class='fas fa-flag' aria-hidden='true'></i>";
						} catch (Exception $e) {
							$flag = "<i class='fas fa-flag' aria-hidden='true'></i>";
						}
						$status = "Active";
						if ($row["expired"] == 1 || (time() > $row["ban_created"] + ($row["ban_length"] * 60) && $row["ban_length"] != 0)) {
							$status = "<span class='indicator'>Expired</span>";
						}

						echo "<tr>
							<td><span class='flag'>$flag</span> " . htmlspecialchars(date('Y-m-d', $row["ban_created"]), ENT_QUOTES, 'UTF-8') . "</td>
							<td>" . htmlspecialchars($row["player_nick"], ENT_QUOTES, 'UTF-8') . "</td>
							<td>" . htmlspecialchars($row["admin_nick"], ENT_QUOTES, 'UTF-8') . "</td>
							<td>" . htmlspecialchars($row["ban_reason"], ENT_QUOTES, 'UTF-8') . "</td>
							<td>" . htmlspecialchars(formatBanLength($row["ban_length"]), ENT_QUOTES, 'UTF-8') . "</td>
							<td>" . $status . "</td>
							<td><button onclick='showModal(\"" . htmlspecialchars($row['bid'], ENT_QUOTES, 'UTF-8') . "\")' class='view-button'>Details</button></td>
						</tr>";
					}
				} else {
					echo "<tr><td colspan='7'>No results found</td></tr>";
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
	
	<?php
	if ($result->num_rows > 0) {
		mysqli_data_seek($result, 0);
		while ($row = $result->fetch_assoc()) {
			$steam64 = isValidSteamID($row["player_id"]);
			$steamProfileLink = $steam64 ? "<a href='https://steamcommunity.com/profiles/" . htmlspecialchars($steam64, ENT_QUOTES, 'UTF-8') . "' target='_blank'>" . htmlspecialchars($steam64, ENT_QUOTES, 'UTF-8') . "</a>" : 'This player is not a Steam user';

			echo "
			<div id='modal-" . htmlspecialchars($row['bid'], ENT_QUOTES, 'UTF-8') . "' class='modal'>
				<div class='modal-content'>
					<h2>Ban Details for " . htmlspecialchars($row['player_nick'], ENT_QUOTES, 'UTF-8') . "</h2>
					<table>
						<tr>
							<th>Field</th>
							<th>Details</th>
						</tr>
						<tr>
							<td>Date</td>
							<td>" . htmlspecialchars(date('Y-m-d H:i:s', $row["ban_created"]), ENT_QUOTES, 'UTF-8') . "</td>
						</tr>
						<tr>
							<td>Player Nick</td>
							<td>" . htmlspecialchars($row['player_nick'], ENT_QUOTES, 'UTF-8') . "</td>
						</tr>
						<tr>
							<td>Steam ID</td>
							<td>" . htmlspecialchars($row['player_id'], ENT_QUOTES, 'UTF-8') . "</td>
						</tr>
						<tr>
							<td>Steam Profile</td>
							<td>$steamProfileLink</td>
						</tr>
						<tr>
							<td>Admin Nick</td>
							<td>" . htmlspecialchars($row['admin_nick'], ENT_QUOTES, 'UTF-8') . "</td>
						</tr>
						<tr>
							<td>Ban Reason</td>
							<td>" . htmlspecialchars($row['ban_reason'], ENT_QUOTES, 'UTF-8') . "</td>
						</tr>
						<tr>
							<td>Ban Length</td>
							<td>" . htmlspecialchars(formatBanLength($row['ban_length']), ENT_QUOTES, 'UTF-8') . "</td>
						</tr>
						<tr>
							<td>Server Name</td>
							<td>" . htmlspecialchars(str_replace("\x01", '', $row['server_name']), ENT_QUOTES, 'UTF-8') . "</td>
						</tr>
					</table>
					<div class='button-container'>
						<button class='close-button' onclick='closeModal(\"" . htmlspecialchars($row['bid'], ENT_QUOTES, 'UTF-8') . "\")'>Close</button>
						<button class='share-button' onclick='window.open(\"ban_details.php?bid=" . htmlspecialchars($row['bid'], ENT_QUOTES, 'UTF-8') . "\", \"_blank\")'>Share</button>
					</div>
				</div>
			</div>";
		}
	}
	?>

	<script src="assets/js/scripts.js"></script>
</body>
</html>

<?php
$stmt->close();
$conn->close();
?>
