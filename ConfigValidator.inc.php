<h1>The configuration of the following devices will be checked</h1>

<?php
error_reporting(E_ALL);
ini_set('display_errors', 'On');

$init_modules = array();
include_once(__DIR__ . '/../../../includes/init.php');
include_once("Differ.php");
include_once("LongestCommonSubsequenceCalculator.php");
include_once("TimeEfficientLongestCommonSubsequenceCalculator.php");

$configPath = "/opt/librenms/html/plugins/ConfigValidator/configs";
$templatePath = "/opt/librenms/html/plugins/ConfigValidator/templates";

foreach (dbFetchRows("SELECT distinct hardware, os from devices") as $device)
{
	if ($device['hardware']) {
		$templateFile = $templatePath . "/" . $device['os'] . '/' . $device['hardware'] . "/template.txt";
		if (file_exists($templateFile)) {
			echo "<h3>".$device['os']." - ".$device['hardware']."</h3>";
			
			echo '<table class="table table-condensed table-hover bootgrid-table">';
			foreach (dbFetchRows("SELECT * from devices WHERE os = '".$device['os']."' AND hardware = '".$device['hardware']."'") as $device2)
			{
				echo "<tr>";
				echo "<td>".$device2['hostname']."</td>";
				$result = checkConfig($device2['device_id'], $templateFile);
				if ($result['result']) {
					echo "<td>ok</td>";
				} else {
					echo "<td>";
					if (isset($result['added']) && count($result['added']) > 0) {
						echo "<h4>The following lines were added</h4>";
						echo "<table>";
						foreach ($result['added'] as $line) {
							echo "<tr>";
							echo "<td>".$line."</td>";
							echo "</tr>";
						}
						echo "</table>";
					}
					if (isset($result['removed']) && count($result['removed']) > 0) {
						echo "<h4>The following lines were removed</h4>";
						echo "<table>";
						foreach ($result['removed'] as $line) {
							echo "<tr>";
							echo "<td>".$line."</td>";
							echo "</tr>";
						}
						echo "</table>";
					}
					echo "</td>";
				}
				echo "</tr>";
			}
			echo "</table>";
			
		}
	}
}
  
  
function checkConfig($deviceId, $templateFile)
{
	global $configPath;
	
	$result = array('added' => array(), 'removed' => array());
	
	if (!file_exists($configPath."/".$deviceId.".txt")) {
		return false;
	}
	$current = file_get_contents($configPath."/".$deviceId.".txt");
	$template = file_get_contents($templateFile);
	
	$differ = new SebastianBergmann\Diff\Differ();
	$diff = $differ->diffToArray($template, $current);
	
	foreach ($diff as $line) {
		if ($line[1] == 1) {
			$result['added'][] = $line[0];
		}
		if ($line[1] == 2) {
			$result['removed'][] = $line[0];
		}
	}
	
	if (count($result['added']) == 0 && count($result['removed']) == 0) {
		$result['result'] = true;
		dbUpdate(array('notes' => ''), 'devices', 'device_id = ?', array($deviceId));
	} else {
		$result['result'] = false;
		dbUpdate(array('notes' => 'config_not_compliant'), 'devices', 'device_id = ?', array($deviceId));
	}
	
	return $result;
}

?>