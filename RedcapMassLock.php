<?php

namespace BCCHR\RedcapMassLock;

use REDCap;

class RedcapMassLock extends \ExternalModules\AbstractExternalModule
{
    /**
     * Array of all records in current project, filtered ny current user's DAG.
     */
    private $records;

    function setRecords() {
        $user_rights = $this->framework->getUser(USERID)->getRights();
        $record_data = REDCap::getData('array',NULL,$this->framework->getRecordIdField(),NULL,$user_rights["group_id"],FALSE,TRUE);
        $this->records = array_keys($record_data);
    }

    /**
     * Retrieve tabs on Project Home, and add a tab for the module at the end. Inject them into the page. 
     */
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

    /**
     * Retrieves all instruments in the current project.
     */
    function getInstruments() {
        // Get the current Instuments
        $instrument_names = REDCap::getInstrumentNames();
        $instrument_options = array();
        $instrument = $_POST["instrument"];
        foreach ($instrument_names as $k => $v) {
            $instrument_options[] = "<option value='$k'" .
                ($k == $instrument ? " selected='selected'":"") .
                "> $v</option>";
        }
        return $instrument_options;
    }

    /**
     * Retrieves all events in the current project.
     */
    function getEvents() {
        // Get the current Events
        $events = REDCap::getEventNames(true);
        $event_options = array();
        $event = $_POST["event"];
        if ($events) {
            foreach ($events as $this_event) {
                $event_options[] = "<option value='$this_event'" .
                    ($this_event == $event ? " selected='selected'" : "") .
                    ">$this_event</option>";
            }
        }
        return $event_options;
    }

    /**
     * Formats all the project records into checkboxes, for user input. 
     */
    function getCheckBoxOptions() {
        $cbx_array = array();
        $post_records = $_POST['records'];
        foreach ($this->records as $record) {
            $cbx_array[] = "<input type='checkbox' name='records[]' value='$record' " .
                (in_array($record, $post_records) ? "checked" : "" ) .
                ">$record";
        }
        return $cbx_array;
    }

    function getMaxLength() {
        $max_length = 0;
        foreach ($this->records as $record) {
            $max_length = max($max_length,strlen($record));
        }
        return $max_length + 3; // add space for checkbox
    }

    /**
     * Handles locking record functionality.
     */
    function lockRecords($record_list, $instrument, $event, $event_id, $project_id) {
        // See if any are already locked?
        $sql = "select * 
            from redcap_locking_data 
            where 
                project_id = $project_id
                and event_id = $event_id
                and form_name = '$instrument'
                and record in ('" . implode("','", $record_list) . "')";

        $q = $this->query($sql);
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
            $output[] = "<style>div.Lock, div.LockNow, div.UnLock, div.UnLockNow {display:none;}</style>";
            unset($_POST['LockNow']);
            unset($_POST['Lock']);
        }

