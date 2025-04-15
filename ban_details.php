<?php
include 'config.php';

if (!isset($_GET['bid'])) {
	echo "No ban ID provided.";
	exit();
}

$bid = intval($_GET['bid']);

$banDetailsSql = "SELECT * FROM acp_bans WHERE bid = ?";
$stmt = $conn->prepare($banDetailsSql);
if ($stmt === false) {
	echo "Error preparing statement: " . htmlspecialchars($conn->error, ENT_QUOTES, 'UTF-8');
	exit();
}
$stmt->bind_param("i", $bid);
$stmt->execute();
$result = $stmt->get_result();
$banDetails = $result->fetch_assoc();

if (!$banDetails) {
	echo "Ban not found.";
	exit();
}

$stmt->close();

$steamID = $banDetails['player_id'];
$banHistorySql = "SELECT * FROM acp_bans WHERE player_id = ? AND bid != ?";
$stmt = $conn->prepare($banHistorySql);
if ($stmt === false) {
	echo "Error preparing statement: " . htmlspecialchars($conn->error, ENT_QUOTES, 'UTF-8');
	exit();
}
$stmt->bind_param("si", $steamID, $bid);
$stmt->execute();
$banHistoryResult = $stmt->get_result();
$banHistory = $banHistoryResult->fetch_all(MYSQLI_ASSOC);

$stmt->close();
$conn->close();

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

$steam64 = isValidSteamID($banDetails['player_id']);
$steamProfileLink = $steam64 ? "<a href='https://steamcommunity.com/profiles/" . htmlspecialchars($steam64, ENT_QUOTES, 'UTF-8') . "' target='_blank'>" . htmlspecialchars($steam64, ENT_QUOTES, 'UTF-8') . "</a>" : 'This player is not a Steam user';

?>

<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=1200, initial-scale=1.0">
	<title>Ban Details</title>
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
	<link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
	<?php include 'navbar.php'; ?>
	<div class="container" style="margin-top: 80px;">
		<h1><a href="index.php"><i class="fa fa-ban"></i> UGC-Gaming - CS 1.6 Bans</a></h1>
		<h2>Ban Details for <?php echo htmlspecialchars($banDetails['player_nick'], ENT_QUOTES, 'UTF-8'); ?></h2>
		<table>
			<tr>
				<th>Field</th>
				<th>Details</th>
			</tr>
			<tr>
				<td>Date</td>
				<td><?php echo htmlspecialchars(date('Y-m-d H:i:s', $banDetails['ban_created']), ENT_QUOTES, 'UTF-8'); ?></td>
			</tr>
			<tr>
				<td>Player Nick</td>
				<td><?php echo htmlspecialchars($banDetails['player_nick'], ENT_QUOTES, 'UTF-8'); ?></td>
			</tr>
			<tr>
				<td>Steam ID</td>
				<td><?php echo htmlspecialchars($banDetails['player_id'], ENT_QUOTES, 'UTF-8'); ?></td>
			</tr>
			<tr>
				<td>Steam Profile</td>
				<td><?php echo $steamProfileLink; ?></td>
			</tr>
			<tr>
				<td>Admin Nick</td>
				<td><?php echo htmlspecialchars($banDetails['admin_nick'], ENT_QUOTES, 'UTF-8'); ?></td>
			</tr>
			<tr>
				<td>Ban Reason</td>
				<td><?php echo htmlspecialchars($banDetails['ban_reason'], ENT_QUOTES, 'UTF-8'); ?></td>
			</tr>
			<tr>
				<td>Ban Length</td>
				<td><?php echo htmlspecialchars(formatBanLength($banDetails['ban_length']), ENT_QUOTES, 'UTF-8'); ?></td>
			</tr>
			<tr>
				<td>Server Name</td>
				<td><?php echo htmlspecialchars(str_replace("\x01", '', $banDetails['server_name']), ENT_QUOTES, 'UTF-8'); ?></td>
			</tr>
		</table>

		<h2>Ban History</h2>
		<table>
			<tr>
				<th>Date</th>
				<th>Nick</th>
				<th>Reason</th>
				<th>Length</th>
				<th>Server</th>
			</tr>
			<?php if (empty($banHistory)) : ?>
				<tr>
					<td colspan="5">No previous bans found for this player.</td>
				</tr>
			<?php else : ?>
				<?php foreach ($banHistory as $ban) : ?>
					<tr>
						<td><?php echo htmlspecialchars(date('Y-m-d H:i:s', $ban['ban_created']), ENT_QUOTES, 'UTF-8'); ?></td>
						<td><?php echo htmlspecialchars($ban['player_nick'], ENT_QUOTES, 'UTF-8'); ?></td>
						<td><?php echo htmlspecialchars($ban['ban_reason'], ENT_QUOTES, 'UTF-8'); ?></td>
						<td><?php echo htmlspecialchars(formatBanLength($ban['ban_length']), ENT_QUOTES, 'UTF-8'); ?></td>
						<td><?php echo htmlspecialchars(str_replace("\x01", '', $ban['server_name']), ENT_QUOTES, 'UTF-8'); ?></td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</table>
	</div>
</body>
</html>
