<?php
include '../config.php';

if (!isset($_GET['id'])) {
	echo "No gag ID provided.";
	exit();
}

$bid = intval($_GET['id']);

$banDetailsSql = "SELECT * FROM ucc_gag WHERE id = ?";
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
	echo "Gag not found.";
	exit();
}

$stmt->close();

$steamID = $banDetails['steamid'];
$banHistorySql = "SELECT * FROM ucc_gag WHERE steamid = ? AND id != ?";
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

function formatBanLength($create_time, $ban_length) {
	if ($ban_length == 0) {
		return 'Permanent';
	}

	if ($ban_length == -1) {
		return 'Expired';
	}

	$seconds = $ban_length - $create_time;

	$minutes = floor($seconds / 60);

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

function formatBlockType($block_type) {
	switch ($block_type) {
		case 0:
			return 'Text';
		case 1:
			return 'Voice';
		case 2:
			return 'Text & Voice';
		default:
			return 'Unknown';
	}
}

$steam64 = isValidSteamID($banDetails['steamid']);
$steamProfileLink = $steam64 ? "<a href='https://steamcommunity.com/profiles/" . htmlspecialchars($steam64, ENT_QUOTES, 'UTF-8') . "' target='_blank'>" . htmlspecialchars($steam64, ENT_QUOTES, 'UTF-8') . "</a>" : 'This player is not a Steam user';

?>

<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=1200, initial-scale=1.0">
	<title>Gag Details</title>
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
	<link rel="stylesheet" href="../assets/css/styles.css">
</head>
<body>
	<?php include 'navbar.php'; ?>
	<div class="container" style="margin-top: 80px;">
		<h1><a href="index.php"><i class="fa fa-ban"></i> UGC-Gaming - CS 1.6 Gags</a></h1>
		<h2>Gag Details for <?php echo htmlspecialchars($banDetails['name'], ENT_QUOTES, 'UTF-8'); ?></h2>
		<table>
			<tr>
				<th>Field</th>
				<th>Details</th>
			</tr>
			<tr>
				<td>Date</td>
				<td><?php echo htmlspecialchars(date('Y-m-d H:i:s', $banDetails['create_time']), ENT_QUOTES, 'UTF-8'); ?></td>
			</tr>
			<tr>
				<td>Player Nick</td>
				<td><?php echo htmlspecialchars($banDetails['name'], ENT_QUOTES, 'UTF-8'); ?></td>
			</tr>
			<tr>
				<td>Steam ID</td>
				<td><?php echo htmlspecialchars($banDetails['steamid'], ENT_QUOTES, 'UTF-8'); ?></td>
			</tr>
			<tr>
				<td>Steam Profile</td>
				<td><?php echo $steamProfileLink; ?></td>
			</tr>
			<tr>
				<td>Admin Nick</td>
				<td><?php echo htmlspecialchars($banDetails['admin_name'], ENT_QUOTES, 'UTF-8'); ?></td>
			</tr>
			<tr>
				<td>Gag Reason</td>
				<td><?php echo htmlspecialchars($banDetails['reason'], ENT_QUOTES, 'UTF-8'); ?></td>
			</tr>
			<tr>
				<td>Block Type</td>
				<td><?php echo htmlspecialchars(formatBlockType($row["block_type"]), ENT_QUOTES, 'UTF-8'); ?></td>
			</tr>
			<tr>
				<td>Gag Length</td>
				<td><?php echo htmlspecialchars(formatBanLength($row['create_time'], $row['expired_time']), ENT_QUOTES, 'UTF-8'); ?></td>
			</tr>
		</table>

		<h2>Gag History</h2>
		<table>
			<tr>
				<th>Date</th>
				<th>Nick</th>
				<th>Reason</th>
				<th>Length</th>
			</tr>
			<?php if (empty($banHistory)) : ?>
				<tr>
					<td colspan="4">No previous gags found for this player.</td>
				</tr>
			<?php else : ?>
				<?php foreach ($banHistory as $ban) : ?>
					<tr>
						<td><?php echo htmlspecialchars(date('Y-m-d H:i:s', $ban['create_time']), ENT_QUOTES, 'UTF-8'); ?></td>
						<td><?php echo htmlspecialchars($ban['name'], ENT_QUOTES, 'UTF-8'); ?></td>
						<td><?php echo htmlspecialchars($ban['reason'], ENT_QUOTES, 'UTF-8'); ?></td>
						<td><?php echo htmlspecialchars(formatBanLength($row['create_time'], $row['expired_time']), ENT_QUOTES, 'UTF-8'); ?></td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</table>
	</div>
</body>
</html>