        if (isset($_POST['Lock'])) {
            // Do confirmation message
            $output[] = "<strong>The following records will be locked for event $event on instrument $instrument:</strong><div><ul><li>" . implode(", ", $records_to_lock) . "</li></ul></div>";
            $output[] = "<style>div.Initial, div.LockNow, div.UnLock, div.UnLockNow {display:none;}</style>";
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
            $output[] = "<style>div.Initial, div.Lock, div.UnLock, div.UnLockNow {display:none;}</style>";
            $output[] = "Result: " . json_encode($q);
        }
        return $output;
    }

    /**
     * Handles unlocking record functionality.
     */
    function unlockRecords($record_list, $instrument, $event, $event_id, $project_id) {
        // See if any are already unlocked?
        $sql = "select * 
            from redcap_locking_data 
            where 
                project_id = $project_id
                and event_id = $event_id
                and form_name = '$instrument'
                and record in ('" . implode("','", $record_list) . "')";
                
        $q = $this->query($sql);
        $already_unlocked_records = $already_unlocked_msg = array();
        while ($row = db_fetch_assoc($q)) {
            $records_to_unlock[] = $row['record'];
        }

        $already_unlocked_records = array_diff($record_list, $records_to_unlock);
        foreach($already_unlocked_records as $locked_record) {
            $already_unlocked_msg[] = "[$event] $instrument: Record " . $locked_record;
        }

        // Prepare output
        $output = array();
        $output[] = "<div class='well'>";
        if (count($already_unlocked_msg) > 0) {
            $output[] = "<strong>The following records are already unlocked and will be skipped:</strong><div><ul><li>" . implode("</li><li>", $already_unlocked_msg) . "</li></ul></div>";
        }

        if (count($records_to_unlock) == 0 ) {
            $output[] = "<div class='alert alert-info'>There are no records to unlock.</div>";
            $output[] = "<style>div.Lock, div.LockNow, div.UnLock, div.UnLockNow {display:none;}</style>";
            unset($_POST['UnLockNow']);
            unset($_POST['UnLock']);
        }

        if (isset($_POST['UnLock'])) {
            // Do confirmation message
            $output[] = "<strong>The following records will be unlocked for event $event on instrument $instrument:</strong><div><ul><li>" . implode(", ", $records_to_unlock) . "</li></ul></div>";
            $output[] = "<style>div.Initial, div.Lock, div.LockNow, div.UnLockNow {display:none;}</style>";
        }

        if (isset($_POST['UnLockNow'])) {
            // Actually unlock records!
            foreach($records_to_unlock as $index => $record) {
                $values .= "'$record'";
                if ($index < sizeof($records_to_unlock)-1) {
                    $values .= ",";
                }
            }
            $lock_sql = "delete from redcap_locking_data where project_id = $project_id and event_id = $event_id and form_name = '$instrument' and record in ($values)";
            $q = $this->query($lock_sql);
            $output[] = "<strong>The following records were unlocked for event $event on instrument $instrument:</strong><div><ul><li>" . implode(", ", $records_to_unlock) . "</li></ul></div>";
            $output[] = "<style>div.Initial, div.Lock, div.LockNow, div.UnLock {display:none;}</style>";
            $output[] = "Result: " . json_encode($q);
        }
        return $output;
    }

    /**
     * Handles POST functionality whenever user submits the page.
     */
    function handlePost() {
        $project_id = $this->getProjectId();

        // Get Inputs
        $instrument = isset($_POST['instrument']) ? $_POST['instrument'] : "";
        $event = isset($_POST['event']) ? $_POST['event'] : '';

        if (REDCap::isLongitudinal()) {
            $event_id = REDCap::getEventIdFromUniqueEvent($event);
        }
        else { // Grab event id for classic projects' hidden "Event 1".
            $sql = "select distinct e.event_id from redcap_events_metadata e, redcap_events_arms a,
            redcap_metadata m where a.arm_id = e.arm_id and a.project_id = m.project_id
            and m.project_id = " . $project_id;
            $q = $this->query($sql);
            while ($row = mysqli_fetch_assoc($q)) {
                $event_id = $row["event_id"];
            }
        }

        // Handle Post
        $post_records = array();
        if (isset($_POST['Lock']) OR isset($_POST['LockNow']) OR isset($_POST['UnLock']) OR isset($_POST['UnLockNow'])) {
            $post_records = $_POST['records'];

            // Filter out any invalid records
            $record_list = array_intersect($post_records,$this->records);

            if (isset($_POST['Lock']) OR isset($_POST['LockNow'])) {
                $output = $this->lockRecords($record_list, $instrument, $event, $event_id, $project_id);
            }
            else if (isset($_POST['UnLock']) OR isset($_POST['UnLockNow'])) {
                $output = $this->unlockRecords($record_list, $instrument, $event, $event_id, $project_id);
            }

            // OUTPUT POST MESSAGE
            $output[] = "</div>";
        } else {
            $output[] = "<style>div.Lock, div.LockNow, div.UnLock, div.UnLockNow {display:none;}</style>";
        }
        return $output;
    }
}