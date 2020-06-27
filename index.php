<?php
// Connect
require_once "../../redcap_connect.php";
require_once "RedcapMassLock.php";

// Page header
include APP_PATH_DOCROOT . 'ProjectGeneral/header.php';

// Page title
renderPageTitle("<img src='".APP_PATH_IMAGES."application_view_icons.png' class='imgfix2'> Run a bunch of DETs!");

$redcap_mass_lock = new BCCHR\RedcapMassLock\RedcapMassLock();
$redcap_mass_lock->setRecords();

# Inject the plugin tabs (must come after including tabs.php)
include APP_PATH_DOCROOT . "ProjectSetup/tabs.php";
$redcap_mass_lock->injectPluginTabs($_GET["pid"], $_SERVER["REQUEST_URI"], 'MASS LOCK');

$max_length = $redcap_mass_lock->getMaxLength();
$cbx_array = $redcap_mass_lock->getCheckBoxOptions();
$instrument_options = $redcap_mass_lock->getInstruments();
$event_options = $redcap_mass_lock->getEvents();
$output = $redcap_mass_lock->handlePost();

?>
<h3>This plugin will (un)lock the selected records in the specified event/instrument:</h3>
<form method='POST'>
	<?php print implode('',$output); ?>
	<div class="Initial">
		<div class="input-group">
			<div class="input-group-addon">Select Instrument</div>
			<select class="form-control" name='instrument'><?php echo implode('', $instrument_options) ?></select>
		</div>
		<?php if (!empty($event_options)) { ?>
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
	<div class="UnLock">
		<button type='submit' name='UnLockNow' class='UnLock btn btn-primary'>Confirm UnLock</button>
		<button type='submit' name='Cancel' class='UnLock btn btn-danger'>Cancel Operation</button>
	</div>
	<div class="UnLockNow">
		<button type='submit' name='Done' class='UnLockNow btn btn-primary'>Done</button>
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
				Close: function() { $(this).dialog('close'); },
				Apply: function() {
					// Parse out contents
					var list = $('#custom_records_dialog textarea').val();
					var items = $.map(list.split(/\n|,/), $.trim);
					$(items).each(function(i, e) {
						console.log (i, e);
						$('input[value="' + e + '"]').prop('checked',true);
					});
					// console.log($('#custom_records_dialog textarea').val());
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