<?php
/*
	This is a utility to refresh DETs for all records in a project - it should be customized...
*/

// Connect
require_once "../../redcap_connect.php";
	
// Page header
include APP_PATH_DOCROOT . 'ProjectGeneral/header.php';

// Page title
renderPageTitle("<img src='".APP_PATH_IMAGES."application_view_icons.png' class='imgfix2'> Run a bunch of DETs!");

##### VALIDATION #####
# Make sure user has permissions for project or is a super user
$these_rights = REDCap::getUserRights(USERID);
$my_rights = $these_rights[USERID];
if (!$my_rights['design'] && !SUPER_USER) {
	showError('Project Setup rights are required to access MASS LOCK plugin.');
	exit;
}
# Make sure the user's rights have not expired for the project
if ($my_rights['expiration'] != "" && $my_rights['expiration'] < TODAY) {
		showError('Your user account has expired for this project.  Please contact the project admin.');
		exit;
}

# Inject the plugin tabs (must come after including tabs.php)
include APP_PATH_DOCROOT . "ProjectSetup/tabs.php";
injectPluginTabs($pid, $_SERVER["REQUEST_URI"], ' MASS LOCK');


// Get the current DET
global $data_entry_trigger_url;
global $redcap_version;

// Get the current Instuments
$instrument_names = REDCap::getInstrumentNames();

// Get the current Events
$events = REDCap::getEventNames(true);

// Get all records (across all arms/events) and DAGs
$record_data = REDCap::getData('array',NULL,REDCap::getRecordIdField(),NULL,NULL,FALSE,TRUE);
$records = array_keys($record_data);

// Are DAGs enabled (check for presence of 'redcap_data_access_group' on the first record)
$first_record = reset($record_data);
$first_event = reset($first_record);
$dag_enabled = isset($first_event['redcap_data_access_group']);


// Get Inputs
$instrument = isset($_POST['instrument']) ? $_POST['instrument'] : "";
$event = isset($_POST['event']) ? $_POST['event'] : '';
$event_id = REDCap::getEventIdFromUniqueEvent($event);
$custom_params = isset($_POST['custom_params']) ? $_POST['custom_params'] : '';



// Handle Post
$post_records = array();
if (isset($_POST['Lock']) OR isset($_POST['LockNow'])) {
	$post_records = $_POST['records'];

	// Filter out any invalid records
	$record_list = array_intersect($post_records,$records);

	// See if any are already logcked?
	$sql = "select * 
		from redcap_locking_data 
		where 
			project_id = $project_id
	   		and event_id = $event_id
  			and form_name = '$instrument'
			and record in ('" . implode("','", $record_list) . "')";
	$q = db_query($sql);
	$already_locked_records = $already_locked_msg = array();
	while ($row = db_fetch_assoc($q)) {
		$already_locked_records[] = $row['record'];
		$already_locked_msg[] = "[$event] $instrument: Record " . $row['record'] . " (locked by " . $row['username'] . " on " . $row['timestamp'].")";
	}
	$records_to_lock = array_diff($record_list, $already_locked_records);

	// Prepare output
	$output = array();
	$output[] = "<div class='well'>";
	if (count($already_locked_msg) > 0) {
		$output[] = "<strong>The following records are already locked and will be skipped:</strong><div><ul><li>" . implode("</li><li>", $already_locked_msg) . "</li></ul></div>";
	}

	if (count($records_to_lock) == 0 ) {
		$output[] = "<div class='alert alert-info'>There are no records to lock.</div>";
		$output[] = "<style>div.Lock, div.LockNow {display:none;}</style>";
		unset($_POST['LockNow']);
		unset($_POST['Lock']);
	}

	if (isset($_POST['Lock'])) {
		// Do confirmation message
		$output[] = "<strong>The following records will be locked for event $event on instrument $instrument:</strong><div><ul><li>" . implode(", ", $records_to_lock) . "</li></ul></div>";
		$output[] = "<style>div.Initial, div.LockNow {display:none;}</style>";
	}
	if (isset($_POST['LockNow'])) {
		// Actually lock the records!
		$values_array = array();
		foreach($records_to_lock as $record) {
			$values_array[] = "($project_id,'$record',$event_id,'$instrument','$userid','" . date('Y-m-d H:i:s') . "')";
		}
		$lock_sql = "insert into redcap_locking_data (project_id,record,event_id,form_name,username,timestamp) ".
			"values " . implode(",",$values_array);
		$q = db_query($lock_sql);
		$output[] = "<strong>The following records were locked for event $event on instrument $instrument:</strong><div><ul><li>" . implode(", ", $records_to_lock) . "</li></ul></div>";
		$output[] = "<style>div.Initial, div.Lock {display:none;}</style>";
		$output[] = "Result: " . json_encode($q);
	}

	// OUTPUT POST MESSAGE
	$output[] = "</div>";
} else {
	$output[] = "<style>div.Lock, div.LockNow {display:none;}</style>";
}

// Render Page
$cbx_array = array();
$max_length = 0;
foreach ($records as $record) {
	$cbx_array[] = "<input type='checkbox' name='records[]' value='$record' " .
		(in_array($record, $post_records) ? "checked" : "" ) .
		">$record";
	$max_length = max($max_length,strlen($record));
}
$max_length = $max_length + 3; // add space for checkbox

$instrument_options = array();
foreach ($instrument_names as $k => $v) {
	$instrument_options[] = "<option value='$k'" .
		($k == $instrument ? " selected='selected'":"") .
		"> $v</option>";
}

$event_options = array();
if ($events) {
	foreach ($events as $this_event) {
		$event_options[] = "<option value='$this_event'" .
			($this_event == $event ? " selected='selected'" : "") .
			">$this_event</option>";
	}
}

function injectPluginTabs($pid, $plugin_path, $plugin_name) {
$msg = "
	<script type='text/javascript'>
		// Get the last tab exlcuding the super user 'edit project' links
		lastTab = $('#sub-nav ul li').filter(function() { return $(this).css('background').indexOf('url') !== -1;}).last();
		// Make a new tab and insert it into the list...
		var newTab = $(\"<li class='active'><a style='font-size:13px;color:#393733;padding:4px 9px 7px 10px;' href='$plugin_path'><img src='" . APP_PATH_IMAGES . "application_view_icons.png' class='imgfix' style='height:16px;width:16px;'>$plugin_name</a></li>\").insertAfter(lastTab);
	</script>";
	echo $msg;
}

#display an error from scratch
function showError($msg) {
		$HtmlPage = new HtmlPage();
		$HtmlPage->PrintHeaderExt();
		echo "<div class='red'>$msg</div>";
		//Display the project footer	
		require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
}

?>
<h3>This plugin will lock the selected records in the specified event/instrument:</h3>

<form method='POST'>
	<?php print implode('',$output); ?>
	<div class="Initial">
		<div class="input-group">
			<div class="input-group-addon">Select Instrument</div>
			<select class="form-control" name='instrument'><?php echo implode('', $instrument_options) ?></select>
		</div>
		<?php if ($events) { ?>
			<div class="input-group">
				<div class="input-group-addon">Select Event</div>
				<select class="form-control" name='event'><?php echo implode('', $event_options) ?></select>
			</div>
		<?php } ?>
		<br/>
		<div class="panel panel-default">
			<div class="panel panel-heading">
				<strong>Select Records</strong>
			</div>
			<div class="panel panel-body wrapper">
				<ul>
					<li><?php print implode("</li><li>", $cbx_array) ?></li>
				</ul>
				<br/>
			</div>
			<div class="panel panel-footing text-center">
				<span data-choice='all' class='btn sel btn-sm btn-primary'/>All</span>
				<span data-choice='none' class='btn sel btn-sm btn-primary'/>None</span>
				<span data-choice='custom' class='btn btn-sm btn-primary customList'/>Custom List</span>
			</div>
		</div>
		<button type='submit' name='Lock' class='btn btn-primary'>Lock</button>
		<button type='submit' name='UnLock' class='btn btn-primary'>UnLock</button>
	</div>
	<div class="Lock">
		<button type='submit' name='LockNow' class='Lock btn btn-primary'>Confirm Lock</button>
		<button type='submit' name='Cancel' class='Lock btn btn-danger'>Cancel Operation</button>
	</div>
	<div class="LockNow">
		<button type='submit' name='Done' class='LockNow btn btn-primary'>Done</button>
	</div>
</form>
<style>
	select.form-control {width:auto;}
	div.input-group-addon {width: 200px;}
	/*button.sel {margin: 0px 10px; font-weight:bold; padding: 5px;}*/
	legend {font-weight: bold; font-size:larger;}
	fieldset {padding: 5px; max-width: 600px;}
	/*form div {padding-bottom: 10px;}*/
	.wrapper {overflow:auto; max-height: 300px;}
	.wrapper ul li {float:left; width: <?php echo $max_length ?>em; display:inline-block;}
	.wrapper br {clear:left;}
	.wrapper {margin-bottom: 1em;}
	.cr {width: 100%; height: 200px; overflow:auto;}
</style>
<script type='text/javascript'>
$(document).ready( function() { 
	$('span.sel').click( function() {
		var state = $(this).data('choice') == 'all';
		$('input[name="records[]"]').prop('checked',state);
		return false;
	});

	$('span.customList').click( function() {
		console.log("Here!");
		// Open up a pop-up with a list
		var data = "<p>Enter a comma-separated or return-separated list of record ids to select</p><textarea class='cr' name='custom_records' placeholder='Enter a comma-separated list of record_ids'></textarea>";
		initDialog("custom_records_dialog", data);
		$('#custom_records_dialog').dialog({ bgiframe: true, title: 'Enter Custom Record List',
			modal: true, width: 650,
			buttons: {
				Close: function() {  },
				Apply: function() {
					// Parse out contents
					var list = $('#custom_records_dialog textarea').val();
					var items = $.map(list.split(/\n|,/), $.trim);
					$(items).each(function(i, e) {
						console.log (i, e);
						$('input[value="' + e + '"]').prop('checked',true);
					});
//					console.log($('#custom_records_dialog textarea').val());
					$(this).dialog('close');
				}
			}
		});
	});
});
</script>

<?php
//Display the project footer
require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
?>

